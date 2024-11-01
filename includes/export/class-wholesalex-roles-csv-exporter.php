<?php
/**
 * Handles WholesaleX Roles CSV export.
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
 * WHOLESALEX_Role_CSV_Exporter Class.
 */
class WHOLESALEX_Role_CSV_Exporter extends \WC_CSV_Batch_Exporter {

	/**
	 * Type of export used in filter names.
	 *
	 * @var string
	 */
	protected $export_type = 'role';

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
				'id'                              => __( 'ID', 'wholesalex' ),
				'_role_title'                     => __( 'Role Title', 'wholesalex' ),
				'_shipping_methods'               => __( 'Shipping Methods', 'wholesalex' ),
				'_payment_methods'                => __( 'Payment Methods', 'wholesalex' ),
				'_disable_coupon'                 => __( 'Disable Coupon', 'wholesalex' ),
				'_auto_role_migration'            => __( 'Auto Role Migration', 'wholesalex' ),
				'_role_migration_threshold_value' => __( 'Role Migration Threshold Value', 'wholesalex' ),
			)
		);
	}

	/**
	 * Prepare data for export.
	 *
	 * @since 1.2.9
	 */
	public function prepare_data_to_export() {

		$roles = get_option( '_wholesalex_roles' );

		$this->total_rows = count( $roles );
		$this->row_data   = array();

		foreach ( $roles as $role ) {
			$this->row_data[] = $this->generate_row_data( $role );
		}

	}

	/**
	 * Take a Role and generate row data from it for export.
	 *
	 * @param array $role WholesaleX Role.
	 *
	 * @return array
	 */
	protected function generate_row_data( $role ) {
		$columns = $this->get_column_names();
		$row     = array();
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			if ( has_filter( "wholesalex_role_export_{$this->export_type}_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "wholesalex_role_export_{$this->export_type}_column_{$column_id}", '', $role, $column_id );

			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $role );

			}

			$row[ $column_id ] = $value;

		}
		/**
		 * Allow third-party plugins to filter the data in a single row of the exported CSV file.
		 *
		 * @since 1.2.5
		 *
		 * @param array                   $row         An associative array with the data of a single row in the CSV file.
		 * @param array                   $role        WholesaleX Role
		 * @param WHOLESALEX_Role_CSV_Exporter $exporter    The instance of the CSV exporter.
		 */
		return apply_filters( 'wholesalex_role_export_row_data', $row, $role, $this );
	}

	/**
	 * Get Role ID
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return int|string
	 */
	protected function get_column_value_id( $role ) {
		$id = isset( $role['id'] ) ? $role['id'] : 0;
		if ( is_numeric( $id ) ) {
			$id = 'wholesalex_old_' . $id;
		}
		return $id;
	}

	/**
	 * Get Role Title
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__role_title( $role ) {
		return isset( $role['_role_title'] ) ? $role['_role_title'] : __( 'Untitled', 'wholesalex' );
	}
	/**
	 * Get Role Shipping Method Ids
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__shipping_methods( $role ) {
		$methods = isset( $role['_shipping_methods'] ) && is_array( $role['_shipping_methods'] ) ? implode( ',', array_filter( $role['_shipping_methods'] ) ) : '';
		return $methods;
	}

	/**
	 * Get Role Payment Method Ids
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__payment_methods( $role ) {
		$methods = isset( $role['_payment_methods'] ) && is_array( $role['_payment_methods'] ) ? implode( ',', array_filter( $role['_payment_methods'] ) ) : '';
		return $methods;
	}

	/**
	 * Get Auto Role Migration Status
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__disable_coupon( $role ) {
		$methods = isset( $role['_disable_coupon'] ) ? $role['_disable_coupon'] : '';
		return $methods;
	}

	/**
	 * Get Auto Role Migration Status
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__auto_role_migration( $role ) {
		$methods = isset( $role['_auto_role_migration'] ) ? $role['_auto_role_migration'] : '';
		return $methods;
	}

	/**
	 * Get Auto Role Migration Threshold Value
	 *
	 * @param array $role Role being exported.
	 *
	 * @since  1.2.9
	 * @return string
	 */
	protected function get_column_value__role_migration_threshold_value( $role ) {
		$methods = isset( $role['_role_migration_threshold_value'] ) ? $role['_role_migration_threshold_value'] : '';
		return $methods;
	}


}
