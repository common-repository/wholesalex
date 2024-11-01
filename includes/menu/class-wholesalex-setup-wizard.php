<?php

/**
 * WholesaleX Initial Setup Wizard
 *
 * @package WHOLESALEX
 * @since 1.1.0
 */

namespace WHOLESALEX;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;

/**
 * WholesaleX Email Class
 */
class WHOLESALEX_Setup_Wizard {


	/**
	 * Contain Plugin Name
	 *
	 * @var string
	 */
	private static $PLUGIN_NAME = 'WholesaleX';
	/**
	 * Contain plugin slug
	 *
	 * @var string
	 */
	private static $PLUGIN_SLUG = 'wholesalex';
	/**
	 * Contain plugin ver
	 *
	 * @var string
	 */
	private static $PLUGIN_VERSION = WHOLESALEX_VER;
	/**
	 * Contain WPXPO Api End Point
	 *
	 * @var string
	 */
	private static $API_ENDPOINT = 'https://inside.wpxpo.com';
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'submenu_page' ) );
		add_action( 'wp_ajax_get_plugin_status', array( $this, 'get_plugin_status' ) );
		add_action( 'wp_ajax_set_plugin_status', array( $this, 'set_plugin_status' ) );

		add_action( 'wp_ajax_get_general_data', array( $this, 'get_general_data' ) );
		add_action( 'wp_ajax_save_general_data', array( $this, 'save_general_data' ) );

		add_action( 'wp_ajax_data_collection', array( $this, 'data_collection' ) );
		add_action( 'wp_ajax_wsx_wizard_update', array( $this, 'wsx_wizard_update' ) );

		add_action( 'in_admin_header', array( $this, 'remove_notices' ) );
	}

	/**
	 * WholesaleX Setup Wizard Add Submenu Page
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function submenu_page() {
		$slug              = apply_filters( 'wholesalex_setup_wizard_submenu_slug', 'wholesalex-setup-wizard' );
		$is_wizard_enabled = apply_filters( 'wholesalex_setup_wizard_menu_page_enabled', true );
		if ( $is_wizard_enabled ) {
			add_submenu_page(
				'wholesalex',
				__( 'Setup Wizard', 'wholesalex' ),
				__( 'Setup Wizard', 'wholesalex' ),
				apply_filters( 'wholesalex_capability_access', 'manage_options' ),
				$slug,
				array( $this, 'initial_setup_content' )
			);
		}
	}

	/**
	 * Addons
	 *
	 * @return array
	 */
	public function addons_config() {
		$config                        = array();
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
			'status'              => function_exists( 'run_wholesalex_dokan' ),
			'lock_status'         => false,
			'setting_id'          => '#dokan_wholesalex',
			'is_installed'        => file_exists( WP_PLUGIN_DIR . '/multi-vendor-marketplace-b2b-for-wholesalex-dokan/multi-vendor-marketplace-b2b-for-wholesalex-dokan.php' ),
			'download_link'       => 'https://downloads.wordpress.org/plugin/multi-vendor-marketplace-b2b-for-wholesalex-dokan.zip',
			'video'               => 'https://www.youtube.com/embed/4UatlL2-XXo',
			'depends_message'     => __('This addon require Dokan plugin','wholesalex')
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
			'status'              => function_exists( 'run_wholesalex_wcfm' ),
			'lock_status'         => false,
			'setting_id'          => '#wcfm_wholesalex',
			'is_installed'        => file_exists( WP_PLUGIN_DIR . '/wholesalex-wcfm-b2b-multivendor-marketplace/wholesalex-wcfm-b2b-multivendor-marketplace.php' ),
			'download_link'       => 'https://downloads.wordpress.org/plugin/wholesalex-wcfm-b2b-multivendor-marketplace.zip',
			'video'               => 'https://www.youtube.com/embed/2OLOqyvv5rE',
			'depends_message'	  => __('This addon require WCFM – Frontend Manager plugin','wholesalex')
		);
		return $config;
	}

	/**
	 * Get Addons
	 *
	 * @return aray
	 */
	public function get_addons() {
		$addons_data = $this->addons_config();

		return $addons_data;
	}

	/**
	 * Initial Setup Content.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function initial_setup_content() {
		add_filter( 'show_admin_bar', '__return_false' );

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );

		Scripts::register_backend_scripts();
		Scripts::register_backend_style();

		wp_enqueue_script( 'wholesalex_wizard' );
		wp_enqueue_style( 'wholesalex' );

		$localize_content = array(
			'url'                  => WHOLESALEX_URL,
			'nonce'                => wp_create_nonce( 'wholesalex-setup-wizard' ),
			'ajax'                 => admin_url( 'admin-ajax.php' ),
			'plugin_install_nonce' => wp_create_nonce( 'updates' ),
			'is_pro_active'        => wholesalex()->is_pro_active(),
			'addons'               => $this->get_addons(),
			'setting_url'          => menu_page_url( 'wholesalex-settings', false ),
			'dashboard_url'        => menu_page_url( 'wholesalex-overview', false ),
		);
		if ( ! $localize_content['setting_url'] ) {
			$localize_content['setting_url'] = get_dashboard_url();
		}
		if ( ! $localize_content['dashboard_url'] ) {
			$localize_content['dashboard_url'] = get_dashboard_url();
		}
		wp_localize_script(
			'wholesalex_wizard',
			'wholesalex_wizard',
			$localize_content
		);
		wp_localize_script(
			'wholesalex_wizard',
			'whx_addons',
			array(
				'addons'      => $this->get_addons(),
				'setting_url' => menu_page_url( 'wholesalex-settings', false ),
			)
		);

		wp_localize_script(
			'wholesalex_wizard',
			'wholesalex',
			array(
				'url'   => WHOLESALEX_URL,
				'nonce' => wp_create_nonce( 'wholesalex-registration' ),
			)
		);
		?>
		<div id="wholesalex_initial_setup_wizard"> </div>
		<?php
	}

	/**
	 * Get Plugin Status
	 *
	 * @since 1.1.0
	 */
	public function get_plugin_status() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ):'';
		if (!wp_verify_nonce($nonce,'wholesalex-setup-wizard') ) {
			die();
		}
		
        if(!current_user_can( 'manage_options' )) {
            return;
        }

		$plugin_status = wholesalex()->get_setting( '_settings_status', 'b2b' );
		wp_send_json_success( $plugin_status );
	}

	/**
	 * Get General Data
	 *
	 * @since 1.1.0
	 */
	public function get_general_data() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ):'';
		if (!wp_verify_nonce($nonce,'wholesalex-setup-wizard') ) {
			die();
		}
        if(!current_user_can( 'manage_options' )) {
            return;
        }

		$site_name           = get_bloginfo( 'name' );
		$tier_layout         = wholesalex()->get_setting( '_settings_tier_layout', 'layout_one' );
		$woocommer_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
		$productx_installed  = file_exists( WP_PLUGIN_DIR . '/product-blocks/product-blocks.php' );
		$data                = array(
			'website_name'        => $site_name,
			'tier_table_layout'   => $tier_layout,
			'install_woocommerce' => 'yes',
			'install_productx'    => 'yes',
		);
		if ( $woocommer_installed ) {
			$is_wc_activated = is_plugin_active( 'woocommerce/woocommerce.php' );
		}
		if ( $woocommer_installed ) {
			$data['wc_installed'] = true;
			if ( $is_wc_activated ) {
				$data['wc_activated'] = true;
			}
		}
		if ( $productx_installed ) {
			$is_productx_activated = is_plugin_active( 'product-blocks/product-blocks.php' );
		}

		if ( $productx_installed ) {
			$data['productx_installed'] = true;
			if ( $is_productx_activated ) {
				$data['productx_activated'] = true;
			}
		}
		wp_send_json_success(
			$data
		);
	}


	/**
	 * Plugin Install
	 *
	 * @param string $plugin Plugin Slug.
	 * @return boolean
	 */
	public function plugin_install( $plugin ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'sections' => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api->get_error_message();
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		return $result;
	}

	/**
	 * Set Plugin Status
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function set_plugin_status() {
		$nonce = isset($_POST['nonce'])?sanitize_key($_POST['nonce']):'';
		if (!wp_verify_nonce( $nonce, 'wholesalex-setup-wizard' ) ) {
			die();
		}
        if(!current_user_can( 'manage_options' )) {
            return;
        }

		$plugin_status = isset( $_POST['plugin_status'] ) ? sanitize_text_field( $_POST['plugin_status'] ) : 'b2b';

		wholesalex()->set_setting( '_settings_status', $plugin_status );
		wp_send_json_success( 'Updated' );
	}

	/**
	 * WholesaleX Plugin Activate
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function wholesalex_activate_plugin() {
		$nonce = isset( $_POST['nonce'] )?sanitize_key($_POST['nonce']):'';
		if ( !wp_verify_nonce($nonce, 'wholesalex-setup-wizard' ) ) {
			die();
		}

		if ( isset( $_POST['slug'] ) ) {
			wp_clean_plugins_cache();
			$slug        = sanitize_text_field( $_POST['slug'] );
			$plugin_name = '';
			if ( 'woocommerce' === $slug ) {
				$plugin_name = 'woocommerce/woocommerce.php';
			} elseif ( 'product-blocks' === $slug ) {
				$plugin_name = 'product-blocks/product-blocks.php';
			}
			$activate_status = activate_plugin( $plugin_name, '', false, true );
			if ( is_wp_error( $activate_status ) ) {

				$data = array(
					'success' => false,
					'message' => $activate_status->get_error_message(),
				);
			} else {
				$data = array(
					'success' => true,
					'message' => __( 'Plugin Activate Successfully', 'wholesalex' ),
				);
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * Save General Data
	 *
	 * @since 1.1.0
	 */
	public function save_general_data() {
		$nonce = isset( $_POST['nonce'] )?sanitize_key($_POST['nonce']):'';
		if ( ! wp_verify_nonce( $nonce, 'wholesalex-setup-wizard' ) ) {
			die();
		}

        if(!current_user_can( 'manage_options' )) {
            return;
        }

		if ( isset( $_FILES['site_icon'] ) ) {

			$file_extension     = strtolower( pathinfo( $_FILES['site_icon']['name'], PATHINFO_EXTENSION ) ); // phpcs:ignore
			$allowed_extenstion = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'ico' );
			if ( in_array( $file_extension, $allowed_extenstion ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$file_id = media_handle_upload( 'site_icon', 0 );
				if ( $file_id ) {
					update_option( 'site_icon', $file_id );
				}
			}
		}

		if ( isset( $_POST['website_name'] ) ) {
			$site_name = sanitize_text_field( $_POST['website_name'] );
			update_option( 'blogname', $site_name );
		}
		if ( isset( $_POST['website_type'] ) ) {
			$site_type = sanitize_text_field( $_POST['website_type'] );
			update_option( '__site_type', $site_type );
		}

		if ( isset( $_POST['tier_table_layout'] ) ) {
			$tier_layout = sanitize_text_field( $_POST['tier_table_layout'] );
			wholesalex()->set_setting( '_settings_tier_layout', $tier_layout );
		}

		$woocommer_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
		$productx_installed  = file_exists( WP_PLUGIN_DIR . '/product-blocks/product-blocks.php' );

		if ( isset( $_POST['install_woocommerce'] ) && 'yes' === $_POST['install_woocommerce'] ) {
			// Check Exist or not.
			if ( ! $woocommer_installed ) {
				$status = $this->plugin_install( 'woocommerce' );
				if ( $status && ! is_wp_error( $status ) ) {
					$activate_status = activate_plugin( 'woocommerce/woocommerce.php', '', false, true );
					if ( is_wp_error( $activate_status ) ) {
						wp_send_json_error( array( 'message' => __( 'WooCommerce Activation Failed!', 'wholesalex' ) ) );
					}
				} else {
					wp_send_json_error( array( 'message' => __( 'WooCommerce Installation Failed!', 'wholesalex' ) ) );
				}
			} else {
				$is_wc_active = is_plugin_active( 'woocommerce/woocommerce.php' );
				if ( ! $is_wc_active ) {
					$activate_status = activate_plugin( 'woocommerce/woocommerce.php', '', false, true );
					if ( is_wp_error( $activate_status ) ) {
						wp_send_json_error( array( 'message' => __( 'WooCommerce Activation Failed!', 'wholesalex' ) ) );
					}
				}
			}
		}
		if ( isset( $_POST['install_productx'] ) && 'yes' === $_POST['install_productx'] ) {
			if ( ! $productx_installed ) {
				$status = $this->plugin_install( 'product-blocks' );
				if ( $status && ! is_wp_error( $status ) ) {
					$activate_status = activate_plugin( 'product-blocks/product-blocks.php', '', false, true );
					if ( is_wp_error( $activate_status ) ) {
						wp_send_json_error( array( 'message' => __( 'ProductX Activation Failed!', 'wholesalex' ) ) );
					}
				} else {
					wp_send_json_error( array( 'message' => __( 'ProductX Installation Failed!', 'wholesalex' ) ) );
				}
			} else {
				$is_wc_active = is_plugin_active( 'product-blocks/product-blocks.php' );
				if ( ! $is_wc_active ) {
					$activate_status = activate_plugin( 'product-blocks/product-blocks.php', '', false, true );
					if ( is_wp_error( $activate_status ) ) {
						wp_send_json_error( array( 'message' => __( 'ProductX Activation Failed!', 'wholesalex' ) ) );
					}
				}
			}
		}

		wp_send_json_success( 'Success' );
	}

	/**
	 * Wholesalex Initial Setup Updated
	 *
	 * @return void
	 */
	public function wsx_wizard_update() {
		update_option( '__wholesalex_initial_setup', true );
	}

	/**
	 * Data Collection Ajax Hanlder
	 *
	 * @return void
	 */
	public function data_collection() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ):'';

		if ( !wp_verify_nonce($nonce, 'wholesalex-setup-wizard' ) ) {
			die();
		}

        if(!current_user_can( 'manage_options' )) {
            return;
        }

		update_option( '__wholesalex_initial_setup', true );

		$data_share_consent  = isset( $_POST['share_data_consent'] ) ? sanitize_text_field( $_POST['share_data_consent'] ) : '';
		$get_updates_consent = isset( $_POST['get_updates_consent'] ) ? sanitize_text_field( $_POST['get_updates_consent'] ) : '';
		if ( $data_share_consent ) {
			set_transient( '__wholesalex_data_collection_consent', true );
		}
		if ( $get_updates_consent ) {
			set_transient( '__wholesalex_get_updates_consent', true );
		}
		if ( $get_updates_consent || $data_share_consent ) {
			$response = self::send_plugin_data( 'installation_wizard' );
		}

		$woocommer_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
		if ( $woocommer_installed ) {
			$is_wc_activated = is_plugin_active( 'woocommerce/woocommerce.php' );
		}
		$redirect_url = ( $woocommer_installed && $is_wc_activated ) ? admin_url( 'admin.php?page=wholesalex-overview' ) : admin_url( 'plugins.php' );
		wp_send_json_success( array( 'redirect' => $redirect_url ) );
	}


	/**
	 * Send Plugin Data To WPXPO Server
	 *
	 * @param string $type Type.
	 */
	public static function send_plugin_data( $type ) {
		$data = self::get_data();

		$data['type'] = $type ? $type : 'deactive';
		$form_data    = isset( $_POST ) ? $_POST : array(); // phpcs:ignore

		if ( current_user_can( 'administrator' ) ) {
			if ( isset( $form_data['action'] ) ) {
				unset( $form_data['action'] );
			}

			$response = wp_remote_post(
				self::$API_ENDPOINT,
				array(
					'method'      => 'POST',
					'timeout'     => 30,
					'redirection' => 5,
					'headers'     => array(
						'user-agent' => 'wpxpo/' . md5( esc_url( home_url() ) ) . ';',
						'Accept'     => 'application/json',
					),
					'blocking'    => true,
					'httpversion' => '1.0',
					'body'        => array_merge( $data, $form_data ),
				)
			);

			return $response;
		}
	}

	/**
	 * Get All Plugins Data
	 *
	 * @since 1.1.0
	 * @return array Plugin Information
	 */
	public static function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$active         = array();
		$inactive       = array();
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $all_plugins as $key => $plugin ) {
			$arr = array();

			$arr['name']    = isset( $plugin['Name'] ) ? $plugin['Name'] : '';
			$arr['url']     = isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
			$arr['author']  = isset( $plugin['Author'] ) ? $plugin['Author'] : '';
			$arr['version'] = isset( $plugin['Version'] ) ? $plugin['Version'] : '';

			if ( in_array( $key, $active_plugins ) ) {
				$active[ $key ] = $arr;
			} else {
				$inactive[ $key ] = $arr;
			}
		}

		return array(
			'active'   => $active,
			'inactive' => $inactive,
		);
	}

	/**
	 * Check Localhost or Live Server
	 *
	 * @return boolean
	 */
	public static function is_local() {
		return in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ) ); //phpcs:ignore
	}


	/**
	 * Get All Themes Data
	 *
	 * @since 1.1.0
	 * @return array Theme Data
	 */
	public static function get_themes() {
		$theme_data = array();
		$all_themes = wp_get_themes();

		if ( is_array( $all_themes ) ) {
			foreach ( $all_themes as $key => $theme ) {
				$attr               = array();
				$attr['name']       = $theme->Name;
				$attr['url']        = $theme->ThemeURI;
				$attr['author']     = $theme->Author;
				$attr['version']    = $theme->Version;
				$theme_data[ $key ] = $attr;
			}
		}

		return $theme_data;
	}

	/**
	 * Get Current Users IP Address
	 *
	 * @since 1.1.0
	 * @return string IP Address
	 */
	public static function get_user_ip() {
		$response = wp_remote_get( 'https://icanhazip.com/' );

		if ( is_wp_error( $response ) ) {
			return '';
		} else {
			$user_ip = trim( wp_remote_retrieve_body( $response ) );
			return filter_var( $user_ip, FILTER_VALIDATE_IP ) ? $user_ip : '';
		}
	}

	/**
	 * All the Valid Information of The Users
	 *
	 * @since 1.1.0
	 * @return array Data
	 */
	public static function get_data() {
		global $wpdb;
		$user         = wp_get_current_user();
		$user_count   = count_users();
		$plugins_data = self::get_plugins();

		$data = array(
			'name'             => get_bloginfo( 'name' ),
			'home'             => esc_url( home_url() ),
			'admin_email'      => $user->user_email,
			'first_name'       => isset( $user->user_firstname ) ? $user->user_firstname : '',
			'last_name'        => isset( $user->user_lastname ) ? $user->user_lastname : '',
			'display_name'     => $user->display_name,
			'wordpress'        => get_bloginfo( 'version' ),
			'memory_limit'     => WP_MEMORY_LIMIT,
			'debug_mode'       => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Yes' : 'No',
			'locale'           => get_locale(),
			'multisite'        => is_multisite() ? 'Yes' : 'No',

			'themes'           => self::get_themes(),
			'active_theme'     => get_stylesheet(),
			'users'            => isset( $user_count['total_users'] ) ? $user_count['total_users'] : 0,
			'active_plugins'   => $plugins_data['active'],
			'inactive_plugins' => $plugins_data['inactive'],
			'server'           => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '', //phpcs:ignore

			'timezone'         => date_default_timezone_get(),
			'php_curl'         => function_exists( 'curl_init' ) ? 'Yes' : 'No',
			'php_version'      => function_exists( 'phpversion' ) ? phpversion() : '',
			'upload_size'      => size_format( wp_max_upload_size() ),
			'mysql_version'    => $wpdb->db_version(),
			'php_fsockopen'    => function_exists( 'fsockopen' ) ? 'Yes' : 'No',

			'ip'               => self::get_user_ip(),
			'plugin_name'      => self::$PLUGIN_NAME,
			'plugin_version'   => self::$PLUGIN_VERSION,
			'plugin_slug'      => self::$PLUGIN_SLUG,
			'is_local'         => self::is_local(),
		);

		if ( get_option( '__site_type' ) ) {
			$data['site_type'] = get_option( '__site_type' );
		}

		return $data;
	}

	/**
	 * Remove All Admin Notice on Wizard Page
	 */
	public function remove_notices() {
		 	if (isset($_GET['page'])) { //phpcs:ignore
			$page = sanitize_key($_GET['page']);  //phpcs:ignore
			if ( 'wholesalex-setup-wizard' === $page ) {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			}
		}
	}
}
