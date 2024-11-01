<?php
/**
 * Handles WholesaleX Dynamic Rule CSV export.
 * Inspired From WooCommerce Core
 *
 * @package WholesaleX
 * @version 1.2.9
 */

namespace WHOLESALEX;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WC_CSV_Batch_Exporter', false ) ) {
	include_once WC_ABSPATH . 'includes/export/abstract-wc-csv-batch-exporter.php';
}

/**
 * WHOLESALEX_Dynamic_Rule_CSV_Exporter Class.
 */
class WHOLESALEX_Dynamic_Rule_CSV_Exporter extends \WC_CSV_Batch_Exporter {

	/**
	 * Type of export used in filter names.
	 *
	 * @var string
	 */
	protected $export_type = 'dynamic_rules';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Return an array of columns to export.
	 *
	 * @since  1.2.9
	 * @return array
	 */
	public function get_default_column_names() {

		return apply_filters(
			"wholesalex_{$this->export_type}_exporter_default_columns",
			array(
				'id'                          => __( 'ID', 'wholesalex' ),
				'_rule_status'                => __( 'Status', 'wholesalex' ),
				'_rule_title'                 => __( 'Title', 'wholesalex' ),
				'_rule_type'                  => __( 'Type', 'wholesalex' ),
				'_rule_for'                   => __( 'Applicable For', 'wholesalex' ),
				'specific_users'              => __( 'Applicable Users', 'wholesalex' ),
				'specific_roles'              => __( 'Applicable Roles', 'wholesalex' ),
				'_product_filter'             => __( 'Applicable On', 'wholesalex' ),
				'products_in_list'            => __( 'Product In Lists', 'wholesalex' ),
				'products_not_in_list'        => __( 'Product Not in Lists', 'wholesalex' ),
				'cat_in_list'                 => __( 'Categories In Lists', 'wholesalex' ),
				'cat_not_in_list'             => __( 'Categories Not in Lists', 'wholesalex' ),
				'attribute_in_list'           => __( 'Variation In Lists', 'wholesalex' ),
				'attribute_not_in_list'       => __( 'Variation Not in Lists', 'wholesalex' ),
				'product_discount'            => __( 'Product Discount Data', 'wholesalex' ),
				'cart_discount'               => __( 'Cart Discount Data', 'wholesalex' ),
				'payment_discount'            => __( 'Payment Discount Data', 'wholesalex' ),
				'payment_order_qty'           => __( 'Payment Order Quantity Data', 'wholesalex' ),
				'buy_x_get_one'               => __( 'BOGO Discount Data', 'wholesalex' ),
				'shipping_rule'               => __( 'Shipping Rule Data', 'wholesalex' ),
				'min_order_qty'               => __( 'Min Order Quantity Data', 'wholesalex' ),
				'tax_rule'                    => __( 'Tax Rule Data', 'wholesalex' ),
				'quantity_based'              => __( 'Quantity Based Data', 'wholesalex' ),
				'extra_charge'                => __( 'Extra Charge Data', 'wholesalex' ),
				'buy_x_get_y'                 => __( 'Buy X Get Y Data', 'wholesalex' ),
				'max_order_qty'               => __( 'Max Order Quantity Data', 'wholesalex' ),
				'restrict_product_visibility' => __( 'Restrict Product Visibility Data', 'wholesalex' ),
				'conditions'                  => __( 'Conditions Data', 'wholesalex' ),
				'_usage_limit'                => __( 'Usages Limit', 'wholesalex' ),
				'_start_date'                 => __( 'Start Date', 'wholesalex' ),
				'_end_date'                   => __( 'End Date', 'wholesalex' ),
			)
		);
	}

	/**
	 * Prepare data for export.
	 *
	 * @since 1.2.9
	 */
	public function prepare_data_to_export() {

		$rules = get_option( '__wholesalex_dynamic_rules' );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}
		$this->total_rows = count( $rules );
		$this->row_data   = array();

		foreach ( $rules as $rule ) {
			$this->row_data[] = $this->generate_row_data( $rule );
		}

	}

	/**
	 * Take a Rule and generate row data from it for export.
	 *
	 * @param array $rule WholesaleX Rule.
	 *
	 * @return array
	 */
	protected function generate_row_data( $rule ) {
		$columns = $this->get_column_names();
		$row     = array();
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			if ( has_filter( "wholesalex_export_{$this->export_type}_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "wholesalex_export_{$this->export_type}_column_{$column_id}", '', $rule, $column_id );

			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $rule );

			}

			$row[ $column_id ] = $value;

		}
		/**
		 * Allow third-party plugins to filter the data in a single row of the exported CSV file.
		 *
		 * @since 1.2.9
		 *
		 * @param array                   $row         An associative array with the data of a single row in the CSV file.
		 * @param array                   $rule        WholesaleX Rule
		 * @param WHOLESALEX_Dynamic_Rule_CSV_Exporter $exporter    The instance of the CSV exporter.
		 */
		return apply_filters( 'wholesalex_export_row_data', $row, $rule, $this );
	}

	/**
	 * Get Rule ID
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return int|string
	 */
	protected function get_column_value_id( $rule ) {
		return isset( $rule['id'] ) ? 'wdr_' . $rule['id'] : 0;
	}

	/**
	 * Get Rule Title
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__rule_title( $rule ) {
		return isset( $rule['_rule_title'] ) ? $rule['_rule_title'] : __( 'Untitled', 'wholesalex' );
	}
	/**
	 * Get Rule TYpe
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__rule_type( $rule ) {
		$methods = isset( $rule['_rule_type'] ) ? $rule['_rule_type'] : '';
		return $methods;
	}
	/**
	 * Get Rule Status Ids
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__rule_status( $rule ) {
		$methods = isset( $rule['_rule_status'] ) && $rule['_rule_status'] ? 'yes' : 'no';
		return $methods;
	}
	/**
	 * Get Rule Applicable FOr
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__rule_for( $rule ) {
		return isset( $rule['_rule_for'] ) ? $rule['_rule_for'] : '';
	}
	/**
	 * Get Rule Applicable on
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__product_filter( $rule ) {
		return isset( $rule['_product_filter'] ) ? $rule['_product_filter'] : '';
	}
	/**
	 * Get Rule Usages Limit
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__usage_limit( $rule ) {
		$limit = isset( $rule['limit'] ) ? $rule['limit'] : array();
		return isset( $limit['_usage_limit'] ) ? $limit['_usage_limit'] : '';
	}
	/**
	 * Get Rule Start Date
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__start_date( $rule ) {
		$limit = isset( $rule['limit'] ) ? $rule['limit'] : array();
		return isset( $limit['_start_date'] ) ? $limit['_start_date'] : '';
	}
	/**
	 * Get Rule End Date
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__end_date( $rule ) {
		$limit = isset( $rule['limit'] ) ? $rule['limit'] : array();
		return isset( $limit['_end_date'] ) ? $limit['_end_date'] : '';
	}
	/**
	 * Get Rule Specific User
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_specific_users( $rule ) {
		$users = isset( $rule['specific_users'] ) ? $rule['specific_users'] : array();
		$data  = array();
		foreach ( $users as $user ) {
			$data[] = $user['value'];
		}
		return implode( ';', $data );
	}
	/**
	 * Get Rule Specific Roles
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_specific_roles( $rule ) {
		$roles         = isset( $rule['specific_roles'] ) ? $rule['specific_roles'] : array();
		$data          = array();
		$get_role_name = apply_filters( 'wholesalex_dynamic_rule_exporter_get_role_name', false );
		foreach ( $roles as $role ) {
			if ( $get_role_name ) {
				$data[] = $role['name'];
			} else {
				$data[] = $role['value'] . '(' . $role['name'] . ')';
			}
		}
		return implode( ';', $data );
	}

	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_products_in_list( $rule ) {
		$products = isset( $rule['products_in_list'] ) ? $rule['products_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}
	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_products_not_in_list( $rule ) {
		$products = isset( $rule['products_not_in_list'] ) ? $rule['products_not_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}

	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_cat_in_list( $rule ) {
		$products = isset( $rule['cat_in_list'] ) ? $rule['cat_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}
	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_cat_not_in_list( $rule ) {
		$products = isset( $rule['cat_not_in_list'] ) ? $rule['cat_not_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}

	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_attribute_in_list( $rule ) {
		$products = isset( $rule['attribute_in_list'] ) ? $rule['attribute_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}
	/**
	 * Get Rule Specific Product
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_attribute_not_in_list( $rule ) {
		$products = isset( $rule['attribute_not_in_list'] ) ? $rule['attribute_not_in_list'] : array();
		$data     = array();
		foreach ( $products as $product ) {
			$data[] = $product['value'];
		}
		return implode( ';', $data );
	}

	/**
	 * Get Product Discount
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_product_discount( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'product_discount' === $rule['_rule_type'] ) {
			$discount = isset( $rule['product_discount'] ) ? $rule['product_discount'] : array();
			$data     = sprintf( '_discount_type:%s;_discount_amount:%s;_discount_name:%s', $discount['_discount_type'], $discount['_discount_amount'], $discount['_discount_name'] );
		}
		return $data;
	}
	/**
	 * Get Cart Discount
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_cart_discount( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'cart_discount' === $rule['_rule_type'] ) {
			$discount = isset( $rule['cart_discount'] ) ? $rule['cart_discount'] : array();
			$data     = sprintf( '_discount_type:%s;_discount_amount:%s;_discount_name:%s', $discount['_discount_type'], $discount['_discount_amount'], $discount['_discount_name'] );
		}
		return $data;
	}
	/**
	 * Get Cart Discount
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_payment_order_qty( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'payment_order_qty' === $rule['_rule_type'] ) {
			$discount = isset( $rule['payment_order_qty'] ) ? $rule['payment_order_qty'] : array();
			if ( ! is_array( $discount ) || ! isset( $discount['_payment_gateways'] ) || ! is_array( $discount['_payment_gateways'] ) ) {
				return '';
			}
			$gateway_data = array();
			if ( ! isset( $discount['_payment_gateways'] ) ) {
				return '';
			}
			if ( ! is_array( $discount['_payment_gateways'] ) ) {
				$discount['_payment_gateways'] = array();
			}
			foreach ( $discount['_payment_gateways'] as $gateway ) {
				$gateway_data[] = $gateway['value'] . '(' . $gateway['name'] . ')';
			}
			$data = sprintf( '_payment_gateways:%s;_order_quantity:%s', implode( ',', $gateway_data ), $discount['_order_quantity'] );
		}
		return $data;
	}

	/**
	 * Get Payment Discount
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_payment_discount( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'payment_discount' === $rule['_rule_type'] ) {
			$discount     = isset( $rule['payment_discount'] ) ? $rule['payment_discount'] : array();
			$gateway_data = array();
			if ( ! isset( $discount['_payment_gateways'] ) ) {
				return '';
			}
			if ( ! is_array( $discount['_payment_gateways'] ) ) {
				$discount['_payment_gateways'] = array();
			}
			foreach ( $discount['_payment_gateways'] as $gateway ) {
				$gateway_data[] = $gateway['value'] . '(' . $gateway['name'] . ')';
			}
			$data = sprintf( '_payment_gateways:%s;_discount_type:%s;_discount_amount:%s;_discount_name:%s', implode( ',', $gateway_data ), $discount['_discount_type'], $discount['_discount_amount'], $discount['_discount_name'] );
		}
		return $data;
	}

	/**
	 * Get Payment Discount
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_extra_charge( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'extra_charge' === $rule['_rule_type'] ) {
			$discount     = isset( $rule['extra_charge'] ) ? $rule['extra_charge'] : array();
			$gateway_data = array();
			if ( ! isset( $discount['_payment_gateways'] ) ) {
				return '';
			}
			if ( ! is_array( $discount['_payment_gateways'] ) ) {
				$discount['_payment_gateways'] = array();
			}
			foreach ( $discount['_payment_gateways'] as $gateway ) {
				$gateway_data[] = $gateway['value'] . '(' . $gateway['name'] . ')';
			}
			$data = sprintf( '_payment_gateways:%s;_charge_type:%s;_charge_amount:%s;_charge_name:%s', implode( ',', $gateway_data ), $discount['_charge_type'], $discount['_charge_amount'], $discount['_charge_name'] );
		}
		return $data;
	}
	/**
	 * Get Tax Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_tax_rule( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'tax_rule' === $rule['_rule_type'] ) {
			$discount = isset( $rule['tax_rule'] ) ? $rule['tax_rule'] : array();
			if ( ! is_array( $discount ) ) {
				return '';
			}
			$data = sprintf( '_tax_exempted:%s;_tax_class:%s', $discount['_tax_exempted'], isset( $discount['_tax_class'] ) ? $discount['_tax_class'] : '' );
		}
		return $data;
	}


	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_shipping_rule( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'shipping_rule' === $rule['_rule_type'] ) {
			$discount           = isset( $rule['shipping_rule'] ) ? $rule['shipping_rule'] : array();
			$shipping_zone_data = array();
			if ( ! isset( $discount['_shipping_zone_methods'] ) ) {
				return '';
			}
			if ( ! is_array( $discount['_shipping_zone_methods'] ) ) {
				$discount['_shipping_zone_methods'] = array();
			}
			foreach ( $discount['_shipping_zone_methods'] as $shipping_zone ) {
				$shipping_zone_data[] = $shipping_zone['value'] . '(' . $shipping_zone['name'] . ')';
			}
			$data = sprintf( '__shipping_zone:%s;_shipping_zone_methods:%s', $discount['__shipping_zone'], implode( ',', $shipping_zone_data ) );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_buy_x_get_one( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'buy_x_get_one' === $rule['_rule_type'] ) {
			$discount = isset( $rule['buy_x_get_one'] ) ? $rule['buy_x_get_one'] : array();
			$data     = sprintf( '_minimum_purchase_count:%s', $discount['_minimum_purchase_count'] );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_buy_x_get_y( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'buy_x_get_y' === $rule['_rule_type'] ) {
			$discount   = isset( $rule['buy_x_get_y'] ) ? $rule['buy_x_get_y'] : array();
			$free_items = array();
			if ( ! isset( $discount['_free_item'] ) ) {
				return '';
			}
			if ( ! is_array( $discount['_free_item'] ) ) {
				$discount['_free_item'] = array();
			}
			foreach ( $discount['_free_item'] as $item ) {
				$free_items[] = $item['value'];
			}
			$data = sprintf( '_minimum_purchase_count:%s;_free_item:%s;_free_item_count:%s', $discount['_minimum_purchase_count'], implode( ',', $free_items ), $discount['_free_item_count'] );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_min_order_qty( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'min_order_qty' === $rule['_rule_type'] ) {
			$discount = isset( $rule['min_order_qty'] ) ? $rule['min_order_qty'] : array();
			$data     = sprintf( '_min_order_qty:%s', $discount['_min_order_qty'] );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_max_order_qty( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'max_order_qty' === $rule['_rule_type'] ) {
			$discount = isset( $rule['max_order_qty'] ) ? $rule['max_order_qty'] : array();
			$data     = sprintf( '_max_order_qty:%s', $discount['_max_order_qty'] );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_quantity_based( $rule ) {
		$data = '';
		if ( isset( $rule['_rule_type'] ) && 'quantity_based' === $rule['_rule_type'] ) {
			$tiers     = isset( $rule['quantity_based']['tiers'] ) ? $rule['quantity_based']['tiers'] : array();
			$tier_data = array();
			foreach ( $tiers as $tier ) {
				$tier_data[] = sprintf( '_discount_type:%s,_discount_amount:%s,_min_quantity:%s,_discount_name:%s', $tier['_discount_type'], $tier['_discount_amount'], $tier['_min_quantity'], $tier['_discount_name'] );
			}
			$data = implode( ';', $tier_data );
		}
		return $data;
	}
	/**
	 * Get Shipping Rule
	 *
	 * @param array $rule Rule being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value_conditions( $rule ) {
		$data = '';
		if ( isset( $rule['conditions'] ) && ! empty( $rule['conditions'] ) ) {
			$tiers     = isset( $rule['conditions']['tiers'] ) ? $rule['conditions']['tiers'] : array();
			$tier_data = array();
			foreach ( $tiers as $tier ) {
				if ( isset( $tier['_conditions_for'], $tier['_conditions_operator'], $tier['_conditions_value'] ) ) {
					$tier_data[] = sprintf( '_conditions_for:%s,_conditions_operator:%s,_conditions_value:%s', $tier['_conditions_for'], $tier['_conditions_operator'], $tier['_conditions_value'] );
				}
			}
			$data = implode( ';', $tier_data );

		}
		return $data;
	}


}
