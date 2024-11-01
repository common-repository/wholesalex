<?php

/**
 * WholesaleX Overview
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

use DateTime;
use WP_Query;
use WP_User_Query;

/**
 * WholesaleX Overview Class
 */
class WHOLESALEX_Overview
{

	/**
	 * Overview Constructor
	 */
	public function __construct()
	{
		add_action('admin_menu', array($this, 'overview_submenu_page_callback'), 1);
		add_action('rest_api_init', array($this, 'overview_callback'));
		add_action('admin_menu', array($this, 'go_pro_menu_page'), 99999);

		add_filter('wholesalex_capability_access', array($this, 'wholesalex_menus_access'));
		global $wpdb;
		add_action('wp_dashboard_setup', array($this, 'my_custom_dashboard_widgets'));
		add_action('wp_ajax_wholesalex_migration_tool_install', array($this, 'install_callback'));
		$this->init_migrations();
	}

	/**
	 * Check b2bking or wholesale Suite is Exist or Not
	 *
	 * @return void
	 */
	public function init_migrations()
	{
		$status = false;
		if ( function_exists('b2bkingcore_run') ) {
			 // Ensure the is_plugin_active() function is available
			 if ( !function_exists('is_plugin_active') ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
		
			if ( is_plugin_active('b2bking-wholesale-for-woocommerce/b2bking.php') ) {
				$status = true;
			}
		}
		if (file_exists(WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php')) {
			 // Ensure the is_plugin_active() function is available
			 if ( !function_exists('is_plugin_active') ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
		
			if (is_plugin_active('woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php')) {
				$status = true;
			}
		}

		if ($status) {
			add_action('admin_menu', array($this, 'migration_tool_submenu_page_callback'));
		}
	}
	/**
	 * Migration Tools Menu callback
	 *
	 * @return void
	 */
	public function migration_tool_submenu_page_callback()
	{
		//$slug = apply_filters( 'wholesalex_migration_submenu_slug', 'wholesalex-migration' );
		$slug = apply_filters('wholesalex_support_submenu_slug', 'wholesalex-migration');
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__('Migration Tool', 'wholesalex'),
			__('Migration Tool', 'wholesalex'),
			'manage_options',
			$slug,
			array($this, 'migration_tools_content'),
			11
		);
	}
	public function migration_tools_content()
	{
		if (method_exists('\WholesaleXMigrationTool', 'migration_tools_content') && is_plugin_active('wholesalex-migration-tool/wholesalex-migration-tool.php')) {
			\WholesaleXMigrationTool::migration_tools_content();
			//wholesalex-migration-tool/wholesalex-migration-tool.php
		} else {
			$this->wholesalex_migration_tool_notice_js();
?>
			<div class="wholesalex_header_wrapper">
				<div class="wholesalex_header">
					<div class="wholesalex_header__left"><img src="<?php echo esc_url( WHOLESALEX_URL . '/assets/img/logo-option.svg' ); ?>" class="wholesalex_logo">
						<span class="wholesalex_version"><?php echo esc_html( 'v' . WHOLESALEX_VER ) ?></span>
						<span class="dashicons dashicons-arrow-right-alt2 wholesalex_right_arrow_icon wholesalex_icon"></span>
						<span class="wholesalex_header__title"> <?php echo esc_html( __('WholesaleX Migration Tool', 'wholesalex') ) ?> </span>
					</div>
					<div class="wholesalex_header__right">
						<div class="wholesalex_dropdown ">
							<div class="wholesalex_dropdown__label dashicons dashicons-editor-help wholesalex_header_help_icon wholesalex_icon wholesalex_header_action"></div>
							<div class="wholesalex_popup_menu__wrapper wholesalex_dropdown_content__wrapper wholesalex-modal-menu-wrapper">
								<div class="wholesalex_popup_menu wholesalex_dropdown_content">
									<ul class="wholesalex_help_popup__links">
										<li class="wholesalex_help_popup__list">
											<span class="dashicons dashicons-phone wholesalex_icon"></span>
											<a href="https://getwholesalex.com/contact/?utm_source=wholesalex-menu&amp;utm_medium=features_page-support&amp;utm_campaign=wholesalex-DB" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Get Supports</span>
											</a>
										</li>
										<li class="wholesalex_help_popup__list">
											<span class="dashicons dashicons-book wholesalex_icon"></span>
											<a href="https://getwholesalex.com/docs/wholesalex/getting-started/?utm_source=wholesalex-menu&amp;utm_medium=features_page-guide&amp;utm_campaign=wholesalex-DB" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Getting Started Guide</span>
											</a>
										</li>
										<li class="wholesalex_help_popup__list">
											<span class="dashicons dashicons-facebook-alt wholesalex_icon"></span>
											<a href="https://www.facebook.com/groups/wholesalexcommunity" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Join Community</span></a>
										</li>
										<li class="wholesalex_help_popup__list"><span class="dashicons dashicons-book wholesalex_icon"></span>
											<a href="https://getwholesalex.com/roadmap/?utm_source=wholesalex-menu&amp;utm_medium=features_page-feature_request&amp;utm_campaign=wholesalex-DB" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Feature Request</span>
											</a>
										</li>
										<li class="wholesalex_help_popup__list"><span class="dashicons dashicons-youtube wholesalex_icon"></span>
											<a href="https://www.youtube.com/@WholesaleX" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Youtube Tutorials</span></a>
										</li>
										<li class="wholesalex_help_popup__list"><span class="dashicons dashicons-book wholesalex_icon"></span>
											<a href="https://getwholesalex.com/documentation/?utm_source=wholesalex-menu&amp;utm_medium=features_page-documentation&amp;utm_campaign=wholesalex-DB" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">Documentation</span>
											</a>
										</li>
										<li class="wholesalex_help_popup__list"><span class="dashicons dashicons-edit wholesalex_icon"></span>
											<a href="https://getwholesalex.com/roadmap/?utm_source=wholesalex-menu&amp;utm_medium=features_page-what’s_new&amp;utm_campaign=wholesalex-DB" class="wholesalex_help_popup__link" target="_blank">
												<span class="wholesalex_help_popup__link_label">What’s New</span>
											</a>
										</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="wc-install wholesalex-migration-tool-required">
				<div class="wholesalex-wrapper-box">
					<div class="wholesalext-migration-title">Migrate Your Data to WholesaleX</div>
					<p class="wholesalext-migration-description">To ensure a smooth transition and retain all your previous B2B data, you'll need to install our Migration Tool.</p>
					<a class="wholesalex-install-btn wholesalex-migration-tool-btn" href="<?php echo esc_url(add_query_arg(array('action' => 'wholesalex_migration_tool_install'), admin_url())); ?>"><span class="dashicons dashicons-image-rotate"></span><?php echo esc_html_e('Install & Activate WholesaleX Migration Tool', 'wholesalex'); ?> <span class="wsx-btn-down-arrow-icon">&#8595;</span></a>
				</div>
				<div class="wholesalex-migration-tool-img">
					<img src="<?php echo esc_url( WHOLESALEX_URL . '/assets/img/migration-image.png' ); ?>" alt="">
				</div>
			</div>
		<?php
		}
	}

	/** Notice Script. */
	public function wholesalex_migration_tool_notice_js()
	{
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				'use strict';
				$(document).on('click', '.wholesalex-migration-tool-btn', function(e) {
					e.preventDefault();
					const $that = $(this);
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: {
							install_plugin: 'wholesalex-migration-tool',
							action: 'wholesalex_migration_tool_install'
						},
						beforeSend: function() {
							$that.parents('.wc-install').addClass('loading');
						},
						success: function(data) {
							$('#installation-msg').html(data);
							$that.parents('.wc-install').remove();
							// $('.wholesalex-install-btn').text('Installing & Activating...');
						},
						complete: function() {
							location.reload();
							$that.parents('.wc-install').removeClass('loading');
						}
					});
				});
				$('.wholesalex_header_action').on('click', function() {
					const $menu = $('.wholesalex-modal-menu-wrapper');
					if ($menu.css('display') === 'none') {
						$menu.css('display', 'block');
					} else {
						$menu.css('display', 'none');
					}
				});

			});
		</script>
	<?php
	}


	/** Install MIgration Tool Callback */
	public function install_callback()
	{
		wholesalex()->wsx_migration_install_callback();
	}



	public function wholesalex_menus_access()
	{
		$menu_access = wholesalex()->get_setting('_settings_access_shop_manager_with_wxs_menu', '');
		return ($menu_access == 'yes' ? 'manage_woocommerce' : 'manage_options');
	}
	/**
	 * Overview Menu callback
	 *
	 * @return void
	 */
	public function overview_submenu_page_callback()
	{

		$wholesalex_menu_icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNTcuOTI0IiBoZWlnaHQ9IjIzNi4yNTciPjxnIGRhdGEtbmFtZT0iR3JvdXAgMzI5MyI+PGcgZGF0YS1uYW1lPSJHcm91cCAzMjcyIj48ZyBkYXRhLW5hbWU9Ikdyb3VwIDMyNjUiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0yNTk4LjQ5MiAxNDguMDk3KSI+PHJlY3Qgd2lkdGg9Ijc0LjU0NiIgaGVpZ2h0PSI3NC41NDYiIGZpbGw9IiNmZmYiIGRhdGEtbmFtZT0iUmVjdGFuZ2xlIDI2MjMiIHJ4PSI2LjU5NCIgdHJhbnNmb3JtPSJyb3RhdGUoLTExLjc3NCA3NjQuNzI1IC0xMjkxNi41MTgpIi8+PC9nPjxnIGRhdGEtbmFtZT0iR3JvdXAgMzI2NiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTI1OTguNDkyIDE0OC4wOTcpIj48cmVjdCB3aWR0aD0iNzQuNTQ2IiBoZWlnaHQ9Ijc0LjU0NiIgZmlsbD0iI2ZmZiIgZGF0YS1uYW1lPSJSZWN0YW5nbGUgMjYyNCIgcng9IjYuNTk0IiB0cmFuc2Zvcm09InJvdGF0ZSgtMTEuNzc0IDExNzIuNjMgLTEyOTU4LjU4KSIvPjwvZz48ZyBkYXRhLW5hbWU9Ikdyb3VwIDMyNjciIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0yNTk4LjQ5MiAxNDguMDk3KSI+PHJlY3Qgd2lkdGg9Ijc0LjU0NiIgaGVpZ2h0PSI3NC41NDYiIGZpbGw9IiNmZmYiIGRhdGEtbmFtZT0iUmVjdGFuZ2xlIDI2MjUiIHJ4PSI2LjU5NCIgdHJhbnNmb3JtPSJyb3RhdGUoLTExLjc3NCA3MjIuNjYgLTEzMzI0LjQ4MykiLz48L2c+PGcgZGF0YS1uYW1lPSJHcm91cCAzMjY4IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMjU5OC40OTIgMTQ4LjA5NykiPjxyZWN0IHdpZHRoPSI3NC41NDYiIGhlaWdodD0iNzQuNTQ2IiBmaWxsPSIjZmZmIiBkYXRhLW5hbWU9IlJlY3RhbmdsZSAyNjI2IiByeD0iNi41OTQiIHRyYW5zZm9ybT0icm90YXRlKC0xMS43NzQgMTEzMC42MiAtMTMzNjYuNTQ0KSIvPjwvZz48ZyBkYXRhLW5hbWU9Ikdyb3VwIDMyNjkiPjxwYXRoIGZpbGw9IiNmZmYiIGQ9Ik02MS41MjggMTc5Ljk4NWE1LjIzNSA1LjIzNSAwIDAgMS01LjExNy00LjE2NmwtMzIuNzYtMTU3LjNhNi42NjYgNi42NjYgMCAwIDAtNy44NzYtNS4xNjcgNi42MjUgNi42MjUgMCAwIDAtNC4yMTUgMi44NzQgNi42IDYuNiAwIDAgMC0uOTUgNS4wMTFMMTMuMTg3IDMzLjZhNS4yMzMgNS4yMzMgMCAwIDEtNC4wNTUgNi4xOSA1LjIzMSA1LjIzMSAwIDAgMS02LjE4OS00LjA1NUwuMzY2IDIzLjM3MmExNi45ODggMTYuOTg4IDAgMCAxIDIuNDQ2LTEyLjg4OEExNy4wMTIgMTcuMDEyIDAgMCAxIDEzLjY0IDMuMTA4YTE3LjE0NSAxNy4xNDUgMCAwIDEgMjAuMjU1IDEzLjI3NmwzMi43NiAxNTcuM2E1LjIzNiA1LjIzNiAwIDAgMS01LjEyNyA2LjNaIiBkYXRhLW5hbWU9IlBhdGggMjE0OSIvPjwvZz48ZyBkYXRhLW5hbWU9Ikdyb3VwIDMyNzAiPjxwYXRoIGZpbGw9IiNmZmYiIGQ9Ik0xMDcuMTUxIDIxMC4wMzNhNS4yMzMgNS4yMzMgMCAwIDEtMS4wNjMtMTAuMzU1bDE0NS41MzUtMzAuMzM0YTUuMjMyIDUuMjMyIDAgMCAxIDIuMTM1IDEwLjI0NGwtMTQ1LjUzNSAzMC4zMzRhNS4zMTkgNS4zMTkgMCAwIDEtMS4wNzIuMTExWiIgZGF0YS1uYW1lPSJQYXRoIDIxNTAiLz48L2c+PGcgZGF0YS1uYW1lPSJHcm91cCAzMjcxIj48cGF0aCBmaWxsPSIjZmZmIiBkPSJNNjkuMjc1IDIzNi4yNTdhMjMuNjY3IDIzLjY2NyAwIDEgMSAyMy4yMTMtMjguNSAyMy42NjMgMjMuNjYzIDAgMCAxLTE4LjMzNyAyNy45OTMgMjMuOTExIDIzLjkxMSAwIDAgMS00Ljg3Ni41MDdabS4wNzUtMzYuODczYTEzLjMgMTMuMyAwIDAgMC0yLjcyLjI4MiAxMy4yIDEzLjIgMCAxIDAgMTUuNjE0IDEwLjIzMSAxMy4yMSAxMy4yMSAwIDAgMC0xMi44OTQtMTAuNTA5WiIgZGF0YS1uYW1lPSJQYXRoIDIxNTEiLz48L2c+PC9nPjwvZz48L3N2Zz4=';
		$wholesalex_menu_icon = apply_filters('wholesalex_menu_icon', $wholesalex_menu_icon);

		$menu_name = apply_filters('wholesalex_plugin_menu_name', __('WholesaleX', 'wholesalex'));
		$menu_slug = apply_filters('wholesalex_plugin_menu_slug', 'wholesalex-overview');
		add_menu_page(
			$menu_name,
			$menu_name,
			apply_filters('wholesalex_capability_access', 'manage_options'),
			$menu_slug,
			array($this, 'output'),
			$wholesalex_menu_icon,
			59
		);
		add_submenu_page(
			$menu_slug,
			__('Dashboard', 'wholesalex'),
			__('Dashboard', 'wholesalex'),
			apply_filters('wholesalex_capability_access', 'manage_options'),
			$menu_slug,
			array($this, 'output'),
		);
	}

	/**
	 * Overview Actions
	 *
	 * @since 1.0.0
	 */
	public function overview_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/overview_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array($this, 'overview_action_callback'),
					'permission_callback' => function () {
						return current_user_can(apply_filters('wholesalex_capability_access', 'manage_options'));
					},
					'args'                => array(),
				),
			)
		);
	}


	public function overview_action_callback( $server ) {
		$post = $server->get_params();
		if (!(isset($post['nonce']) && wp_verify_nonce(sanitize_key($post['nonce']), 'wholesalex-registration'))) {
			return;
		}

		$response = array(
			'status' => false,
			'data'   => array(),
		);

		$type = isset($post['type']) ? sanitize_text_field($post['type']) : '';
		if ('post' === $type) {
			wp_send_json($response);
		} elseif ('get' === $type) {
			$request_for = isset($post['request_for']) ? sanitize_text_field($post['request_for']) : '';
			$start_date  = isset($post['start_date']) ? sanitize_text_field($post['start_date']) : '';
			$end_date    = isset($post['end_date']) ? sanitize_text_field($post['end_date']) : '';
			$period      = isset($post['period']) ? sanitize_text_field($post['period']) : 'day';
			switch ($request_for) {
				case 'top_customers':
					$response['status'] = true;
					$response['data']   = $this->get_top_customers(10);
					break;
				case 'recent_orders':
					$response['status'] = true;
					$response['data']   = $this->get_b2b_recent_orders(10);
					break;
				case 'pending_registrations':
					$response['status'] = true;
					$response['data']   = $this->get_pending_users(10);
					break;
				case 'new_registrations_count':
					$response['status'] = true;
					$response['data']   = $this->get_new_registrations_count();
					break;
				case 'new_messages_count':
					$response['status'] = true;
					$response['data']   = $this->get_new_messages_count();
					break;
				case 'b2b_customer_count':
					$response['status'] = true;
					$response['data']   = $this->get_b2b_customer_count($start_date, $end_date);
					break;
				case 'b2b_order_data':
					$response['status'] = true;
					$response['data']   = $this->get_b2b_order_data($start_date, $end_date, $period);
					break;
				default:
					// code...
					break;
			}
		}

		wp_send_json($response);
	}

	public function my_custom_dashboard_widgets()
	{
		wp_add_dashboard_widget(
			'custom_help_widget',          // Widget slug
			'WholesaleX - Last Month Insights',               // Title
			array($this, 'wholesalex_wp_dashboard_callback') // Display function
		);
	}

	/**
	 * WholesaleX Wordpress Dashboard Widget
	 *
	 * @return void
	 */
	public function wholesalex_wp_dashboard_callback()
	{
		$current_date = new DateTime();
		$first_day_of_last_month = $current_date->modify('first day of last month')->format('Y-m-d');
		$last_day_of_last_month = $current_date->modify('last day of this month')->format('Y-m-d');
		$wsx_sales_summary = $this->get_b2b_order_data($first_day_of_last_month, $last_day_of_last_month);
		$wsx_b2b_customer_count = $this->get_b2b_customer_count($first_day_of_last_month, $last_day_of_last_month);
	?>

		<div id="wholesalex-wp-dashboard" class="wholesalex-wp-dashboard">
			<?php
			$cards = [
				['b2b_customer', 'dashicons-admin-users', 'Customer No. (B2B)', $wsx_b2b_customer_count],
				['b2b_total_order', 'dashicons-screenoptions', 'Total Order (B2B)', $wsx_sales_summary['total_orders']],
				['b2b_total_sale', 'dashicons-money-alt', 'Total Sale (B2B)', $wsx_sales_summary['total_sales']],
				['b2b_net_earning', WHOLESALEX_URL . '/assets/img/net_earning.svg', 'Net Revenue (B2B)', $wsx_sales_summary['net_revenue'], true],
				['b2b_gross_sale', WHOLESALEX_URL . '/assets/img/gross_sale.svg', 'Gross Sale (B2B)', $wsx_sales_summary['gross_sales'], true],
			];

			foreach ($cards as $card) {
				$is_img = isset($card[4]) && $card[4];
			?>
				<div class="wholesalex_wp_dashboard_sale_summary_card wholesalex_dashboard_sale_summary_card__<?php echo esc_attr( $card[0] ); ?>">
					<div class="wholesalex_dashboard_sale_summary_card__image">
						<?php if ($is_img) { ?>
							<img class="wholesalex_sale_summary_card_icon" src="<?php echo esc_attr( $card[1] ); ?>">
						<?php } else { ?>
							<span class="dashicons <?php echo esc_attr( $card[1] ); ?> wholesalex_sale_summary_card_icon"></span>
						<?php } ?>
					</div>
					<div class="wholesalex_dashboard_sale_summary_card__content">
						<div class="wholesalex_dashboard_sale_summary_card__title"><?php echo esc_attr( $card[2] ); ?></div>
						<div class="wholesalex_dashboard_sale_summary_card__count"><?php echo $card[3] ; ?></div>
					</div>
				</div>
			<?php } ?>
		</div>
	<?php
	}

	/**
	 * Get b2b customer count.
	 *
	 * @param string $start_date Start Date.
	 * @param string $end_date End Date.
	 * @return int
	 */
	public function get_b2b_customer_count($start_date = '', $end_date = '')
	{
		$start_date = gmdate('Y-m-d H:i:s', strtotime($start_date));
		$end_date   = gmdate('Y-m-d H:i:s', strtotime('+1 DAY', strtotime($end_date)));

		// Meta key and excluded values
		$meta_key       = '__wholesalex_role';
		$exclude_values = array('', 'wholesalex_guest', 'wholesalex_b2c_users');

		// Meta query to exclude non-B2B users
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => $meta_key,
				'value'   => $exclude_values,
				'compare' => 'NOT IN',
			),
		);

		// Date query if both start and end dates are provided
		if (!empty($start_date) && !empty($end_date)) {
			$date_query = array(
				array(
					'column'    => 'user_registered',
					'after'     => $start_date,
					'before'    => $end_date,
					'inclusive' => true,
					'compare'   => 'BETWEEN',
					'type'      => 'DATETIME',
				),
			);
			$meta_query[] = $date_query; // Adding date query to existing meta query
		}

		// User query arguments
		$args = array(
			'meta_query'  => $meta_query,
			'count_total' => true, // Only retrieve the count
		);

		// Run the user query
		$user_query = new WP_User_Query($args);

		// Get the total count of users
		$user_count = $user_query->get_total();

		return $user_count;
	}



	/**
	 * Output Function
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function output()
	{
		/**
		 * Enqueue Script
		 *
		 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
		 */
		wp_enqueue_script('wholesalex_overview');
		wp_enqueue_style('wc-components');
		$conversation_slug = apply_filters('wholesalex_addon_conversation_endpoint', 'wholesalex-conversation');
		$users_slug        = apply_filters('wholesalex_users_submenu_slug', 'wholesalex-users');

		wp_localize_script(
			'wholesalex_overview',
			'wholesalex_overview',
			array(
				'top_customer_heading'         => $this->prepare_as_heading_data($this->get_top_customers_columns()),
				'recent_order_heading'         => $this->prepare_as_heading_data($this->get_recent_orders_columns()),
				'pending_registration_heading' => $this->prepare_as_heading_data($this->get_pending_registrations_columns()),
				'wholesalex_conversation'      => menu_page_url($conversation_slug, false),
				'wholesalex_users'             => menu_page_url($users_slug, false),
				'i18n' => array(
					'select_a_date_range_to_view_sale_data' => __('Select a date range to view sale data', 'wholesalex'),
					'select_a_date_range' => __('Select a date range', 'wholesalex'),
					'presets' => __('Presets', 'wholesalex'),
					'custom' => __('Custom', 'wholesalex'),
					'reset' => __('Reset', 'wholesalex'),
					'update' => __('Update', 'wholesalex'),
					'today' => __('Today', 'wholesalex'),
					'yesterday' => __('Yesterday', 'wholesalex'),
					'week_to_date' => __('Week to date', 'wholesalex'),
					'last_week' => __('Last week', 'wholesalex'),
					'month_to_date' => __('Month to date', 'wholesalex'),
					'last_month' => __('Last month', 'wholesalex'),
					'quarter_to_date' => __('Quarter to date', 'wholesalex'),
					'last_quarter' => __('Last quarter', 'wholesalex'),
					'year_to_date' => __('Year to date', 'wholesalex'),
					'last_year' => __('Last year', 'wholesalex'),
					'dashboard' => __('Dashboard', 'wholesalex'),
					'new_messages' => __('New Messages', 'wholesalex'),
					'view_all' => __('View All', 'wholesalex'),
					'new_registrations' => __('New Registrations', 'wholesalex'),
					'approve' => __('Approve', 'wholesalex'),
					'no_new_orders_found' => __('No New Orders Found!', 'wholesalex'),
					'pending_registration_in_empty' => __('Pending Registration in Empty!', 'wholesalex'),
					'view_order' => __('View Order', 'wholesalex'),
					'review' => __('Review', 'wholesalex'),
					'no_new_customer_found' => __('No New Customer Found!', 'wholesalex'),
					'customer_no_b2b' => __('Customer No. (B2B)', 'wholesalex'),
					'total_order_b2b' => __('Total Order (B2B)', 'wholesalex'),
					'total_sale_b2b' => __('Total Sale (B2B)', 'wholesalex'),
					'net_revenue_b2b' => __('Net Revenue (B2B)', 'wholesalex'),
					'gross_sale_b2b' => __('Gross Sale (B2B)', 'wholesalex'),
					'full_access_to_dynamic_rules' => __('Full Access to Dynamic Rules', 'wholesalex'),
					'multiple_pricing_tiers' => __('Multiple Pricing Tiers', 'wholesalex'),
					'bulk_order_form' => __('Bulk Order Form', 'wholesalex'),
					'request_a_quote' => __('Request A Quote', 'wholesalex'),
					'subaccounts' => __('Subaccounts', 'wholesalex'),
					'conversation' => __('Conversation', 'wholesalex'),
					'wholesalex_wallet' => __('WholesaleX Wallet', 'wholesalex'),
					'and_much_more' => __('And Much More!', 'wholesalex'),
					'unlock_message_pro' => __('Unlock the full potential of WholesaleX to create and manage WooCommerce B2B or B2B+B2C stores with ease!', 'wholesalex'),
					'join_the_community_message' => __('Join the Facebook community of WholesaleX to stay up-to-date and share your thoughts and feedback.', 'wholesalex'),
					'feature_request_message' => __('Can’t find your desired feature? Let us know your requirements. We will definitely take them into our consideration.', 'wholesalex'),
					'getting_started_guide' => __('Getting Started Guides', 'wholesalex'),
					'how_to_create_the_dynamic_rule' => __('How to Create the Dynamic Rules', 'wholesalex'),
					'how_to_create_user_roles' => __('How to Create User Roles', 'wholesalex'),
					'how_to_create_registration_form' => __('How to Create a Registration Form', 'wholesalex'),
					'wholesalex_blog' => __('WholesaleX Blog', 'wholesalex'),
					'join_wholesalex_community' => __('Join WowCommerce Community', 'wholesalex'),
					'request_a_feature' => __('Request a Feature', 'wholesalex'),
					'pro_features' => __('Pro Features', 'wholesalex'),
					'wholesalex_community' => __('WowCommerce Community', 'wholesalex'),
					'feature_request' => __('Feature Request', 'wholesalex'),
					'news_tips_updates' => __('News, Tips & Updates', 'wholesalex'),
					'sale_summary_b2b' => __('Sales Summary (B2B)', 'wholesalex'),
					'top_customers' => __('Top Customers', 'wholesalex'),
					'recent_orders' => __('Recent Orders', 'wholesalex'),
					'pending_registrations' => __('Pending Registrations', 'wholesalex'),
					'select_a_preset_period' => __('select a preset period', 'wholesalex'),
				)
			)
		);
		wp_set_script_translations('wholesalex_overview', 'wholesalex', WHOLESALEX_PATH . 'languages/');

		//wizerd
		wp_enqueue_script('wholesalex_wizard');
		wp_enqueue_style('wholesalex');
		$localize_content = array(
			'url'                  			=> WHOLESALEX_URL,
			'nonce'                			=> wp_create_nonce('wholesalex-setup-wizard'),
			'ajax'                 			=> admin_url('admin-ajax.php'),
			'plugin_install_nonce' 			=> wp_create_nonce('updates'),
			'is_pro_active'        			=> wholesalex()->is_pro_active(),
			'setting_url'          			=> menu_page_url('wholesalex-settings', false),
			'dashboard_url'        			=> menu_page_url('wholesalex-overview', false),
			'site_name'            			=> get_bloginfo('name'),
			'__wholesalex_initial_setup'   	=> get_option('__wholesalex_initial_setup', false),
			'woocommer_installed'   		=> file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php'),
			'productx_installed'   			=> file_exists(WP_PLUGIN_DIR . '/product-blocks/product-blocks.php'),
		);
		if (!$localize_content['setting_url']) {
			$localize_content['setting_url'] = get_dashboard_url();
		}
		if (!$localize_content['dashboard_url']) {
			$localize_content['dashboard_url'] = get_dashboard_url();
		}
		if (!$localize_content['site_name']) {
			$localize_content['site_name'] = get_bloginfo('name');
		}
		if (!$localize_content['__wholesalex_initial_setup']) {
			$localize_content['__wholesalex_initial_setup'] = get_option('__wholesalex_initial_setup', false);
		}
		wp_localize_script(
			'wholesalex_wizard',
			'wholesalex_wizard',
			$localize_content
		);
	?>
		<div id="wholesalex-overview"></div>
<?php
	}
	/**
	 * Get Top Customer Columns.
	 *
	 * @return array
	 */
	public function get_top_customers_columns()
	{
		$columns = array(
			'name_n_email'    => __('Name and Email', 'wholesalex'),
			'wallet_balance'  => __('Wallet Balance', 'wholesalex'),
			'total_purchase'  => __('Total Purchase', 'wholesalex'),
			/* translators: %s - Plugin Name */
			'wholesalex_role' => wp_sprintf(__('%s Role', 'wholesalex'), wholesalex()->get_plugin_name()),
		);

		if (!('yes' === wholesalex()->get_setting('wsx_addon_wallet') && wholesalex()->is_pro_active())) {
			unset($columns['wallet_balance']);
		}

		$columns = apply_filters('wholesalex_dashboard_top_customer_columns', $columns);

		return $columns;
	}

	/**
	 * Get Recent Orders Columns
	 *
	 * @return array
	 */
	public function get_recent_orders_columns()
	{
		$columns = array(
			'ID'            => __('Order ID', 'wholesalex'),
			'customer_name' => __('Name', 'wholesalex'),
			'order_date'    => __('Date', 'wholesalex'),
			'order_status'  => __('Status', 'wholesalex'),
			'view_order'    => __('Action', 'wholesalex'),
		);

		$columns = apply_filters('wholesalex_dashboard_recent_orders_columns', $columns);

		return $columns;
	}

	/**
	 * Get Pending Registrations Column.
	 *
	 * @return array
	 */
	public function get_pending_registrations_columns()
	{
		$columns = array(
			'name_n_email'      => __('Name and Email', 'wholesalex'),
			'user_registered'   => __('Regi. Date', 'wholesalex'),
			'registration_role' => __('Regi. Role', 'wholesalex'),
			'edit_user'         => __('Action', 'wholesalex'),
		);

		$columns = apply_filters('wholesalex_dashboard_pending_registrations_columns', $columns);

		return $columns;
	}

	/**
	 * Prepare Column as Heading Data.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function prepare_as_heading_data($columns)
	{
		$heading_data = array();
		foreach ($columns as $key => $value) {
			$data          = array();
			$data['name']  = $key;
			$data['title'] = $value;
			switch ($key) {
				case 'wallet_balance':
					$data['type'] = 'html';
					break;
				case 'total_purchase':
					$data['type'] = 'html';
					break;
				case 'name_n_email':
					$data['type'] = 'name_n_email';
					break;
				case 'view_order':
					$data['type'] = 'view_order';
					break;

				case 'edit_user':
					$data['type'] = 'edit_user';
					break;
				default:
					$data['type'] = 'text';
					break;
			}

			$heading_data[$key] = $data;
		}

		return $heading_data;
	}

	/**
	 * Add Go Pro Menu Page
	 *
	 * @return void
	 * @since 1.1.2
	 */
	public function go_pro_menu_page()
	{
		if (!wholesalex()->is_pro_active()) {
			$title = sprintf('<span class="wholesalex-submenu-title__upgrade-to-pro"><span class="dashicons dashicons-star-filled"></span>%s</span>', __('Upgrade to Pro', 'wholesalex'));
			add_submenu_page(
				'wholesalex-overview',
				'',
				$title,
				apply_filters('wholesalex_capability_access', 'manage_options'),
				'go_wholesalex_pro',
				array($this, 'go_pro_redirect')
			);
		}
	}

	/**
	 * Go Pro Redirect From Dashboard
	 *
	 * @since 1.1.2
	 */
	public function go_pro_redirect()
	{
		if (isset($_GET['page']) && 'go_wholesalex_pro' === sanitize_text_field($_GET['page'])) { //phpcs:ignore 
			wp_redirect('https://getwholesalex.com/pricing/?utm_source=wholesalex-plugins&utm_medium=go_pro&utm_campaign=wholesalex-DB'); //phpcs:ignore
			die();
		} else {
			return;
		}
	}


	/**
	 * Get top customers
	 *
	 * @param integer $limit Limit.
	 * @return array
	 */
	public function get_top_customers($limit = 10)
	{
		// For Getting Top B2B Users Data. (Based on Sales).
		$active_user_query = new WP_User_Query(
			array(
				'fields'     => array('display_name', 'user_email', 'ID'),
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '__wholesalex_status',
						'value'   => 'active',
						'compare' => '=',
					),
					array(
						'key'     => '__wholesalex_role',
						'value'   => array('', 'wholesalex_guest', 'wholesalex_b2c_users'),
						'compare' => '!=',
					),

				),
				'number'     => $limit,

			)
		);

		$active_users_data = (array) $active_user_query->get_results();

		foreach ($active_users_data as $key => $value) {
			$__temp                   = (array) $value;
			$__id                     = $__temp['id'];
			$__temp['avatar_url']     = get_avatar_url($__id);
			$__temp['total_purchase'] = wc_get_customer_total_spent($__id);
			if (wholesalex()->is_pro_active() && function_exists('wholesalex_wallet')) {
				$__temp['wallet_balance'] = wholesalex_wallet()->get_wholesalex_balance($__id);
			}
			$__temp['wholesalex_role'] = wholesalex()->get_role_name_by_role_id(get_user_meta($__id, '__wholesalex_role', true));
			$active_users_data[$key] = $__temp;
		}

		$__sort_colum = array_column($active_users_data, 'total_purchase');
		array_multisort($__sort_colum, SORT_DESC, $active_users_data);

		return $active_users_data;
	}

	/**
	 * Get b2b recent orders
	 *
	 * @param integer $limit limit.
	 * @return array
	 */
	public function get_b2b_recent_orders($limit = 10)
	{
		$args = array(
			'status'      => array('completed', 'on-hold'),
			'limit'       => 10,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => '__wholesalex_order_type',
					'value'   => 'b2b',
					'compare' => '=',
				),
			),
		);

		$orders = wc_get_orders($args);
		$b2b_recent_orders = array();
		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			// Get order details
			$order_data = array(
				'ID'            => $order->get_id(),
				'customer_id'   => $order->get_user_id(),
				'order_date'    => $order->get_date_created()->date('Y-m-d'),
				'order_status'  => $order->get_status(),
			);

			$__user_data = get_userdata($order_data['customer_id']);
			$order_data['customer_name'] = $__user_data ? $__user_data->display_name : 'Guest';
			$order_data['order_status'] = wc_get_order_status_name($order_data['order_status']);
			$order_data['view_order'] = admin_url('post.php?post=' . $order_data['ID'] . '&action=edit');
			$b2b_recent_orders[] = $order_data;
		}
		return $b2b_recent_orders;
	}

	/**
	 * Get pending users.
	 *
	 * @param integer $limit limit.
	 * @return array
	 */
	public function get_pending_users($limit = 10)
	{
		$pending_user_query = new WP_User_Query(
			array(
				'meta_key'     => '__wholesalex_status',
				'meta_value'   => 'pending',
				'meta_compare' => '=',
				'fields'       => array('display_name', 'user_email', 'ID', 'user_registered'),
				'count_total'  => true,
				'number'       => $limit,
			)
		);

		$pending_user_data = (array) $pending_user_query->get_results();

		foreach ($pending_user_data as $key => $value) {
			$__temp                      = (array) $value;
			$__id                        = $__temp['id'];
			$__temp['avatar_url']        = get_avatar_url($__id);
			$__temp['registration_role'] = wholesalex()->get_role_name_by_role_id(get_user_meta($__id, '__wholesalex_registration_role', true));
			$__temp['edit_user']         = get_edit_user_link($__id);
			$pending_user_data[$key]   = $__temp;
		}

		return $pending_user_data;
	}

	/**
	 * Get new registration count.
	 *
	 * @return int
	 */
	public function get_new_registrations_count()
	{
		$pending_user_query = new WP_User_Query(
			array(
				'meta_key'     => '__wholesalex_status',
				'meta_value'   => 'pending',
				'meta_compare' => '=',
				'fields'       => array('display_name', 'user_email', 'ID', 'user_registered'),
				'count_total'  => true,
			)
		);

		return $pending_user_query->get_total();
	}

	/**
	 * Get new messages count.
	 *
	 * @return int
	 */
	public function get_new_messages_count()
	{
		$messages_query = new WP_Query(
			array(
				'post_type'    => 'wsx_conversation',
				'post_status'  => 'publish',
				'meta_key'     => '__conversation_status',
				'meta_value'   => 'open',
				'meta_compare' => '=',
			)
		);

		return $messages_query->found_posts;
	}

	/**
	 * Get b2b order data.
	 *
	 * @param string $start_date start date.
	 * @param string $end_date end data.
	 * @param string $period period.
	 * @return array
	 */
	public function get_b2b_order_data($start_date = '', $end_date = '', $period = '')
	{
		global $wpdb;

		$start_date = gmdate('Y-m-d H:i:s', strtotime($start_date));
		$end_date   = gmdate('Y-m-d H:i:s', strtotime('+1 DAY', strtotime($end_date)));

		$graph_data   = array();
		$graph_legend = array();

		$startDate = new DateTime($start_date);
		$endDate   = new DateTime($end_date);

		$currentDate = clone $startDate;

		while ($currentDate < $endDate) {
			$formattedDate = $currentDate->format('Y-m-d');
			$graph_data[$formattedDate] = 0;
			$graph_legend[]             = $formattedDate;
			$currentDate->modify('+1 day');
		}

		$args = array(
			'status' => array('wc-completed', 'wc-refunded'),
			'date_paid' => $start_date . '...' . $end_date,
			'meta_key' => '__wholesalex_order_type',
			'meta_value' => 'b2b',
			'limit' => -1,
		);

		$orders = wc_get_orders($args);

		$total_sales = 0;
		$gross_sales = 0;
		$net_revenue = 0;
		$total_orders = 0;

		foreach ($orders as $order) {
			$total_orders++;
			$total_sales += $order->get_total();
			$gross_sales += $order->get_subtotal();
			$net_revenue += $order->get_total() - $order->get_total_refunded();

			$paid_date = $order->get_date_paid()->date('Y-m-d');
			if (!isset($graph_data[$paid_date])) {
				$graph_data[$paid_date] = 0;
			}
			$graph_data[$paid_date] += $order->get_total();
		}

		$data = array(
			'total_sales'   => wc_price($total_sales),
			'gross_sales'   => wc_price($gross_sales),
			'net_revenue'   => wc_price($net_revenue),
			'total_orders'  => $total_orders,
			'sales_graph'   => $graph_data,
			'gross_graph'   => $graph_data,
			'revenue_graph' => $graph_data,
			'order_graph'   => $graph_data,
			'graph_legend'  => $graph_legend,
		);

		return $data;
	}
}
