<?php
/**
 * WholesaleX Recaptcha
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

use Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WholesaleX Recaptcha Class
 *
 * @since 1.0.0
 */
class Recaptcha {


	/**
	 * Shortcodes Constructor
	 */
	public function __construct() {
		add_filter( 'wholesalex_register_scripts', array( $this, 'register_recaptcha' ) );
		add_action( 'wholesalex_before_process_user_registration', array( $this, 'add_recaptcha_on_registration_form' ), 10 );
		// add_action( 'wholesalex_before_process_user_login', array( $this, 'add_recaptcha_on_login_form' ), 10 );
		add_action( 'wholesalex_before_process_conversation', array( $this, 'add_recaptcha' ), 10, 1 );
		add_action( 'wholesalex_before_registration_form_render', array( $this, 'add_recaptcha_script' ) );
		add_action( 'wholesalex_conversation_metabox_content', array( $this, 'add_recaptcha_script' ) );
		add_action( 'wholesalex_conversation_metabox_content_account_page', array( $this, 'add_recaptcha_script' ) );
		add_action( 'wholesalex_before_conversation_create', array( $this, 'add_recaptcha' ) );
		add_action( 'wholesalex_before_conversation_reply', array( $this, 'add_recaptcha' ) );
		/**
		 * WooCommerce Login Recaptcha Verification
		 *
		 * @since 1.0.1
		 */
		add_action( 'woocommerce_login_form', array( $this, 'process_recaptcha_script' ) );
		add_filter( 'wp_authenticate_user', array( $this, 'recaptcha_on_wc_login' ) );
	}

	/**
	 * Contains instance of this class.
	 *
	 * @var Recaptcha
	 * @since 1.0.0
	 */

	protected static $_instance = null; //phpcs:ignore


	/**
	 * Recaptcha instance
	 *
	 * @return class object
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Parse recaptcha Response
	 *
	 * @param string $response reCaptcha response token.
	 * @return array
	 * @since 1.0.0
	 */
	private function parse_recaptcha_response( $response ) {
		$secret_key           = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
		$recaptcha_verify_api = sprintf( 'https://www.google.com/recaptcha/api/siteverify?secret=%s&response=%s', $secret_key, $response );
		$response             = (array) wp_remote_get( $recaptcha_verify_api );
		$error                = array(
			'success'     => false,
			'error-codes' => array( 'unknown' ),
		);

		return isset( $response['body'] ) ? json_decode( $response['body'], true ) : $error;
	}

	/**
	 * Recaptcha Error Code
	 *
	 * @param string $code recaptcha error code.
	 * @return string Error Message.
	 * @since 1.0.0
	 */
	private function recaptcha_error_message( $code ) {
		switch ( $code ) {
			case 'missing-input-secret':
				return __( 'The secret parameter is missing.', 'wholesalex' );
			case 'invalid-input-secret':
				return __( 'The secret parameter is invalid or malformed.', 'wholesalex' );
			case 'missing-input-response':
				return __( 'The response parameter is missing.', 'wholesalex' );
			case 'invalid-input-response':
				return __( 'The response parameter is invalid or malformed.', 'wholesalex' );
			case 'bad-request':
				return __( 'The request is invalid or malformed.', 'wholesalex' );
			case 'timeout-or-duplicate':
				return __( 'The response is no longer valid: either is too old or has been used previously.', 'wholesalex' );
			default:
				return __( 'Unknown!', 'wholesalex' );
		}
	}

	/**
	 * WholesaleX Process Recaptcha in user registration form
	 *
	 * @param object $post_data User Input Data.
	 * @throws Exception Recaptcha Error.
	 * @since 1.0.0
	 */
	public function add_recaptcha( $post_data ) {
		$__site_key      = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
		$__secret_key    = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
		$__token         = isset( $post_data['token'] ) ? $post_data['token'] : '';
		$__minimum_score = apply_filters( 'wholesalex_recaptcha_minimum_score_allow', 0.5 );
		if ( isset( $__token ) && $__site_key && $__secret_key ) {
			$parsed_response = $this->parse_recaptcha_response( $__token );
			if ( ! ( isset( $parsed_response['success'] ) && $parsed_response['success'] && $parsed_response['score'] >= $__minimum_score ) ) {
				$error_header = __( 'reCAPTCHA v3:', 'wholesalex' );
				if ( function_exists( 'wc_add_notice' ) && DOING_AJAX ) {
					try {
						wc_add_notice( $error_header . $this->recaptcha_error_message( $parsed_response['error-codes'][0] ), 'error' );
						wp_send_json_error( array( 'messages' => wc_print_notices( true ) ) );
					} catch ( Exception $e ) {
						throw new Exception( 'reCAPTCHA v3 Error!' );
					}
				} else {
					wp_safe_redirect( esc_url_raw( $post_data['_wp_http_referer'] ) );
					exit();
				}
			}
		}
	}

	public function add_recaptcha_on_registration_form(  ) {
		if(isset($_POST['token'],$_POST['wholesalex-registration-nonce']) && wp_verify_nonce(sanitize_key($_POST['wholesalex-registration-nonce']),'wholesalex-registration') ) {
			$data = array(
				'error_messages' => array()
			);
			
			$__site_key      = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
			$__secret_key    = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
			$__token         = sanitize_text_field( $_POST['token']);
			$__minimum_score = apply_filters( 'wholesalex_recaptcha_minimum_score_allow', 0.5 );

			if ( isset( $__token ) && $__site_key && $__secret_key ) {
				$parsed_response = $this->parse_recaptcha_response( $__token );
				if ( ! ( isset( $parsed_response['success'] ) && $parsed_response['success'] && $parsed_response['score'] >= $__minimum_score ) ) {
					$error_header = __( 'reCAPTCHA v3:', 'wholesalex' );
					$data['error_messages']['recaptcha'] = wc_print_notice($error_header . $this->recaptcha_error_message( $parsed_response['error-codes'][0] ),'error',array(),true);
					wp_send_json_success($data);
				}
			}
			
		}
	}

	public function add_recaptcha_on_login_form(  ) {
		if(isset($_POST['token'],$_POST['wholesalex-login-nonce']) && wp_verify_nonce(sanitize_key($_POST['wholesalex-login-nonce']),'wholesalex-login') ) {
			$data = array(
				'error_messages' => array()
			);
			
			$__site_key      = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
			$__secret_key    = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
			$__token         = sanitize_text_field( $_POST['token']);
			$__minimum_score = apply_filters( 'wholesalex_recaptcha_minimum_score_allow', 1 );

			if ( isset( $__token ) && $__site_key && $__secret_key ) {
				$parsed_response = $this->parse_recaptcha_response( $__token );
				if ( ! ( isset( $parsed_response['success'] ) && $parsed_response['success'] && $parsed_response['score'] >= $__minimum_score ) ) {
					$error_header = __( 'reCAPTCHA v3:', 'wholesalex' );
					$data['error_messages']['recaptcha'] = wc_add_notice($error_header . $this->recaptcha_error_message( $parsed_response['error-codes'][0] ),'error');
					wp_send_json_success($data);
				}
			}
			
		}
	}

	/**
	 * Add Recaptcha Script in user registration form
	 *
	 * @since 1.0.0
	 */
	public function add_recaptcha_script() {
		$site_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );

		wp_enqueue_script( 'wholesalex-google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, array(), WHOLESALEX_VER, true );
		wp_add_inline_script( 'wholesalex-google-recaptcha-v3', 'var recaptchaSiteKey=' . wp_json_encode( $site_key ) );
	}

	/**
	 * Register Google Recaptcha Script
	 *
	 * @param array $scripts Scripts Array.
	 * @since 1.0.0
	 */
	public function register_recaptcha( $scripts ) {
		$site_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
		if ( ! $site_key ) {
			return $scripts;
		}
		$scripts['wholesalex-google-recaptcha-v3'] = array(
			'src'       => 'https://www.google.com/recaptcha/api.js?render=' . $site_key,
			'deps'      => array(),
			'ver'       => WHOLESALEX_VER,
			'in_footer' => true,
		);

		return $scripts;
	}

	/**
	 * Process Recaptcha on WooCommerce Login
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
	 *                                   callback failed authentication.
	 * @since 1.0.1
	 */
	public function recaptcha_on_wc_login( $user ) {
		$__site_key      = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
		$__secret_key    = wholesalex()->get_setting( '_settings_google_recaptcha_v3_secret_key' );
		$__token         = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : ''; //phpcs:ignore
		$__minimum_score = apply_filters( 'wholesalex_recaptcha_minimum_score_allow', 0.5 );
		
		if ( ! empty( $__token ) && $__site_key && $__secret_key ) {
			$parsed_response = $this->parse_recaptcha_response( $__token );

			if ( ! ( isset( $parsed_response['success'] ) && $parsed_response['success'] && $parsed_response['score'] >= $__minimum_score ) ) {
				
				return new WP_Error(
					'recaptcha_error',
					__( '<strong>reCAPTCHA v3:</strong> Error!', 'wholesalex' )
				);
			}
		}

		return $user;
	}

	/**
	 * Process Recaptcha Script
	 */
	public function process_recaptcha_script() {
		$this->add_recaptcha_script();
		if ( 'yes' === wholesalex()->get_setting( 'wsx_addon_recaptcha' ) ) {
			$__site_key = wholesalex()->get_setting( '_settings_google_recaptcha_v3_site_key' );
			?>
			<script type="text/javascript">
				(function($) {
					$(document).ready(function() {
						$("form.woocommerce-form.woocommerce-form-login.login").submit(function(e) {
							e.preventDefault();
							let curState = this;
							if(typeof grecaptcha !== 'undefined') {
								grecaptcha.ready(function() {
								try {
									grecaptcha
									.execute('<?php echo esc_html( $__site_key ); ?>', {
										action: "submit",
									})
									.then(function(token) {
										$("<input>")
											.attr({
												name: "token",
												id: "token",
												type: "hidden",
												value: token,
											})
											.appendTo("form");
										$("<input>")
											.attr({
												name: "login",
												id: "login",
												type: "hidden",
												value: 'Login',
											})
											.appendTo("form");
										curState.submit();
									});
								} catch (error) {

								}
							});
							}
						});
					});
				})(jQuery);
			</script>
			<?php
		}
	}
}
