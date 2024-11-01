<?php
/**
 * Addons Page
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Setting Class
 */
class Addons {

	/**
	 * Setting Constructor
	 */
	public function __construct() {
		add_filter( 'wholesalex_addons_config', array( $this, 'pro_addons_config' ), 1 );

		add_action( 'rest_api_init', array( $this, 'register_addons_restapi' ) );

		add_action( 'admin_menu', array( $this, 'submenu_page' ) );

	}

	/**
	 * Addons Page Submenu
	 *
	 * @return void
	 */
	public function submenu_page() {
		$slug = apply_filters( 'wholesalex_addons_submenu_slug', 'wholesalex-addons' );
		$title = sprintf('<span class="wholesalex-submenu-title__addons">%s</span>',__('Addons','wholesalex'));
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__( 'Addons', 'wholesalex' ),
			$title,
			apply_filters( 'wholesalex_capability_access', 'manage_options' ),
			$slug,
			array( $this, 'addons_page_content' ),
			7
		);
	}

	/**
	 * Register addon restapi route
	 *
	 * @return void
	 */
	public function register_addons_restapi() {
		register_rest_route(
			'wholesalex/v1',
			'/addons/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'addon_restapi_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Addon RestAPI Callback
	 *
	 * @param object $server Server.
	 * @return void
	 */
	public function addon_restapi_callback( $server ) {
		$post = $server->get_params();
	
		// Nonce validation
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh the page and try again.', 'wholesalex' ) ) );
			return;
		}
	
		$type = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';
	
		$response = array(
			'status' => false,
			'data'   => array(),
		);
	
		switch ( $type ) {
			case 'get':
				$response['status'] = true;
				$response['data']   = $this->get_addons();
				break;
	
			case 'post':
				$request_for = isset( $post['request_for'] ) ? sanitize_text_field( $post['request_for'] ) : '';
				$addon       = isset( $post['addon'] ) ? sanitize_text_field( $post['addon'] ) : '';
	
				if ( 'install_plugin' === $request_for ) {
					$response['status'] = true;
					$response['data']   = $this->install_and_active_plugin( $addon );
					wp_send_json( $response );
					return;  // No need for die(), return will stop execution
				}
	
				$addon_name  = isset( $post['addon'] ) ? sanitize_text_field( $post['addon'] ) : '';
				$addon_value = isset( $post['status'] ) ? sanitize_text_field( $post['status'] ) : '';
	
				// Handle specific addon cases
				if ( 'wsx_addon_dokan_integration' === $addon_name ) {
					if ( 'no' === $addon_value ) {
						$activate_status = deactivate_plugins( 'multi-vendor-marketplace-b2b-for-wholesalex-dokan/multi-vendor-marketplace-b2b-for-wholesalex-dokan.php', true );
						$message         = 'Success';
					} else {
						$activate_status = activate_plugin( 'multi-vendor-marketplace-b2b-for-wholesalex-dokan/multi-vendor-marketplace-b2b-for-wholesalex-dokan.php', '', false, true );
						$message = is_wp_error( $activate_status ) ? $activate_status->get_error_message() : 'Successfully Installed and Activated';
					}
					wp_send_json(
						array(
							'status' => true,
							'data'   => $message,
						)
					);
					return;
				}
	
				if ( 'wsx_addon_wcfm_integration' === $addon_name ) {
					if ( 'no' === $addon_value ) {
						$activate_status = deactivate_plugins( 'wholesalex-wcfm-b2b-multivendor-marketplace/wholesalex-wcfm-b2b-multivendor-marketplace.php', true );
						$message         = 'Success';
					} else {
						$activate_status = activate_plugin( 'wholesalex-wcfm-b2b-multivendor-marketplace/wholesalex-wcfm-b2b-multivendor-marketplace.php', '', false, true );
						$message = is_wp_error( $activate_status ) ? $activate_status->get_error_message() : 'Successfully Installed and Activated';
					}
					wp_send_json(
						array(
							'status' => true,
							'data'   => $message,
						)
					);
					return;
				}
	
				if ( 'wsx_addon_recaptcha' === $addon_name ) {
					$__site_key   = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
					$__secret_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
					if ( empty( $__site_key ) || empty( $__secret_key ) ) {
						wp_send_json_error(
							array(
								'message' => sprintf(
									__( 'Please set Site Key and Secret Key before enabling Recaptcha (Path: Dashboard > %s > Settings > Recaptcha)', 'wholesalex' ),
									wholesalex()->get_plugin_name()
								),
							)
						);
						return;
					}
				}
	
				// General addon processing with hooks
				do_action( 'wholesalex_' . $addon_name . '_before_status_update', $addon_value );
				$error = apply_filters( 'wholesalex_' . $addon_name . '_error', '', $addon_value );
				
				// Validate permissions and errors
				if ( $addon_name && current_user_can( 'administrator' ) && '' === $error ) {
					$addon_data                                    = wholesalex()->get_setting();
					$addon_data[ $addon_name ]                     = $addon_value;
					$GLOBALS['wholesalex_settings'][ $addon_name ] = $addon_value;
					update_option( 'wholesalex_settings', $addon_data );
					do_action( 'wholesalex_' . $addon_name . '_after_status_update', $addon_value );
					
					$response['status'] = true;
					$response['data']   = __( 'Successfully Updated!', 'wholesalex' );
				} else {
					$response['status'] = false;
					$response['data']   = __( 'Update Failed!', 'wholesalex' );
				}
				break;
	
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid request type.', 'wholesalex' ) ) );
				return;
		}
	
		wp_send_json( $response );
	}

	/**
	 * Install and Active Addon Plugin
	 *
	 * @param string $addon AddonId.
	 * @return string
	 */
	public function install_and_active_plugin( $addon ) {

		$message    = '';
		$plugin_url = '';
		if ( 'wsx_addon_dokan_integration' == $addon ) {
			$plugin_url = 'https://downloads.wordpress.org/plugin/multi-vendor-marketplace-b2b-for-wholesalex-dokan.zip';
		} elseif ( 'wsx_addon_wcfm_integration' == $addon ) {
			$plugin_url = 'https://downloads.wordpress.org/plugin/wholesalex-wcfm-b2b-multivendor-marketplace.zip';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $plugin_url );

		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();
		} else {
			if ( 'wsx_addon_dokan_integration' == $addon ) {
				deactivate_plugins( 'wholesalex-dokan-b2b-multi-vendor-marketplace/wholesalex-dokan-b2b-multi-vendor-marketplace.php', true );
				$activate_status = activate_plugin( 'multi-vendor-marketplace-b2b-for-wholesalex-dokan/multi-vendor-marketplace-b2b-for-wholesalex-dokan.php', '', false, true );
			} elseif ( 'wsx_addon_wcfm_integration' == $addon ) {
				$activate_status = activate_plugin( 'wholesalex-wcfm-b2b-multivendor-marketplace/wholesalex-wcfm-b2b-multivendor-marketplace.php', '', false, true );
			}
			if ( is_wp_error( $activate_status ) ) {
				$message = $activate_status->get_error_message();
			} else {
				$message = 'Successfully Installed and Activated';
			}
		}

		return $message;
	}

	/**
	 * Get All Addons
	 *
	 * @return array
	 */
	public function get_addons() {
		$addons_data = apply_filters( 'wholesalex_addons_config', array() );

		return $addons_data;
	}

	/**
	 * Pro Addons Config
	 *
	 * @param object $config Addon Configuration.
	 * @return object $config .
	 * @since 1.0.0
	 * @since 1.0.4 Add Bulk Order.
	 */
	public function pro_addons_config( $config ) {
		$config['wsx_addon_bulkorder'] = array(
			'name'                => __( 'Bulk Order Form', 'wholesalex' ),
			'desc'                => __( 'Let buyers order products in bulk using the bulk order form. Customers can access them from their account page or create their own purchase list.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/bulkorder.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/add-on/bulk-order/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'eligible_price_ids'  => array( '1', '2', '3', '4', '5', '6', '7' ),
			'moreFeature'         => 'https://getwholesalex.com/bulk-order/?utm_source=wholesalex-menu&utm_medium=addons-more_features&utm_campaign=wholesalex-DB',
			'video'               => 'https://www.youtube.com/embed/uwHojBY0lZk',
			'status'              => wholesalex()->get_setting( 'wsx_addon_bulkorder' ),
			'setting_id'          => '#bulkorder',
			'lock_status'         => ! ( wholesalex()->is_pro_active() ),
		);

		$config['wsx_addon_subaccount'] = array(
			'name'                => __( 'Subaccounts ', 'wholesalex' ),
			'desc'                => __( 'Registered users in the B2B store can create sub accounts - allowing sub account holders to purchase products and maintain the account on behalf of the main account holder.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/subaccount.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/add-on/subaccounts/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'eligible_price_ids'  => array( '1', '2', '3', '4', '5', '6', '7' ),
			'moreFeature'         => 'https://getwholesalex.com/subaccounts-in-woocommerce-b2b-stores/?utm_source=wholesalex-menu&utm_medium=addons-more_features&utm_campaign=wholesalex-DB',
			'video'               => 'https://www.youtube.com/embed/cO4AYwkXyco',
			'status'              => wholesalex()->get_setting( 'wsx_addon_subaccount' ),
			'lock_status'         => ! ( wholesalex()->is_pro_active() ),
		);

		$config['wsx_addon_raq'] = array(
			'name'                => __( 'Request a Quote', 'wholesalex' ),
			'desc'                => __( 'Send and receive custom quotes from buyers directly. Users can send custom quote queries from the cart page. Admins can negotiate on the quote directly.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/raq.svg',
			'docs'                => 'https://getwholesalex.com/request-a-quote/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'depends_on'          => apply_filters( 'wholesalex_addon_raq_depends_on', array( 'wsx_addon_conversation' => __( 'Conversation', 'wholesalex' ) ) ),
			'eligible_price_ids'  => array( '1', '2', '3', '4', '5', '6', '7' ),
			'moreFeature'         => 'https://getwholesalex.com/request-a-quote/?utm_source=wholesalex-menu&utm_medium=addons-more_features&utm_campaign=wholesalex-DB',
			'video'               => 'https://www.youtube.com/embed/jOIdNj18OEI',
			'status'              => wholesalex()->get_setting( 'wsx_addon_raq' ),
			'lock_status'         => ! ( wholesalex()->is_pro_active() ),
		);

		$config['wsx_addon_conversation'] = array(
			'name'                => __( 'Conversations', 'wholesalex' ),
			'desc'                => __( 'Enable “conversations” with the customer and admin. Users can directly send queries from their account page to the admin directly.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/conversation.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/add-on/conversation/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'eligible_price_ids'  => array( '1', '2', '3', '4', '5', '6', '7' ),
			'moreFeature'         => 'https://getwholesalex.com/conversation/?utm_source=wholesalex-menu&utm_medium=addons-more_features&utm_campaign=wholesalex-DB',
			'status'              => wholesalex()->get_setting( 'wsx_addon_conversation' ),
			'lock_status'         => ! ( wholesalex()->is_pro_active() ),
		);

		$config['wsx_addon_wallet']    = array(
			'name'                => __( 'WholesaleX Wallet', 'wholesalex' ),
			/* translators: %s Plugin Name */
			'desc'                => sprintf( __( 'Use the %s wallet for storewide payments. Purchase products by adding funds to your Wallet from the Wholesale store.', 'wholesalex' ), wholesalex()->get_plugin_name() ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/wallet.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/add-on/wallet/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'eligible_price_ids'  => array( '1', '2', '3', '4', '5', '6', '7' ),
			'moreFeature'         => 'https://getwholesalex.com/wallet/?utm_source=wholesalex-menu&utm_medium=addons-more_features&utm_campaign=wholesalex-DB',
			'status'              => wholesalex()->get_setting( 'wsx_addon_wallet' ),
			'setting_id'          => '#wallet',
			'lock_status'         => ! ( wholesalex()->is_pro_active() ),
		);

		$config['wsx_addon_whitelabel'] = array(
			'name'                => __( 'White Label', 'wholesalex' ),
			'desc'                => __( 'Brand you Wholesale store using the white label addon. Set the custom plugin name, change the email, registration, roles, and more.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/whitelabel.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/add-on/white-label/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'                => '',
			'is_pro'              => true,
			'is_different_plugin' => false,
			'eligible_price_ids'  => array( '3', '6', '7' ),
			'status'              => wholesalex()->get_setting( 'wsx_addon_whitelabel' ),
			'lock_status'         => ! ( wholesalex()->is_pro_active() && in_array( get_option( '__wholesalex_price_id', '' ), array( '3', '6', '7' ) ) ),
			'setting_id'          => '#whitelabel',
			'video'               => 'https://www.youtube.com/embed/xMTJYQFbWEw',
		);

		$config['wsx_addon_recaptcha'] = array(
			'name'        => __( 'reCAPTCHA', 'wholesalex' ),
			'desc'        => __( "Protect your website from suspicious login attempts with an added security layer using Google reCAPTCHA v3 for extended safety measures.  ", 'wholesalex' ),
			'img'         => WHOLESALEX_URL . 'assets/img/addons/recaptcha.svg',
			'docs'        => 'https://getwholesalex.com/docs/wholesalex/add-on/recaptcha/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'live'        => '',
			'is_pro'      => false,
			'moreFeature' => 'https://getwholesalex.com/docs/wholesalex/add-on/recaptcha/?utm_source=wholesalex-menu&utm_medium=addons-docs&utm_campaign=wholesalex-DB',
			'status'      => wholesalex()->get_setting( 'wsx_addon_recaptcha' ),
			'setting_id'  => '#recaptcha',
			'lock_status' => false,
		);

		$config['wsx_addon_dokan_integration'] = array(
			'name'                => __( 'WholesaleX for Dokan', 'wholesalex' ),
			'desc'                => __( 'Turn your store into a B2B multi-vendor marketplace. Create and manage wholesale discounts with dynamic rules and user roles. Also manage conversations with WholesaleX for Dokan.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/dokan_integration.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/wholesalex-for-dokan/',
			'live'                => '',
			'is_pro'              => false,
			'is_different_plugin' => true,
			'eligible_price_ids'  => array( 1, 2, 3, 4, 5, 6, 7 ),
			'status'              => function_exists( 'wholesalex_dokan_run' ),
			'lock_status'         => false,
			'setting_id'          => '#dokan_wholesalex',
			'is_installed'        => file_exists( WP_PLUGIN_DIR . '/multi-vendor-marketplace-b2b-for-wholesalex-dokan/multi-vendor-marketplace-b2b-for-wholesalex-dokan.php' ),
			'download_link'       => 'https://downloads.wordpress.org/plugin/multi-vendor-marketplace-b2b-for-wholesalex-dokan.zip',
			'video'               => 'https://www.youtube.com/embed/4UatlL2-XXo',
			// Translators: %s is the name of the required plugin
			'depends_message'     => sprintf( __('This addon require %s plugin', 'wholesalex'),'<a href="https://wordpress.org/plugins/dokan-lite/" target="_blank">Dokan</a>')
		);

		$config['wsx_addon_wcfm_integration'] = array(
			'name'                => __( 'WholesaleX for WCFM', 'wholesalex' ),
			'desc'                => __( 'Let vendors set wholesale prices and discounts to your wholesale store with WCFM for Dokan integration. Manage conversations with users as well in your B2B multi-vendor store.', 'wholesalex' ),
			'img'                 => WHOLESALEX_URL . 'assets/img/addons/wcfm_integration.svg',
			'docs'                => 'https://getwholesalex.com/docs/wholesalex/wcfm-marketplace-integration/',
			'live'                => '',
			'is_pro'              => false,
			'is_different_plugin' => true,
			'eligible_price_ids'  => array( 1, 2, 3, 4, 5, 6, 7 ),
			'status'              => function_exists( 'wholesalex_wcfm_run' ),
			'lock_status'         => false,
			'setting_id'          => '#wcfm_wholesalex',
			'is_installed'        => file_exists( WP_PLUGIN_DIR . '/wholesalex-wcfm-b2b-multivendor-marketplace/wholesalex-wcfm-b2b-multivendor-marketplace.php' ),
			'download_link'       => 'https://downloads.wordpress.org/plugin/wholesalex-wcfm-b2b-multivendor-marketplace.zip',
			'video'               => 'https://www.youtube.com/embed/2OLOqyvv5rE',
			// Translators: %s is the name of the required plugin with an HTML link.
			'depends_message'     => sprintf( __('This addon require %s plugin', 'wholesalex'),'<a href="https://wordpress.org/plugins/wc-frontend-manager/" target="_blank">WCFM – Frontend Manager</a>')
		);
		return $config;
	}

	public function addons_page_content() {
		wp_enqueue_script( 'whx_addons' );
		wp_enqueue_script( 'wholesalex_node_vendors' );
		wp_enqueue_script( 'wholesalex_components' );
		$setting_slug = apply_filters( 'wholesalex_settings_submenu_slug', 'wholesalex-settings' );
		wp_localize_script(
			'whx_addons',
			'whx_addons',
			array(
				'addons'      => $this->get_addons(),
				'setting_url' => menu_page_url( $setting_slug, false ),
			)
		);
		?>
			<div id="wholesalex_addons_root"> </div>
		<?php
	}
}
