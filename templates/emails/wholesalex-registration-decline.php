<?php

defined( 'ABSPATH' ) || exit;

$user       = get_user_by( 'login', $user_login );
$user_email = $user->user_email; //phpcs:ignore
$site_name  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html_x( 'Hi %s,', 'WholesaleX Registration Decline Email (Customer)' ,'wholesalex' ), esc_html( $user_login ) ); ?></p>
<?php /* translators: %s: Site Name */ ?>
<p><?php printf( esc_html_x( 'Thank you for the registration request on %s. Unfortunately, your registration request has been declined.', 'WholesaleX Registration Decline Email (Customer)', 'wholesalex' ), esc_html( $site_name ) ); ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
