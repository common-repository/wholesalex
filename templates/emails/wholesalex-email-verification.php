<?php
	
defined( 'ABSPATH' ) || exit;

$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html_x( 'Hi %s,', 'WholesaleX Email Verification Email (Customer)' ,'wholesalex' ), esc_html( $user_login ) ); ?></p>
<p><?php printf(esc_html_x('To complete your registration, please click the link below to confirm your email address.','WholesaleX Email Verification Email (Customer)','wholesalex')); ?>
<p><?php printf( '%s',make_clickable( esc_url( $confirmation_url ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
<p><?php printf(esc_html_x("By confirming your Email, you will gain full access to your account and enjoy all our platform's benefits",'WholesaleX Email Verification Email (Customer)','wholesalex')); ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
