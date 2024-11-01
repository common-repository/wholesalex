<?php
/**
 * Recaptcha Addon Init
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'wholesalex_addons_config', 'wholesalex_recaptcha_config', 1 );
/**
 * WholesaleX Recaptcha Config
 *
 * @param object $config Addon Configuration.
 * @return object $config .
 * @since 1.0.0
 */
function wholesalex_recaptcha_config( $config ) {
	$config['wsx_addon_recaptcha'] = array(
		'name'        => __( 'reCAPTCHA', 'wholesalex' ),
		'desc'        => __( "Protect your website from suspicious login attempts with an added security layer using Google reCAPTCHA v3 for extended safety measures.", 'wholesalex' ),
		'img'         => WHOLESALEX_URL . 'assets/img/addons/recaptcha.svg',
		'docs'        => 'https://getwholesalex.com/docs/wholesalex/add-on/recaptcha/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
		'live'        => '',
		'is_pro'      => false,
		'moreFeature' => 'https://getwholesalex.com/docs/wholesalex/add-on/recaptcha/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
		'status'      => wholesalex()->get_setting( 'wsx_addon_recaptcha' ),
		'setting_id'  => '#recaptcha',
		'lock_status' => false,

	);
	return $config;
}

add_action( 'init', 'wholesalex_recaptcha_init' );
/**
 * WholesaleX Recaptcha Init
 *
 * @return void
 * @since 1.0.0
 */
function wholesalex_recaptcha_init() {
	$is_enable  = 'yes' === wholesalex()->get_setting( 'wsx_addon_recaptcha' );
	$site_key   = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
	$secret_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
	if ( $is_enable && $site_key && $secret_key ) {
		require_once WHOLESALEX_PATH . 'addons/recaptcha/class-recaptcha.php';
		new \WHOLESALEX\Recaptcha();
	}
}


/**
 * WholesaleX Recaptcha Instance
 *
 * @return Recaptcha WholesaleX Recaptcha Instance.
 * @since 1.0.0
 */
function wholesalex_recaptcha() {
	require_once WHOLESALEX_PATH . 'addons/recaptcha/class-recaptcha.php';
	return WHOLESALEX\Recaptcha::instance();
}
