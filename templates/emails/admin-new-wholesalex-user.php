<?php
	
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
$user = get_user_by('login', $user_login);
$user_email = $user->user_email; //phpcs:ignore

 ?>

<p>
	<?php esc_html_x( 'You have a new customer registration.','New WholesaleX User Email (Admin)', 'wholesalex');	?>
	<br /><br />
 	<?php esc_html_x( 'Username: ','New WholesaleX User Email (Admin)','wholesalex'); echo esc_html($user_login); ?>
 	<br />
 	<?php esc_html_x( 'Email: ','New WholesaleX User Email (Admin)','wholesalex'); echo esc_html($user_email); ?>
</p>
<?php

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
