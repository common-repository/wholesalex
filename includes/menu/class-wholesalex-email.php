<?php
/**
 * WholesaleX Email Template
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Email Class
 */
class WHOLESALEX_Email {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wholesalex_email_submenu_page' ) );
		add_action( 'wp_ajax_save_wholesalex_email_settings', array( $this, 'save_wholesalex_email_settings' ) );
        
		add_action( 'rest_api_init', array( $this, 'register_email_template_restapi' ) );

		add_filter( 'wholealex_email_footer_text', array( $this, 'replace_email_footer_smart_tags' ) );

		add_filter( 'woocommerce_email_classes', array( $this, 'wholesalex_add_email_classes' ) );

		add_filter( 'woocommerce_email_actions', array( $this, 'wholesalex_add_email_actions' ) );

	}

	/**
	 * Add WholesaleX Email Classes
	 *
	 * @param array $email_classes WC email classes.
	 * @return array
	 */
	public function wholesalex_add_email_classes( $email_classes ) {
		$email_classes['WholesaleX_New_User_Auto_Approved_Email']                       = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-auto-approved.php';
		$email_classes['WholesaleX_Admin_New_User_Notification_Email']                  = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-registered.php';
		$email_classes['WholesaleX_New_User_Verification_Email']                        = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-verification.php';
		$email_classes['WholesaleX_New_User_Pending_For_Approval_Email']                = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-pending-for-approval.php';
		$email_classes['WholesaleX_Admin_New_User_Awating_Approval_Notification_Email'] = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-approval-require.php';
		$email_classes['WholesaleX_New_User_Approved_Email']                            = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-approved.php';
		$email_classes['WholesaleX_New_User_Verified_Email']                            = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-email-verified.php';
		$email_classes['WholesaleX_New_User_Rejected_Email']                            = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-new-user-rejected.php';
		$email_classes['WholesaleX_User_Profile_Update_Notification_Email']             = include WHOLESALEX_PATH . '/includes/emails/class-wholesalex-user-update.php';
		return $email_classes;
	}

	/**
	 * Add Email Actions
	 *
	 * @param array $actions Email Actions.
	 * @return array
	 */
	public function wholesalex_add_email_actions( $actions ) {
		$actions[] = 'wholesalex_registration_form_user_status_auto_approve';
		$actions[] = 'wholesalex_user_email_confirmation';
		$actions[] = 'wholesalex_registration_form_user_status_admin_approve';
		$actions[] = 'wholesalex_set_status_active';
		$actions[] = 'wholesalex_set_status_reject';
		$actions[] = 'wholesalex_user_email_verified';
		$actions[] = 'wholesalex_user_profile_update_notify';
		return $actions;
	}


	/**
	 * Register Email template rest api
	 *
	 * @return void
	 */
	public function register_email_template_restapi() {
		register_rest_route(
			'wholesalex/v1',
			'/email_templates/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'email_template_rest_callback' ),
					'permission_callback' => function () {
						return current_user_can( apply_filters( 'wholesalex_capability_access', 'manage_options' ) );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Sanitize Multiple Email Fields
	 *
	 * @param string $emails Emails.
	 * @return array
	 */
	public function sanitize_multiple_email_fields( $emails ) {
		$recipients = array_map( 'trim', explode( ',', $emails ) );
		$recipients = array_filter( $recipients, 'is_email' );
		return implode( ', ', $recipients );
	}

	/**
	 * Email Template Rest API Callback
	 *
	 * @param object $server Server.
	 * @return void
	 */
	public function email_template_rest_callback( $server ) {
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key($post['nonce']), 'wholesalex-registration' ) ) ) {
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
				$response['data']   = self::get_email_templates();
				break;
			case 'update_status':
				$template_name = isset( $post['template_name'] ) ? sanitize_text_field( $post['template_name'] ) : '';
				$status        = isset( $post['enabled'] ) ? sanitize_text_field( $post['enabled'] ) : 'no';
				if ( $template_name ) {
					$template_key_name            = 'woocommerce_' . $template_name . '_settings';
					$template_settings            = get_option( $template_key_name, array() );
					$template_settings['enabled'] = $status;
	
					update_option( $template_key_name, $template_settings );
					$response['status'] = true;
					$response['data']   = __( 'Success', 'wholesalex' );
				}
				break;
			case 'save_template':
				$template_name = isset( $post['template_name'] ) ? sanitize_text_field( $post['template_name'] ) : '';
				if ( $template_name ) {
					$template_key_name = 'woocommerce_' . $template_name . '_settings';
					$template_settings = get_option( $template_key_name, array() );
					if ( isset( $post['template']['recipient'] ) ) {
						$template_settings['recipient'] = $this->sanitize_multiple_email_fields( $post['template']['recipient'] );
					}
					if ( isset( $post['template']['subject'] ) ) {
						$template_settings['subject'] = wp_kses_post( $post['template']['subject'] );
					}
					if ( isset( $post['template']['heading'] ) ) {
						$template_settings['heading'] = wp_kses_post( $post['template']['heading'] );
					}
					if ( isset( $post['template']['additional_content'] ) ) {
						$template_settings['additional_content'] = wp_kses_post( $post['template']['additional_content'] );
					}
					if ( isset( $post['template']['email_type'] ) ) {
						$template_settings['email_type'] = wp_kses_post( $post['template']['email_type'] );
					}
	
					update_option( $template_key_name, $template_settings );
					$response['status'] = true;
					$response['data']   = __( 'Success', 'wholesalex' );
				}
				$response['status'] = true;
				$response['data']   = __( 'Success', 'wholesalex' );
				break;
	
			default:
				// code...
				break;
		}
	
		wp_send_json( $response );
	}

	/**
	 * Get All WholesaleX Email Templates
	 *
	 * @return array
	 */
	public static function get_email_templates() {
		$admin_email_template_ids = array(
			'wholesalex_new_user_approval_required',
			'wholesalex_new_user_registered',
		);
		$pro_template_ids         = apply_filters(
			'wholesalex_pro_email_template_ids',
			array(
				'wholesalex_conversation_email',
				'wholesalex_conversation_reply_email',
				'wholesalex_raq_make_offer',
				'wholesalex_raq_expiring_offer',
				'wholesalex_raq_admin_new_quote_request',
				'wholesalex_subaccount_create',
				'wholesalex_subaccount_order_approval_require',
				'wholesalex_subaccount_order_approved',
				'wholesalex_subaccount_order_pending',
				'wholesalex_subaccount_order_placed',
				'wholesalex_subaccount_order_reject',
				'wholesalex_user_profile_update_notify',
				'wholesalex_wallet_recharge_email',
				'wholesalex_wallet_debit_email',
			)
		);

		$templates_ids = apply_filters(
			'wholesalex_email_templates',
			array(
				'wholesalex_new_user_approval_required'  => array(
					'enabled'            => 'yes',
					'subject'            => __('A New user registered and awaiting your approval.','wholesalex'),
					'heading'            => __('A New user registered and awaiting your approval.','wholesalex'),
					'additional_content' => __('We look forward to seeing you soon.','wholesalex'),
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: New User Approval Required', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_new_user_approved'           => array(
					'enabled'            => 'yes',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('Your %s Registration Request Approved','wholesalex'),'{site_title}'),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('Your %s Registration Request Approved','wholesalex'),'{site_title}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Registration Approve', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_new_user_auto_approve'       => array(
					'enabled'            => 'yes',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('Your %s account successfully created!','wholesalex'),'{site_title}'),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('Welcome to %s','wholesalex'),'{site_title}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: New User (Auto Approved)', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_new_user_email_verified'     => array(
					'enabled'            => 'yes',
					'subject'            => __('Congratulations! Your Account is Now Verified and Approved','wholesalex'),
					'heading'            => __('Congratulations! Your Account is Now Verified and Approved','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Email Verified', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_registration_pending'        => array(
					'enabled'            => 'yes',
					'subject'            => __('Registration Request Received','wholesalex'),
					'heading'            => __('Registration Request Received','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Registration Pending For Approval', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_new_user_registered'         => array(
					'enabled'            => 'yes',
					'subject'            => __('A New User Has been registered','wholesalex'),
					'heading'            => __('A New User Has been registered','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: New User Registered', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),
				),
				'wholesalex_registration_rejected'       => array(
					'enabled'            => 'yes',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('Your %s Registration Request Rejected','wholesalex'),'{site_title}'),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('Your %s Registration Request Rejected','wholesalex'),'{site_title}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Registration Rejected', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_new_user_email_verification' => array(
					'enabled'            => 'yes',
					'subject'            => __('Account Registration Confirmation - Action Required','wholesalex'),
					'heading'            => __('Account Registration Confirmation - Action Required','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Email Verification', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_raq_admin_new_quote_request'              => array(
					'enabled'            => 'yes',
					'subject'            => __('You have received a new Quote Request','wholesalex'),
					'heading'            => __('You have received a new Quote Request','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: New Quote Request', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
						'{customer_display_name}'  => __('Customer Name','wholesalex'),
					),

				),
				'wholesalex_raq_make_offer'              => array(
					'enabled'            => 'yes',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('You have received an offer from %s','wholesalex'),'{site_name}'),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('You have received an offer from %s','wholesalex'),'{site_name}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Quote Request Offer', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_wallet_recharge_email'              => array(
					'enabled'            => 'no',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('You’ve received funds!','wholesalex')),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('Your WholesaleX Wallet has been Recharged!','wholesalex')),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Wallet Credit Email', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_wallet_debit_email'              => array(
					'enabled'            => 'no',
					/* translators: %s Site Title */
					'subject'            => sprintf(__('Funds deducted from Your WholesaleX wallet.','wholesalex')),
					/* translators: %s Site Title */
					'heading'            => sprintf(__('Your WholesaleX Wallet has been Debited','wholesalex')),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Wallet Debit Email', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_raq_expiring_offer'          => array(
					'enabled'            => 'yes',
					'subject'            => __('Offer is expiring soon.','wholesalex'),
					'heading'            => __('Offer is expiring soon.','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Quote Request Offer Expiring', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'       => __('Show The Current Date','wholesalex'),
						'{admin_name}' => __('Show Site Admin Name','wholesalex'),
						'{site_name}'  => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_conversation_email'           => array(
					'enabled'            => 'no',
					'subject'            => __('User Send Message Notification','wholesalex'),
					'heading'            => __('User Send Message','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: New Conversation Alert Email', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'             => __('Show Current Date','wholesalex'),
						'{admin_name}'       => __('Show Site Admin Name','wholesalex'),
						'{site_name}'        => __('Show Site Name','wholesalex'),
						'{conversation_email}'  => __('Show Conversation Name','wholesalex'),
						'{conversation_email}' => __('Show Conversation Email','wholesalex'),
					),

				),
				'wholesalex_user_profile_update_notify'           => array(
					'enabled'            => 'no',
					'subject'            => __('User Data Updated by Admin','wholesalex'),
					'heading'            => __('Your Profile Data Was Updated!','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Profile Data Update Email', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'             => __('Show Current Date','wholesalex'),
						'{admin_name}'       => __('Show Site Admin Name','wholesalex'),
						'{site_name}'        => __('Show Site Name','wholesalex'),
					),

				),
				'wholesalex_conversation_reply_email'           => array(
					'enabled'            => 'no',
					'subject'            => __('User reply Message Notification','wholesalex'),
					'heading'            => __('User reply Message to the you','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Reply to Conversation Alert Email ', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'             => __('Show Current Date','wholesalex'),
						'{admin_name}'       => __('Show Site Admin Name','wholesalex'),
						'{site_name}'        => __('Show Site Name','wholesalex'),
						'{conversation_email}'  => __('Show Conversation Name','wholesalex'),
						'{conversation_email}' => __('Show Conversation Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_create'           => array(
					'enabled'            => 'yes',
					'subject'            => __('Subaccount Creation Confirmation','wholesalex'),
					'heading'            => __('Subaccount Creation Confirmation','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Create', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{date}'             => __('Show Current Date','wholesalex'),
						'{admin_name}'       => __('Show Site Admin Name','wholesalex'),
						'{site_name}'        => __('Show Site Name','wholesalex'),
						'{subaccount_name}'  => __('Show Subaccount Name','wholesalex'),
						'{subaccount_email}' => __('Show Subaccount Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_order_approval_require' => array(
					'enabled'            => 'yes',
					'subject'            => __('Approval Require For New Order #{order_number}','wholesalex'),
					'heading'            => __('Approval Require For New Order #{order_number}','wholesalex'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Order Approval Require', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{subaccount_name}'         => __('Show Subaccount Name','wholesalex'),
						'{order_date}'              => __('Show Order Date','wholesalex'),
						'{order_number}'            => __('Show Order Number','wholesalex'),
						'{order_billing_full_name}' => __('Show Billing Full Name','wholesalex'),
						'{view_order}'              => __('Show View Order URL','wholesalex'),
						'{admin_name}'              => __('Show Site Admin Name','wholesalex'),
						'{date}'                    => __('Show Current Date','wholesalex'),
						'{site_name}'               => __('Show Site Name','wholesalex'),
						'{subaccount_email}'        => __('Show Subaccount Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_order_approved'   => array(
					'enabled'            => 'yes',
					/* translators: %s Order Number */
					'subject'            => sprintf(__('Your Order #%s Has Been Approved','wholesalex'),'{order_number}'),
					/* translators: %s Order Number */
					'heading'            => sprintf(__('Your Order #%s Has Been Approved','wholesalex'),'{order_number}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Order Approved', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{subaccount_name}'         => __('Show Subaccount Name','wholesalex'),
						'{order_date}'              => __('Show Order Date','wholesalex'),
						'{order_number}'            => __('Show Order Number','wholesalex'),
						'{order_billing_full_name}' => __('Show Billing Full Name','wholesalex'),
						'{view_order}'              => __('Show View Order URL','wholesalex'),
						'{admin_name}'              => __('Show Site Admin Name','wholesalex'),
						'{date}'                    => __('Show Current Date','wholesalex'),
						'{site_name}'               => __('Show Site Name','wholesalex'),
						'{subaccount_email}'        => __('Show Subaccount Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_order_pending'    => array(
					'enabled'            => 'yes',
					/* translators: %s Order Number */
					'subject'            => sprintf(__('Order #%s Pending For Parent Account Approval','wholesalex'),'{order_number}'),
					/* translators: %s Order Number */
					'heading'            => sprintf(__('Order #%s Pending For Parent Account Approval','wholesalex'),'{order_number}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Order Pending For Parent Approval', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{subaccount_name}'         => __('Show Subaccount Name','wholesalex'),
						'{order_date}'              => __('Show Order Date','wholesalex'),
						'{order_number}'            => __('Show Order Number','wholesalex'),
						'{order_billing_full_name}' => __('Show Billing Full Name','wholesalex'),
						'{view_order}'              => __('Show View Order URL','wholesalex'),
						'{admin_name}'              => __('Show Site Admin Name','wholesalex'),
						'{date}'                    => __('Show Current Date','wholesalex'),
						'{site_name}'               => __('Show Site Name','wholesalex'),
						'{subaccount_email}'        => __('Show Subaccount Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_order_placed'     => array(
					'enabled'            => 'yes',
					/* translators: %s Subaccount Name */
					'subject'            => sprintf(__('An Order Placed By %s','wholesalex'),'{subaccount_name}'),
					/* translators: %s Subaccount Name */
					'heading'            => sprintf(__('An Order Placed By %s','wholesalex'),'{subaccount_name}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Order Placed', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{subaccount_name}'         => __('Show Subaccount Name','wholesalex'),
						'{order_date}'              => __('Show Order Date','wholesalex'),
						'{order_number}'            => __('Show Order Number','wholesalex'),
						'{order_billing_full_name}' => __('Show Billing Full Name','wholesalex'),
						'{view_order}'              => __('Show View Order URL','wholesalex'),
						'{admin_name}'              => __('Show Site Admin Name','wholesalex'),
						'{date}'                    => __('Show Current Date','wholesalex'),
						'{site_name}'               => __('Show Site Name','wholesalex'),
						'{subaccount_email}'        => __('Show Subaccount Email','wholesalex'),
					),

				),
				'wholesalex_subaccount_order_reject'     => array(
					'enabled'            => 'yes',
					/* translators: %s Order Number */
					'subject'            => sprintf(__('Your Order #%s Has Been Rejected','wholesalex'),'{order_number}'),
					/* translators: %s Order Number */
					'heading'            => sprintf(__('Your Order #%s Has Been Rejected','wholesalex'),'{order_number}'),
					'additional_content' => '',
					'email_type'         => 'html',
					/* translators: %s: Plugin Name */
					'title'              => sprintf( __( '%s: Subaccount Order Rejected', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'smart_tags'         => array(
						'{subaccount_name}'         => __('Show Subaccount Name','wholesalex'),
						'{order_date}'              => __('Show Order Date','wholesalex'),
						'{order_number}'            => __('Show Order Number','wholesalex'),
						'{order_billing_full_name}' => __('Show Billing Full Name','wholesalex'),
						'{view_order}'              => __('Show View Order URL','wholesalex'),
						'{admin_name}'              => __('Show Site Admin Name','wholesalex'),
						'{date}'                    => __('Show Current Date','wholesalex'),
						'{site_name}'               => __('Show Site Name','wholesalex'),
						'{subaccount_email}'        => __('Show Subaccount Email','wholesalex'),
					),

				),
			)
		);

		$templates_data = array();

		foreach ( $templates_ids as $template_id => $template ) {
			$template_key_name = 'woocommerce_' . $template_id . '_settings';
			$template_settings = get_option( $template_key_name, $template );

			if ( in_array( $template_id, $pro_template_ids ) ) {
				$template_settings['is_lock'] = ! wholesalex()->is_pro_active();
			}
			if ( in_array( $template_id, $admin_email_template_ids ) ) {
				$template['recipient'] = get_option( 'admin_email' );
			}

			$templates_data[ $template_id ] = wp_parse_args( $template_settings, $template );

		}

		return $templates_data;
	}

	/**
	 * Save Email Template
	 *
	 * @param string $template_name Email Template Name.
	 * @param array  $template Email Tamplate.
	 * @return void
	 */
	public function save_email_template( $template_name, $template ) {
		$saved_templates                   = get_option( '__wholesalex_email_templates', array() );
		$saved_templates[ $template_name ] = $template;
		update_option( '__wholesalex_email_templates', $saved_templates );

	}


	/**
	 * WholesaleX Email Add Submenu Page
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function wholesalex_email_submenu_page() {
		$slug = apply_filters( 'wholesalex_emails_submenu_slug', 'wholesalex-email' );
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__( 'Emails', 'wholesalex' ),
			__( 'Emails', 'wholesalex' ),
			apply_filters( 'wholesalex_capability_access', 'manage_options' ),
			$slug,
			array( $this, 'email_page_content' )
		);
	}

	/**
	 * Email Page Content
	 *
	 * @return void
	 */
	public function email_page_content() {
		wp_enqueue_script( 'whx_email_templates' );
		wp_enqueue_script( 'wholesalex_node_vendors' );
		wp_enqueue_script( 'wholesalex_components' );
		wp_localize_script('whx_email_templates','whx_email_templates',array(
			'i18n' => array(
				'admin_email_recipient'=> __('Admin Email Recipient','wholesalex'),
				'subject'=> __('Subject','wholesalex'),
				'heading'=> __('Heading','wholesalex'),
				'additional_content'=> __('Additional Content','wholesalex'),
				'smart_tag_used'=> __('Smart Tag Used','wholesalex'),
				'email_type'=> __('Email Type','wholesalex'),
				'smart_tags'=> __('Smart Tags','wholesalex'),
				'save_changes'=> __('Save Changes','wholesalex'),
				'status'=> __('Status','wholesalex'),
				'email_template'=> __('Email Template','wholesalex'),
				'content_type'=> __('Content Type','wholesalex'),
				'action'=> __('Action','wholesalex'),
				'edit'=> __('Edit','wholesalex'),
				'unlock'=> __('UNLOCK','wholesalex'),
				'unlock_full_email_access'=> __('Unlock Full Email Access','wholesalex'),
				'with_wholesalex_pro'=> __('With WholesaleX Pro','wholesalex'),
				'upgrade_pro_message'=> __('We are sorry, but only a limited number of emails are available on the free version. Please upgrade to a pro plan to get full access.','wholesalex'),
				'upgrade_to_pro_btn'=> __('Upgrade to Pro  ➤','wholesalex'),
				'emails'=> __('Emails','wholesalex'),
			)
		));
		?>
			<div id='wholesalex_email_templates_root'></div>
		<?php
	}


	/**
	 * Save WholesaleX Email Status
	 *
	 * @since 1.0.0
	 */
	public function save_wholesalex_email_settings() {
		if ( ! ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wholesalex-registration' ) ) ) {
			die( 'Nonce Verification Faild!' );
		}
        if( !current_user_can( 'manage_options' )) {
            return;
        }
		$__id    = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		$__value = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '';
		if ( ! empty( $__id ) ) {
			update_option( $__id, ( 'true' === $__value ? true : false ) );
		}
		wp_send_json_success( __( 'Success.', 'wholesalex' ) );
	}

	/**
	 * Replace Email Footer Smart Tag
	 *
	 * @param string $string Tag.
	 * @return string
	 */
	public function replace_email_footer_smart_tags( $string ) {
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );

		return str_replace(
			array(
				'{site_title}',
				'{site_address}',
				'{site_url}',
				'{woocommerce}',
				'{WooCommerce}',
			),
			array(
				wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
				$domain,
				$domain,
				'<a href="https://woocommerce.com">WooCommerce</a>',
				'<a href="https://woocommerce.com">WooCommerce</a>',
			),
			$string
		);
	}
}
