<?php
/**
 * Settings Action.
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * Settings Class.
 */
class Settings {
	/**
	 * Setup class.
	 *
	 * @since v.1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'settings_submenu_page_callback' ) );
		add_action( 'rest_api_init', array( $this, 'save_settings_callback' ) );
		add_filter( 'option_woocommerce_tax_display_shop', array( $this, 'display_price_shop_including_tax' ) );
		add_filter( 'option_woocommerce_tax_display_cart', array( $this, 'display_price_cart_including_tax' ) );
		add_filter( 'woocommerce_get_price_suffix', array( $this, 'price_suffix_handler' ), 10, 2 );
		add_filter( 'wholesalex_recaptcha_minimum_score_allow', array( $this, 'recaptcha_minimum_score_allow' ) );
		add_filter( 'option_woocommerce_myaccount_page_id', array( $this, 'separate_my_account_page_for_b2b' ), 10, 1 );
		add_filter( 'woocommerce_coupons_enabled', array( $this, 'hide_coupon_fields' ) );
		/**
		 * Force Redirect Logged Out User to Specific Page
		 *
		 * @since 1.0.8
		 */
		add_action( 'plugins_loaded', array( $this, 'force_redirect_guest_users' ) );

		/**
		 * Allow Hidden Product to checkout
		 *
		 * @since 1.1.0
		 */
		add_filter( 'wholesalex_allow_hidden_product_to_checkout', array( $this, 'allow_hidden_product_to_checkout' ) );
		add_filter( 'wholesalex_allow_hidden_filter_to_checkout', array( $this, 'allow_hidden_product_to_checkout' ) );

		if ( wholesalex()->is_pro_active() ) {

			add_filter( 'wholesalex_settings_product_tier_layout', array( $this, 'product_tier_layout' ), 30 );
			add_filter( 'wholesalex_single_product_tier_layout', array( $this, 'product_tier_layout' ), 30 );
			add_filter( 'wholesalex_dynamic_rules_condition_options', array( $this, 'unlock_option' ), 20 );

			add_filter( 'wholesalex_dynamic_rules_rule_type_options', array( $this, 'unlock_option' ), 20 );

			add_filter( 'wholesalex_dynamic_rules_rule_for_options', array( $this, 'unlock_option' ), 20 );

			add_filter( 'wholesalex_dynamic_rules_product_filter_options', array( $this, 'unlock_option' ), 20 );
		}

	}


	/**
	 * Save Wholesalex Settings Actions
	 *
	 * @since 1.0.0
	 */
	public function save_settings_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/settings_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'settings_action_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}


	/**
	 * Save Wholesalex Settings
	 *
	 * @param object $server Server.
	 * @since 1.0.0
	 */
	public function settings_action_callback( $server ) {
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key($post['nonce']), 'wholesalex-registration' ) ) ) {
			return;
		}

		$type = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';

		if ( 'set' === $type ) {
			$post = wholesalex()->sanitize( $post );

			if ( 'email_confirmation_require' === $post['settings']['_settings_user_status_option'] ) {
				$__confirmation_email_status = get_option( 'wholesalex_email_verification_email_status' );
				if ( 'no' == $__confirmation_email_status ) { //phpcs:ignore
					/* translators: %1s - Plugin Name, %2s - Plugin Name,  */
					wp_send_json_error( sprintf( __( 'To Active "Email Confirmation" Please Enable %1$s Email Template from "Dashboard > %2$s > Emails".', 'wholesalex' ), wholesalex()->get_plugin_name(), wholesalex()->get_plugin_name() ) );
				}
			}
			wholesalex()->set_setting_multiple( $post['settings'] );
			wp_send_json_success( __( 'Successfully Saved.', 'wholesalex' ) );
		} elseif ( 'get' === $type ) {
			$data            = array();
			$data['default'] = $this->get_option_settings();
			$data['value']   = wholesalex()->get_setting();
			wp_send_json_success( $data );
		}
	}


	/**
	 * Settings Menu callback
	 *
	 * @return void
	 */
	public function settings_submenu_page_callback() {
		$slug = apply_filters( 'wholesalex_settings_submenu_slug', 'wholesalex-settings' );
		$title = sprintf('<span class="wholesalex-submenu-title__settings">%s</span>',__('Settings','wholesalex'));
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__( 'Settings', 'wholesalex' ),
			$title,
			apply_filters( 'wholesalex_capability_access', 'manage_options' ),
			$slug,
			array( $this, 'settings_html_callback' )
		);
	}


	/**
	 * Settings Sub Menu Page Callback
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function settings_html_callback() {
		/**
		 * Enqueue Script
		 *
		 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
		 */
		wp_enqueue_script( 'wholesalex_settings' );
		wp_localize_script(
			'wholesalex_settings',
			'whx_settings',
			array(
				'fields' => self::get_option_settings(),
				'data'   => wholesalex()->get_setting(),
				'i18n' => array(
					'settings' => __('Settings','wholesalex'),
					'unlock' => __("UNLOCK",'wholesalex'),
					'unlock_heading' => __("Unlock All Features",'wholesalex'),
					'unlock_desc' => __("We are sorry, but unfortunately, this feature is unavailable in the free version. Please upgrade to a pro plan to unlock all features.",'wholesalex'),
					'upgrade_to_pro' => __("Upgrade to Pro  ➤",'wholesalex'),
				)
			)
		);

		?>
		<div id="_wholesalex_settings"></div> 
			<?php
	}


	/**
	 * Settings Field Return
	 *
	 * @since 1.0.0
	 * @since 1.0.4 DragList Option Error Fixed.
	 * @since 1.2.13 Login to see price login Url Field Added
	 */
	public static function get_option_settings() {

		$__pages        = get_pages();
		$__pages_option = array();
		foreach ( $__pages as $page ) {
			$__pages_option[ esc_attr( $page->ID ) ] = esc_html( $page->post_title );
		}
		$my_account_id = get_option( 'woocommerce_myaccount_page_id' );

		$weight_unit = get_option('woocommerce_weight_unit');
		return apply_filters(
			'wholesalex_setting_fields',
			array(
				'general'              => array(
					'label' => __( 'General Settings', 'wholesalex' ),
					'attr'  => array(
						'_settings_status'                 => array(
							'type'    => 'radio',
							'label'   => __( 'Plugin Status', 'wholesalex' ),
							'options' => array(
								'b2b'       => __( 'B2B (Wholesale Only)', 'wholesalex' ),
								'b2c'       => __( 'B2C (Public Only)', 'wholesalex' ),
								'b2b_n_b2c' => __( 'B2B & B2C Hybrid (Wholesale and Public)', 'wholesalex' ),
							),
							'default' => 'b2b',
							/* translators: %s - Plugin Name */
							'tooltip' => sprintf( __( 'Choose which type of store you want to create & manage with %s.', 'wholesalex' ), wholesalex()->get_plugin_name() ),
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'help_popup' => true,
							// 'popup_gif_link' => 'https://plugins.svn.wordpress.org/wholesalex/assets/Screenshot-1.jpg',
						),
						'_settings_show_table'             => array(
							'type'    => 'switch',
							'label'   => __( 'Show Tiered Pricing Table', 'wholesalex' ),
							'desc'    => __( 'Product Single Page', 'wholesalex' ),
							'default' => 'yes',
							'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'help_popup' => true,
							// 'popup_gif_link' => 'https://plugins.svn.wordpress.org/wholesalex/assets/Screenshot-1.jpg',
						),
						// '_settings_pricing_table'        => array(
						// 'type'    => 'switch',
						// 'label'   => __( 'Merge Tierd Pricing Table', 'wholesalex' ),
						// 'desc'    => __( 'Merge All Tiers', 'wholesalex' ),
						// 'help'    => __( 'By default, It will display the table which is currently applied. If you enable this option, It will display a merged table of all tiers.', 'wholesalex' ),
						// 'default' => '',
						// ),
						'_settings_quantity_based_discount_priority' => array(
							'type'    => 'DragList',
							'label'   => __( 'Pricing / Discount Priority', 'wholesalex' ),
							'desc'    => __( 'Set the priority to declare which will be applied if discounts are assigned in multiple ways.', 'wholesalex' ),
							// 'is_pro'  => true,
							'options' => array( 'profile', 'single_product', 'category', 'dynamic_rule' ),
							'default' => array( 'profile', 'single_product', 'category', 'dynamic_rule' ),
							'tooltip' => 'Decide and select which pricing will be applicable if the prices are set in multiple ways.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'b2b_stock_management_status' => array(
							'type'    => 'switch',
							'label'   => __( 'Enable B2B Stock Management', 'wholesalex' ),
							'desc'    => __( 'B2B Stock Management', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							'tooltip' => 'Enabling this option will give a option on inventory tab on product page to manage b2b stock.',
						),
						'_settings_disable_coupon'         => array(
							'type'    => 'switch',
							'label'   => __( 'Disable Coupons', 'wholesalex' ),
							'desc'    => __( 'Hide coupon form of cart and checkout pages from wholesale users.', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							'tooltip' => 'Enabling this option will hide the coupon fields of the cart and checkout pages from the wholesale customers.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'help_popup' => true,
							// 'popup_gif_link' => 'https://plugins.svn.wordpress.org/wholesalex/assets/Screenshot-1.jpg',
						),
						'_settings_hide_products_from_b2c' => array(
							'type'    => 'switch',
							'label'   => __( 'Hide All Products From B2C Users', 'wholesalex' ),
							'desc'    => __( 'Click on the checkbox to hide all products from B2C users.', 'wholesalex' ),
							'help'    => __( 'Once you click on this check box all products will be hidden from b2c users.', 'wholesalex' ),
							'default' => 'no',
							'tooltip' => 'Enabling this option will hide all products of your store from the B2C customers.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'help_popup' => true,
							// 'popup_gif_link' => 'https://plugins.svn.wordpress.org/wholesalex/assets/Screenshot-1.jpg',
						),
						'_settings_hide_all_products_from_guest' => array(
							'type'    => 'switch',
							'label'   => __( 'Hide All Products From Guest Users', 'wholesalex' ),
							'desc'    => __( 'Click on the check box if you want to hide all products from guest users.', 'wholesalex' ),
							'help'    => __( 'Once you click on this check box all products will be hidden from guest users.', 'wholesalex' ),
							'default' => 'no',
							'tooltip' => 'Enabling this option will hide all products of your store from the Guest customers.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'tooltip' => __('Once you click on this check box all products will be hidden from guest users.','wholesalex'),
							// 'doc_link' => 'https://getwholesalex.com/pricing/?utm_source=wholesalex_plugin&utm_medium=support&utm_campaign=wholesalex-DB',
						),
						'_settings_display_price_shop_page' => array(
							'type'    => 'select',
							'label'   => __( 'Display Prices in the Shop', 'wholesalex' ),
							'options' => array(
								'woocommerce_default_tax' => __( 'Use WooCommerce default', 'wholesalex' ),
								'incl'                    => __( 'Including Tax', 'wholesalex' ),
								'excl'                    => __( 'Excluding Tax', 'wholesalex' ),
							),
							'help'    => __( 'Display prices including or excluding taxes on the shop page.', 'wholesalex' ),
							'default' => 'woocommerce_default_tax',
							'tooltip' => 'Decide and select whether the product prices on the shop page will be with or without taxes.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'tooltip' => __('Display prices including or excluding taxes on the shop page.','wholesalex'),
							// 'doc_link' => 'https://getwholesalex.com/pricing/?utm_source=wholesalex_plugin&utm_medium=support&utm_campaign=wholesalex-DB',

						),
						'_settings_display_price_cart_checkout' => array(
							'type'    => 'select',
							'label'   => __( 'Display Prices in Cart and Checkout Page', 'wholesalex' ),
							'options' => array(
								'woocommerce_default_tax' => __( 'Use WooCommerce default', 'wholesalex' ),
								'incl'                    => __( 'Including Tax', 'wholesalex' ),
								'excl'                    => __( 'Excluding Tax', 'wholesalex' ),
							),
							'help'    => __( 'Display prices including or excluding taxes on the cart and checkout pages.', 'wholesalex' ),
							'default' => 'woocommerce_default_tax',
							'tooltip' => 'Decide and select whether the product prices on the cart and checkout pages will be with or without taxes.',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
							// 'tooltip' => __('Display prices including or excluding taxes on the cart and checkout pages.','wholesalex'),
							// 'doc_link' => 'https://getwholesalex.com/pricing/?utm_source=wholesalex_plugin&utm_medium=support&utm_campaign=wholesalex-DB',

						),
						'_settings_regular_price_suffix'   => array(
							'type'        => 'text',
							'label'       => __( 'Override Regular Price Suffix', 'wholesalex' ),
							'placeholder' => '',
							'help'        => __( 'Display desired text after regular prices on shop and single products pages.', 'wholesalex' ),
							'default'     => '',
							'tooltip'     => 'Add your custom text to replace the default one, which will be displayed just after the wholesale prices of the products of the shop and single product pages',
							'doc'         => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_wholesalex_price_suffix' => array(
							'type'        => 'text',
							/* translators: %s - Plugin Name */
							'label'       => sprintf( __( '%s Price Suffix', 'wholesalex' ), wholesalex()->get_plugin_name() ),
							'placeholder' => '',
							'help'        => __( 'Display desired text after wholesale prices on shop and single products pages.', 'wholesalex' ),
							'default'     => '',
							'tooltip'     => 'Add the custom text that you want to display just after the wholesale prices of the products of the shop and single product pages.',
							'doc'         => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_private_store'          => array(
							'type'    => 'switch',
							'label'   => __( 'Make The Store Private', 'wholesalex' ),
							'desc'    => __( 'Click the check box to make the store private from logged out users', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							'tooltip' => 'Enabling this option will make your store private from logged-out users. ',
							'doc'     => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_private_store_redirect_url' => array(
							'type'       => 'text',
							'label'      => __( 'Force Redirect URL', 'wholesalex' ),
							'depends_on' => array(
								array(
									'key'   => '_settings_private_store',
									'value' => 'yes',
								),
							),
							'help'       => __( 'Enter an url where you want to force redirect logged out users.', 'wholesalex' ),
							'default'    => get_permalink( $my_account_id ),
							'tooltip'    => 'Add your desired URL where you want to redirect the logged-out users',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_private_store_whitelist_url' => array(
							'type'       => 'text',
							'label'      => __( 'Whitelist URL (For Private Store)', 'wholesalex' ),
							'depends_on' => array(
								array(
									'key'   => '_settings_private_store',
									'value' => 'yes',
								),
							),
							'help'       => __( 'Enter Comma Separated URLs to make these whitelist on private store', 'wholesalex' ),
							'default'    => '',
							// 'tooltip' => 'Enter URL to make this whitelist on private store',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_allow_hidden_product_checkout' => array(
							'type'    => 'switch',
							'label'   => __( 'Allow Hidden Product to Checkout', 'wholesalex' ),
							'desc'    => __( 'Click the check box if you want to allow hidden product to checkout', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_allow_tax_with_cart_total_amount' => array(
							'type'    => 'switch',
							'label'   => __( 'Allow Tax To Cart Total Amount', 'wholesalex' ),
							'desc'    => __( 'Click the check box if you want to allow Tax with Cart Total Amount', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_access_shop_manager_with_wxs_menu' => array(
							'type'    => 'switch',
							'label'   => __( 'Allow Full Access to Shop Manager', 'wholesalex' ),
							'desc'    => __( 'Check this box if you want to enable Full Access of WholesaleX to the Shop Manager', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_access_delete_wholesalex_plugin_data' => array(
							'type'    => 'switch',
							'class'	  => 'wsx-plugin-delete-setting',
							'label'   => __( 'Delete Plugin Data Permanently', 'wholesalex' ),
							'desc'    => __( 'Check the box if you want to permanently delete the WholesaleX plugin data from the database', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
					),
				),
				'registration_n_login' => array(
					'label' => __( 'Registration & Login', 'wholesalex' ),
					'attr'  => array(
						'_settings_user_login_option'  => array(
							'type'    => 'select',
							'label'   => __( 'User Login Option', 'wholesalex' ),
							'options' => array(
								'manual_login' => __( 'Manual Login After Registration', 'wholesalex' ),
								'auto_login'   => __( 'Auto Login After Registration', 'wholesalex' ),
							),
							'help'    => __( 'Auto login after registration will work only if the user status option is set to “Auto Approve”. ', 'wholesalex' ),
							'link'    => 'https://getwholesalex.com/docs/wholesalex/registration-form-builder/',
							'default' => 'manual_login',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_user_status_option' => array(
							'type'    => 'select',
							'label'   => __( 'User Status Option', 'wholesalex' ),
							'options' => array(
								'email_confirmation_require' => __( 'Email Confirmation to Active Account', 'wholesalex' ),
								'auto_approve'  => __( 'Auto Approval Account', 'wholesalex' ),
								'admin_approve' => __( 'Admin Approval Required to Activate Account', 'wholesalex' ),
							),
							'default' => 'admin_approve',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_seperate_page_b2b'  => array(
							'type'    => 'select',
							'label'   => __( 'Separate My Account Page for B2B Users', 'wholesalex' ),
							'options' => $__pages_option,
							'help'    => __( 'Select your desired page if you want to separate the My Account Page for B2B users.', 'wholesalex' ),
							'default' => $my_account_id,
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',

						),
						'_settings_show_form_for_logged_in' => array(
							'type'    => 'switch',
							'label'   => __( 'Show Registration Form For Logged In User', 'wholesalex' ),
							'desc'    => __( 'Click on the check box if you want to show registration form logged in users.', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_redirect_url_registration' => array(
							'type'        => 'text',
							'label'       => __( 'Redirect Page URL (After Registration)', 'wholesalex' ),
							'placeholder' => __( 'http://', 'wholesalex' ),
							'help'        => '',
							'default'     => get_permalink( $my_account_id ),
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_redirect_url_login' => array(
							'type'        => 'text',
							'label'       => __( 'Redirect Page URL (After Login)', 'wholesalex' ),
							'placeholder' => __( 'http://', 'wholesalex' ),
							'help'        => '',
							'default'     => get_permalink( get_option( 'woocommerce_shop_page_id' ) ),
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_registration_success_message' => array(
							'type'    => 'textarea',
							'label'   => __( 'Registration Successful Message', 'wholesalex' ),
							'help'    => '',
							'default' => __( 'Thank you for registering. Your account will be reviewed by us & approve manually. Please wait to be approved.', 'wholesalex' ),
							// 'tooltip' => 'Enabling this option will display the pricing tier table on the single product pages. {Check out the documentation} to learn more about the pricing tiers.',
							// 'doc' => 'https://getwholesalex.com/docs/wholesalex/wholesalex-how-to-guide/change-store-mode-b2b-b2c-b2bb2c/?utm_source=wholesalex-menu&utm_medium=settings-documentation&utm_campaign=wholesalex-DB',
						),
						'_settings_message_for_logged_in_user' => array(
							'type'    => 'textarea',
							'label'   => __( 'Registration Form Message For Logged In User', 'wholesalex' ),
							'help'    => '',
							'default' => __( 'Sorry You Are Not Allowed To View This Form', 'wholesalex' ),
						),
					),
				),
				'price'                => array(
					'label' => __( 'Price', 'wholesalex' ),
					'attr'  => array(
						'_settings_price_text'            => array(
							'type'        => 'text',
							'label'       => __( 'Wholesale Price Text for Product Pages', 'wholesalex' ),
							'placeholder' => __( 'Wholesale Price:', 'wholesalex' ),
							'help'        => __( 'The text is shown immediately before the wholesale price in product single page. The default text is “Wholesale Price:”', 'wholesalex' ),
							'default'     => __( 'Wholesale Price:', 'wholesalex' ),
						),
						'_settings_price_text_product_list_page' => array(
							'type'        => 'text',
							'label'       => __( 'Wholesale Price Text for Product Listing Page', 'wholesalex' ),
							'placeholder' => __( 'Wholesale Price:', 'wholesalex' ),
							'help'        => __( 'The text is shown immediately before the wholesale price in product listing page. The default text is “Wholesale Price:”.', 'wholesalex' ),
							'default'     => __( 'Wholesale Price:', 'wholesalex' ),
						),
						'_settings_price_product_list_page' => array(
							'type'        => 'select',
							'label'       => __( 'Wholesale Price On Product Listing Page', 'wholesalex' ),
							'options'     => array(
								'pricing_range'   => __( 'Pricing Range', 'wholesalex' ),
								'minimum_pricing' => __( 'Minimum Pricing', 'wholesalex' ),
								'maximum_pricing' => __( 'Maximum Pricing', 'wholesalex' ),
							),
							'placeholder' => __( 'Pricing Range, Minimum Pricing, Maximum Pricing', 'wholesalex' ),
							'help'        => __( 'Select whether you want to display wholesale price range, minimum price, or maximize price on the product listing page.', 'wholesalex' ),
							'default'     => 'pricing_range',
						),
						'_settings_hide_retail_price'     => array(
							'type'    => 'switch',
							'label'   => __( 'Hide Retail Price', 'wholesalex' ),
							'desc'    => __( 'Click on the check box if you want to hide the retail price.', 'wholesalex' ),
							'help'    => __( 'Once you click on this check box the regular price will be hidden if the wholesale price is present.', 'wholesalex' ),
							'default' => 'no',
						),
						'_settings_hide_wholesalex_price' => array(
							'type'    => 'switch',
							'label'   => __( 'Hide Wholesale Price', 'wholesalex' ),
							'desc'    => __( 'Hide wholesale price for all users.', 'wholesalex' ),
							'help'    => __( 'This option will hide wholesale price in price-column of product-listing page.', 'wholesalex' ),
							'default' => 'no',
						),
						'_settings_login_to_view_price_product_list' => array(
							'type'    => 'switch',
							'label'   => __( 'Show Login to view price on Product Listing Page', 'wholesalex' ),
							'desc'    => __( 'Login to view price', 'wholesalex' ),
							'help'    => __( 'Display logging option on the product listing page to view price.', 'wholesalex' ),
							'default' => 'no',
						),
						'_settings_login_to_view_price_product_page' => array(
							'type'    => 'switch',
							'label'   => __( 'Show Login to view price on Single Product Page', 'wholesalex' ),
							'desc'    => __( 'Login to view price', 'wholesalex' ),
							'help'    => __( 'Display logging option on single product pages to view price.', 'wholesalex' ),
							'default' => 'no',
						),
						'_settings_login_to_view_price_login_url' => array(
							'type'    => 'text',
							'label'   => __( 'Login to View Price Login URL', 'wholesalex' ),
							'desc'    => __( 'Login to View Price Login URL', 'wholesalex' ),
							'help'    => __( 'Will redirect to this link for login, when login to view prices enabled.', 'wholesalex' ),
							'default' => get_permalink( $my_account_id ),
						),
					),
				),
				'dynamic_rules' => array(
					'label' => __( 'Dynamic Rules', 'wholesalex' ),
					'attr'  => array(
						'show_promotions_on_sp' => array(
							'type'    => 'switch',
							'label'   => __( 'Show Promotions', 'wholesalex' ),
							'desc'    => __( 'Click on the check box to display promotional texts on single product pages.', 'wholesalex' ),
							'help'    => '',
							'default' => 'no',
						),
						'promo_button_text_on_sp'            => array(
							'type'        => 'text',
							'label'       => __( 'Main Promo Text', 'wholesalex' ),
							'placeholder' => __( 'Get exclusive offers', 'wholesalex' ),
							'help'        => '',
							'default'     => __( 'Get exclusive offers', 'wholesalex' ),
							'depends_on' => array(
								array(
									'key'   => 'show_promotions_on_sp',
									'value' => 'yes',
								),
							),
						),
						'product_discount_promo_section' => array(
							'type' => 'dynamic_rule_promo_section',
							'label' => __('Product Discount','wholesalex'),
							'attr'	=> array(
								'show_product_discounts_text' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Product Discount on Pop Up', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display product discounts promotional texts on single product pages.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'product_discount_rule_sp_show_rule_info' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Label Text', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display product discounts rule info before promotional card.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'product_discount_rule_info_rule_type_text' => array(
									'type'        => 'text',
									'label'       => __( 'Label Text', 'wholesalex' ),
									'placeholder' => __( 'Product Discount', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Product Discount', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								
							)
						),
						'cart_discount_promo_section'	=> array(
							'label'=> __('Cart Discount','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_cart_discount_text' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Cart Discount on Pop Up', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display cart discounts promotional texts on single product pages.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_discount_rule_sp_show_rule_info' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Label Text', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display cart discounts rule info before promotional card.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_discount_rule_info_rule_type_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Label Text', 'wholesalex' ),
									'placeholder' => __( 'Cart Discount', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Cart Discount', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_discount_promo_sp_desc_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Promo Description Text', 'wholesalex' ),
									'placeholder' => __( 'After adding to the cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'After adding to the cart ', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
							)
						),
						'payment_method_discount_promo_section' => array(
							'label' => __('Payment Method Discounts','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_payment_method_discount_promo_text_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Payment Method Discount on Pop Up', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display payment method promotional texts on single product pages.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'payment_discount_rule_sp_show_rule_info' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Label Text', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display payment discounts rule info before promotional card.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'payment_method_discount_label_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Label Text', 'wholesalex' ),
									'placeholder' => __( 'Payment Method Discount', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Payment Method Discount', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'payment_method_discount_promo_sp_desc_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Promo Description Text', 'wholesalex' ),
									'placeholder' => __( 'Use {payment_methods}', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Use {payment_methods}', 'wholesalex' ),
									'smart_tags'  => array(
										'{payment_methods}' => __('Allowed Payment Methods','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								
							)
						),
						'free_shipping_promo_section' => array(
							'label'=> __('Free Shipping','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_free_shipping_promo_text_on_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Promotion on Product Page', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display free shipping texts on single product pages.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'free_shipping_text_on_sp'            => array(
									'type'        => 'text',
									'label'       => __( 'Promo Text', 'wholesalex' ),
									'placeholder' => __( 'Free Shipping', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Free Shipping', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'free_shipping_heading_text_on_sp'            => array(
									'type'        => 'text',
									'label'       => __( 'Text on Promotional Popup', 'wholesalex' ),
									'placeholder' => __( 'Free Shipping', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Free Shipping', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
							)
						),
						'bogo_discount_promo_section' => array(
							'label' => __('Buy X Get 1 (BOGO)','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_bogo_discount_promo_text_on_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Show BOGO Discounts Promotions Text', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display BOGO Discounts promotional texts on single product pages.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'bogo_discount_rule_sp_show_rule_info' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Label Text', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display bogo discounts rule info before promotional card.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'bogo_discount_bogo_badge_enable' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Bogo Badge', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display BOGO BADGE in Shop and Product page.', 'wholesalex' ),
									'help'    => '',
									'default' => 'yes',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'no',
										),
									),
								),
								'bogo_discount_rule_info_rule_type_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Label Text ', 'wholesalex' ),
									'placeholder' => __( 'BOGO Discount', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'BOGO Discount', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								
								'bogo_discount_free_text_on_sp'            => array(
									'type'        => 'text',
									'label'       => __( 'Bogo Offer Text', 'wholesalex' ),
									'placeholder' => __( 'Get 1 Free', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Get 1 Free', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'bogo_discounts_promo_sp_desc_text_on_sp'            => array(
									'type'        => 'text',
									'label'       => __( 'Promo Text on Pop-Up', 'wholesalex' ),
									'placeholder' => __( 'Buy at least {required_quantity} products', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Buy at least {required_quantity} products', 'wholesalex' ),
									'smart_tags'  => array(
										'{required_quantity}' => __('Required Quantity To Get Discounts','wholesalex'),
										'{product_title}' => __('Product Title','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'_settings_bogo_discount_text' => array(
									'type'        => 'text',
									'label'       => __( 'Promo Text on Cart', 'wholesalex' ),
									'placeholder' => '',
									'help'        => __( 'Use the following Smart Tags to highlight the BOGO discount on the cart page. ', 'wholesalex' ),
									'default'     => '{product_title} (BOGO Discounts)',
									'smart_tags'  => array(
										'{x}' => __('minimum product quantity to avail discounts','wholesalex'),
										'{y}' => __('Free products quantity','wholesalex'),
										'{product_title}' => __('Product Title','wholesalex'),
									),
								),
							)
						),
						'bxgy_discounts_promo_section' => array(
							'label' => __('Buy X Get Y ','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'_settings_show_bxgy_free_products_on_single_product_page' => array(
									'type'    => 'switch',
									'label'   => __( 'Buy X Get Y (Show free item on Product Page)', 'wholesalex' ),
									'desc'    => __( 'Click on the check box, if you want to showcase the free product(s) on the single product page.', 'wholesalex' ),
									'help'    => '',
									'default' => '',
								),
								'_settings_show_bxgy_free_products_badge' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Badge on Shop & Product Page)', 'wholesalex' ),
									'desc'    => __( 'Click on the check box, if you want to show BADGE  on the shop & single product page.', 'wholesalex' ),
									'help'    => '',
									'default' => 'yes',
								),
							)
						),
						'restrict_checkout_promo_section' => array(
							'label' => __('Checkout Restriction','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'only_total_cart_value_promo_text' => array(
									'type'        => 'text',
									'label'       => __( 'Cart Price', 'wholesalex' ),
									'placeholder' => __( 'Specify the minimum amount for checkout', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Specify the minimum amount for checkout', 'wholesalex' ),
									'smart_tags'  => array(
										'{cart_value}' => __('Cart Price','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_total_cart_quantity_promo_text' => array(
									'type'        => 'text',
									'label'       => __( 'Cart Quantity', 'wholesalex' ),
									'placeholder' => __( 'Specify the minimum number of items for checkout', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Specify the minimum number of items for checkout', 'wholesalex' ),
									'smart_tags'  => array(
										'{cart_quantity}' => __('Cart Quantity','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_total_cart_weight_promo_text' => array(
									'type'        => 'text',
									'label'       => __( 'Cart Weight', 'wholesalex' ),
									'placeholder' => __( 'Specify the minimum cart weight for checkout', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Specify the minimum cart weight for checkout', 'wholesalex' ),
									'smart_tags'  => array(
										'{cart_weight}' => __('Cart Weight','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_total_user_order_count_promo_text' => array(
									'type'        => 'text',
									'label'       => __( 'User Order Count', 'wholesalex' ),
									'placeholder' => __( 'Specify the minimum user order count for checkout', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Specify the minimum user order count for checkout', 'wholesalex' ),
									'smart_tags'  => array(
										'{order_count}' => __( 'User Order Count','wholesalex' ),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_total_purchase_amount_promo_text' => array(
									'type'        => 'text',
									'label'       => __( 'Total Purchase Amount', 'wholesalex' ),
									'placeholder' => __( 'Specify the minimum total purchase amount for checkout', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Specify the minimum total purchase amount for checkout', 'wholesalex' ),
									'smart_tags'  => array(
										'{total_purchase_amount}' => __( 'Total Purchase Amount','wholesalex' ),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
							)
						),
						'min_max_discounts_promo_section' => array(
							'label' => __('Min & Max Order Quantity','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr'  => array(
								'show_order_qty_text_on_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Required Order Quantity Notice on Product Page', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display required order quantity notice.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_minimum_order_qty_promo_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Minimum Order Quantity Text', 'wholesalex' ),
									'placeholder' => __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ),
									'smart_tags'  => array(
										'{minimum_qty}' => __('Minimum Order Quantity','wholesalex'),
										'{product_title}' => __('Product Title','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'only_maximum_order_qty_promo_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Maximum Order Quantity Text', 'wholesalex' ),
									'placeholder' => __( 'You can add maximum {maximum_qty} quantity', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'You can add maximum {maximum_qty} quantity', 'wholesalex' ),
									'smart_tags'  => array(
										'{maximum_qty}' => __('Maximum Order Quantity','wholesalex'),
										'{product_title}' => __('Product Title','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'min_max_both_order_qty_promo_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Min and Max Order Quantity text', 'wholesalex' ),
									'placeholder' => __( 'You can add minimum {minimum_qty} and maximum {maximum_qty} quantity of this product', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'You can add minimum {minimum_qty} and maximum {maximum_qty} quantity of this product', 'wholesalex' ),
									'smart_tags'  => array(
										'{minimum_qty}' => __('Minimum Order Quantity','wholesalex'),
										'{maximum_qty}' => __('Maximum Order Quantity','wholesalex'),
										'{product_title}' => __('Product Title','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								
							)
						),
						'conditions_promo_section' => array(
							'label' => __('Conditions','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_discount_conditions_on_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Cart Total Value Discount on Pop Up', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display discount conditions text.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_value_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Value Min Text', 'wholesalex' ),
									'placeholder' => __( 'Spend upto {min_value}', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Spend upto {min_value}', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_value_min_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Value Max Text ', 'wholesalex' ),
									'placeholder' => __( 'Spend max {max_value}', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Spend max {max_value}', 'wholesalex' ),
									'smart_tags'  => array(
										'{max_value}' => __('Maximum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_value_min_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Value Min & Max Text', 'wholesalex' ),
									'placeholder' => __( 'Spend {min_value} to {max_value}', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Spend {min_value} to {max_value}', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
										'{max_value}' => __('Maximum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_qty_min_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Quantity Min & Max Text', 'wholesalex' ),
									'placeholder' => __( 'Add {min_value} to {max_value} product(s) to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add {min_value} to {max_value} product(s) to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
										'{max_value}' => __('Maximum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_qty_min_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Weight Min Text', 'wholesalex' ),
									'placeholder' => __( 'Add min {max_value} product(s) to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add min {max_value} product(s) to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{max_value}' => __('Maximum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_qty_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Weight Max Text', 'wholesalex' ),
									'placeholder' => __( 'Add {min_value} or more product(s) to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add {min_value} or more product(s) to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
		
		
								'cart_total_weight_min_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Weight Min & Max Text', 'wholesalex' ),
									'placeholder' => __( 'Add {min_value} to {max_value} {unit} to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add {min_value} to {max_value} {unit} to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
										'{max_value}' => __('Maximum Value','wholesalex'),
										// Translators: %s is the unit of measurement for weight.
										'{unit}' => sprintf(__('Unit: %s','wholesalex'),$weight_unit),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_weight_min_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Quantity Min Text', 'wholesalex' ),
									'placeholder' => __( 'Add min {max_value} {unit} to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add min {max_value} {unit} to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{max_value}' => __('Maximum Value','wholesalex'),
										// Translators: %s is the unit of measurement for weight.
										'{unit}' => sprintf(__('Unit: %s','wholesalex'),$weight_unit),
		
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'cart_total_weight_max_conditions_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Cart Total Quantity Max Text', 'wholesalex' ),
									'placeholder' => __( 'Add up to {min_value} {unit} to cart', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Add up to {min_value} {unit} to cart', 'wholesalex' ),
									'smart_tags'  => array(
										'{min_value}' => __('Minimum Value','wholesalex'),
										// Translators: %s is the unit of measurement for weight.
										'{unit}' => sprintf(__('Unit: %s','wholesalex'),$weight_unit),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
							)
						),
						'discounts_validity_promo_section' => array(
							'label' => __('Discounts Validity','wholesalex'),
							'type' => 'dynamic_rule_promo_section',
							'attr' => array(
								'show_discounts_validity_text_on_sp' => array(
									'type'    => 'switch',
									'label'   => __( 'Show Discounts Validity on Pop-Up', 'wholesalex' ),
									'desc'    => __( 'Click on the check box to display Discount Validity texts on promo texts.', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
								'discounts_validity_text'            => array(
									'type'        => 'text',
									'label'       => __( 'Discount Validity Text', 'wholesalex' ),
									'placeholder' => __( 'Valid till: {end_date}', 'wholesalex' ),
									'help'        => '',
									'default'     => __( 'Valid till: {end_date}', 'wholesalex' ),
									'smart_tags'  => array(
										'{end_date}' => __('End Date','wholesalex'),
									),
									'depends_on' => array(
										array(
											'key'   => 'show_promotions_on_sp',
											'value' => 'yes',
										),
									),
								),
							)
						),
						'dynamic_rule_promotional_explainer_text_single_discount'            => array(
							'type'        => 'text',
							'label'       => __( 'Promotions Explainer Text for single discounts', 'wholesalex' ),
							'placeholder' => __( 'You can avail one of the following offers by completing the requirements.', 'wholesalex' ),
							'help'        => '',
							'default'     => __( 'You can avail one of the following offers by completing the requirements.', 'wholesalex' ),
							'depends_on' => array(
								array(
									'key'   => 'show_promotions_on_sp',
									'value' => 'yes',
								),
							),
						),
						'dynamic_rule_promotional_explainer_text_multiple_discount'            => array(
							'type'        => 'text',
							'label'       => __( 'Promotions Explainer Text for Multiple discounts', 'wholesalex' ),
							'placeholder' => __( 'You can avail following offers by completing the requirements.', 'wholesalex' ),
							'help'        => '',
							'default'     => __( 'You can avail following offers by completing the requirements.', 'wholesalex' ),
							'depends_on' => array(
								array(
									'key'   => 'show_promotions_on_sp',
									'value' => 'yes',
								),
							),
						),
					),
				),
				'language_n_text'      => array(
					'label' => __( 'Language and Text', 'wholesalex' ),
					'attr'  => array(
						'_language_registraion_from_combine_login_text' => array(
							'type'        => 'text',
							'label'       => __( 'Login Text', 'wholesalex' ),
							'placeholder' => '',
							'help'        => '',
							'default'     => __( 'Login', 'wholesalex' ),
						),
						'_language_registraion_from_combine_registration_text' => array(
							'type'        => 'text',
							'label'       => __( 'Registration Text', 'wholesalex' ),
							'placeholder' => '',
							'help'        => '',
							'default'     => __( 'Registration', 'wholesalex' ),
						),
						'_language_logout_to_see_this_form' => array(
							'type'        => 'text',
							'label'       => __( 'Logout To See this form Text', 'wholesalex' ),
							'placeholder' => '',
							'help'        => '',
							'default'     => __( 'Logout To See this form', 'wholesalex' ),
						),
						'_language_login_to_see_prices' => array(
							'type'        => 'text',
							'label'       => __( 'Login To See Price Text', 'wholesalex' ),
							'placeholder' => '',
							'help'        => '',
							'default'     => __( 'Login to see prices', 'wholesalex' ),
						),
						'_language_price_is_hidden'     => array(
							'type'        => 'text',
							'label'       => __( 'Price is hidden Text', 'wholesalex' ),
							'placeholder' => '',
							'help'        => '',
							'default'     => __( 'Price is hidden!', 'wholesalex' ),
						),
						'_language_profile_force_free_shipping_text' => array(
							'type'        => 'text',
							/* translators: %s - Plugin Name */
							'label'       => sprintf( __( '%s Free Shipping Text', 'wholesalex' ), wholesalex()->get_plugin_name() ),
							'placeholder' => '',
							'help'        => '',
							/* translators: %s - Plugin Name */
							'default'     => sprintf( __( '%s Free Shipping Text', 'wholesalex' ), wholesalex()->get_plugin_name() ),
						),
					),
				),
				'design'               => array(
					'label' => __( 'Design', 'wholesalex' ),
					'attr'  => array(
						'_settings_tier_layout'          => array(
							'type'    => 'choosebox',
							'label'   => __( 'Tier Table Layout in Product Single Page', 'wholesalex' ),
							'options' => apply_filters(
								'wholesalex_settings_product_tier_layout',
								array(
									'layout_one'   => WHOLESALEX_URL . '/assets/img/layout_one.png',
									'layout_two'   => WHOLESALEX_URL . '/assets/img/layout_two.png',
									'layout_three' => WHOLESALEX_URL . '/assets/img/layout_three.png',
								)
							),
							'default' => 'layout_one',
						),
						'_settings_tier_position'        => array(
							'type'    => 'switch',
							'label'   => __( 'Tier Position in Product Single Page', 'wholesalex' ),
							'desc'    => __( 'Above Cart Button', 'wholesalex' ),
							'help'    => __( 'Display tiered pricing above the “Add to Cart” button.', 'wholesalex' ),
							'default' => 'no',
						),
						'_settings_primary_color'        => array(
							'type'    => 'color',
							'label'   => __( 'Primary Color', 'wholesalex' ),
							'desc'    => '#5a40e8',
							'default' => '#5a40e8',
						),
						'_settings_primary_button_color' => array(
							'type'    => 'color',
							'label'   => __( 'Primary Button Color', 'wholesalex' ),
							'desc'    => '#FFFFFF',
							'default' => '#ffffff',
						),
						'_settings_primary_hover_color'  => array(
							'type'    => 'color',
							'label'   => __( 'Primary Hover Color', 'wholesalex' ),
							'desc'    => '#24A88F',
							'default' => '#24A88F',
						),
						'_settings_text_color'           => array(
							'type'    => 'color',
							'label'   => __( 'Text Color', 'wholesalex' ),
							'desc'    => '#272727',
							'default' => '#272727',
						),
						'_settings_border_color'         => array(
							'type'    => 'color',
							'label'   => __( 'Border', 'wholesalex' ),
							'desc'    => '#E5E5E5',
							'default' => '#E5E5E5',
						),
						'_settings_active_tier_color'    => array(
							'type'    => 'color',
							'label'   => __( 'Active Tier', 'wholesalex' ),
							'desc'    => '#E5E5E5',
							'default' => '#E5E5E5',
						),
					),
				),
				'recaptcha'            => array(
					'label' => __( 'reCAPTCHA', 'wholesalex' ),
					'attr'  => array(
						'_settings_google_recaptcha_v3_site_key'   => array(
							'type'        => 'text',
							'label'       => __( 'Site Key', 'wholesalex' ),
							'placeholder' => __( 'Site Key...', 'wholesalex' ),
							'default'     => '',
							'help'        => __( 'For Gettings reCAPTCHA Site Key, You have to create an project on google recaptcha. ', 'wholesalex' ),
							'link'        => 'https://getwholesalex.com/add-on/recaptcha/',
						),
						'_settings_google_recaptcha_v3_secret_key' => array(
							'type'        => 'text',
							'label'       => __( 'Secret Key', 'wholesalex' ),
							'placeholder' => __( 'Secret Key...', 'wholesalex' ),
							'default'     => '',
							'help'        => __( 'For Gettings reCAPTCHA secret Key, You have to create an project on google recaptcha. ', 'wholesalex' ),
							'link'        => 'https://getwholesalex.com/add-on/recaptcha/',
						),
						'_settings_google_recaptcha_v3_allowed_score' => array(
							'type'        => 'text',
							'label'       => __( 'Minimum Allowed Score', 'wholesalex' ),
							'placeholder' => '',
							'default'     => __( '0.5', 'wholesalex' ),
							'help'        => __( 'Set minimum allowed score for reCAPTCHA. Default Range: 0.0 - 1.00', 'wholesalex' ),
						),
					),
				),
				'_save'                => array(
					'type'  => 'button',
					'label' => __( 'Save Changes', 'wholesalex' ),
				),

			),
		);
	}

	/**
	 * Display Price Including Tax in Shop
	 *
	 * @param String $option Include tax or Exclude Tax in Shop.
	 * @since 1.0.0
	 * @access public
	 */
	public function display_price_shop_including_tax( $option ) {
		$__display_price_shop = wholesalex()->get_setting( '_settings_display_price_shop_page', 'woocommerce_default_tax' ) ? wholesalex()->get_setting( '_settings_display_price_shop_page', 'woocommerce_default_tax' ) : '';

		if ( ! empty( $__display_price_shop ) && 'woocommerce_default_tax' !== $__display_price_shop ) {
			$option = $__display_price_shop;
		}
		return $option;
	}

	/**
	 * Display Price Including Tax in Cart Page
	 *
	 * @param String $option Include tax or Exclude Tax in Cart Page.
	 * @since 1.0.0
	 * @access public
	 */
	public function display_price_cart_including_tax( $option ) {
		$__display_price_cart_checkout = wholesalex()->get_setting( '_settings_display_price_cart_checkout', 'woocommerce_default_tax' ) ? wholesalex()->get_setting( '_settings_display_price_cart_checkout', 'woocommerce_default_tax' ) : '';
		if ( ! empty( $__display_price_cart_checkout ) && 'woocommerce_default_tax' !== $__display_price_cart_checkout ) {
			$option = $__display_price_cart_checkout;
		}
		return $option;
	}

	/**
	 * WholesaleX Price Suffix Handler
	 *
	 * @param String     $price_suffix Default Price Suffix.
	 * @param WC_Product $product Woocommerce Product.
	 * @since 1.0.0
	 * @access public
	 * @return String $price_suffix
	 */
	public function price_suffix_handler( $price_suffix, $product ) {

		$wholesalex_regular_price_suffix = wholesalex()->get_setting( '_settings_regular_price_suffix' );
		$wholesalex_price_suffix         = wholesalex()->get_setting( '_settings_wholesalex_price_suffix' );
		if ( isset( $wholesalex_regular_price_suffix ) && ! empty( $wholesalex_regular_price_suffix ) && ! $product->is_on_sale() ) {
			if ( '{price_including_tax}' === $wholesalex_regular_price_suffix ) {
				$wholesalex_regular_price_suffix = wc_price( wc_get_price_including_tax( $product ) );
			} elseif ( '{price_excluding_tax}' === $wholesalex_regular_price_suffix ) {
				$wholesalex_regular_price_suffix = wc_price( wc_get_price_excluding_tax( $product ) );
			}
			return '<small class="woocommerce-price-suffix">' . $wholesalex_regular_price_suffix . '</small>';
		} elseif ( isset( $wholesalex_price_suffix ) && ! empty( $wholesalex_price_suffix ) && $product->is_on_sale() ) {
			if ( '{price_including_tax}' === $wholesalex_price_suffix ) {
				$wholesalex_price_suffix = wc_price( wc_get_price_including_tax( $product ) );
			} elseif ( '{price_excluding_tax}' === $wholesalex_price_suffix ) {
				$wholesalex_price_suffix = wc_price( wc_get_price_excluding_tax( $product ) );
			}
			return '<small class="woocommerce-price-suffix">' . $wholesalex_price_suffix . '</small>';
		}
		return $price_suffix;
	}


	/**
	 * Separate My Account Page For B2B Users
	 *
	 * @param string $option Option.
	 * @return string
	 */
	public function separate_my_account_page_for_b2b( $option ) {
		$__user_role = wholesalex()->get_current_user_role();

		$__is_b2b = ( 'wholesalex_guest' !== $__user_role && 'wholesalex_b2c_users' !== $__user_role && ! empty( $__user_role ) ) ? true : false;

		$__my_account_page = wholesalex()->get_setting( '_settings_seperate_page_b2b', $option );
		if ( $__is_b2b && ! empty( $__my_account_page ) ) {
			return $__my_account_page;
		}

		return $option;
	}

	/**
	 * Hide Coupon Fields For WholesaleX User
	 *
	 * @param bool $enabled Coupon Fields Enable Status.
	 * @return bool
	 * @since 1.0.0
	 */
	public function hide_coupon_fields( $enabled ) {
		if ( is_user_logged_in() ) {
			$current_user_id   = get_current_user_id();
			$current_user_role = get_the_author_meta( '__wholesalex_role', $current_user_id );
			$__status          = wholesalex()->get_setting( '_settings_disable_coupon' );

			if ( isset( $current_user_role ) && ! empty( $current_user_role ) && 'yes' === $__status ) {
				return false;
			}
		}
		return $enabled;
	}

	/**
	 * Recaptcha Minimum Score Allowed
	 *
	 * @return string
	 * @since 1.0.0 Reposition on v1.0..7
	 */
	public function recaptcha_minimum_score_allow() {
		$__score = wholesalex()->get_setting( '_settings_google_recaptcha_v3_allowed_score', 0.5 ) ?? 0.5;
		return $__score;
	}


	/**
	 * Force Redirect Guest Users
	 *
	 * @return void
	 * @since 1.0.8
	 * @since 1.2.4 Check Whitelist url without query param
	 */
	public function force_redirect_guest_users() {
		if ( ! is_user_logged_in() ) {
			$is_private = wholesalex()->get_setting( '_settings_private_store' );
			if ( 'yes' === $is_private ) {
				add_action(
					'wp',
					function () {
						$protocol = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
						$cur_url  = $protocol . '' . $_SERVER['HTTP_HOST'] . '' . $_SERVER['REQUEST_URI']; //phpcs:ignore
						$cur_url  = strtok( $cur_url, '?' );
						$cur_url  = rtrim( $cur_url, '/' );

						$redirect_url = apply_filters( 'wholesalex_force_redirect_guest_user_url', wholesalex()->get_setting( '_settings_private_store_redirect_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ) );
						$redirect_url = rtrim( esc_url_raw( $redirect_url ), '/' );

						$default_url = rtrim( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ), '/' );

						if ( '' === $redirect_url ) {
							$redirect_url = $default_url;
						}

						$whitelists_urls = wholesalex()->get_setting( '_settings_private_store_whitelist_url' );

						$whitelists_urls = explode( ',', $whitelists_urls );

						$is_cur_url_whitelist = false;

						if ( is_array( $whitelists_urls ) ) {
							foreach ( $whitelists_urls as $whitelist_url ) {
								if ( rtrim( esc_url_raw( $whitelist_url ), '/' ) == $cur_url ) {
									$is_cur_url_whitelist = true;
								}
							}
						}

						if ( filter_var( $redirect_url, FILTER_VALIDATE_URL ) ) {
							if ( ( $default_url !== $cur_url && $redirect_url != $cur_url ) && ! $is_cur_url_whitelist ) {
								wp_safe_redirect( $redirect_url );
								exit;
							}
						} else {
							auth_redirect();
						}
					}
				);
			}
		}
	}

	/**
	 * Allow Hidden Product to Checkout
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	public function allow_hidden_product_to_checkout() {
		$is_allow = ( 'yes' === wholesalex()->get_setting( '_settings_allow_hidden_product_checkout' ) );
		return $is_allow;
	}

	/**
	 * Product Tier Layout
	 *
	 * @param array $options Options.
	 * @return array
	 * @since 1.0.0
	 * @since 1.0.1 More Tire Layout Added.
	 * @since 1.0.6 Delete Existing Code and Rewrite for new pricing plan.
	 */
	public function product_tier_layout( $options ) {
		return wholesalex()->unlock_layouts( $options );
	}

	/**
	 * Unlock Options
	 *
	 * @param array $options Options.
	 * @return array
	 */
	public function unlock_option( $options ) {
		return wholesalex()->unlock_options( $options );
	}
}
