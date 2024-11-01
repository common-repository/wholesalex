<?php
	
defined( 'ABSPATH' ) || exit;

$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf(esc_html_x( 'Hi %s,', 'WholesaleX Registration Pending (Customer)' ,'wholesalex' ), esc_html( $user_login ) ); ?></p>
<p><?php printf(esc_html_x('We have received your registration request. Our team will review your information, and you will receive another email once your Request has been approved/rejected.', 'WholesaleX Registration Pending (Customer)','wholesalex')); ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
