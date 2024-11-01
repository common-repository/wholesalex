<?php
/**
 * WholesaleX Email Manager
 *
 * @package WHOLELSALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Email Manager
 *
 * @since 1.0.0
 */
class WHOLESALEX_Email_Manager {

	/**
	 * It contain Email Templates.
	 *
	 * @var array
	 */
	public $email_templates = '';


	/**
	 * Constructor
	 */
	public function __construct() {
		$this->email_templates = get_option( '__wholesalex_email_templates', array() );
	}

	/**
	 * Return Super Admin Display Name
	 */
	public static function get_admin_display() {
		$super_admin = '';
		$super       = get_super_admins();
		if ( isset( $super[0] ) ) {
			$super       = get_user_by( 'login', $super[0] );
			$super_admin = isset( $super->data->display_name ) ? $super->data->display_name : '';
		}
		return $super_admin;
	}

}
