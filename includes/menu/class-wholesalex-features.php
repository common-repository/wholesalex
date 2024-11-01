<?php
/**
 * WholesaleX Features Page
 *
 * @package WHOLESALEX
 * @since 1.2.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Feature Class
 */
class WholesaleX_Features {
	/**
	 * Constructor
	 */
	public function __construct() {
		/**
		 * Add Feature Submenu Page
		 */
		add_action( 'admin_menu', array( $this, 'wholesalex_features_submenu_page' ) );
	}

	/**
	 * WholesaleX Email Add Submenu Page
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function wholesalex_features_submenu_page() {
		$slug                = apply_filters( 'wholesalex_features_submenu_slug', 'wholesalex-features' );
		$is_features_enabled = apply_filters( 'wholesalex_features_menu_page_enabled', true );
		if ( $is_features_enabled ) {
			add_submenu_page(
				wholesalex()->get_menu_slug(),
				__( 'Features', 'wholesalex' ),
				__( 'Features', 'wholesalex' ),
				apply_filters( 'wholesalex_capability_access', 'manage_options' ),
				$slug,
				array( $this, 'feature_page_content' )
			);
		}
	}

	/**
	 * Feature Page Content
	 *
	 * @return void
	 */
	public function feature_page_content() {
		wp_enqueue_script( 'whx_features' );
		wp_enqueue_script( 'wholesalex_node_vendors' );
		wp_enqueue_script( 'wholesalex_components' );

        wp_localize_script( 'whx_features', 'whx_features', array(
            'i18n' => array(
                'features' => __('Features','wholesalex'),
                'restrict_guest_access' => __('Restrict Guest Access','wholesalex'),
                'bulk_order' => __('Bulk Order','wholesalex'),
                'request_a_quote' => __('Request A Quote', 'wholesalex'),
                'wholesale_pricing' => __('Wholesale Pricing', 'wholesalex'),
                'registration_form' => __('Registration Form', 'wholesalex'),
                'subaccounts_management' => __('Subaccounts Management', 'wholesalex'),
                'wallet_management' => __('Wallet management', 'wholesalex'),
                'dynamic_discount_rules' => __('Dynamic Discount Rules', 'wholesalex'),
                'conversations_built_in_messaging' => __('Conversations.Built-in messaging.', 'wholesalex'),
                'create_unlimited_user_roles' => __('Create Unlimited User Roles', 'wholesalex'),
                'tax_control' => __('Tax Control', 'wholesalex'),
                'import_and_export_role_base_sale_price' => __('Import and Export Role Base/Sale Price', 'wholesalex'),
                'import_export_customer' => __('Import Export Customer', 'wholesalex'),
                'automatic_approval_for_b2b_registration' => __('Automatic Approval For B2B Registration', 'wholesalex'),
                'manual_approval_for_b2b_registration' => __('Manual Approval For B2B Registration', 'wholesalex'),
                'email_notifications_for_different_actions' => __('Email Notifications For Different Actions', 'wholesalex'),
                'control_redirect_urls' => __('Control Redirect URLs', 'wholesalex'),
                'visibility_control' => __('Visibility Control', 'wholesalex'),
                'shipping_control' => __('Shipping Control', 'wholesalex'),
                'force_free_shipping' => __('Force Free Shipping', 'wholesalex'),
                'payment_gateway_control' => __('Payment Gateway Control', 'wholesalex'),
                'extra_charge' => __('Extra Charge', 'wholesalex'),
                'bogo_discounts' => __('BOGO Discounts', 'wholesalex'),
                'show_login_to_view_prices' => __('Show Login to view prices', 'wholesalex'),
                'buy_x_get_y' => __('Buy X Get Y', 'wholesalex'),
                'control_order_quantity' => __('Control Order Quantity', 'wholesalex'),
                'google_rrecaptcha_v3_integration' => __('Google RreCAPTCHA V3 Integration', 'wholesalex'),
                'quote_request' => __('Quote Request', 'wholesalex'),
                'conversation_with_store_owner' => __('Conversation With Store Owner', 'wholesalex'),
                'auto_role_migration' => __('Auto Role Migration', 'wholesalex'),
                'rolewise_credit_limit' => __('Rolewise Credit Limit', 'wholesalex'),
                'conditions_and_limits' => __('Conditions and Limits', 'wholesalex'),
                'restrict_guest_access_desc' => __('Make your wholesale area private for registered users and restrict guest users.', 'wholesalex'),
                'bulk_order_desc' => __('Allow your customers to order products in bulk or create purchase lists to order later.', 'wholesalex'),
                'request_a_quote_desc' => __('Let the potential buyers send quote requests to you directly from the cart page.', 'wholesalex'),
                'wholesale_pricing_desc' => __('Effortlessly manage wholesale pricing based on multiple wholesale/b2b user roles.', 'wholesalex'),
                'registration_form_desc' => __('Create a custom registration form with custom fields for effective customer acquisition.', 'wholesalex'),
                'subaccounts_management_desc' => __('Let your registered B2B customers create subaccounts with necessary user access.', 'wholesalex'),
                'wallet_management_desc' => __('Let B2B customers add funds to their digital wallets and use it as a payment method.', 'wholesalex'),
                'dynamic_discount_rules_desc' => __('Effectively manage discounted wholesale pricing using the dynamic discount rules.', 'wholesalex'),
                'conversations_built_in_messaging_desc' => __('Let your registered customers communicate with you with the in-built conversation system.', 'wholesalex'),
                'the_most_complete_woocommerce' => __('The Most Complete WooCommerce', 'wholesalex'),
                'b2b_n_b2c' => __('B2B + B2C', 'wholesalex'),
                'hybrid_solution' => __('Hybrid Solution', 'wholesalex'),
                'checkout_the_main_attractive' => __('Check out the main attractive features at a glance', 'wholesalex'),
                'explore_more' => __('Explore More', 'wholesalex'),
                'wholesalex_core' => __('WholesaleX Core', 'wholesalex'),
                'components' => __('Components', 'wholesalex'),
                'explore_more_features' => __('Explore More Features', 'wholesalex'),
            )
        ) );
		?>
			<div id="wholesalex_features_root"> </div>
		<?php
	}
}
