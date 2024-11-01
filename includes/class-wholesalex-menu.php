<?php
/**
 * WholesaleX Menu
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Menu Class.
 */
class WHOLESALEX_Menu {

	/**
	 * Menu Constructor
	 */
	public function __construct() {
		// add_action( 'admin_menu', array( $this, 'admin_menu_callback' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_settings_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . WHOLESALEX_BASE, array( $this, 'plugin_action_links_callback' ) );
	}

	/**
	 * Settings Pro Update Link
	 *
	 * @since v.1.0.0
	 * @return ARRAY
	 */
	public function plugin_action_links_callback( $links ) {
		$upgrade_link = array();
		$setting_link = array();
		if ( ! defined( 'WHOLESALEX_PRO_VER' ) ) {
			$upgrade_link = array(
				'wholesalex_pro' => '<a href="' . esc_url( 'https://getwholesalex.com/pricing/?utm_source=wholesalex-plugins&utm_medium=go_pro&utm_campaign=wholesalex-DB' ) . '" target="_blank"><span style="color: #e83838; font-weight: bold;">' . esc_html__( 'Go Pro', 'wholesalex' ) . '</span></a>',
			);
		}
		$setting_link['wholesalex_settings'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wholesalex-settings' ) ) . '">' . esc_html__( 'Settings', 'wholesalex' ) . '</a>';
		return array_merge( $setting_link, $links, $upgrade_link );
	}

	/**
	 * Plugin Page Menu Add
	 *
	 * @since v.1.0.0
	 * @return ARRAY
	 */
	public function plugin_settings_meta( $links, $file ) {
		if ( strpos( $file, 'wholesalex.php' ) !== false ) {
			$new_links = array(
				'wholesalex_docs'    => '<a href="https://getwholesalex.com/documentation/?utm_source=wholesalex_plugin&utm_medium=support&utm_campaign=wholesalex-DB" target="_blank">' . esc_html__( 'Docs', 'wholesalex' ) . '</a>',
				'wholesalex_support' => '<a href="' . esc_url( 'https://getwholesalex.com/contact/?utm_source=wholesalex_plugin&utm_medium=support&utm_campaign=wholesalex-DB' ) . '" target="_blank">' . esc_html__( 'Support', 'wholesalex' ) . '</a>',
			);
			$links     = array_merge( $links, $new_links );
		}
		return $links;
	}

	/**
	 * Add menu page
	 *
	 * @since 1.0.0
	 */
	public function admin_menu_callback() {
		// require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-users.php';
		require_once WHOLESALEX_PATH . 'includes/options/Addons.php';

		// new \WHOLESALEX\WHOLESALEX_Users();
		new \WHOLESALEX\Addons();
	}
}
