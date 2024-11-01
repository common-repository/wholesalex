<?php
/**
 * Admin Action.
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Notice Class
 */
class WHOLESALEX_Notice {

	/**
	 * Contains Notice Version
	 *
	 * @var string
	 */
	private $notice_version = 'v9';

	/**
	 * Contains Notice type
	 *
	 * @var string
	 */
	private $type = '';
	/**
	 * Contains notice is force or not
	 *
	 * @var string
	 */
	private $force = '';
	/**
	 * Contain Notice content.
	 *
	 * @var string
	 */
	private $content = '';

	private $heading = '';

	private $subheading = '';

	private $days_remaining = '';

	private $available_notice = array();

	private $price_id = false;

	/**
	 * Admin WooCommerce Installation Notice Action
	 *
	 * @since 1.0.0
	 */
	public function install_notice() {
		add_action( 'admin_notices', array( $this, 'wc_installation_notice_callback' ) );
		add_action('wp_ajax_wsx_install_woocommerce_plugin', array( $this, 'wsx_install_woocommerce_plugin' ) );
	}


	/**
	 * Admin WooCommerce Activation Notice Action
	 *
	 * @since 1.0.0
	 */
	public function active_notice() {
		add_action( 'admin_notices', array( $this, 'wc_activation_notice_callback' ) );
		add_action('wp_ajax_wsx_activate_woocommerce_plugin', array( $this, 'wsx_activate_woocommerce_plugin' ) );
	}

	/**
	 * Promotional Notice Callback
	 *
	 * @since 1.0.0
	 */
	public function promotion() {
		add_action( 'admin_init', array( $this, 'notice_callback' ) );
		add_action( 'admin_notices', array( $this, 'display_notices' ), 0 );
	}
	
	public function display_notices() {

		usort( $this->available_notice, array( $this, 'sort_notices' ) );
		$displayed_notice_count = 0;

		foreach ( $this->available_notice as $notice ) {
			if ( $this->is_valid_notice( $notice ) ) {
				if ( isset( $notice['show_if'] ) && true === $notice['show_if'] ) {
					if ( 0 !== $displayed_notice_count && false === $notice['display_with_other_notice'] ) {
						continue;
					}
					if ( isset( $notice['id'], $notice['design_type'] ) ) {
						echo $this->get_notice_content( $notice['id'], $notice['design_type'] );

						++$displayed_notice_count;
					}
				}
			}
		}
	}
	
	private function get_notice_by_id( $id ) {
		if ( isset( $this->available_notice[ $id ] ) ) {
			return $this->available_notice[ $id ];
		}
	}

	/**
	 * WooCommerce Installation Notice
	 *
	 * @since 1.0.0
	 */
	public function wc_installation_notice_callback() {
		if ( ! get_option( 'wholesalex_dismiss_notice' ) ) {
			$this->wc_notice_css();
			?>
			<style>
				.spinner {
					display: inline-block;
					width: 20px;
					height: 20px;
					border: 2px solid rgba(0, 0, 0, 0.1);
					border-left-color: #000;
					border-radius: 50%;
					animation: spin 1s linear infinite;
					margin-left: 10px;
					vertical-align: middle;
				}
				
				@keyframes spin {
					to {
						transform: rotate(360deg);
					}
				}
				
				.installing #install-woocommerce,
				.activating #install-woocommerce {
					pointer-events: none;
					opacity: 0.6;
				}
			</style>
			<div class="wholesalex-wc-install">
				<img loading="lazy" width="200" src="<?php echo esc_url( WHOLESALEX_URL . 'assets/img/woocommerce.png' ); ?>" alt="logo" />
				<div class="wholesalex-wc-install-body">
					<h3><?php /* translators: %s: Plugin Name */ echo sprintf( esc_html__( 'Welcome to %s.', 'wholesalex' ), esc_html( wholesalex()->get_plugin_name() ) ); ?></h3>
					<p><?php /* translators: %s: Plugin Name */ echo sprintf( esc_html__( 'WooCommerce %s is a WooCommerce plugin. To use this plugins you have to install and activate WooCommerce.', 'wholesalex' ), esc_html( wholesalex()->get_plugin_name() ) ); ?></p>
					<p><button class="wholesalex-wc-install-btn button button-primary button-hero" id="wholesalex-install-woocommerce" class="button button-primary"> <?php esc_html_e( 'Install WooCommerce', 'wholesalex' ); ?> <span class="spinner" style="display:none;"></span></button></p>
					<div id="installation-msg"></div>
				</div>
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wholesalex-install-woocommerce').on('click', function(e) {
					e.preventDefault();
					var $button = $(this);
					$button.addClass('installing');
					$button.find('.spinner').show();
					$button.text('<?php esc_html_e( 'Installing...', 'wholesalex' ); ?>').append('<span class="spinner"></span>');
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wsx_install_woocommerce_plugin',
							_ajax_nonce: '<?php  echo esc_attr( wp_create_nonce( "install_woocommerce" ) ); ?>'// phpcs:ignore
						},
						success: function(response) {
							if (response.success) {
								$button.removeClass('installing').addClass('activating');
								$button.text('<?php esc_html_e( 'Activating...', 'wholesalex' ); ?>').append('<span class="spinner"></span>');
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'wsx_activate_woocommerce_plugin',
										_ajax_nonce: '<?php echo esc_attr( wp_create_nonce( "activate_woocommerce" ) ); ?>'// phpcs:ignore
									},
									success: function(response) {
										$button.removeClass('activating');
										$button.find('.spinner').hide();
										if (response.success) {
											//location.reload();
											window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wholesalex-overview' ) ); ?>';
										} else {
											console.log(response.data);
										}
									},
									error: function() {
										$button.removeClass('activating');
										$button.find('.spinner').hide();
										console.log('There was an error activating WooCommerce.');
									}
								});
							} else {
								$button.removeClass('installing');
								$button.find('.spinner').hide();
								console.log( response.data );
							}
						},
						error: function() {
							$button.removeClass('installing');
							$button.find('.spinner').hide();
							console.log('There was an error installing WooCommerce.');
						}
					});
				});
			});
			</script>
			
			<?php
		}
	}


	/**
	 * Install Woo Plugin With Ajax
	 *
	 * @return void
	 */
	public function wsx_install_woocommerce_plugin() {
		check_ajax_referer('install_woocommerce', '_ajax_nonce');
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to install plugins.', 'wholesalex' ) );
		}
	
		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		$plugin_slug = 'woocommerce';
		$api = plugins_api( 'plugin_information', array(
			'slug' => $plugin_slug,
			'fields' => array(
				'sections' => false,
			),
		) );
	
		if ( is_wp_error( $api ) ) {
			wp_send_json_error( $api->get_error_message() );
		}
		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result = $upgrader->install( $api->download_link );
	
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		if ( ! $result ) {
			wp_send_json_error( __( 'Plugin installation failed.', 'wholesalex' ) );
		}
		wp_send_json_success();
	}
	
	/**
	 * After Install Woo Automatic Activated Woo Plugin
	 *
	 * @return void
	 */
	public function wsx_activate_woocommerce_plugin() {
		check_ajax_referer('activate_woocommerce', '_ajax_nonce');
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to activate plugins.', 'wholesalex' ) );
		}
		$result = activate_plugin('woocommerce/woocommerce.php');
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success();
	}


	/**
	 * WooCommerce Activation Notice
	 *
	 * @since 1.0.0
	 */
	public function wc_activation_notice_callback() {
		if ( ! get_option( 'wholesalex_dismiss_notice' ) ) {
			$this->wc_notice_css();
			?>
			<div class="wholesalex-wc-install">
				<img loading="lazy" width="200" src="<?php echo esc_url( WHOLESALEX_URL . 'assets/img/woocommerce.png' ); ?>" alt="logo" />
				<div class="wholesalex-wc-install-body">
				<h3><?php /* translators: %s: Plugin Name */ echo sprintf( esc_html__( 'Welcome to %s.', 'wholesalex' ), esc_html( wholesalex()->get_plugin_name() ) ); ?></h3>
				<p><?php /* translators: %s: Plugin Name */ echo sprintf( esc_html__( 'WooCommerce %s is a WooCommerce plugin. To use this plugins you have to install and activate WooCommerce.', 'wholesalex' ), esc_html( wholesalex()->get_plugin_name() ) ); ?></p>
				<p><button class="wholesalex-wc-install-btn button button-primary button-hero" id="wholesalex-activate-woocommerce" class="button button-primary"> <?php esc_html_e( 'Activate WooCommerce', 'wholesalex' ); ?> <span class="spinner" style="display:none;"></span></button></p>
				</div>
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wholesalex-activate-woocommerce').on('click', function(e) {
					e.preventDefault();
					let $button = $(this);
					$button.removeClass('installing').addClass('activating');
					$button.text('<?php esc_html_e( 'Activating...', 'wholesalex' ); ?>').append('<span class="spinner"></span>');
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wsx_activate_woocommerce_plugin',
							_ajax_nonce: '<?php echo esc_attr( wp_create_nonce( 'activate_woocommerce' ) ); ?>'
						},
						success: function(response) {
							$button.removeClass('activating');
							$button.find('.spinner').hide();
							if (response.success) {
								window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wholesalex-overview' ) ); ?>';
							} else {
								console.log( response.data );
							}
						},
						error: function() {
							$button.removeClass('activating');
							$button.find('.spinner').hide();
							console.log('There was an error activating WooCommerce.');
						}
					});
				});
			});
			</script>

			<?php
		}
	}
	public function notice_callback() {
		$this->price_id         = $this->get_price_id();
		$activate_date          = get_option( 'wholesalex_installation_date', false );
		$this->available_notice = array(
			// Free to Pro
			'wsx_free_promo_hwllo_Sale12'        => $this->set_new_notice( 'wsx_free_promo_hwllo_Sale12', 'promotion', 'summer_promotion_new_discount_40', '21-10-2024', '28-10-2024', false, 10, ! wholesalex()->is_pro_active() ),
			'wsx_free_promo_hwllo_Sale123'       => $this->set_new_notice( 'wsx_free_promo_hwllo_Sale123', 'promotion', 'summer_promotion_new_discount_402', '29-10-2024', '02-11-2024', false, 10, ! wholesalex()->is_pro_active() ),
		);

		if ( isset( $_GET['wsx-notice-disable'] ) ) {//phpcs:ignore
			$notice_key = sanitize_text_field( $_GET['wsx-notice-disable'] );//phpcs:ignore
			$notice     = $this->get_notice_by_id( $notice_key );
			if ( 'data_collect' == $notice['type'] ) {
				if ( isset( $notice['repeat_notice_after'] ) && $notice['repeat_notice_after'] ) {
					$repeat_timestamp = ( DAY_IN_SECONDS * intval( $notice['repeat_notice_after'] ) );
					$this->set_notice( $notice_key, 'off', $repeat_timestamp );
				}
			} else {
				if ( isset( $notice['repeat_notice_after'] ) && $notice['repeat_notice_after'] ) {
					$repeat_timestamp = time() + ( DAY_IN_SECONDS * intval( $notice['repeat_notice_after'] ) );
					$this->set_user_notice_meta( $notice_key, 'off', $repeat_timestamp );
				} else {
					$this->set_user_notice_meta( $notice_key, 'off', false );
				}
			}
		}
	}

	public function get_notice_content( $key, $design_type ) {

		$close_url = add_query_arg( 'wsx-notice-disable', $key );

		switch ( $design_type ) {
			case 'summer_promotion_new_discount_40':
				//
				// Will Get Free User
				$icon        = WHOLESALEX_URL . 'assets/img/icon.svg';
				$url         = 'https://getwholesalex.com/pricing/?utm_source=wholesalex_topbar&utm_medium=special_discount_pro&utm_campaign=wholesalex-DB';
				$full_access = 'https://getwholesalex.com';

				ob_start();
				?>
				<div class="wsx-display-block">
				<div class="wsx-notice-wrapper wsx-notice-type-1 notice"> 
					<div class="wsx-notice-icon"> <img src="<?php echo esc_url( $icon ); ?>"/>  </div>
					<div class="wsx-notice-content-wrapper">
					<div class="wsx-notice-content"> <strong> Halloween Sale </strong> is LIVE! Boost Your Wholesale Business with up to <strong>60% OFF </strong> on <strong> WholesaleX </strong></div>
					<div class="wsx-notice-buttons"> 
						<a class="wsx-notice-btn button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank"> Upgrade to Pro   </a>
						<a class="wsx-notice-btn button" href="<?php echo esc_url( $full_access ); ?>" target="_blank">  Explore WholesaleX  </a>
						<a href="<?php echo esc_url( $close_url ); ?>" class="wsx-notice-dont-save-money">   I Don’t Want To Save Money </a>
					</div>
					</div>
					<a href="<?php echo esc_url( $close_url ); ?>" class="wsx-notice-close"><span class="wsx-notice-close-icon dashicons dashicons-dismiss"> </span></a>
				</div>
				</div>
				<?php
				return ob_get_clean();
				// code...
				break;
				case 'summer_promotion_new_discount_402':
				$icon        = WHOLESALEX_URL . 'assets/img/halloween_banner_offer.jpg';
				$url         = 'https://getwholesalex.com/pricing/?utm_source=wholesalex_topbar&utm_medium=special_discount_pro&utm_campaign=wholesalex-DB';
				ob_start();
					?>
					<div class="wsx-display-block">
					<div class="wsx-notice-wrapper notice">
                    <div class="wsx-install-body wsx-image-banner">
                        <a href="<?php echo esc_url( $close_url ); ?>" class="promotional-dismiss-notice">
                            <?php esc_html_e( 'Dismiss', 'wholesalex' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                            <img class="wsx-halloween-img-banner" src="<?php echo $icon; ?>" alt="Banner">
                        </a>
                    </div>
                </div>
                </div>
				<?php
				return ob_get_clean();
				break;
			default:
				// code...
				break;
		}
		return '';

	}

	/**
	 * WooCommerce Notice Styles
	 *
	 * @since 1.0.0
	 */
	public function wc_notice_css() {
		?>
		<style type="text/css">
			.button.activated-message:before, .button.activating-message:before, .button.installed:before, .button.installing:before, .button.updated-message:before, .button.updating-message:before {
				margin: 12px 5px 0 -2px;
			}
			.button.activating-message:before, .button.installing:before, .button.updating-message:before, .import-php .updating-message:before, .update-message p:before, .updating-message p:before {
				color: #ffffff;
				content: "\f463";
			}
			/* .wholesalex-wc-install.wholesalex-pro-notice-v2 {
				padding-bottom: 0px;
			} */
			.wholesalex-content-notice {
				color: white;
				background-color: #6C6CFF;
				position: relative;
				font-size: 16px;
				padding-left: 10px;
				line-height: 23px;
			}

			.wholesalex-notice-content-wrapper {
				margin-bottom: 0px !important;
				padding: 10px 5px;
			}

			.wholesalex-wc-install .wholesalex-content-notice .wholesalex-btn-notice-pro {
				margin-left: 5px;
				background-color: #3c3cb7 !important;
				border-radius: 4px;
				max-height: 30px !important;
				padding: 8px 12px !important;
				font-size: 14px;
				position: relative;
				top: -4px;
			}
			.wholesalex-wc-install .wholesalex-content-notice .wholesalex-btn-notice-pro:hover {
				background-color: #29298c !important;
			}

			/* .wholesalex-content-notice .content-notice-dissmiss {
				position: absolute;
				top: 0;
				right: 0;
				color: white;
				background-color: black;
				padding: 5px;
				font-size: 12px;
				line-height: 1;
				border-bottom-left-radius: 5px;
			} */

			/* .whx-new-dismiss{
				position: absolute;
				top: 0;
				right: 0;
				color: white;
				background-color: black;
				padding: 4px 5px 5px;
				font-size: 12px;
				line-height: 1;
				border-bottom-left-radius: 3px;
				text-decoration: none;
			} */

			.wholesalex-content-notice .content-notice-dissmiss {
				position: absolute;
				top: 0;
				right: 0;
				color: white;
				background-color: #3f3fa6;
				padding: 4px 5px 5px;
				font-size: 12px;
				line-height: 1;
				border-bottom-left-radius: 3px;
				text-decoration: none;
			}
			.wholesalex-image-banner-v2{
				padding:0;
			}
			.wholesalex-wc-install {
				display: -ms-flexbox;
				display: flex;
				align-items: center;
				background: #fff;
				margin-top: 40px;
				width: calc(100% - 30px);
				border: 1px solid #ccd0d4;
				border-left: 3px solid #46b450;
				padding: 4px;
				border-radius: 4px;
				gap:20px;
			}   
			.wholesalex-wc-install img {
				margin-right: 10;
				max-width: 12%; 
			}
			.wholesalex-image-banner-v2.wholesalex-wc-install-body{
				position: relative;
			}
			.wholesalex-wc-install-body {
				-ms-flex: 1;
				flex: 1;
			}
			.wholesalex-wc-install-body > div {
				max-width: 450px;
				margin-bottom: 20px;
			}
			.wholesalex-wc-install-body h3 {
				margin-top: 0;
				font-size: 24px;
				margin-bottom: 15px;
			}
			.wholesalex-install-btn {
				margin-top: 15px;
				display: inline-block;
			}
			.wholesalex-wc-install .dashicons{
				display: none;
				animation: dashicons-spin 1s infinite;
				animation-timing-function: linear;
			}
			.wholesalex-wc-install.loading .dashicons {
				display: inline-block;
				margin-top: 12px;
				margin-right: 5px;
			}
			@keyframes dashicons-spin {
				0% {
					transform: rotate( 0deg );
				}
				100% {
					transform: rotate( 360deg );
				}
			}
			.wholesalex-image-banner-v2 .wc-dismiss-notice {
				color: #fff;
				background-color: #000000;
				padding-top: 0px;
				position: absolute;
				right: 0;
				top: 0px;
				padding:5px;
				/* padding: 10px 10px 14px; */
				border-radius: 0 0 0 4px;
				display: inline-block;
				transition: 400ms;
				font-size: 12px;
			}
			.wholesalex-image-banner-v2 .wc-dismiss-notice:focus{
				outline: none;
				box-shadow: unset;
			}
			.wholesalex-btn-image:focus{
				outline: none;
				box-shadow: unset;
			}
			.wc-dismiss-notice {
				position: relative;
				text-decoration: none;
				float: right;
				right: 26px;
			}
			.wc-dismiss-notice .dashicons{
				display: inline-block;
				text-decoration: none;
				animation: none;
			}

			.wholesalex-pro-notice-v2 .wholesalex-wc-install-body h3 {
				font-size: 20px;
				margin-bottom: 5px;
			}
			.wholesalex-pro-notice-v2 .wholesalex-wc-install-body > div {
				max-width: 100%;
				margin-bottom: 10px;
			}
			.wholesalex-pro-notice-v2 .button-hero {
				padding: 8px 14px !important;
				min-height: inherit !important;
				line-height: 1 !important;
				box-shadow: none;
				border: none;
				transition: 400ms;
			}
			.wholesalex-pro-notice-v2 .wholesalex-btn-notice-pro {
				background: #2271b1;
				color: #fff;
			}
			.wholesalex-pro-notice-v2 .wholesalex-btn-notice-pro:hover,
			.wholesalex-pro-notice-v2 .wholesalex-btn-notice-pro:focus {
				background: #185a8f;
			}
			.wholesalex-pro-notice-v2 .button-hero:hover,
			.wholesalex-pro-notice-v2 .button-hero:focus {
				border: none;
				box-shadow: none;
			}
			.wc-dismiss-notice:hover {
				color:red;
			}
			.wc-dismiss-notice .dashicons{
				display: inline-block;
				text-decoration: none;
				animation: none;
				font-size: 16px;
			}
		</style>
		<?php
	}

	public function set_notice( $key = '', $value = '', $expiration = '' ) {
		if ( $key ) {
			$option_name = 'wholesalex_notice';
			$notice_data = wholesalex()->get_option_without_cache( $option_name, array() );
			if ( ! isset( $notice_data ) || ! is_array( $notice_data ) ) {
				$notice_data = array();
			}

			$notice_data[ $key ] = $value;

			if ( $expiration ) {
				$expire_notice_key                 = 'timeout_' . $key;
				$notice_data[ $expire_notice_key ] = time() + $expiration;
			}
			update_option( $option_name, $notice_data );
		}
	}

	public function get_notice( $key = '' ) {
		if ( $key ) {
			$option_name = 'wholesalex_notice';
			$notice_data = wholesalex()->get_option_without_cache( $option_name, array() );
			if ( ! isset( $notice_data ) || ! is_array( $notice_data ) ) {
				return false;
			}

			if ( isset( $notice_data[ $key ] ) ) {
				$expire_notice_key = 'timeout_' . $key;
				if ( isset( $notice_data[ $expire_notice_key ] ) && $notice_data[ $expire_notice_key ] < time() ) {
					unset( $notice_data[ $key ] );
					unset( $notice_data[ $expire_notice_key ] );
					update_option( $option_name, $notice_data );
					return false;
				}
				return $notice_data[ $key ];
			}
		}
		return false;
	}

	/**
	 * Sort the notices based on the given priority of the notice.
	 *
	 * @since 1.5.2
	 * @param array $notice_1 First notice.
	 * @param array $notice_2 Second Notice.
	 * @return array
	 */
	public function sort_notices( $notice_1, $notice_2 ) {
		if ( ! isset( $notice_1['priority'] ) ) {
			$notice_1['priority'] = 10;
		}
		if ( ! isset( $notice_2['priority'] ) ) {
			$notice_2['priority'] = 10;
		}

		return $notice_1['priority'] - $notice_2['priority'];
	}

	private function set_new_notice( $id = '', $type = '', $design_type = '', $start = '', $end = '', $repeat = false, $priority = 10, $show_if = false ) {

		return array(
			'id'                        => $id,
			'type'                      => $type,
			'design_type'               => $design_type,
			'start'                     => $start, // Start Date
			'end'                       => $end, // End Date
			'repeat_notice_after'       => $repeat, // Repeat after how many days
			'priority'                  => $priority, // Notice Priority
			'display_with_other_notice' => false, // Display With Other Notice
			'show_if'                   => $show_if, // Notice Showing Conditions
			'capability'                => 'manage_options', // Capability of users, who can see the notice
		);
	}

	private function get_price_id() {
		if ( wholesalex()->is_pro_active() ) {
			$license_data = get_option( 'edd_wholesalex_license_data', false );
			$license_data = (array) $license_data;
			if ( is_array( $license_data ) && isset( $license_data['price_id'] ) ) {
				return $license_data['price_id'];
			} else {
				return false;
			}
		}
		return false;
	}

	public function is_valid_notice( $notice ) {
		$is_data_collect = isset( $notice['type'] ) && 'data_collect' == $notice['type'];
		$notice_status   = $is_data_collect ? $this->get_notice( $notice['id'] ) : $this->get_user_notice( $notice['id'] );

		if ( ! current_user_can( $notice['capability'] ) || 'off' === $notice_status ) {
			return false;
		}

		$current_time = gmdate( 'U' ); // Todays Data
		// $current_time = 1710493466;
		if ( $current_time > strtotime( $notice['start'] ) && $current_time < strtotime( $notice['end'] ) && isset( $notice['show_if'] ) && true === $notice['show_if'] ) { // Has Duration
			// Now Check Max Duration
			return true;
		}
	}



	public function set_user_notice_meta( $key = '', $value = '', $expiration = '' ) {
		if ( $key ) {
			$user_id     = get_current_user_id();
			$meta_key    = 'wholesalex_notice';
			$notice_data = get_user_meta( $user_id, $meta_key, true );
			if ( ! isset( $notice_data ) || ! is_array( $notice_data ) ) {
				$notice_data = array();
			}

			$notice_data[ $key ] = $value;

			if ( $expiration ) {
				$expire_notice_key                 = 'timeout_' . $key;
				$notice_data[ $expire_notice_key ] = $expiration;
			}

			update_user_meta( $user_id, $meta_key, $notice_data );

		}
	}

	public function get_user_notice( $key = '' ) {
		if ( $key ) {
			$user_id     = get_current_user_id();
			$meta_key    = 'wholesalex_notice';
			$notice_data = get_user_meta( $user_id, $meta_key, true );
			if ( ! isset( $notice_data ) || ! is_array( $notice_data ) ) {
				return false;
			}

			if ( isset( $notice_data[ $key ] ) ) {
				$expire_notice_key = 'timeout_' . $key;
				$current_time      = time();
				if ( isset( $notice_data[ $expire_notice_key ] ) && $notice_data[ $expire_notice_key ] < $current_time ) {
					unset( $notice_data[ $key ] );
					unset( $notice_data[ $expire_notice_key ] );
					update_user_meta( $user_id, $meta_key, $notice_data );
					return false;
				}
				return $notice_data[ $key ];
			}
		}
		return false;
	}
}
