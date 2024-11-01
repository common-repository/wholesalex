<?php
/**
 * WooCommerce Product CSV importer
 * Inspired By WooCommerce Core Product Import
 *
 * @package WholesaleX
 * @version 1.2.9
 */

namespace WHOLESALEX;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WHOLESALEX_Role_Importer', false ) ) {
	include_once dirname( __FILE__ ) . '/abstract-wholesalex-role-importer.php';
}

if ( ! class_exists( 'WHOLESALEX_Role_CSV_Importer_Controller', false ) ) {
	include_once dirname( __FILE__ ) . '/class-wholesalex-role-csv-importer-controller.php';
}

/**
 * WHOLESALEX_Role_CSV_Importer Class.
 */
class WHOLESALEX_Role_CSV_Importer extends WHOLESALEX_Role_Importer {

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
		if ( ! WHOLESALEX_Role_CSV_Importer_Controller::is_file_valid_csv( $this->file ) ) {
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
		if ( $this->starts_with( $value, 'wholesalex_old_' ) ) {
			$value = str_replace( 'wholesalex_old_', '', $value );
		}
		return $value;
	}

	/**
	 * Parse Title Field
	 *
	 * @param string $value Role Title.
	 * @return string
	 */
	public function parse_title_field( $value ) {

		return sanitize_text_field( $value );
	}

	/**
	 * Parse Disable Coupon Field
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function parse_disable_coupon_field( $value ) {
		$value = sanitize_text_field( $value );
		if ( ! ( 'yes' === $value || 'no' === $value ) ) {
			$value = '';
		}
		return $value;
	}

	/**
	 * Parse auto role migration field
	 *
	 * @param string $value value.
	 * @return string
	 */
	public function parse_auto_role_migration_field( $value ) {

		return $value;
	}

	/**
	 * Parse relative comma-delineated field and return role ID.
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
			'id'                   => array( $this, 'parse_id_field' ),
			'_role_title'          => array( $this, 'parse_title_field' ),
			'_shipping_methods'    => array( $this, 'parse_comma_field' ),
			'_payment_methods'     => array( $this, 'parse_comma_field' ),
			'_disable_coupon'      => array( $this, 'parse_disable_coupon_field' ),
			'_auto_role_migration' => array( $this, 'parse_float_field' ),
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

		return apply_filters( 'wholesalex_role_importer_formatting_callbacks', $callbacks, $this );
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

			do_action( 'wholesalex_role_importer_before_set_parsed_data', $row, $mapped_keys );

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
			 * Filter role importer parsed data.
			 *
			 * @param array $parsed_data Parsed data.
			 * @param WHOLESALEX_Role_Importer $importer Importer instance.
			 *
			 * @since
			 */
			$this->parsed_data[] = apply_filters( 'wholesalex_role_importer_parsed_data', $data, $this );
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
		$title    = isset( $parsed_data['"_role_title"'] ) ? esc_attr( $parsed_data['"_role_title"'] ) : '';
		$row_data = array();

		if ( $title ) {
			$row_data[] = $title;
		}
		if ( $id ) {
			/* translators: %d: role ID */
			$row_data[] = sprintf( __( 'ID %d', 'wholesalex' ), $id );
		}

		return implode( ', ', $row_data );
	}

	/**
	 * Process importer.
	 *
	 * Do not import roles with IDs or SKUs that already exist if option
	 * update existing is false, and likewise, if updating roles, do not
	 * process rows which do not exist if an ID/SKU is provided.
	 *
	 * @return array
	 */
	public function import() {
		$this->start_time = time();
		$index            = 0;

		$update_existing = $this->params['update_existing'];
		$data            = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			do_action( 'wholesalex_role_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? $parsed_data['id'] : 0;
			$id_exists = false;

			if ( $id ) {
				$id_exists = ! empty( wholesalex()->get_roles( 'by_id', $id ) );
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'wholesalex_role_importer_error',
					esc_html__( 'A role with this ID already exists.', 'wholesalex' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( isset( $parsed_data['id'] ) ) && ! $id_exists ) {
				$data['skipped'][] = new WP_Error(
					'wholesalex_role_importer_error',
					esc_html__( 'No matching role exists to update.', 'wholesalex' ),
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
