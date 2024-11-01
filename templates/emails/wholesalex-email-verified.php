<?php
	
defined( 'ABSPATH' ) || exit;

$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html_x( 'Hi %s,', 'WholesaleX Email Verified Email (Customer)' ,'wholesalex' ), esc_html( $user_login ) ); ?></p>
<p><?php printf(esc_html_x('We are excited to inform you that your Email has been successfully verified, and your account has been approved','WholesaleX Email Verified Email (Customer)','wholesalex')); ?>
<?php /* translators: %1$s: Username, %2$s: My account link */ ?>
<p><?php printf( esc_html_x( 'Your username is %1$s. You can access your account area to view orders, change your password, and more at: %2$s','WholesaleX Email Verified Email (Customer)' ,'wholesalex' ),'<strong>' . esc_html( $user_login ) . '</strong>', make_clickable( esc_url( wc_get_page_permalink( 'myaccount' ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
