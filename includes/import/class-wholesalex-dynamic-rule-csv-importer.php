<?php //phpcs:ignore
/**
 * WHOLESALEX_Dynamic_Rule_CSV_Importer
 * Inspired By WooCommerce Core Product Import
 *
 * @package WholesaleX
 * @version 1.2.9
 */

namespace WHOLESALEX;

use WC_Payment_Gateways;
use WC_Product_Variation;
use WC_Shipping_Zones;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WHOLESALEX_Dynamic_Rule_Importer', false ) ) {
	include_once dirname( __FILE__ ) . '/abstract-wholesalex-dynamic-rule-importer.php';
}

if ( ! class_exists( 'WHOLESALEX_Dynamic_Rule_CSV_Importer_Controller', false ) ) {
	include_once dirname( __FILE__ ) . '/class-wholesalex-dynamic-rule-csv-importer-controller.php';
}

/**
 * WHOLESALEX_Dynamic_Rule_CSV_Importer Class.
 */
class WHOLESALEX_Dynamic_Rule_CSV_Importer extends WHOLESALEX_Dynamic_Rule_Importer {

	/**
	 * Tracks current row being parsed.
	 *
	 * @var integer
	 */
	protected $parsing_raw_data_index = 0;

	/**
	 * Initialize importer.
	 *
	 * @param string $file   File to read.
	 * @param array  $params Arguments for the parser.
	 */
	public function __construct( $file, $params = array() ) {
		$default_args = array(
			'start_pos'        => 0, // File pointer start.
			'end_pos'          => -1, // File pointer end.
			'lines'            => -1, // Max lines to read.
			'mapping'          => array(), // Column mapping. csv_heading => schema_heading.
			'parse'            => false, // Whether to sanitize and format data.
			'update_existing'  => false, // Whether to update existing items.
			'delimiter'        => ',', // CSV delimiter.
			'prevent_timeouts' => true, // Check memory and time usage and abort if reaching limit.
			'enclosure'        => '"', // The character used to wrap text in the CSV.
			'escape'           => "\0", // PHP uses '\' as the default escape character. This is not RFC-4180 compliant. This disables the escape character.
		);

		$this->params = wp_parse_args( $params, $default_args );

		$this->file = $file;

		if ( isset( $this->params['mapping']['from'], $this->params['mapping']['to'] ) ) {
			$this->params['mapping'] = array_combine( $this->params['mapping']['from'], $this->params['mapping']['to'] );
		}

		$this->read_file();
	}

	/**
	 * Convert a string from the input encoding to UTF-8.
	 *
	 * @param string $value The string to convert.
	 * @return string The converted string.
	 */
	private function adjust_character_encoding( $value ) {
		$encoding = $this->params['character_encoding'];
		return 'UTF-8' === $encoding ? $value : mb_convert_encoding( $value, 'UTF-8', $encoding );
	}

	/**
	 * Read file.
	 */
	protected function read_file() {
		if ( ! WHOLESALEX_Dynamic_Rule_CSV_Importer_Controller::is_file_valid_csv( $this->file ) ) {
			wp_die( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.', 'wholesalex' ) );
		}

		$handle = fopen( $this->file, 'r' ); // @codingStandardsIgnoreLine.

		if ( false !== $handle ) {
			$this->raw_keys = array_map( 'trim', fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ) ); // @codingStandardsIgnoreLine

			if ( isset( $this->params['character_encoding'] ) && $this->params['character_encoding'] ) {
				$this->raw_keys = array_map( array( $this, 'adjust_character_encoding' ), $this->raw_keys );
			}

			// Remove line breaks in keys, to avoid mismatch mapping of keys.
			$this->raw_keys = wc_clean( wp_unslash( $this->raw_keys ) );

			// Remove BOM signature from the first item.
			if ( isset( $this->raw_keys[0] ) ) {
				$this->raw_keys[0] = $this->remove_utf8_bom( $this->raw_keys[0] );
			}

			if ( 0 !== $this->params['start_pos'] ) {
				fseek( $handle, (int) $this->params['start_pos'] );
			}

			while ( 1 ) {
				$row = fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ); // @codingStandardsIgnoreLine

				if ( false !== $row ) {
					if ( isset( $this->params['character_encoding'] ) && $this->params['character_encoding'] ) {
						$row = array_map( array( $this, 'adjust_character_encoding' ), $row );
					}

					$this->raw_data[]                                 = $row;
					$this->file_positions[ count( $this->raw_data ) ] = ftell( $handle );

					if ( ( $this->params['end_pos'] > 0 && ftell( $handle ) >= $this->params['end_pos'] ) || 0 === --$this->params['lines'] ) {
						break;
					}
				} else {
					break;
				}
			}

			$this->file_position = ftell( $handle );
		}

		if ( ! empty( $this->params['mapping'] ) ) {
			$this->set_mapped_keys();
		}

		if ( $this->params['parse'] ) {
			$this->set_parsed_data();
		}
	}

	/**
	 * Remove UTF-8 BOM signature.
	 *
	 * @param string $string String to handle.
	 *
	 * @return string
	 */
	protected function remove_utf8_bom( $string ) {
		if ( 'efbbbf' === substr( bin2hex( $string ), 0, 6 ) ) { // EFBBF is the byte order mark (BOM) of UTF-8.
			$string = substr( $string, 3 );
		}

		return $string;
	}

	/**
	 * Set file mapped keys.
	 */
	protected function set_mapped_keys() {
		$mapping = $this->params['mapping'];

		foreach ( $this->raw_keys as $key ) {
			$this->mapped_keys[] = isset( $mapping[ $key ] ) ? $mapping[ $key ] : $key;
		}
	}
	/**
	 * Parse the ID field.
	 *
	 * @param string $value Field value.
	 *
	 * @return int
	 */
	public function parse_id_field( $value ) {
		if ( $this->starts_with( $value, 'wdr_' ) ) {
			$value = str_replace( 'wdr_', '', $value );
		}
		return $value;
	}

	/**
	 * Parse Title Field
	 *
	 * @param string $value Title.
	 * @return string
	 */
	public function parse_title_field( $value ) {

		return sanitize_text_field( $value );
	}

	/**
	 * Parse relative comma-delineated field and return dynamic_rule ID.
	 *
	 * @param string $value Field value.
	 *
	 * @return array
	 */
	public function parse_relative_comma_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		return array_filter( array_map( array( $this, 'parse_relative_field' ), $this->explode_values( $value ) ) );
	}

	/**
	 * Parse a comma-delineated field from a CSV.
	 *
	 * @param string $value Field value.
	 *
	 * @return array
	 */
	public function parse_comma_field( $value ) {
		if ( empty( $value ) && '0' !== $value ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		return array_map( 'wc_clean', $this->explode_values( $value ) );
	}

	/**
	 * Parse a field that is generally '1' or '0' but can be something else.
	 *
	 * @param string $value Field value.
	 *
	 * @return bool|string
	 */
	public function parse_bool_field( $value ) {
		if ( '0' === $value ) {
			return false;
		}

		if ( '1' === $value ) {
			return true;
		}

		// Don't return explicit true or false for empty fields or values like 'notify'.
		return wc_clean( $value );
	}

	/**
	 * Parse a float value field.
	 *
	 * @param string $value Field value.
	 *
	 * @return float|string
	 */
	public function parse_float_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return floatval( $value );
	}


	/**
	 * Just skip current field.
	 *
	 * By default is applied wc_clean() to all not listed fields
	 * in self::get_formatting_callback(), use this method to skip any formatting.
	 *
	 * @param string $value Field value.
	 *
	 * @return string
	 */
	public function parse_skip_field( $value ) {
		return $value;
	}


	/**
	 * Parse an int value field
	 *
	 * @param int $value field value.
	 *
	 * @return int
	 */
	public function parse_int_field( $value ) {
		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return intval( $value );
	}

	/**
	 * Parse Rule Status
	 *
	 * @param string $value Status.
	 * @return boolean
	 */
	public function parse_rule_status( $value ) {
		$value = sanitize_text_field( $value );
		return 'yes' === $value;
	}


	/**
	 * Parse Rule Type Field
	 *
	 * @param string $value Rule Type.
	 * @return string
	 */
	public function parse_rule_type_field( $value ) {
		$rule_types = apply_filters(
			'wholesalex_dynamic_rules_rule_type_options',
			array(
				'product_discount'                => __( 'Product Discount', 'wholesalex' ),
				'cart_discount'                   => __( 'Cart Discount ', 'wholesalex' ),
				'payment_discount'                => __( 'Payment Discount', 'wholesalex' ),
				'payment_order_qty'               => __( 'Payment Order Quantity ', 'wholesalex' ),
				'buy_x_get_one'                   => __( 'BOGO Discounts (Buy X Get One Free)', 'wholesalex' ),
				'shipping_rule'                   => __( 'Shipping Rule', 'wholesalex' ),
				'min_order_qty'                   => __( 'Minimum Order Quantity', 'wholesalex' ),
				'tax_rule'                        => __( 'Tax Rule', 'wholesalex' ),
				'pro_quantity_based'              => __( 'Quantity Based Discount (Pro)', 'wholesalex' ),
				'pro_extra_charge'                => __( 'Extra Charge (Pro)', 'wholesalex' ),
				'pro_buy_x_get_y'                 => __( 'Buy X Get Y (Pro)', 'wholesalex' ),
				'pro_max_order_qty'               => __( 'Maximum Order Quantity (Pro)', 'wholesalex' ),
				'pro_restrict_product_visibility' => __( 'Restrict Product Visibility (Pro)', 'wholesalex' ),
			),
			'rule_type'
		);
		$rule_types = array_keys( $rule_types );
		$value      = sanitize_text_field( $value );
		if ( ! in_array( $value, $rule_types ) ) { //phpcs:ignore
			$value = '';
		}

		return $value;
	}


	/**
	 * Parse Rule For Field
	 *
	 * @param string $value Rule For.
	 * @return string
	 */
	public function parse_rule_for_field( $value ) {
		$rule_for = apply_filters(
			'wholesalex_dynamic_rules_rule_for_options',
			array(
				'all_users'      => __( 'All Users', 'wholesalex' ),
				'all_roles'      => __( 'All Roles', 'wholesalex' ),
				'specific_users' => __( 'Specific Users', 'wholesalex' ),
				'specific_roles' => __( 'Specific Roles', 'wholesalex' ),
			),
			'rule_for'
		);

		$rule_for = array_keys( $rule_for );
		$value    = sanitize_text_field( $value );
		if ( ! in_array( $value, $rule_for ) ) { //phpcs:ignore
			$value = '';
		}

		return $value;

	}

	/**
	 * Parse Specific Users Field
	 *
	 * @param string $value Specific Users String.
	 * @return array
	 */
	public function parse_specific_users_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $user ) {
				if ( ! is_numeric( $user ) ) {
					$id = explode( '_', $user );
					if ( isset( $id[1] ) ) {
						$id = $id[1];
					}
				}
				$user_obj = get_user_by( 'id', $id );
				if ( $user_obj ) {
					$data[] = array(
						'name'  => $user_obj->user_login,
						'value' => 'user_' . $id,
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Parse Specific Role Field
	 *
	 * @param string $value Specific Roles String.
	 * @return array
	 */
	public function parse_specific_roles_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $role ) {
				if ( ! is_numeric( $role ) ) {
					$role = preg_replace( '/\\(.*\\)/', '', $role );
				}
				$role_title = wholesalex()->get_role_name_by_role_id( $role );
				if ( $role_title ) {
					$data[] = array(
						'name'  => esc_attr( $role_title ),
						'value' => $role,
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Parse Product Filter Value
	 *
	 * @param string $value Product Filter.
	 * @return string
	 */
	public function parse_product_filter_field( $value ) {
		$product_filters = apply_filters(
			'wholesalex_dynamic_rules_product_filter_options',
			array(
				'all_products'          => __( 'All Products', 'wholesalex' ),
				'products_in_list'      => __( 'Product in list', 'wholesalex' ),
				'products_not_in_list'  => __( 'Product not in list', 'wholesalex' ),
				'cat_in_list'           => __( 'Categories in list', 'wholesalex' ),
				'cat_not_in_list'       => __( 'Categories not in list', 'wholesalex' ),
				'attribute_in_list'     => __( 'Attribute in list', 'wholesalex' ),
				'attribute_not_in_list' => __( 'Attribute not in list', 'wholesalex' ),
			),
			'product_filter'
		);

		$product_filters = array_keys( $product_filters );

		$value = sanitize_text_field( $value );
		if ( ! in_array( $value, $product_filters ) ) { //phpcs:ignore
			$value = '';
		}

		return $value;
	}

	/**
	 * Parse Product Lists Field
	 *
	 * @param string $value Product Lists String.
	 * @return array
	 */
	public function parse_products_list_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $product_id ) {
				if ( ! is_numeric( $product_id ) ) {
					$product_id = explode( '_', $product_id );
					if ( isset( $product_id[1] ) ) {
						$product_id = $product_id[1];
					}
				}
				$product_obj = wc_get_product( $product_id );
				if ( $product_obj ) {
					$data[] = array(
						'name'  => esc_attr($product_obj->get_title()),
						'value' => $product_id,
					);
				}
			}
		}

		return $data;

	}

	/**
	 * Parse Cat Lists Field
	 *
	 * @param string $value Cat Lists String.
	 * @return array
	 */
	public function parse_cat_list_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $cat_id ) {
				if ( ! is_numeric( $cat_id ) ) {
					$cat_id = explode( '_', $cat_id );
					if ( isset( $cat_id[1] ) ) {
						$cat_id = $cat_id[1];
					}
				}
				$cat_data = get_term_by( 'id', $cat_id, 'product_cat', 'ARRAY_A' );

				if ( $cat_data && isset( $cat_data['name'] ) ) {
					$data[] = array(
						'name'  => esc_attr( $cat_data['name'] ),
						'value' => $cat_id,
					);
				}
			}
		}

		return $data;

	}

	/**
	 * Parse Attribute Lists Field
	 *
	 * @param string $value Attribute Lists String.
	 * @return array
	 */
	public function parse_attribute_list_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $variation_id ) {
				if ( ! is_numeric( $variation_id ) ) {
					$variation_id = explode( '_', $variation_id );
					if ( isset( $variation_id[1] ) ) {
						$variation_id = $variation_id[1];
					}
				}

				$variation            = new WC_Product_Variation( $variation_id );
				$variation_name       = $variation->get_name();
				$attributes           = $variation->get_variation_attributes();
				$number_of_attributes = count( $attributes );
				if ( $number_of_attributes > 2 ) {
					$variation_name .= ' - ';
					foreach ( $attributes as $attribute ) {
						$variation_name .= $attribute . ', ';
					}
					$variation_name = substr( $variation_name, 0, -2 );
				}

				if ( $variation ) {
					$data[] = array(
						'name'  => esc_attr( $variation_name ),
						'value' => $variation_id,
					);
				}
			}
		}

		return $data;

	}

	/**
	 * Parse Discount Field
	 *
	 * @param string $value Discount String.
	 * @return array
	 */
	public function parse_discount_field( $value ) {
		// _discount_type:percentage;_discount_amount:5;_discount_name:test
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $discount ) {
				$temp_data = explode( ':', $discount );
				if ( isset( $temp_data[0], $temp_data[1] ) ) {
					$data[ $temp_data[0] ] = $temp_data[1];
				}
			}
		}

		return $data;
	}

	/**
	 * Parse Payment Discount Field
	 *
	 * @param string $value Payment Discount String.
	 * @return array
	 */
	public function parse_payment_discount_field( $value ) {
		// _payment_gateways:cod(Cash on delivery),bacs(Direct bank transfer);_discount_type:amount;_discount_amount:40;_discount_name:fdasfefaf
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $discount ) {
				$temp_data = explode( ':', $discount );

				if ( isset( $temp_data[0], $temp_data[1] ) ) {
					$data[ $temp_data[0] ] = $temp_data[1];

					if ( '_payment_gateways' === $temp_data[0] ) {
						$gateways = $temp_data[1];

						$gateways = explode( ';', $gateways );
						$gateways = array_map( array( $this, 'explode_values_formatter' ), $gateways );

						$wc_gateways = new WC_Payment_Gateways();

						// Get an array of all available payment gateways.
						$payment_gateways = $wc_gateways->get_available_payment_gateways();

						$gateway_data = array();

						foreach ( $gateways as $gateway ) {

							$gateway_id = preg_replace( '/\\(.*\\)/', '', $gateway );

							if ( isset( $payment_gateways[ $gateway_id ] ) ) {
								$title          = $payment_gateways[ $gateway_id ]->get_title();
								$gateway_data[] = array(
									'name'  => esc_attr( $title ),
									'value' => $gateway_id,
								);
							}
						}

						$data[ $temp_data[0] ] = $gateway_data;
					}
				}
			}
		}

		return $data;
	}


	/**
	 * Parse Payment Gateway Field
	 *
	 * @param string $value Payment Gateway String.
	 * @return array
	 */
	public function parse_payment_gateway_field( $value ) {
		// _payment_gateways:cod(Cash on delivery);_order_quantity:10
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $discount ) {
				$temp_data = explode( ':', $discount );

				if ( isset( $temp_data[0], $temp_data[1] ) ) {
					$data[ $temp_data[0] ] = $temp_data[1];

					if ( '_payment_gateways' === $temp_data[0] ) {
						$gateways = $temp_data[1];

						$gateways = explode( ',', $gateways );
						$gateways = array_map( array( $this, 'explode_values_formatter' ), $gateways );

						$wc_gateways = new WC_Payment_Gateways();

						// Get an array of all available payment gateways.
						$payment_gateways = $wc_gateways->get_available_payment_gateways();

						$gateway_data = array();

						foreach ( $gateways as $gateway ) {

							$gateway_id = preg_replace( '/\\(.*\\)/', '', $gateway );

							if ( isset( $payment_gateways[ $gateway_id ] ) ) {
								$title          = $payment_gateways[ $gateway_id ]->get_title();
								$gateway_data[] = array(
									'name'  => esc_attr( $title ),
									'value' => $gateway_id,
								);
							}
						}

						$data[ $temp_data[0] ] = $gateway_data;
					}
				}
			}
		}

		return $data;
	}


	/**
	 * Parse Single Rule Field
	 *
	 * @param string $value Single Rule String.
	 * @return array
	 */
	public function parse_single_rule_field( $value ) {
		// _minimum_purchase_count:12
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ':', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );
			if ( isset( $values[0], $values[1] ) ) {
				$data[ $values[0] ] = $values[1];
			}
		}
		return $data;
	}

	/**
	 * Parse Single Tier Field
	 *
	 * @param string $value Single Tier String.
	 * @return array
	 */
	public function parse_single_tier_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ',', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			$data = array();
			foreach ( $values as $single_tier ) {
				$temp_tier             = explode( ':', $single_tier );
				$data[ $temp_tier[0] ] = isset( $temp_tier[1] ) ? $temp_tier[1] : '';

			}
		}
		return $data;
	}


	/**
	 * Parse Shipping Rule Field
	 *
	 * @param string $value Shipping Rule String.
	 * @return array
	 */
	public function parse_shipping_rule_field( $value ) {
		// __shipping_zone:1;_shipping_zone_methods:3(Free shipping)

		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $discount ) {
				$temp_data = explode( ':', $discount );

				if ( isset( $temp_data[0], $temp_data[1] ) ) {
					$data[ $temp_data[0] ] = $temp_data[1];

					if ( '_shipping_zone_methods' === $temp_data[0] ) {
						$shipping_methods = $temp_data[1];

						$shipping_methods = explode( ',', $shipping_methods );
						$shipping_methods = array_map( array( $this, 'explode_values_formatter' ), $shipping_methods );

						$shipping_data = array();

						foreach ( $shipping_methods as $shipping_method ) {

							$method_id = preg_replace( '/\\(.*\\)/', '', $shipping_method );

							$method = WC_Shipping_Zones::get_shipping_method( $method_id );

							if ( $method ) {
								$shipping_data[] = array(
									'name'  => esc_attr($method->get_title() ),
									'value' => $method_id,
								);
							}
						}

						$data[ $temp_data[0] ] = $shipping_data;
					}
				}
			}
		}
		return $data;
	}


	/**
	 * Parse Buy X Get Y Field
	 *
	 * @param string $value Buy X Get Y String.
	 * @return array
	 */
	public function parse_buy_x_get_y_field( $value ) {
		// _minimum_purchase_count:2
		// _free_item:32
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );

			foreach ( $values as $discount ) {
				$temp_data = explode( ':', $discount );

				if ( isset( $temp_data[0], $temp_data[1] ) ) {
					$data[ $temp_data[0] ] = $temp_data[1];

					if ( '_free_item' === $temp_data[0] ) {
						$products = $temp_data[1];

						$products = explode( ',', $products );
						$products = array_map( array( $this, 'explode_values_formatter' ), $products );

						$free_products_data = array();

						foreach ( $products as $product_id ) {

							$product = wc_get_product( $product_id );
							if ( $product ) {
								$free_products_data[] = array(
									'name'  => esc_attr( $product->get_title() ),
									'value' => $product_id,
								);
							}
						}

						$data[ $temp_data[0] ] = $free_products_data;
					}
				}
			}
		}
		return $data;

	}

	/**
	 * Parse Tier Field
	 *
	 * @param string $value Tier String.
	 * @return array
	 */
	public function parse_tier_field( $value ) {
		$data = array();
		if ( ! empty( $value ) && $value ) {
			$value  = str_replace( '\\,', '::separator::', $value );
			$values = explode( ';', $value );
			$values = array_map( array( $this, 'explode_values_formatter' ), $values );
			foreach ( $values as $tier ) {
				$data[] = $this->parse_single_tier_field( $tier );
			}
		}
		if ( empty( $data ) ) {
			return '';
		}

		return array( 'tiers' => $data );

	}

	/**
	 * Parse dates from a CSV.
	 * Dates requires the format YYYY-MM-DD and time is optional.
	 *
	 * @param string $value Field value.
	 *
	 * @return string|null
	 */
	public function parse_date_field( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		if ( preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])([ 01-9:]*)$/', $value ) ) {
			// Don't include the time if the field had time in it.
			return current( explode( ' ', $value ) );
		}

		return null;
	}

	/**
	 * Deprecated get formatting callback method.
	 *
	 * @deprecated 4.3.0
	 * @return array
	 */
	protected function get_formating_callback() {
		return $this->get_formatting_callback();
	}

	/**
	 * Get formatting callback.
	 *
	 * @since 4.3.0
	 * @return array
	 */
	protected function get_formatting_callback() {
		/**
		 * Columns not mentioned here will get parsed with 'wc_clean'.
		 * column_name => callback.
		 */
		$data_formatting = array(
			'id'                    => array( $this, 'parse_id_field' ),
			'_rule_status'          => array( $this, 'parse_rule_status' ),
			'_rule_title'           => array( $this, 'parse_title_field' ),
			'_rule_type'            => array( $this, 'parse_rule_type_field' ),
			'_rule_for'             => array( $this, 'parse_rule_for_field' ),
			'specific_users'        => array( $this, 'parse_specific_users_field' ),
			'specific_roles'        => array( $this, 'parse_specific_roles_field' ),
			'_product_filter'       => array( $this, 'parse_product_filter_field' ),
			'products_in_list'      => array( $this, 'parse_products_list_field' ),
			'products_not_in_list'  => array( $this, 'parse_products_list_field' ),
			'cat_in_list'           => array( $this, 'parse_cat_list_field' ),
			'cat_not_in_list'       => array( $this, 'parse_cat_list_field' ),
			'attribute_in_list'     => array( $this, 'parse_attribute_list_field' ),
			'attribute_not_in_list' => array( $this, 'parse_attribute_list_field' ),
			'product_discount'      => array( $this, 'parse_discount_field' ),
			'cart_discount'         => array( $this, 'parse_discount_field' ),
			'payment_discount'      => array( $this, 'parse_payment_discount_field' ),
			'payment_order_qty'     => array( $this, 'parse_payment_gateway_field' ),
			'buy_x_get_one'         => array( $this, 'parse_single_rule_field' ),
			'shipping_rule'         => array( $this, 'parse_shipping_rule_field' ),
			'min_order_qty'         => array( $this, 'parse_single_rule_field' ),
			'max_order_qty'         => array( $this, 'parse_single_rule_field' ),
			'tax_rule'              => array( $this, 'parse_discount_field' ),
			'extra_charge'          => array( $this, 'parse_payment_gateway_field' ),
			'buy_x_get_y'           => array( $this, 'parse_buy_x_get_y_field' ),
			'quantity_based'        => array( $this, 'parse_tier_field' ),
			'conditions'            => array( $this, 'parse_tier_field' ),
			'_start_date'           => array( $this, 'parse_date_field' ),
			'_end_date'             => array( $this, 'parse_date_field' ),
		);

		$callbacks = array();

		// Figure out the parse function for each column.
		foreach ( $this->get_mapped_keys() as $index => $heading ) {
			$callback = 'wc_clean';

			if ( isset( $data_formatting[ $heading ] ) ) {
				$callback = $data_formatting[ $heading ];
			}
			$callbacks[] = $callback;
		}

		return apply_filters( 'wholesalex_dynamic_rule_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Map and format raw data to known fields.
	 */
	protected function set_parsed_data() {

		$parse_functions = $this->get_formatting_callback();
		$mapped_keys     = $this->get_mapped_keys();
		$use_mb          = function_exists( 'mb_convert_encoding' );

		// Parse the data.
		foreach ( $this->raw_data as $row_index => $row ) {
			// Skip empty rows.
			if ( ! count( array_filter( $row ) ) ) {
				continue;
			}

			$this->parsing_raw_data_index = $row_index;

			$data = array();

			do_action( 'wholesalex_dynamic_rule_importer_before_set_parsed_data', $row, $mapped_keys );

			foreach ( $row as $id => $value ) {
				// Skip ignored columns.
				if ( empty( $mapped_keys[ $id ] ) ) {
					continue;
				}

				// Convert UTF8.
				if ( $use_mb ) {
					$encoding = mb_detect_encoding( $value, mb_detect_order(), true );
					if ( $encoding ) {
						$value = mb_convert_encoding( $value, 'UTF-8', $encoding );
					} else {
						$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
					}
				} else {
					$value = wp_check_invalid_utf8( $value, true );
				}

				$data[ $mapped_keys[ $id ] ] = call_user_func( $parse_functions[ $id ], $value );
			}

			/**
			 * Filter dynamic_rule importer parsed data.
			 *
			 * @param array $parsed_data Parsed data.
			 * @param WHOLESALEX_Dynamic_Rule_Importer $importer Importer instance.
			 *
			 * @since
			 */
			$this->parsed_data[] = apply_filters( 'wholesalex_dynamic_rule_importer_parsed_data', $data, $this );
		}

	}

	/**
	 * Get a string to identify the row from parsed data.
	 *
	 * @param array $parsed_data Parsed data.
	 *
	 * @return string
	 */
	protected function get_row_id( $parsed_data ) {
		$id       = isset( $parsed_data['id'] ) ? $parsed_data['id'] : 0;
		$title    = isset( $parsed_data['"_rule_title"'] ) ? esc_attr( $parsed_data['"_rule_title"'] ) : '';
		$row_data = array();

		if ( $title ) {
			$row_data[] = $title;
		}
		if ( $id ) {
			/* translators: %d: dynamic_rule ID */
			$row_data[] = sprintf( __( 'ID %d', 'wholesalex' ), $id );
		}

		return implode( ', ', $row_data );
	}

	/**
	 * Process importer.
	 *
	 * Do not import dynamic_rules with IDs or SKUs that already exist if option
	 * update existing is false, and likewise, if updating dynamic_rules, do not
	 * process rows which do not exist if an ID/SKU is provided.
	 *
	 * @return array
	 */
	public function import() {
		$this->start_time = time();
		$index            = 0;

		$update_existing = $this->params['update_existing'];

		$data = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			do_action( 'wholesalex_dynamic_rule_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? $parsed_data['id'] : 0;
			$id_exists = false;

			if ( $id ) {
				$id_exists = ! empty( wholesalex()->get_dynamic_rules( $id ) );
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'wholesalex_dynamic_rule_importer_error',
					esc_html__( 'A dynamic_rule with this ID already exists.', 'wholesalex' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( isset( $parsed_data['id'] ) ) && ! $id_exists ) {
				$data['skipped'][] = new WP_Error(
					'wholesalex_dynamic_rule_importer_error',
					esc_html__( 'No matching dynamic_rule exists to update.', 'wholesalex' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			$result = $this->process_item( $parsed_data );

			if ( is_wp_error( $result ) ) {
				$result->add_data(
					array(
						'row' => $this->get_row_id( $parsed_data ),
						'id'  => $id,
					)
				);
				$data['failed'][] = $result;
			} elseif ( $result['updated'] ) {
				$data['updated'][] = $result['id'];
			} else {
				$data['imported'][] = $result['id'];
			}

			$index ++;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		return $data;
	}

	/**
	 * Check if strings starts with determined word.
	 *
	 * @param string $haystack Complete sentence.
	 * @param string $needle   Excerpt.
	 *
	 * @return bool
	 */
	protected function starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}
}
