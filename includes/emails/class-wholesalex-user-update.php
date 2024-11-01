<?php
/**
 * Class Wholesalex User Profile Update Notification.
 *
 * @package WHOLESALEX
 * @since 1.2.3
 */

namespace WHOLESALEX;

use WC_Email;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Customer New Account.
 *
 * An email sent to the Wholesalex customer when Admin Change User Profile Data.
 *
 * @extends     WC_Email
 */
class WholesaleX_User_Profile_Update_Notification_Email extends WC_Email {

	/**
	 * User login name.
	 *
	 * @var string
	 */
	public $user_login;

	/**
	 * User email.
	 *
	 * @var string
	 */
	public $user_email;

	/**
	 * User password.
	 *
	 * @var string
	 */
	public $updated_data;

	/**
	 * Is the password generated?
	 *
	 * @var bool
	 */
	public $password_generated;

	/**
	 * Magic link to set initial password.
	 *
	 * @var string
	 */
	public $set_password_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id            = 'wholesalex_user_profile_update_notify';
		$this->title         = __( 'WholesaleX: Profile Data Update Email', 'wholesalex' );
		$this->description   = __( 'WhoesaleX: User Get a Notification When Admin Can Changed Any User Data and User Can See The Changes.', 'wholesalex' );
		$this->template_base = WHOLESALEX_PATH . 'templates/emails/';
		$this->template_html = 'wholesalex-user-update_notify.php';
		$this->placeholders  = apply_filters( 'wholesalex_email_new_user_registered_smart_tags', wholesalex()->smart_tag_name('{date}', '{admin_name}', '{site_name}') );

		// Call parent constructor.
		parent::__construct();
		// $this->recipient = '';
		add_action( 'wholesalex_user_profile_update_notify_notification', array( $this, 'user_profile_update_notify' ), 10, 2 );
	}

    function user_profile_update_notify( $user_id, $updated_data ) {
    $updated_dated = apply_filters( 'wholesalex_profile_updated_data', $updated_data, $user_id );
        if( $user_id && !empty( $updated_dated ) ) {
            $this->trigger( $user_id, $updated_dated );
        }
    }
	/**
	 * Get email subject.
	 *
	 * @since  1.2.3
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'User Data Updated by Admin', 'wholesalex' );
	}

	/**
	 * Get email heading.
	 *
	 * @since  1.2.3
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your Profile Data Was Updated!', 'wholesalex' );
	}

	/**
	 * Trigger.
	 *
	 * @param int    $user_id User ID.
	 * @param string $user_pass User password.
	 * @param bool   $password_generated Whether the password was generated automatically or not.
	 */
	public function trigger( $user_id, $updated_data = '' ) {
		$this->setup_locale();

		if ( $user_id ) {
            $user = get_userdata($user_id);
			$this->object = new WP_User( $user_id );
            $this->recipient     = $user->user_email;
			$this->updated_data  = $updated_data;
			$this->user_login = stripslashes( $this->object->user_login );
			$this->user_email = stripslashes( $this->object->user_email );
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'user_login'         => $this->user_login,
				'updated_data'       => $this->updated_data,
				'blogname'           => $this->get_blogname(),
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
				'set_password_url'   => $this->set_password_url,
			),
			$this->template_base,
			$this->template_base
		);
	}


	/**
	 * Default content to show below main email content.
	 *
	 * @since 1.2.3
	 * @return string
	 */
	public function get_default_additional_content() {
		return '';
	}

	/**
	 * Initialise Settings Form Fields - these are generic email options most will use.
	 */
	public function init_form_fields() {
		/* translators: %s: list of placeholders */
		$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'wholesalex' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'wholesalex' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'wholesalex' ),
				'default' => 'no',
			),
			'recipient'          => array(
				'title'       => esc_html__( 'Recipient(s)', 'wholesalex' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter recipients (comma separated) for this email. Defaults to', 'wholesalex' ) . sprintf( '<code>%s</code>.', esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => esc_attr( get_option( 'admin_email' ) ),
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'wholesalex' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => 'This control the email subject. To change default email subject, makes changes here.',
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'wholesalex' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => 'This control the email heading. To change default email heading, makes changes here.',
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'wholesalex' ),
				'description' => __( 'Text to appear below the main email content.', 'wholesalex' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'wholesalex' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'wholesalex' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'wholesalex' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => array(
					'html'      => __( 'HTML', 'wholesalex' ),
					'multipart' => __( 'Multipart', 'wholesalex' ),
				),
				'desc_tip'    => true,
			),
		);
	}
}



return new WholesaleX_User_Profile_Update_Notification_Email();
