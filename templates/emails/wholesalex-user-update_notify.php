<?php
	
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore
$updated_profile_data = implode(", ", $updated_data);

 ?>

<p>
    <?php /* translators: %1$s is a URL, %2$s is a user login name */ ?>
    <p> <?php  printf( 'Hello <a href="%1$s">%2$s</a>', esc_url(admin_url('user-edit.php?user_id=' . $user->ID)), esc_html($user_login) ); ?> </p><!-- phpcs:ignore -->
    <?php /* translators: 1: User Profile URL, 2: Username */ ?>
    <p><?php printf( esc_html_x('Please note that the admin(s) made changes to your User Data: %s','wholesalex' ), $updated_profile_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
    <p><?php printf(esc_html_x('Please review these changes immediately.','wholesalex')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

</p>
<?php

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
