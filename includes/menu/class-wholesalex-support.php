<?php
/**
 * WholesaleX Support Page
 *
 * @package WHOLESALEX
 * @since 1.2.20
 */

namespace WHOLESALEX;

/**
 * WholesaleX Support Class
 */
class WholesaleX_Support {
	/**
	 * Constructor
	 */
	public function __construct() {
		/**
		 * Add Feature Submenu Page
		 */
		add_action( 'admin_menu', array( $this, 'wholesalex_support_submenu_page' ) );
		add_action( 'rest_api_init', array( $this, 'support_page_rest_api' ) );

	}

	/**
	 * WholesaleX Support Add Submenu Page
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function wholesalex_support_submenu_page() {
		$slug                = apply_filters( 'wholesalex_support_submenu_slug', 'wholesalex-support' );
		$is_features_enabled = apply_filters( 'wholesalex_support_menu_page_enabled', true );
		if ( $is_features_enabled ) {
			add_submenu_page(
				wholesalex()->get_menu_slug(),
				__( 'Quick Support', 'wholesalex' ),
				__( 'Quick Support', 'wholesalex' ),
				apply_filters( 'wholesalex_capability_access', 'manage_options' ),
				$slug,
				array( $this, 'support_page_content' )
			);
		}
	}

    public function support_page_rest_api() {
		register_rest_route(
			'wholesalex/v1',
			'/support/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'support_action_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}
	
	public function support_action_callback( $server ) {
	
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key($post['nonce']), 'wholesalex-registration' ) ) ) {
			return;
		}
        if (isset($post['type'])) {
            switch ($post['type']) {
                case 'support_data':
                    $user_info = get_userdata( get_current_user_id() );
                    $name = $user_info->first_name . ($user_info->last_name ? ' ' . $user_info->last_name : '');
                    return array(
                        'success' => true,
                        'data' => array(
                            'name' => $name ? $name : $user_info->user_login,
                            'email' => $user_info->user_email
                        )
                    );

                case 'support_action':
                    $api_params = array(
                        'user_name' => sanitize_text_field($post['name']),
                        'user_email' => sanitize_email($post['email']),
                        'subject' => sanitize_text_field($post['subject']),
                        'desc' => sanitize_textarea_field($post['desc']),
                    );
                    $response = wp_remote_get(
                        'https://wpxpo.com/wp-json/v2/support_mail',
                        array(
                            'method' => 'POST',
                            'timeout' => 120,
                            'body' =>  $api_params
                        )
                    );
                    $response_data = json_decode($response['body']);
                    $success = ( isset($response_data->success) && $response_data->success ) ? true : false;

                    return array(
                        'success' => $success,
                        'message' => $success ? __('New Support Ticket has been Created.', 'wholesalex') : __('New Support Ticket is not Created Due to Some Issues.', 'wholesalex')
                    );
                    break;


                default:
                    # code...
                    break;
            }
        }
	}

	/**
	 * Feature Page Content
	 *
	 * @return void
	 */
	public function support_page_content() {
		wp_enqueue_script( 'whx_support' );
		wp_enqueue_script( 'wholesalex_node_vendors' );
		wp_enqueue_script( 'wholesalex_components' );

		wp_localize_script('whx_support','whx_support',array(
			'i18n' => array(
				'quick_support' => __('Quick Support','wholesalex'),
				'technical_support' => __('Technical Support','wholesalex'),
				'free_support' => __('Free Support (WordPress ORG)','wholesalex'),
				'presale_question' => __('Presale Questions','wholesalex'),
				'license_activation_issue' => __('License Activation Issues','wholesalex'),
				'bug_report' => __('Bug Report','wholesalex'),
				'compatibility_issue' => __('Compatibility Issues','wholesalex'),
				'feature_request' => __('Feature Request','wholesalex'),
				'getting_started_with_wholesalex' => __('Getting Started with WholesaleX','wholesalex'),
				'dynamic_pricing_n_discount_rules' => __('Dynamic Pricing & Discount Rules','wholesalex'),
				'wholesale_user_roles' => __('Wholesale User Roles','wholesalex'),
				'regi_form_builder' => __('Registration Form Builder','wholesalex'),
				'wholesalex_addons' => __('WholeasaleX Addons','wholesalex'),
				'how_to_create_private_store' => __('How to Create a Private Store','wholesalex'),
				'please_select_support_type' => __('Please Select Support type.','wholesalex'),
				'please_fill_all_the_input_field' => __('Please Fill all the Input Field..','wholesalex'),
				'having_difficulties' => __('Having Difficulties? We are here to help','wholesalex'),
				'let_us_know' => __('Let us know how we can help you.','wholesalex'),
				'pro' => __('(Pro)','wholesalex'),
				'create_a_ticket' => __('Create a Ticket','wholesalex'),
				'select_support_type' => __('Select Support Type from above','wholesalex'),
				'name' => __('Name','wholesalex'),
				'email' => __('Email','wholesalex'),
				'subject' => __('Subject','wholesalex'),
				'desc' => __('Description','wholesalex'),
				'you_can_contact_in_support' => __('You can Contact in Support via our ','wholesalex'),
				'contact_form' => __('Contact Form','wholesalex'),
				'submit_ticket' => __('Submit Ticket','wholesalex'),
				'wholesalex_community' => __('WowCommerce Community','wholesalex'),
				'join_community' => __('Join Community','wholesalex'),
				'useful_guides' => __('Useful Guides','wholesalex'),
				'check_out_in_depth_docs' => __('Check out the in depth documentation','wholesalex'),
				'explore_docs' => __('Explore Docs','wholesalex'),
				'join_wholesalex_community_msg' => __('Join the Facebook community of WholesaleX to stay up-to-date and share your thoughts and feedback.','wholesalex'),
			)
		));
		?>
			<div id="wholesalex_support_page"> </div>
		<?php
	}
}
