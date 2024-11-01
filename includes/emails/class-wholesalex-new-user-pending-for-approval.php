<?php
/**
 * Class wholesalex new user Registration admin notification file.
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
 * An email sent to the wholesalex customer when they create an account.
 *
 * @extends     WC_Email
 */
class WholesaleX_New_User_Pending_For_Approval_Email extends WC_Email {

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
	public $user_pass;

	/**
	 * Is the password generated?
	 *
	 * @var bool
	 */
	public $password_generated;

	/**
	 * Magic link to verify email.
	 *
	 * @var string
	 */
	public $confirmation_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->customer_email = true;
		$this->id             = 'wholesalex_registration_pending';
		$this->title          = __( 'WholesaleX: Registration Pending For Approval', 'wholesalex' );
		$this->description    = __( 'WholesaleX: Registration Pending For Approval emails are sent to the users when a customer signs up via wholesalex registration from and user status set to approval required.', 'wholesalex' );
		$this->template_base  = WHOLESALEX_PATH . 'templates/emails/';
		$this->template_html  = 'wholesalex-registration-pending.php';
		$this->placeholders   = apply_filters( 'wholesalex_registration_pending_email_smart_tags', wholesalex()->smart_tag_name('{date}', '{admin_name}', '{site_name}') );

		// Call parent constructor.
		parent::__construct();
		// $this->recipient = '';
		add_action( 'wholesalex_registration_form_user_status_admin_approve_notification', array( $this, 'trigger' ), 10, 3 );
	}

	/**
	 * Get email subject.
	 *
	 * @since  1.2.3
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Registration Request Received', 'wholesalex' );
	}

	/**
	 * Get email heading.
	 *
	 * @since  1.2.3
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Registration Request Received', 'wholesalex' );
	}

	/**
	 * Trigger.
	 *
	 * @param int    $user_id User ID.
	 * @param string $user_pass User password.
	 * @param bool   $password_generated Whether the password was generated automatically or not.
	 */
	public function trigger( $user_id, $user_pass = '', $password_generated = false ) {

		$this->setup_locale();

		if ( $user_id ) {

			$this->object = new WP_User( $user_id );

			$this->user_pass  = '';
			$this->user_login = stripslashes( $this->object->user_login );
			$this->user_email = stripslashes( $this->object->user_email );
			$this->recipient  = $this->user_email;
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
				'user_pass'          => $this->user_pass,
				'blogname'           => $this->get_blogname(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
				'confirmation_url'   => $this->confirmation_url,
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
				'default' => 'yes',
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



return new WholesaleX_New_User_Pending_For_Approval_Email();
