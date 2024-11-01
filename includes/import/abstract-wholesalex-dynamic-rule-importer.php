<?php
/**
 * Abstract WholesaleX Dynamic Rule Importer
 * Inspired By WooCommerce Core Product Importer
 *
 * @package  WHOLESALEX
 * @version  1.2.9
 */

namespace WHOLESALEX;

use Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WHOLESALEX_Importer_Interface', false ) ) {
	include_once WHOLESALEX_PATH . 'includes/import/class-wholesalex-importer-interface.php';
}

/**
 * WHOLESALEX_Dynamic_Rule_Importer Class.
 */
abstract class WHOLESALEX_Dynamic_Rule_Importer implements WHOLESALEX_Importer_Interface {

	/**
	 * CSV file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * The file position after the last read.
	 *
	 * @var int
	 */
	protected $file_position = 0;

	/**
	 * Importer parameters.
	 *
	 * @var array
	 */
	protected $params = array();

	/**
	 * Raw keys - CSV raw headers.
	 *
	 * @var array
	 */
	protected $raw_keys = array();

	/**
	 * Mapped keys - CSV headers.
	 *
	 * @var array
	 */
	protected $mapped_keys = array();

	/**
	 * Raw data.
	 *
	 * @var array
	 */
	protected $raw_data = array();

	/**
	 * Raw data.
	 *
	 * @var array
	 */
	protected $file_positions = array();

	/**
	 * Parsed data.
	 *
	 * @var array
	 */
	protected $parsed_data = array();

	/**
	 * Start time of current import.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 */
	protected $start_time = 0;

	/**
	 * Get file raw headers.
	 *
	 * @return array
	 */
	public function get_raw_keys() {
		return $this->raw_keys;
	}

	/**
	 * Get file mapped headers.
	 *
	 * @return array
	 */
	public function get_mapped_keys() {
		return ! empty( $this->mapped_keys ) ? $this->mapped_keys : $this->raw_keys;
	}

	/**
	 * Get raw data.
	 *
	 * @return array
	 */
	public function get_raw_data() {
		return $this->raw_data;
	}

	/**
	 * Get parsed data.
	 *
	 * @return array
	 */
	public function get_parsed_data() {
		/**
		 * Filter wholesalex roles importer parsed data.
		 *
		 * @param array $parsed_data Parsed data.
		 * @param WHOLESALEX_Dynamic_Rule_Importer $importer Importer instance.
		 */
		return apply_filters( 'wholesalex_dynamic_rule_importer_parsed_data', $this->parsed_data, $this );
	}

	/**
	 * Get importer parameters.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get file pointer position from the last read.
	 *
	 * @return int
	 */
	public function get_file_position() {
		return $this->file_position;
	}

	/**
	 * Get file pointer position as a percentage of file size.
	 *
	 * @return int
	 */
	public function get_percent_complete() {
		$size = filesize( $this->file );
		if ( ! $size ) {
			return 0;
		}

		return absint( min( floor( ( $this->file_position / $size ) * 100 ), 100 ) );
	}

	/**
	 * Process a single item and save.
	 *
	 * @throws Exception If item cannot be processed.
	 * @param  array $data Raw CSV data.
	 * @return array|WP_Error
	 */
	protected function process_item( $data ) {
		try {
			do_action( 'wholesalex_dynamic_rule_import_before_process_item', $data );
			$data = apply_filters( 'wholesalex_dynamic_rule_import_process_item_data', $data );

			$rule     = wholesalex()->get_dynamic_rules( $data['id'] );
			$updating = false;

			if ( ! empty( $rule ) ) {
				$updating = true;
				$rule     = $data;
			} else {
				$rule = $data;
			}

			if ( isset( $data['_start_date'] ) ) {
				$rule['limit']['_start_date'] = $data['_start_date'];
				unset( $rule['_start_date'] );
			}
			if ( isset( $data['_end_date'] ) ) {
				$rule['limit']['_end_date'] = $data['_end_date'];
				unset( $rule['_end_date'] );
			}
			if ( isset( $data['_usage_limit'] ) ) {
				$rule['limit']['_usage_limit'] = $data['_usage_limit'];
				unset( $rule['_usage_limit'] );
			}

			$rule = apply_filters( 'wholesalex_dynamic_rule_import_pre_insert_rule_data', $rule, $data );

			wholesalex()->set_dynamic_rules( $rule['id'], $rule );

			do_action( 'wholesalex_dynamic_rule_import_inserted_rule', $rule, $data );

			return array(
				'id'      => $rule['id'],
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'wholesalex_dynamic_rule_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}




	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;
		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}
		return apply_filters( 'wholesalex_dynamic_rule_importer_memory_exceeded', $return );
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @return bool
	 */
	protected function time_exceeded() {
		$finish = $this->start_time + apply_filters( 'wholesalex_dynamic_rule_importer_default_time_limit', 20 ); // 20 seconds
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}
		return apply_filters( 'wholesalex_dynamic_rule_importer_time_exceeded', $return );
	}

	/**
	 * Explode CSV cell values using commas by default, and handling escaped
	 * separators.
	 *
	 * @since  1.2.9
	 * @param  string $value     Value to explode.
	 * @param  string $separator Separator separating each value. Defaults to comma.
	 * @return array
	 */
	protected function explode_values( $value, $separator = ',' ) {
		$value  = str_replace( '\\,', '::separator::', $value );
		$values = explode( $separator, $value );
		$values = array_map( array( $this, 'explode_values_formatter' ), $values );

		return $values;
	}

	/**
	 * Remove formatting and trim each value.
	 *
	 * @since  1.2.9
	 * @param  string $value Value to format.
	 * @return string
	 */
	protected function explode_values_formatter( $value ) {
		return trim( str_replace( '::separator::', ',', $value ) );
	}

	/**
	 * The exporter prepends a ' to escape fields that start with =, +, - or @.
	 * Remove the prepended ' character preceding those characters.
	 *
	 * @since 1.2.9
	 * @param  string $value A string that may or may not have been escaped with '.
	 * @return string
	 */
	protected function unescape_data( $value ) {
		$active_content_triggers = array( "'=", "'+", "'-", "'@" );

		if ( in_array( mb_substr( $value, 0, 2 ), $active_content_triggers, true ) ) {
			$value = mb_substr( $value, 1 );
		}

		return $value;
	}

}
