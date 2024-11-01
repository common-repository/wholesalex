<?php
/**
 * Group Action.
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

defined( 'ABSPATH' ) || exit;
/**
 * WholesaleX RequestAPI Class.
 */
class WHOLESALEX_Request_API {

	/**
	 * RequestAPI Constructor
	 */
	public function __construct() {
		// Addon Enable - Disable.
		add_action( 'wp_ajax_wsx_addon', array( $this, 'addon_active_callback' ) );
	}
	/**
	 * Addon Activation Callback
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.0.5 Added wholesalex_{addon_name}_before_active and after_active hook.
	 *              Added wholesalex_{addon_name}_error Filter Hook
	 */
	public function addon_active_callback() {
		if ( ! ( isset( $_REQUEST['wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['wpnonce'] ) ), 'wholesalex-registration' ) ) ) {
			return;
		}
		$addon_name  = isset( $_POST['addon'] ) ? sanitize_text_field( $_POST['addon'] ) : '';
		$addon_value = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '';
		if ( 'wsx_addon_recaptcha' === $addon_name ) {
			$__site_key   = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
			$__secret_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
			if ( empty( $__site_key ) || empty( $__secret_key ) ) {
				/* translators: %1s Plugin Name */
				wp_send_json_error( sprintf( __( 'Please Set Site Key and Secret Key Before Enable Recaptcha (Path: Dashboard > %s > Settings > Recaptcha)', 'wholesalex' ) ),wholesalex()->get_plugin_name());
			}
		}
		do_action( 'wholesalex_' . $addon_name . '_before_status_update', $addon_value );
		$error = apply_filters( 'wholesalex_' . $addon_name . '_error', '', $addon_value );
		if ( $addon_name && current_user_can( 'administrator' ) && '' === $error ) {
			$addon_data                                    = wholesalex()->get_setting();
			$addon_data[ $addon_name ]                     = $addon_value;
			$GLOBALS['wholesalex_settings'][ $addon_name ] = $addon_value;
			update_option( 'wholesalex_settings', $addon_data );
			do_action( 'wholesalex_' . $addon_name . '_after_status_update', $addon_value );
			wp_send_json_success();
		} else {
			wp_send_json_error( $error );
		}
	}
}
