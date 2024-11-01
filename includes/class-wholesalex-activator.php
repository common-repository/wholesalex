<?php
/**
 * WholesaleX Activator
 *
 * @link              https://www.wpxpo.com/
 * @since             1.0.0
 * @package           WholesaleX
 */

namespace WHOLESALEX;

/**
 * WholesaleX Activator Class
 */
class Activator {

	/**
	 * Activator Constructor
	 */
	public function __construct() {
		if ( ! get_option( 'wholesalex_settings' ) ) {
			$this->init_set_data();
		}
		if ( ! get_option( '_wholesalex_roles' ) ) {
			$this->init_roles();
		}

		add_action( 'activated_plugin', array( $this, 'activation_redirect' ) );
	}

	/**
	 * Initialize Settings data
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.0.1  _settings_show_form_for_logged_in and _settings_message_for_logged_in_user Default Value Added.
	 * @since 1.0.2 Plugin Default Status set B2B and B2C.
	 */
	public function init_set_data() {
		$data      = get_option( 'wholesalex_settings', array() );
		$init_data = array(
			// Recaptcha
			'_settings_google_recaptcha_v3_allowed_score' => '0.5',
			// Addons
			'wsx_addon_conversation'                      => 'no',
			'wsx_addon_wallet'                            => 'no',
			'wsx_addon_recaptcha'                         => 'no',
			// General
			'_settings_status'                            => 'b2b_n_b2c',
			'_settings_show_table'                        => 'yes',
			'_settings_quantity_based_discount_priority'  => array( 'profile', 'single_product', 'category', 'dynamic_rule' ),
			'_settings_display_price_shop_page'           => 'woocommerce_default_tax',
			'_settings_display_price_cart_checkout'       => 'woocommerce_default_tax',
			// Registration & Login
			'_settings_user_login_option'                 => 'manual_login',
			'_settings_user_status_option'                => 'admin_approve',
			'_settings_redirect_url_registration'         => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
			'_settings_redirect_url_login'                => get_permalink( get_option( 'woocommerce_shop_page_id' ) ),
			'_settings_registration_success_message'      => __( 'Thank you for registering. Your account will be reviewed by us & approve manually. Please wait to be approved.', 'wholesalex' ),
			'_settings_seperate_page_b2b'                 => get_option( 'woocommerce_myaccount_page_id' ),
			'_settings_show_form_for_logged_in'           => 'no',
			'_settings_message_for_logged_in_user'        => __( 'Sorry You Are Not Allowed To View This Form', 'wholesalex' ),
			// Price
			'_settings_price_text'                        => __( 'Wholesale Price:', 'wholesalex' ),
			'_settings_price_text_product_list_page'      => __( 'Wholesale Price:', 'wholesalex' ),
			'_settings_price_product_list_page'           => 'pricing_range',
			// Design
			'_settings_tier_layout'                       => 'layout_one',
			'_settings_primary_color'                     => '#2FC4A7',
			'settings_primary_hover_color'                => '#24A88F',
			'_settings_text_color'                        => '#272727',
			'_settings_border_color'                      => '#E5E5E5',
			'_settings_active_tier_color'                 => '#1986f4',
			// Conversation
			'_settings_show_conversation_my_account_page' => 'yes',
		);
		if ( empty( $data ) ) {
			update_option( 'wholesalex_settings', $init_data );
			$GLOBALS['wholesalex_settings'] = $init_data;
		} else {
			foreach ( $init_data as $key => $single ) {
				if ( ! isset( $data[ $key ] ) ) {
					$data[ $key ] = $single;
				}
			}
			update_option( 'wholesalex_settings', $data );
			$GLOBALS['wholesalex_settings'] = $data;
		}
		$installation_date = get_option( 'wholesalex_installation_date' );
		if ( ! $installation_date ) {
			update_option( 'wholesalex_installation_date', gmdate( 'U' ) );
		}
	}
	/**
	 * Initial Role.
	 * After plugin activation default role is create.
	 *
	 * @return void
	 */
	public function init_roles() {
		$__inital_roles =
			apply_filters(
				'wholesalex_initial_roles',
				array(
					'wholesalex_b2c_users' => array(
						'id'          => 'wholesalex_b2c_users',
						'_role_title' => __( 'B2C Users', 'wholesalex' ),
						'removeable'  => false,
					),
					'wholesalex_guest'     => array(
						'id'          => 'wholesalex_guest',
						'_role_title' => __( 'Guest Users', 'wholesalex' ),
						'removeable'  => false,
					),
				)
			);
		foreach ( $__inital_roles as $role ) {
			if ( empty( wholesalex()->get_roles( 'by_id', $role['id'] ) ) ) {
				wholesalex()->set_roles( $role['id'], $role );
			}
		}
	}

	/**
	 * Activation Redirect
	 *
	 * @param string $plugin Plugin Slug.
	 * @since 1.0.1
	 * @since 1.1.0 Initial Setup Wizard Added
	 */
	public function activation_redirect( $plugin ) {
		if ( wp_doing_ajax() ) {
            return;
        }
		if ( 'wholesalex/wholesalex.php' === $plugin ) {
			if ( ! class_exists( 'woocommerce' ) ) {
				return;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wholesalex-overview' ) );
			exit();
		}
	}
}
