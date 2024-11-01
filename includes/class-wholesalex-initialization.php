<?php
/**
 * WholesaleX Initialization. Initialize All Files And Dependencies
 *
 * @link              https://www.wpxpo.com/
 * @since             1.0.0
 * @package           WholesaleX
 */

use WHOLESALEX\Scripts;

defined( 'ABSPATH' ) || exit;


/**
 * WholesaleX_Initialization Class
 */
class WholesaleX_Initialization {
	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->include_addons(); // Include Addons
		// Admin Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Frontend Assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );

		add_action( 'admin_init', array( $this, 'remove_admin_notices' ), 11 );

		add_filter( 'admin_body_class', array( $this, 'add_wholesalex_class_on_backend' ) );
	}

	/**
	 * Remove All Notices from wholesalex pages.
	 */
	public function remove_admin_notices() {
		$post_type = isset( $_GET['post'] ) ? get_post_type( sanitize_text_field( $_GET['post'] ) ) : ''; // @codingStandardsIgnoreLine.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : $post_type; // @codingStandardsIgnoreLine.
		if ( isset( $_GET['page'] ) && wholesalex()->is_wholesalex_page( sanitize_key( $_GET['page'] ) ) || wholesalex()->is_wholesalex_page( $post_type ) ) { //phpcs:ignore
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	/**
	 * Include Addons Directory
	 *
	 * @since v.1.0.0
	 */
	public function include_addons() {
		$addons_dir = array_filter( glob( WHOLESALEX_PATH . 'addons/*' ), 'is_dir' );
		if ( count( $addons_dir ) > 0 ) {
			foreach ( $addons_dir as $key => $value ) {
				$addon_dir_name = str_replace( dirname( $value ) . '/', '', $value );
				$file_name      = WHOLESALEX_PATH . 'addons/' . $addon_dir_name . '/init.php';
				if ( file_exists( $file_name ) ) {
					include_once $file_name;
				}
			}
		}
	}

	/**
	 * Load All Required Dependencies
	 */
	private function load_dependencies() {
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-overview.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-role.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-menu.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-profile.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-request-api.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-product.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-category.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-shortcodes.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-email.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-email-manager.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-dynamic-rules.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-settings.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-scripts.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-registration.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-orders.php';
		require_once WHOLESALEX_PATH . 'includes/class-wholesalex-import-export.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-features.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-users.php';
		require_once WHOLESALEX_PATH . 'includes/menu/class-wholesalex-support.php';
		require_once WHOLESALEX_PATH . 'includes/options/Addons.php';
		require_once WHOLESALEX_PATH . 'includes/Deactive.php';

		
		new \WHOLESALEX\WHOLESALEX_Role();
		new \WHOLESALEX\WHOLESALEX_Registration();
		new \WHOLESALEX\WHOLESALEX_Dynamic_Rules();

		new \WHOLESALEX\Addons();
		new \WHOLESALEX\WHOLESALEX_Users();
		new \WHOLESALEX\WHOLESALEX_Email();
		new \WHOLESALEX\Settings();
		new \WHOLESALEX\WHOLESALEX_Category();
		new \WHOLESALEX\WHOLESALEX_Menu();
		new \WHOLESALEX\WHOLESALEX_Profile();
		new \WHOLESALEX\WHOLESALEX_Product();
		new \WHOLESALEX\WHOLESALEX_Request_API();
		new \WHOLESALEX\WHOLESALEX_Shortcodes();
		new \WHOLESALEX\WHOLESALEX_Email_Manager();
		new \WHOLESALEX\WHOLESALEX_Orders();
		new \WHOLESALEX\ImportExport();
		new \WHOLESALEX\WholesaleX_Features();
		new \WHOLESALEX\WholesaleX_Support();
		new \WHOLESALEX\Deactive();
		new \WHOLESALEX\WHOLESALEX_Overview();

		add_action( 'template_redirect', array( $this, 'wholesalex_process_user_email_confirmation' ) );

	}


	/**
	 * Admin Enque Scripts
	 */
	public function admin_enqueue_scripts( ) {
		$price_id = get_option('__wholesalex_price_id','');
		Scripts::register_backend_scripts();
		Scripts::register_backend_style();

		wp_enqueue_style( 'wholesalex' );
		wp_enqueue_script( 'wholesalex' );

		wp_localize_script(
			'wholesalex',
			'wholesalex',
			apply_filters(
				'wholesalex_backend_localize_data',
				array(
					'url'                 => WHOLESALEX_URL,
					'nonce'               => wp_create_nonce( 'wholesalex-registration' ),
					'ajax'                => admin_url( 'admin-ajax.php' ),
					'wholesalex_roles'    => get_option( '_wholesalex_roles' ),
					'currency_symbol'     => get_woocommerce_currency_symbol(),
					'current_version'     => WHOLESALEX_VER,
					'wallet_status'       => wholesalex()->get_setting( 'wsx_addon_wallet' ),
					'conversation_status' => wholesalex()->get_setting( 'wsx_addon_conversation' ),
					'recaptcha_status'    => wholesalex()->get_setting( 'wsx_addon_recaptcha' ),
					'pro_link'            => wholesalex()->get_premium_link(),
					'is_pro_active'       => wholesalex()->is_pro_active(),
					'ver'                 => WHOLESALEX_VER,
					'pro_ver'             => wholesalex()->is_pro_active() ? WHOLESALEX_PRO_VER : '',
					'settings'            => wholesalex()->get_setting(),
					'license_status'      => wholesalex()->is_pro_enabled() ? wholesalex()->get_license_status() : '',
					'logo_url'            => apply_filters( 'wholesalex_logo_url', WHOLESALEX_URL . 'assets/img/logo-option.svg' ),
					'plugin_name'         => wholesalex()->get_plugin_name(),
					'whitelabel_enabled'  => 'yes' == wholesalex()->get_setting( 'wsx_addon_whitelabel' ) && function_exists( 'wholesalex_whitelabel_init' ),
					'is_admin_interface'  => is_admin(),
					'i18n'				  => array(
						'smart_tags' => __('Available Smart Tags: ','wholesalex'),
					)
				)
			)
		);
	}

	/**
	 * Frontend Enque Scripts
	 */
	public function frontend_enqueue_scripts() {
		Scripts::register_frontend_scripts();
		Scripts::register_fronend_style();
		wp_enqueue_style( 'wholesalex' );
		
		if ( wp_style_is( 'wholesalex', 'enqueued' ) ) {
			$css = '.wholesalex-price-table table tbody td { background-color: inherit !important; }';
			wp_add_inline_style( 'wholesalex', $css );
		}

		wp_enqueue_script( 'wholesalex' );

		do_action( 'wholesalex_after_frontend_enqueue_scripts' );



		wp_localize_script(
			'wholesalex',
			'wholesalex',
			apply_filters(
				'wholesalex_frontend_localize_data',
				array(
					'url'                => WHOLESALEX_URL,
					'nonce'              => wp_create_nonce( 'wholesalex-registration' ),
					'ajax'               => admin_url( 'admin-ajax.php' ),
					'wallet_status'      => wholesalex()->get_setting( 'wsx_addon_wallet' ),
					'recaptcha_status'   => wholesalex()->get_setting( 'wsx_addon_recaptcha' ),
					'ver'                => WHOLESALEX_VER,
					'is_pro_active'      => wholesalex()->is_pro_active(),
					'pro_ver'            => wholesalex()->is_pro_active() ? WHOLESALEX_PRO_VER : '',
					'settings'           => wholesalex()->get_setting(),
					'logo_url'           => apply_filters( 'wholesalex_logo_url', WHOLESALEX_URL . 'assets/img/logo-option.svg' ),
					'plugin_name'        => wholesalex()->get_plugin_name(),
					'is_admin_interface' => is_admin(),
					'i18n' => array(
						'cannot_register_message_for_logged_in_user' => __('You cannot register while you are logged in.','wholesalex'),
						'is_required' => __('is required','wholesalex'),
						'register' => __('Register','wholesalex'),
						
					)

				)
			)
		);
	}

	/**
	 * Process WholesaleX User Email Confirmation
	 */
	public function wholesalex_process_user_email_confirmation() {

		if ( isset( $_REQUEST['confirmation_code'],$_REQUEST['user_id'] ) ) { // @codingStandardsIgnoreLine.
			$__user_id           = sanitize_text_field( $_REQUEST['user_id']  ); // @codingStandardsIgnoreLine.
			$__confirmation_code = get_user_meta( $__user_id, '__wholesalex_email_confirmation_code', true );
			$confirmation_status = get_user_meta( $__user_id, '__wholesalex_account_confirmed', true );
            $requested_confirmation_code = sanitize_text_field($_REQUEST['confirmation_code']); // @codingStandardsIgnoreLine.

			if ( $confirmation_status ) {
				wc_add_notice( __( ' Your account is already confirmed!. ', 'wholesalex' ), 'notice' );
			} elseif ( $requested_confirmation_code === $__confirmation_code ) {
				update_user_meta( $__user_id, '__wholesalex_account_confirmed', true );
				update_user_meta( $__user_id, '__wholesalex_status', 'active' );

                $__registration_role = get_user_meta( $__user_id, '__wholesalex_registration_role',true);

				wholesalex()->change_role( $__user_id, $__registration_role );
				wc_add_notice( __( '<strong>Success:</strong> Your account is successfully confirmed. ', 'wholesalex' ) );
				// do_action( 'wholesalex_set_status_active', $__user_id, $__registration_role );
				do_action( 'wholesalex_user_email_verified', $__user_id );
			}
		}
	}

	/**
	 * Add wholesalex class on backend
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public function add_wholesalex_class_on_backend( $classes ) {

		$post_type = isset( $_GET['post'] ) ? get_post_type( sanitize_text_field( $_GET['post'] ) ) : ''; // @codingStandardsIgnoreLine.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : $post_type; // @codingStandardsIgnoreLine.
		if ( isset( $_GET['page'] ) && wholesalex()->is_wholesalex_page( sanitize_key( $_GET['page'] ) ) || wholesalex()->is_wholesalex_page( $post_type ) ) { // @codingStandardsIgnoreLine.
			$classes .= ' wholesalex_backend_body';
		}
		return $classes;
	}

}
