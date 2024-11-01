<?php
	
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore


 ?>

<p>
    <p><?php printf( esc_html_x( 'Hi,','WholesaleX Registration Pending(Admin) Email', 'wholesalex' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></p>
    <?php /* translators: 1: User Profile URL, 2: Username */ ?>
    <p><?php printf(_x('A new user, <a href="%1$s">%2$s</a>, has registered and is awaiting your approval to access our platform.','WholesaleX Registration Pending(Admin) Email','wholesalex'),admin_url('user-edit.php?user_id=' . $user->ID), $user_login); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
    <p><?php printf( esc_html_x('Please review the registration details and take the necessary steps to grant access to the new User.','WholesaleX Registration Pending(Admin) Email','wholesalex' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

</p>
<?php

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
