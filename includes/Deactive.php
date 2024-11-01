<?php
/**
 * Deactivation Action.
 * 
 * @package WHOLESALEX
 * @since v.1.4.3
 */
namespace WHOLESALEX;

defined( 'ABSPATH' ) || exit;


/**
 * Deactive class.
 */
class Deactive {

	public static $PLUGIN_NAME = 'WholesaleX';
	public static $PLUGIN_SLUG = 'wholesalex';
	public static $PLUGIN_VERSION = WHOLESALEX_VER;
    public static $API_ENDPOINT = 'https://inside.wpxpo.com';
    
    public function __construct() {
		global $pagenow;
        if ( $pagenow == 'plugins.php' ) {
			add_action( 'admin_footer', array( $this, 'get_source_data_callback' ) );
		}
		add_action( 'wp_ajax_wholesalex_deactive_plugin',  array(__CLASS__,'send_plugin_data')  );
		$this->wholesalex_plugin_data_remove();
	}

	/**
	 * Check Local / Live Server
     * 
     * @since v.1.4.3
	 * @return ARRAY | Return From The Server
	 */
	public function is_local() {
		return in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ) ); //phpcs:ignore
	}

	public static function send_plugin_data( $type='deactive' , $site = '' ) {
		
		if ( current_user_can( 'administrator' ) ) {
			$data = self::get_data();
			$data['site_type'] = $site ? $site : get_option( '__site_type' );

			$data['type'] = $type ? $type : 'deactive';
			$form_data = isset($_POST) ? $_POST : array(); //phpcs:ignore
		
			if( isset( $form_data['action'] ) ){
				unset( $form_data['action'] );
			}

			$response = wp_remote_post( self::$API_ENDPOINT, array(
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'user-agent' => 'wpxpo/' . md5( esc_url( home_url() ) ) . ';',
					'Accept'     => 'application/json',
				),
				'blocking'    => true,
				'httpversion' => '1.0',
				'body'        => array_merge( $data, $form_data ),
			) );

			return $response;
		}		
	}
	

	/**
	 * Settings Arguments
     * 
     * @since v.1.4.3
	 * @return ARRAY
	 */
	public function get_settings() {
		$attr = array(
			array(
				'id'          	=> 'no-need',
				'input' 		=> false,
				'text'        	=> __( "I no longer need the plugin.", "wholesalex" )
			),
			array(
				'id'          	=> 'better-plugin',
				'input' 		=> true,
				'text'        	=> __( "I found a better plugin.", "wholesalex" ),
				'placeholder' 	=> __( "Please share which plugin.", "wholesalex" ),
			),
			array(
				'id'          	=> 'stop-working',
				'input' 		=> true,
				'text'        	=> __( "The plugin suddenly stopped working.", "wholesalex" ),
				'placeholder' 	=> __( "Please share more details.", "wholesalex" ),
			),
			array(
				'id'          	=> 'not-working',
				'input' 		=> false,
				'text'        	=> __( "I could not get the plugin to work.", "wholesalex" )
			),
			array(
				'id'          	=> 'temporary-deactivation',
				'input' 		=> false,
				'text'        	=> __( "It's a temporary deactivation.", "wholesalex" )
			),
			array(
				'id'          	=> 'other',
				'input' 		=> true,
				'text'        	=> __( "Other.", "wholesalex" ),
				'placeholder' 	=> __( "Please share the reason.", "wholesalex" ),
			),
		);
		return $attr;
	}

	/**
	 * Popup Module of Action
     * 
     * @since v.1.4.3
	 * @return ARRAY
	 */
    public function get_source_data_callback(){
        $this->deactive_html();
		$this->deactive_css();
		$this->deactive_js();
	}

	public function deactive_html() { ?>
    	<div class="wholesalex-modal" id="wholesalex-deactive-modal">
            <div class="wholesalex-modal-wrap">
			
                <div class="wholesalex-modal-header">
                    <h2><?php esc_html_e( "Quick Feedback", "wholesalex" ); ?></h2>
                    <button class="wholesalex-modal-cancel"><span class="dashicons dashicons-no-alt"></span></button>
                </div>

                <div class="wholesalex-modal-body">
                    <h3><?php esc_html_e( "If you have a moment, please let us know why you are deactivating WholesaleX:", "wholesalex" ); ?></h3>
                    <ul class="wholesalex-modal-input">
						<?php foreach ($this->get_settings() as $key => $setting) { ?>
							<li>
								<label>
									<input type="radio" <?php echo ($key == 0 ? 'checked="checked"' : ''); ?> id="<?php echo esc_attr($setting['id']); ?>" name="<?php echo esc_attr(self::$PLUGIN_SLUG); ?>" value="<?php echo esc_attr($setting['text']); ?>">
									<div class="wholesalex-reason-text"><?php echo esc_html($setting['text']); ?></div>
									<?php if( isset($setting['input']) && $setting['input'] ) { ?>
										<textarea placeholder="<?php echo esc_attr($setting['placeholder']); ?>" class="wholesalex-reason-input <?php echo ($key == 0 ? 'wholesalex-active' : ''); ?> <?php echo esc_html($setting['id']); ?>"></textarea>
									<?php } ?>
								</label>
							</li>
						<?php } ?>
                    </ul>
                </div>

                <div class="wholesalex-modal-footer">
                    <a class="wholesalex-modal-submit wholesalex-btn wholesalex-btn-primary" href="#"><?php esc_html_e( "Submit & Deactivate", "wholesalex" ); ?><span class="dashicons dashicons-update rotate"></span></a>
                    <a class="wholesalex-modal-deactive" href="#"><?php esc_html_e( "Skip & Deactivate", "wholesalex" ); ?></a>
				</div>
				
            </div>
        </div>
	<?php }

	public function deactive_css() { ?>
		<style type="text/css">
			.wholesalex-modal {
                position: fixed;
                z-index: 99999;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                background: rgba(0,0,0,0.5);
                display: none;
                box-sizing: border-box;
                overflow: scroll;
            }
            .wholesalex-modal * {
                box-sizing: border-box;
            }
            .wholesalex-modal.modal-active {
                display: block;
            }
			.wholesalex-modal-wrap {
                max-width: 870px;
                width: 100%;
                position: relative;
                margin: 10% auto;
                background: #fff;
            }
			.wholesalex-reason-input{
				display: none;
			}
			.wholesalex-reason-input.wholesalex-active{
				display: block;
			}
			.rotate{
				animation: rotate 1.5s linear infinite; 
			}
			@keyframes rotate{
				to{ transform: rotate(360deg); }
			}
			.wholesalex-popup-rotate{
				animation: popupRotate 1s linear infinite; 
			}
			@keyframes popupRotate{
				to{ transform: rotate(360deg); }
			}
			#wholesalex-deactive-modal {
				background: rgb(0 0 0 / 85%);
				overflow: hidden;
			}
			#wholesalex-deactive-modal .wholesalex-modal-wrap {
				max-width: 570px;
				border-radius: 5px;
				margin: 5% auto;
				overflow: hidden
			}
			#wholesalex-deactive-modal .wholesalex-modal-header {
				padding: 17px 30px;
				border-bottom: 1px solid #ececec;
				display: flex;
				align-items: center;
				background: #f5f5f5;
			}
			#wholesalex-deactive-modal .wholesalex-modal-header .wholesalex-modal-cancel {
				padding: 0;
				border-radius: 100px;
				border: 1px solid #b9b9b9;
				background: none;
				color: #b9b9b9;
				cursor: pointer;
				transition: 400ms;
			}
			#wholesalex-deactive-modal .wholesalex-modal-header .wholesalex-modal-cancel:focus {
				color: #ff0000;
				border: 1px solid #ff0000;
				outline: 0;
			}
			#wholesalex-deactive-modal .wholesalex-modal-header .wholesalex-modal-cancel:hover {
				color: #ff0000;
				border: 1px solid #ff0000;
			}
			#wholesalex-deactive-modal .wholesalex-modal-header h2 {
				margin: 0;
				padding: 0;
				flex: 1;
				line-height: 1;
				font-size: 20px;
				text-transform: uppercase;
				color: #8e8d8d;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body {
				padding: 25px 30px;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body h3{
				padding: 0;
				margin: 0;
				line-height: 1.4;
				font-size: 15px;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul {
				margin: 25px 0 10px;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li {
				display: flex;
				margin-bottom: 10px;
				color: #807d7d;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li:last-child {
				margin-bottom: 0;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li label {
				align-items: center;
				width: 100%;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li label input {
				padding: 0 !important;
				margin: 0;
				display: inline-block;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li label textarea {
				margin-top: 8px;
				width: 350px;
			}
			#wholesalex-deactive-modal .wholesalex-modal-body ul li label .wholesalex-reason-text {
				margin-left: 8px;
				display: inline-block;
			}
			#wholesalex-deactive-modal .wholesalex-modal-footer {
				padding: 0 30px 30px 30px;
				display: flex;
				align-items: center;
			}
			#wholesalex-deactive-modal .wholesalex-modal-footer .wholesalex-modal-submit {
				display: flex;
				align-items: center;
			}
			#wholesalex-deactive-modal .wholesalex-modal-footer .wholesalex-modal-submit span {
				margin-left: 4px;
				display: none;
			}
			#wholesalex-deactive-modal .wholesalex-modal-footer .wholesalex-modal-submit.loading span {
				display: block;
			}
			#wholesalex-deactive-modal .wholesalex-modal-footer .wholesalex-modal-deactive {
				margin-left: auto;
				color: #c5c5c5;
				text-decoration: none;
			}
			.wholesalex-btn {
				font-size: var(--wholesalex-size-16);
				line-height: 1;
				font-weight: 600;
				text-decoration: none;
				border-radius: 2px;
				transition: 400ms;
				padding: 15px 35px;
				cursor: pointer;
			}
			.wpxpo-btn-tracking-notice {
				display: flex;
                align-items: center;
                flex-wrap: wrap;
                padding: 5px 0;
			}
			.wpxpo-btn-tracking-notice .wpxpo-btn-tracking {
				margin: 0 5px;
				text-decoration: none;
			}
		</style>
    <?php }

	public function deactive_js() { ?>
        <script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				'use strict';

				// Modal Radio Input Click Action
				$('.wholesalex-modal-input input[type=radio]').on( 'change', function(e) {
					$('.wholesalex-reason-input').removeClass('wholesalex-active');
					$('.wholesalex-modal-input').find( '.'+$(this).attr('id') ).addClass('wholesalex-active');
				});

				// Modal Cancel Click Action
				$( document ).on( 'click', '.wholesalex-modal-cancel', function(e) {
					$( '#wholesalex-deactive-modal' ).removeClass( 'modal-active' );
				});

				// Deactivate Button Click Action
				$( document ).on( 'click', '#deactivate-wholesalex', function(e) {
					e.preventDefault();
					$( '#wholesalex-deactive-modal' ).addClass( 'modal-active' );
					$( '.wholesalex-modal-deactive' ).attr( 'href', $(this).attr('href') );
					$( '.wholesalex-modal-submit' ).attr( 'href', $(this).attr('href') );
				});

				// Submit to Remote Server
				$( document ).on( 'click', '.wholesalex-modal-submit', function(e) {
					e.preventDefault();
					$(this).addClass('loading');
					const url = $(this).attr('href')
					$.ajax({
						url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
						type: 'POST',
						data: { 
							action: 'wholesalex_deactive_plugin',
							cause_id: $('input[type=radio]:checked').attr('id'),
							cause_title: $('.wholesalex-modal-input input[type=radio]:checked').val(),
							cause_details: $('.wholesalex-reason-input.wholesalex-active').val()
						},
						success: function (data) {
							$( '#wholesalex-deactive-modal' ).removeClass( 'modal-active' );
							window.location.href = url;
						},
						error: function(xhr) {
							console.log( 'Error occured. Please try again' + xhr.statusText + xhr.responseText );
						},
					});
				});
			});
		</script>
    <?php }


	/**
	 * Plugin Data Callback
     * 
     * @since v.1.4.3
	 * @return ARRAY | Plugins Information
	 */
	public static function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
            include ABSPATH . '/wp-admin/includes/plugin.php';
        }

		$active = array();
        $inactive = array();
        $all_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        foreach ( $all_plugins as $key => $plugin ) {
			$arr = array();
			
			$arr['name'] 	= isset( $plugin['Name'] ) ? $plugin['Name'] : '';
			$arr['url'] 	= isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
			$arr['author'] 	= isset( $plugin['Author'] ) ? $plugin['Author'] : '';
			$arr['version'] = isset( $plugin['Version'] ) ? $plugin['Version'] : '';

			if ( in_array( $key, $active_plugins ) ){
				$active[$key] = $arr;
			} else {
				$inactive[$key] = $arr;
			}
		}

		return array( 'active' => $active, 'inactive' => $inactive );		
	}


	/**
	 * Get Theme Data Callback
     * 
     * @since v.1.4.3
	 * @return ARRAY | Theme Information
	 */
	public static function get_themes() {
		$theme_data = array();
		$all_themes = wp_get_themes();	
		if ( is_array( $all_themes ) ){
			foreach ( $all_themes as $key => $theme ) {
				$attr = array();
				$attr['name'] 		= $theme->Name;
				$attr['url'] 		= $theme->ThemeURI;
				$attr['author'] 	= $theme->Author;
				$attr['version'] 	= $theme->Version;
				$theme_data[$key] 	= $attr;
			}
		}
		return $theme_data;
	}

	/**
	 * Get Current Users IP Address
     * 
     * @since v.1.4.3
	 * @return STRING | IP Address
	 */
	public static function get_user_ip() {
		$response = wp_remote_get( 'https://icanhazip.com/' );
		
        if ( is_wp_error( $response ) ) {
            return '';
        } else {
			$user_ip = trim( wp_remote_retrieve_body( $response ) );
			return filter_var( $user_ip, FILTER_VALIDATE_IP ) ? $user_ip : '';
		}
    }

	/**
	 * All the Valid Information of The Users
     * 
     * @since v.1.4.3
	 * @return STRING | IP Address
	 */
	public static function get_data() {
		global $wpdb;
		$user = wp_get_current_user();
		$user_count = count_users();
		$plugins_data = self::get_plugins();

		$data = array(
			'name' => get_bloginfo( 'name' ),
			'home' => esc_url( home_url() ),
			'admin_email' => $user->user_email,
			'first_name' => isset($user->user_firstname) ? $user->user_firstname : '',
			'last_name' => isset($user->user_lastname) ? $user->user_lastname : '',
			'display_name' => $user->display_name,
			'wordpress' => get_bloginfo( 'version' ),
			'memory_limit' => WP_MEMORY_LIMIT,
			'debug_mode' => ( defined('WP_DEBUG') && WP_DEBUG ) ? 'Yes' : 'No',
			'locale' => get_locale(),
			'multisite' => is_multisite() ? 'Yes' : 'No',

			'themes' => self::get_themes(),
			'active_theme' => get_stylesheet(),
			'users' => isset($user_count['total_users']) ? $user_count['total_users'] : 0,
			'active_plugins' => $plugins_data['active'],
			'inactive_plugins' => $plugins_data['inactive'],
			'server' => isset( $_SERVER['SERVER_SOFTWARE'] ) ?  $_SERVER['SERVER_SOFTWARE'] : '', //phpcs:ignore
			
			'timezone' => date_default_timezone_get(),
			'php_curl' => function_exists( 'curl_init' ) ? 'Yes' : 'No',
			'php_version' => function_exists('phpversion') ? phpversion() : '',
			'upload_size' => size_format( wp_max_upload_size() ),
			'mysql_version' => $wpdb->db_version(),
			'php_fsockopen' => function_exists( 'fsockopen' ) ? 'Yes' : 'No',

			'ip' => self::get_user_ip(),
			'plugin_name' => self::$PLUGIN_NAME,
			'plugin_version' => self::$PLUGIN_VERSION,
			'plugin_slug' => self::$PLUGIN_SLUG
		);

		return $data;
	}


	// Hook into plugin deactivation

private function wholesalex_plugin_data_remove() {
	$is_plugin_data_delete = wholesalex()->get_setting( '_settings_access_delete_wholesalex_plugin_data', '' );
    if ( $is_plugin_data_delete == 'yes' ) {
        global $wpdb;

        $option_keys = [
            'wholesalex_settings',
            'wholesalex_installation_date',
            '__wholesalex_customer_import_export_stats',
            'wholesalex_notice',
            '__wholesalex_single_product_settings',
            '__wholesalex_single_product_db_update_v2',
            '__wholesalex_category_settings',
            '__wholesalex_dynamic_rules',
            '_wholesalex_roles',
            '__wholesalex_registration_form',
            '__wholesalex_email_templates',
            '__wholesalex_initial_setup',
            'woocommerce_wholesalex_new_user_approval_required_settings',
            'woocommerce_wholesalex_new_user_approved_settings',
            'woocommerce_wholesalex_new_user_auto_approve_settings',
            'woocommerce_wholesalex_new_user_email_verified_settings',
            'woocommerce_wholesalex_registration_pending_settings',
            'woocommerce_wholesalex_new_user_registered_settings',
            'woocommerce_wholesalex_registration_rejected_settings',
            'woocommerce_wholesalex_new_user_email_verification_settings',
            'woocommerce_wholesalex_user_profile_update_notify_settings',
        ];

        // Delete options from the wp_options table
        $placeholders = array_fill(0, count($option_keys), '%s');
        $query = "DELETE FROM $wpdb->options WHERE option_name IN (" . implode(', ', $placeholders) . ")";
        $wpdb->query($wpdb->prepare($query, ...$option_keys));//phpcs:ignore

        $post_meta_keys = [
            'wholesalex_b2b_stock_status',
            'wholesalex_b2b_stock',
            'wholesalex_b2b_backorders',
            'wholesalex_b2b_separate_stock_status',
            'wholesalex_b2b_variable_stock',
            'wholesalex_b2b_variable_backorders',
            'wholesalex_b2b_variable_separate_stock_status',
        ];

        // Delete post meta keys from the wp_postmeta table
        $placeholders = array_fill(0, count($post_meta_keys), '%s');
        $query = "DELETE FROM $wpdb->postmeta WHERE meta_key IN (" . implode(', ', $placeholders) . ")";
        $wpdb->query($wpdb->prepare($query, ...$post_meta_keys));//phpcs:ignore
		$dynamic_prefix = 'wholesalex_';
        $suffixes = ['_base_price', '_sale_price'];
        foreach ($suffixes as $suffix) {
            $wpdb->query($wpdb->prepare( //phpcs:ignore
                "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s",
                $wpdb->esc_like($dynamic_prefix) . '%' . $wpdb->esc_like($suffix)
            ));
        }
        // Delete all keys with prefix 'wholesalex_'
        $wpdb->query($wpdb->prepare(//phpcs:ignore
            "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s",
            $wpdb->esc_like($dynamic_prefix) . '%'
        ));

		$wpdb->query($wpdb->prepare(//phpcs:ignore
            "DELETE FROM $wpdb->termmeta WHERE meta_key LIKE %s",
            $wpdb->esc_like($dynamic_prefix) . '%'
        ));

		$wpdb->query($wpdb->prepare(//phpcs:ignore
            "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s",
            $wpdb->esc_like('__wholesalex_') . '%'
        ));
        $user_meta_keys = [
            '__wholesalex_status',
            '__wholesalex_account_confirmed',
            'wholesalex_notice',
            '__wholesalex_role',
            '__wholesalex_profile_discounts',
            '__wholesalex_profile_settings',
            '__wholesalex_email_confirmation_code',
        ];

        // Delete user meta keys from the wp_usermeta table
        $placeholders = array_fill(0, count($user_meta_keys), '%s');
        $query = "DELETE FROM $wpdb->usermeta WHERE meta_key IN (" . implode(', ', $placeholders) . ")";
        $wpdb->query($wpdb->prepare($query, ...$user_meta_keys));//phpcs:ignore

        $user_option_keys = [
            'wholesalex_dynamic_rule_import_mapping',
            'wholesalex_role_import_mapping',
            'wholesalex_role_import_error_log',
        ];

        // Delete user option keys from the wp_usermeta table for specific users
        $placeholders = array_fill(0, count($user_option_keys), '%s');
        $query = "DELETE FROM $wpdb->usermeta WHERE meta_key IN (" . implode(', ', $placeholders) . ")";//phpcs:ignore
        $wpdb->query($wpdb->prepare($query, ...$user_option_keys));//phpcs:ignore

    }
}


    
}