<?php
	
defined( 'ABSPATH' ) || exit;

$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html_x( 'Hi %s,', 'WholesaleX Registration Approved Email (Customer)' ,'wholesalex' ), esc_html( $user_login ) ); ?></p>
<p><?php printf(esc_html_x('Congratulations! We are thrilled to inform you that your registration request has been successfully reviewed and approved.','WholesaleX Registration Approved Email (Customer)','wholesalex')); ?>
<p><?php printf(esc_html_x('You can log in using your provided credentials and start exploring all the exciting features and benefits available to you.','WholesaleX Registration Approved Email (Customer)','wholesalex')); ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
