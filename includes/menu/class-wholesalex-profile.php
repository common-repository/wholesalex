<?php
/**
 * WholesaleX Profile
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

use stdClass;
use WC_Data_Store;
use WC_Shipping_Zone;
use WP_User;

/**
 * WholesaleX Profile Class
 */
class WHOLESALEX_Profile {

	/**
	 * Profile Constructor
	 */
	public function __construct() {
		// Add and Update in User Profile.
		add_action( 'user_new_form', array( $this, 'wholesalex_profile_rules_field' ) );
		add_action( 'user_new_form', array( $this, 'wholesalex_user_fields' ) );
		add_action( 'show_user_profile', array( $this, 'wholesalex_profile_rules_field' ) );
		add_action( 'show_user_profile', array( $this, 'wholesalex_user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'wholesalex_profile_rules_field' ) );
		add_action( 'edit_user_profile', array( $this, 'wholesalex_user_fields' ) );

		add_action( 'user_register', array( $this, 'save_wholesalex_profile_data' ) );
		add_action( 'personal_options_update', array( $this, 'save_wholesalex_profile_data' ) );

		add_action( 'edit_user_profile_update', array( $this, 'save_wholesalex_profile_data' ) );

		// User Table Column.
		add_action( 'manage_users_custom_column', array( $this, 'manage_wholesalex_role_column' ), 10, 3 );
		add_filter( 'manage_users_columns', array( $this, 'add_wholesalex_role_column' ) );
		// User Role.
		add_filter( 'manage_users_sortable_columns', array( $this, 'wholesalex_add_custom_columns_sortable' ) );
		add_action( 'pre_get_users', array( $this, 'wholesalex_custom_sortable_columns_handler' ) );

		// User Table Filter.
		add_action( 'restrict_manage_users', array( $this, 'add_role_filter' ) );
		add_action( 'pre_get_users', array( $this, 'filter_user_section' ) );

		add_action( 'restrict_manage_users', array( $this, 'add_status_filter' ) );

		add_action( 'rest_api_init', array( $this, 'profile_restapi_callback' ) );

		/**
		 * WholesaleX Free Shipping Title Text Change
		 *
		 * @since 1.1.8
		 */

		add_filter( 'wholesalex_free_shipping_title', array( $this, 'change_free_shipping_title' ), 9999999 );

	}

	/**
	 * Profile Rest API Callback
	 *
	 * @since 1.0.0
	 */
	public function profile_restapi_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/profile_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'profile_action_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Get Profile Fields
	 *
	 * @param object $server Server.
	 * @return void
	 */
    public function profile_action_callback( $server ) {
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}

		$type    = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';
		$user_id = isset( $post['user_id'] ) ? sanitize_text_field( $post['user_id'] ) : '';

		if ( 'get' === $type ) {

			$__tiers         = get_user_meta( $user_id, '__wholesalex_profile_discounts', true );
			$__user_settings = get_user_meta( $user_id, '__wholesalex_profile_settings', true );
			if ( empty( $__user_settings ) ) {
				$__user_settings = array();
			}

			$__role                 = get_user_meta( $user_id, '__wholesalex_role', true );
			$__user_settings        = apply_filters( 'wholesalex_get_user_settings_data', $__user_settings, $user_id );
			$__user_status          = wholesalex()->get_user_status( $user_id );
			$__registration_role_id = get_user_meta( $user_id, '__wholesalex_registration_role', true );
			$__registration_role    = wholesalex()->get_role_name_by_role_id( $__registration_role_id );
			$__role_settings        = array(
				'__wholesalex_status'            => $__user_status,
				'__wholesalex_registration_role' => $__registration_role ? $__registration_role : '',
				'_wholesalex_role'               => $__role ? $__role : ( $__registration_role_id ? $__registration_role_id : '' ),
			);
			wp_send_json_success(
				array(
					'default'  => $this->get_profile_fields(),
					'tiers'    => $__tiers,
					'settings' => array_merge( $__user_settings, $__role_settings ),
				)
			);
		} elseif ( 'post' === $type ) {
			$__user_action = isset( $post['user_action'] ) ? sanitize_text_field( $post['user_action'] ) : '';
			$__user_role   = isset( $post['user_role'] ) ? sanitize_text_field( $post['user_role'] ) : '';
			switch ( $__user_action ) {
				case 'approve_user':
				case 'active_user':
					update_user_meta( $user_id, '__wholesalex_status', 'active' );
					wholesalex()->change_role( $user_id, $__user_role );
					do_action( 'wholesalex_set_status_active', $user_id,'' );
					break;
				case 'reject_user':
					update_user_meta( $user_id, '__wholesalex_status', 'active' );
					do_action( 'wholesalex_set_status_reject', $user_id );
					break;
				case 'delete_user':
					if ( ! current_user_can( 'delete_users' ) ) {
						die( 'You cannot delete an user!' );
					}
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $user_id, 0 );
					do_action( 'wholesalex_delete_user', $user_id );

					wp_send_json_success(
						array(
							'redirect' => get_site_url() . '/wp-admin/users.php',
						)
					);
					break;
				case 'deactive_user':
					update_user_meta( $user_id, '__wholesalex_status', 'inactive' );
					do_action( 'wholesalex_set_status_inactive', $user_id );
					break;

				default:
					break;
			}

			$__updated_role      = get_user_meta( $user_id, '__wholesalex_role', true );
			$__user_status       = wholesalex()->get_user_status( $user_id );
			$__registration_role = wholesalex()->get_role_name_by_role_id( get_user_meta( $user_id, '__wholesalex_registration_role', true ) );
			$__role_settings     = array(
				'__wholesalex_status'            => $__user_status,
				'__wholesalex_registration_role' => $__registration_role ? $__registration_role : '',
				'_wholesalex_role'               => $__updated_role,
			);
			wp_send_json_success(
				array(
					'settings' => $__role_settings,
				)
			);
		}
	}

	/**
	 * WholesaleX Profile Rules
	 *
	 * @since 1.0.0
	 * @since 1.0.9 Account Type Check Added
	 * @access public
	 * @return void
	 */
	public function wholesalex_profile_rules_field( $user ) {
		$id = '';
		if ( is_object( $user ) ) {
			$id = $user->ID;
		} elseif ( is_array( $user ) ) {
			$id = $user['ID'];
		}
		if ( $id ) {
			$account_type = get_user_meta( $id, '__wholesalex_account_type', true );
			if ( 'subaccount' !== $account_type ) {
				/**
				 * Enqueue Script
				 *
				 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
				 */
				wp_enqueue_script( 'wholesalex_profile' );
				wp_localize_script('wholesalex_profile','wholesalex_profile',array(
					'i18n' => array(
						'no_data_found' => __('No Data Found! Please try with another keyword.','wholesalex'),
						'enter_more_character' => __('Enter 2 or more character to search.','wholesalex'),
						'searching' => __('Searching...','wholesalex'),
						'this_user' => __('This User','wholesalex'),
						'unlock' => __("UNLOCK",'wholesalex'),
						'unlock_heading' => __("Unlock All Features",'wholesalex'),
						'unlock_desc' => __("We are sorry, but unfortunately, this feature is unavailable in the free version. Please upgrade to a pro plan to unlock all features.",'wholesalex'),
						'upgrade_to_pro' => __("Upgrade to Pro  ➤",'wholesalex'),
					)
				));
				?>
				<div id="_wholesalex_edit_profile" class=""></div>
				<?php
			}
		}
	}

	/**
	 * Save Users Wholesalex Profile Rules Data
	 *
	 * @param int $user_id User ID.
	 */
	public function save_wholesalex_profile_data( $user_id ) {

		if ( isset( $_POST['_wpnonce_create-user'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce_create-user'] ), 'create-user' ) ) {
				return;
			}
		} else {
			if ( ! ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'update-user_' . $user_id ) ) ) {
				return;
			}
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		do_action( 'wholesalex_save_profile_data', $user_id, wholesalex()->sanitize( $_POST ) );

		if ( isset( $_POST['wholesalex_profile_tiers'] ) && ! empty( $_POST['wholesalex_profile_tiers'] ) ) {

			$__tiers = wholesalex()->sanitize( json_decode( wp_unslash( $_POST['wholesalex_profile_tiers'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			update_user_meta( $user_id, '__wholesalex_profile_discounts', $__tiers );
		}
		if ( isset( $_POST['wholesalex_profile_settings'] ) && ! empty( $_POST['wholesalex_profile_settings'] ) ) {

			$__settings = wholesalex()->sanitize( json_decode( wp_unslash( $_POST['wholesalex_profile_settings'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$__settings = apply_filters( 'wholesalex_profile_setting_data', $__settings, $user_id );

			do_action( 'wholesalex_before_save_profile_settings', $user_id, $__settings );

			if ( isset( $__settings['_wholesalex_role'] ) && ! empty( $__settings['_wholesalex_role'] ) ) {
				$previous_role = get_user_meta( $user_id, '__wholesalex_role', true );
				$updated_role  = $__settings['_wholesalex_role'];
				/**
				 * Set WordPress Role Manually Because By Default WordPress Allow only one role.
				 * For setting wholesalex role with WordPress default, we set WordPress role manually.
				 *
				 * @since 1.1.2
				 */
				if ( ! empty( $_POST['role'] ) ) {
					$wp_roles = wp_roles();
					$user     = new stdClass();
					$user_id  = (int) $user_id;
					if ( $user_id ) {
						$update           = true;
						$user->ID         = $user_id;
						$userdata         = get_userdata( $user_id );
						$user->user_login = wp_slash( $userdata->user_login );
					} else {
						$update = false;
					}

					if ( ! $update && isset( $_POST['user_login'] ) ) {
						$user->user_login = sanitize_user( wp_unslash( $_POST['user_login'] ), true );
					}

					if ( isset( $_POST['role'] ) && current_user_can( 'promote_users' ) && ( ! $user_id || current_user_can( 'promote_user', $user_id ) ) ) {
						$new_role = sanitize_text_field( $_POST['role'] );

						// If the new role isn't editable by the logged-in user die with error.
						$editable_roles = get_editable_roles();
						if ( ! empty( $new_role ) && empty( $editable_roles[ $new_role ] ) ) {
							wp_die( __( 'Sorry, you are not allowed to give users that role.','wholesalex'), 403 ); //phpcs:ignore
						}

						$potential_role = isset( $wp_roles->role_objects[ $new_role ] ) ? $wp_roles->role_objects[ $new_role ] : false;

						/*
						* Don't let anyone with 'promote_users' edit their own role to something without it.
						* Multisite super admins can freely edit their roles, they possess all caps.
						*/
						if (
							( is_multisite() && current_user_can( 'manage_network_users' ) ) ||
							get_current_user_id() !== $user_id ||
							( $potential_role && $potential_role->has_cap( 'promote_users' ) )
						) {
							$user->role = $new_role;
						}
					}
					$user_id = wp_update_user( $user );
					unset( $_POST['role'] );
				}
				wholesalex()->change_role( $user_id, $updated_role, $previous_role ? $previous_role : '' );
			}
			update_user_meta( $user_id, '__wholesalex_profile_settings', $__settings );
		}
		$__role_id = get_user_meta( $user_id, '__wholesalex_role', true );

		$__fields = isset( $GLOBALS['wholesalex_registration_fields']['wholesalex_fields'])?$GLOBALS['wholesalex_registration_fields']['wholesalex_fields']:array();


		$default_fields   = array( 'user_login', 'user_pass', 'display_name', 'nickname', 'first_name', 'last_name', 'description', 'user_email', 'url', 'user_confirm_email', 'user_confirm_password', 'default_user_role', 'registration_role','wholesalex_registration_role','user_confirm_pass' );

		if ( is_array( $__fields ) ) {

			foreach ( $__fields as $field ) {
				if ( ! isset( $field['name'] ) ) {
					continue;
				}

				if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
					$field_name = $field['name'];
				} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
					$field_name = 'wholesalex_cf_' . $field['name'];
				}
                if ( in_array( $field['name'], $default_fields ) ) { // phpcs:ignore
					continue;
				}

				if ( isset( $_POST[ $field_name ] ) && isset( $field['type'] ) ) {

					$__value = '';

					switch ( $field['type'] ) {
						case 'email':
							$__value = sanitize_email( $_POST[ $field_name ] );
							break;
						case 'textarea':
							$__value = sanitize_textarea_field( $_POST[ $field_name ] );							
							break;
						default:
							if ( is_array( $_POST[ $field_name ] ) ) {
								$__value = wholesalex()->sanitize( $_POST[ $field_name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
							} else {
								$__value = sanitize_text_field( $_POST[ $field_name ] );
							}
							break;
					}
					
					update_user_meta( $user_id, $field_name, $__value );
				}
			}
		}


		if ( isset( $_POST['action'] ) && 'createuser' === sanitize_text_field( $_POST['action'] ) ) {
			update_user_meta( $user_id, '__wholesalex_status', 'active' );
		}
	}


	/**
	 * Add WholesaleX Role Column In All Users Page
	 *
	 * @param array $columns .
	 * @since 1.0.0
	 * @access public
	 * @return array $columns
	 */
	public function add_wholesalex_role_column( $columns ) {
		/* translators: %s - Plugin Name */
		$columns['wholesalex_role'] = sprintf( __( '%s Role', 'wholesalex' ), wholesalex()->get_plugin_name() );
		/* translators: %s - Plugin Name */
		$columns['wholesalex_status'] = sprintf( __( '%s Status', 'wholesalex' ), wholesalex()->get_plugin_name() );

		return $columns;
	}

	/**
	 * Manage WholesaleX Role Column In All Users Page
	 *
	 * @param String $value Value Name.
	 * @param String $column_name Columm Name.
	 * @param int    $user_id User ID.
	 * @since 1.0.0
	 * @access public
	 * @return array $columns
	 */
	public function manage_wholesalex_role_column( $value, $column_name, $user_id ) {
		if ( 'wholesalex_role' === $column_name ) {
			$__role_id      = get_user_meta( $user_id, '__wholesalex_role', true );
			$__role_content = wholesalex()->get_roles( 'by_id', $__role_id );
			$__title        = isset( $__role_content['_role_title'] ) ? $__role_content['_role_title'] : '';
			return $__title;
		}
		if ( 'wholesalex_status' === $column_name ) {
			$_wholesalex_status = get_user_meta( $user_id, '__wholesalex_status', true );
			if ( 'active' === $_wholesalex_status ) {
				return 'Active';
			} elseif ( 'pending' === $_wholesalex_status ) {
				return 'Waiting Approval';
			} elseif ( 'reject' === $_wholesalex_status ) {
				return 'Rejected';
			} elseif ( 'inactive' === $_wholesalex_status ) {
				return 'Inactive';
			}
		}
		return $value;
	}

	/**
	 * Add Role Filer
	 *
	 * @param String $which Role Filter Position ( top or bottom).
	 */
	public function add_role_filter( $which ) {
		$st      = '<select name="filter_wholesalex_role_%s" style="float:none;"><option value="">%s</option>%s</select>';
		$options = '';
		$roles   = wholesalex()->get_roles( 'roles_option' );
		$status  = isset( $_GET[ 'filter_wholesalex_role_' . $which ] ) ? sanitize_text_field( $_GET[ 'filter_wholesalex_role_' . $which ] ) : ''; // @codingStandardsIgnoreLine.
		foreach ( $roles as $option ) {
			$options .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $option['value'] ), selected( $status, $option['value'], false ), esc_html( $option['name'] ) );
		}

		$select = sprintf( $st, $which, __( '- Wholesale Role -', 'wholesalex' ), $options );
		echo wp_kses(
			$select,
			array(
				'select' => array(
					'name'  => array(),
					'style' => array(),
				),
				'option' => array( 'value' => array() ),
			)
		);

		submit_button( __( 'Filter', 'wholesalex' ), 'button', $which, false );
	}

	/**
	 * Filter Role Section
	 *
	 * @param WP_Query $query Query.
	 * @since 1.0.0
	 * @access public
	 */
	public function filter_user_section( $query ) {
		global $pagenow;
		$get_data = wholesalex()->sanitize( $_GET ); // @codingStandardsIgnoreLine.
		if ( is_admin() && 'users.php' === $pagenow ) {
			$button = key(
				array_filter(
					$get_data,
					function ( $v ) {
						return __( 'Filter', 'wholesalex' ) === $v;
					}
				)
			);
			if ( isset( $get_data[ 'filter_wholesalex_role_' . $button ] ) && !empty( $get_data[ 'filter_wholesalex_role_' . $button ] ) ) { // @codingStandardsIgnoreLine.
				$selected_role = $get_data[ 'filter_wholesalex_role_' . $button ]; // @codingStandardsIgnoreLine.
				$meta_query    = array(
					array(
						'key'     => '__wholesalex_role',
						'value'   => $selected_role,
						'compare' => '=',
					),
				);
				$query->set( 'meta_key', '__wholesalex_role' );
				// $query->set( 'meta_value', $selected_role );
				// $query->set( 'meta_compare', '=' );
				$query->set( 'meta_query', $meta_query );
			} elseif ( isset( $get_data[ 'filter_wholesalex_status_' . $button ] )  && !empty($get_data[ 'filter_wholesalex_status_' . $button ])) { // @codingStandardsIgnoreLine.
				$selected_status = $get_data[ 'filter_wholesalex_status_' . $button ]; // @codingStandardsIgnoreLine.
				$meta_query      = array(
					array(
						'key'   => '__wholesalex_status',
						'value' => $selected_status,
					),
				);
				$query->set( 'meta_key', '__wholesalex_status' );
				$query->set( 'meta_query', $meta_query );
			}
		}

		// return $query;
	}

	/**
	 * Add Status Filer
	 *
	 * @param String $which Status Filter Position ( top or bottom).
	 * @since 1.0.4
	 */
	public function add_status_filter( $which ) {
		$st             = '<select name="filter_wholesalex_status_%s" style="float:none;"><option value="">%s</option>%s</select>';
		$options        = '';
		$status_options = array(
			'pending'  => __( 'Waiting Approval', 'wholesalex' ),
			'active'   => __( 'Active', 'wholesalex' ),
			'inactive' => __( 'Inactive', 'wholesalex' ),
			'reject'   => __( 'Reject', 'wholesalex' ),
		);
		$status         = isset( $_GET[ 'filter_wholesalex_status_' . $which ] ) ? sanitize_text_field( $_GET[ 'filter_wholesalex_status_' . $which ] ) : ''; // @codingStandardsIgnoreLine.
		foreach ( $status_options as $key => $option ) {
			$options .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $status, $key, false ), esc_html( $option ) );
		}

		$select = sprintf( $st, $which, __( '- Wholesale Status -', 'wholesalex' ), $options );
		echo wp_kses(
			$select,
			array(
				'select' => array(
					'name'  => array(),
					'style' => array(),
				),
				'option' => array(
					'value'    => array(),
					'selected' => array(),
				),
			)
		);

		submit_button( __( 'Filter', 'wholesalex' ), 'button', $which, false );
	}

	/**
	 * Wolesalex Add Custom Columns Sortable.
	 */
	public function wholesalex_add_custom_columns_sortable() {
		$columns['wholesalex_role']   = 'wholesalex_role';
		$columns['wholesalex_status'] = 'wholesalex_status';
		return $columns;

	}

	/**
	 * Custom Sortable column handler
	 *
	 * @param WP_Query $query query.
	 * @return void
	 */
	public function wholesalex_custom_sortable_columns_handler( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		switch ( $orderby ) {
			case 'wholesalex_role':
				$query->set( 'meta_key', '_wholesalex_role' );
				$query->set( 'orderby', 'meta_value' );
				break;
			case 'wholesalex_status':
				$query->set( 'meta_key', '__wholesalex_status' );
				$query->set( 'orderby', 'meta_value' );
				break;

			default:
				break;
		}
	}

	/**
	 * Show WholesaleX Extra Fields on Edit Profile Page
	 *
	 * @param WP_User $user User Object.
	 * @since 1.0.0
	 * @since 1.0.1 Extra Fields Visible Based on Registration Role instead of WholesaleX Role.
	 * @since 1.0.3 File Field Added
	 * @since 1.0.9 Subaccount Check Added
	 */
	public function wholesalex_user_fields( $user ) {

		if ( ! ( isset( $user ) && isset( $user->ID ) ) ) {
			return;
		}

		$__fields = isset( $GLOBALS['wholesalex_registration_fields']['wholesalex_fields'])?$GLOBALS['wholesalex_registration_fields']['wholesalex_fields']:array();

		$__user_id = $user->ID;

		$account_type = get_user_meta( $__user_id, '__wholesalex_account_type', true );
		if ( 'subaccount' === $account_type ) {
			return;
		}

		$__role_id = get_user_meta( $__user_id, '__wholesalex_registration_role', true );

		if ( empty( $__role_id ) ) {
			$__role_id = get_user_meta( $__user_id, '__wholesalex_role', true );
		}

		if ( empty( $__fields ) || ! is_array( $__fields ) ) {
			return;
		}


		$default_fields     = array( 'user_login', 'user_pass', 'display_name', 'nickname', 'first_name', 'last_name', 'description', 'user_email', 'url', 'user_confirm_email', 'user_confirm_password', 'default_user_role', 'registration_role','wholesalex_registration_role','user_confirm_pass' );
		$__has_extra_fields = false;
		?>
			<h2 id="wholesalex_extra_information"><?php echo sprintf( esc_html__( '%s Extra Information', 'wholesalex' ), wholesalex()->get_plugin_name() ); //phpcs:ignore ?></h2>
			<table class="form-table">
				<?php

				foreach ($__fields as $field) {

					$user_role = wholesalex()->get_user_role($user->ID);
					if(empty($user_role)) {
						$user_role = get_user_meta($user->ID,'__wholesalex_registration_role',true);
					}

					if(isset($field['status']) && $field['status']) {
						if(isset($field['excludeRoles']) && is_array($field['excludeRoles']) && in_array($user_role,$field['excludeRoles'])) {
							continue; // Exclude For this user
						} else {

							if(!isset($field['title']) && isset($field['label'])) {
								$field['title'] = $field['label'];
							}

							if ( (!isset($field['name']) || !isset($field['title'] )) || in_array( $field['name'], $default_fields ) || ( isset($field['billing_connection']) && !empty($field['billing_connection']) )   ) { // phpcs:ignore
								continue;
							}
							$__has_extra_fields = true;
							$field_name = $field['name'];

							if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
								$field_name = $field['name'];
							} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
								$field_name = 'wholesalex_cf_' . $field['name'];
							}

							

							
							?>

								<tr>
									<th>
										<label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
									</th>
									<td>
										<?php

										switch ( $field['type'] ) {

											case 'select':
												if ( ! ( isset( $field['option'] ) && is_array( $field['option'] ) ) ) {
													break;
												}
												?>
													<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" class="regular-text" style="width: 25em;">
														<?php
														if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
															$selected = get_user_meta( $user->ID, $field['name'], true );
														} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
															$selected = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
														}
														foreach ( $field['option'] as $option ) :
															?>
															<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $selected, $option['value'], true ); ?>><?php echo esc_html( $option['name'] ); ?></option>
														<?php endforeach; ?>
													</select>
												<?php
												break;
											case 'checkbox':
												if ( ! ( isset( $field['option'] ) && is_array( $field['option'] ) ) ) {
													break;
												}

												if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
													$__selected_values = get_user_meta( $user->ID, $field['name'], true );
												} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
													$__selected_values = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
												}
												if ( empty( $__selected_values ) || ! is_array( $__selected_values ) ) {
													$__selected_values = array();
												}

												foreach ( $field['option'] as $option ) :
													?>
													<div>
														<input type="checkbox" name="<?php echo esc_attr( $field_name ) . '[]'; ?>" id="<?php echo esc_attr( $option['value'] ); ?>" value=<?php echo esc_attr( $option['value'] ); ?> class="regular-text" <?php checked( in_array( $option['value'], $__selected_values ), 1, true ); //phpcs:ignore ?> />

														<label for=<?php echo esc_attr( $option['value'] ); ?> > <?php echo esc_html( $option['name'] ); ?>  </label>
													</div>

													<?php
												endforeach;

												break;
											case 'radio':
												if ( ! ( isset( $field['option'] ) && is_array( $field['option'] ) ) ) {
													break;
												}

												if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
													$__selected_value = get_user_meta( $user->ID, $field['name'], true );
												} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
													$__selected_value = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
												}
												foreach ( $field['option'] as $option ) :
													?>
													<div>
														<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $option['value'] ); ?>" value=<?php echo esc_attr( $option['value'] ); ?> class="regular-text" <?php checked( $__selected_value === $option['value'], 1, true ); ?> />

														<label for=<?php echo esc_attr( $option['value'] ); ?> > <?php echo esc_html( $option['name'] ); ?>  </label>
													</div>

													<?php
												endforeach;
												break;
											case 'file':
												// $__value = get_user_meta( $user->ID, 'file_' . $field['name'], true );

												if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
													$__value = get_user_meta( $user->ID, 'file_'.$field['name'], true );
												} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
													$__value = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
												}
												if ( ! is_wp_error( $__value ) && ! empty( $__value ) ) {
													$__url = wp_get_attachment_url( $__value );
													if ( $__url ) {
														?>
														<div class="wholesalex_download_file"><a href="<?php echo esc_url_raw( $__url ); ?>"><?php esc_html_e( 'Download File', 'wholesalex' ); ?></a></div>
														<?php
													} else {
														?>
													<input type='text' class="wholesalex_download_file_not_exist" readonly value="<?php esc_html_e( 'The file does not exist.', 'wholesalex' ); ?>"/>
														<?php
													}
												} else {
													?>
													<input type='text' class="wholesalex_download_file_not_exist" readonly value="<?php esc_html_e( 'The file does not exist.', 'wholesalex' ); ?>"/>
													<?php
												}
												?>

												<?php
												break;
											case 'textarea':
												if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
													$__value = get_user_meta( $user->ID, $field['name'], true );
												} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
													$__value = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
												}
												?>
												<textarea name="<?php echo esc_attr( $field_name); ?>" id="<?php echo esc_attr( $field_name); ?>" value="<?php echo esc_attr( $__value ); ?>"><?php echo esc_attr( $__value ); ?></textarea>
												<?php
												break;

											default:
												$__value = '';
												if(isset($field['migratedFromOldBuilder']) && $field['migratedFromOldBuilder']  ) {
													$__value = get_user_meta( $user->ID, $field['name'], true );
												} else if ( isset( $field['custom_field'] ) && $field['custom_field'] ) {
													$__value = get_user_meta( $user->ID, 'wholesalex_cf_' . $field['name'], true );
												}
												
												?>
												<input type=<?php echo esc_attr( $field['type'] ); ?> name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $__value ); ?>" class="regular-text" />
												<?php
												break;
										}

										?>
									</td>
								</tr>

							<?php

						}
					}
					

				}
				?>
				
			</table>

			<?php
			if ( ! $__has_extra_fields ) {
				?>
				<style>
					#wholesalex_extra_information{
						display: none;
					}
				</style>
				<?php
			}

	}


		/**
		 * Get Shipping Zones
		 *
		 * @since 1.2.4
		 */

	public static function get_shipping_zones() {
		$data_store           = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones            = $data_store->get_zones();
		$zones                = array();
		$__shipping_zones     = array();
		$__shipping_methods   = array();
		$__shipping_zones[''] = __( 'Choose Shipping Zone...', 'wholesalex' );
		foreach ( $raw_zones as $raw_zone ) {
			$zone                         = new WC_Shipping_Zone( $raw_zone );
			$zone_id                      = $zone->get_id();
			$zone_name                    = $zone->get_zone_name();
			$__shipping_zones[ $zone_id ] = $zone_name;
		}

		return $__shipping_zones;
	}

	/**
	 * Get Shipping Methods by zone id
	 *
	 * @param string $zone_id Zone ID.
	 * @return array
	 */
	public function get_shipping_methods( $zone_id ) {
		$zone                  = WC_Shipping_Zones::get_zone( $zone_id );
		$zone_shipping_methods = $zone->get_shipping_methods();

		$__shipping_methods = array();

		foreach ( $zone_shipping_methods as $key => $method ) {
			if ( $method->is_enabled() ) {
				$method_instance_id   = $method->get_instance_id();
				$method_title         = $method->get_title();
				$__shipping_methods[] = array(
					'value' => strval( $method_instance_id ),
					'name'  => $method_title,
				);
			}
		}

		return array(
			'status' => true,
			'data'   => $__shipping_methods,
		);
	}

	/**
	 * Get WholesaleX Profile Fields
	 */
	public function get_profile_fields() {
		// Roles Options.
		$__roles_options = wholesalex()->get_roles( 'mapped_roles' );

		return apply_filters(
			'wholesalex_profile_fields',
			array(
				'_profile_settings' => array(
					/* translators: %s - Plugin Name */
					'label' => sprintf( __( '%s Profile Settings', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'attr'  => array(
						'_tax_section'                   => array(
							'label' => __( 'Override Tax Exemption', 'wholesalex' ),
							'attr'  => array(
								'_wholesalex_profile_override_tax_exemption' => array(
									'type'        => 'select',
									'label'       => __( 'Tax Exemption', 'wholesalex' ),
									'options'     => array(
										''    => __( 'Select Tax Exeption Status...', 'wholesalex' ),
										'yes' => __( 'Yes', 'wholesalex' ),
										'no'  => __( 'No', 'wholesalex' ),
									),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
							),

						),
						'_shipping_section'              => array(
							'label' => __( 'Override Shipping Method', 'wholesalex' ),
							'attr'  => array(
								'_wholesalex_profile_override_shipping_method' => array(
									'type'        => 'select',
									'label'       => __( 'Shipping Options', 'wholesalex' ),
									'options'     => array(
										''    => __( 'Select Shipping Option Override Status...', 'wholesalex' ),
										'yes' => __( 'Yes', 'wholesalex' ),
										'no'  => __( 'No', 'wholesalex' ),
									),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_wholesalex_profile_shipping_method_type' => array(
									'type'        => 'select',
									'label'       => __( 'Shipping Method Type', 'wholesalex' ),
									'depends_on'  => array(
										array(
											'key'   => '_wholesalex_profile_override_shipping_method',
											'value' => 'yes',
										),
									),
									'options'     => array(
										'' => __( 'Choose Shipping Method...', 'wholesalex' ),
										'force_free_shipping' => __( 'Force Free Shipping', 'wholesalex' ),
										'specific_shipping_methods' => __( 'Specify Shipping Methods', 'wholesalex' ),
									),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_wholesalex_profile_shipping_zone' => array(
									'type'        => 'select',
									'label'       => __( 'Shipping Zone', 'wholesalex' ),
									'depends_on'  => array(
										array(
											'key'   => '_wholesalex_profile_override_shipping_method',
											'value' => 'yes',
										),
										array(
											'key'   => '_wholesalex_profile_shipping_method_type',
											'value' => 'specific_shipping_methods',
										),
									),
									'options'     => self::get_shipping_zones(),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_shipping_zones',
									'ajax_search' => false,
								),
								'_wholesalex_profile_shipping_zone_methods' => array(
									'type'                 => 'multiselect',
									'label'                => __( 'Shipping Zone Methods', 'wholesalex' ),
									'depends_on'           => array(
										array(
											'key'   => '_wholesalex_profile_override_shipping_method',
											'value' => 'yes',
										),
										array(
											'key'   => '_wholesalex_profile_shipping_method_type',
											'value' => 'specific_shipping_methods',
										),
									),
									'options_dependent_on' => '_wholesalex_profile_shipping_zone',
									'options'              => array(),
									'default'              => '',
									'placeholder'          => '',
									'help'                 => '',
									'is_ajax'              => true,
									'ajax_action'          => 'get_shipping_methods',
									'ajax_search'          => false,
								),
							),
						),
						'_payment_gateway_section'       => array(
							'label' => __( 'Override Payment Gateway Options', 'wholesalex' ),
							'attr'  => array(
								'_wholesalex_profile_override_payment_gateway' => array(
									'type'        => 'select',
									'label'       => __( 'Payment Gateway Options', 'wholesalex' ),
									'options'     => array(
										''    => __( 'Override Payment Gateway...', 'wholesalex' ),
										'yes' => __( 'Yes', 'wholesalex' ),
										'no'  => __( 'No', 'wholesalex' ),
									),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_wholesalex_profile_payment_gateways' => array(
									'type'        => 'multiselect',
									'label'       => __( 'Payment Gateways', 'wholesalex' ),
									'depends_on'  => array(
										array(
											'key'   => '_wholesalex_profile_override_payment_gateway',
											'value' => 'yes',
										),
									),
									'options'     => array(),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_payment_gateways',
									'ajax_search' => false,
								),
							),
						),
						'_profile_discounts_section'     => array(
							'label' => '',
							'attr'  => array(
								'_profile_discounts' => array(
									/* translators: %s - Plugin Name */
									'label'    => sprintf( __( '%s Profile Discount', 'wholesalex' ), wholesalex()->get_plugin_name() ),
									'type'     => 'tiers',
									'is_pro'   => true,
									'pro_data' => array(
										'type'  => 'limit',
										'value' => 3,
									),
									'attr'     => apply_filters(
										'wholesalex_profile_discounts_fields',
										array(
											'discounts' => array(
												'type'   => 'tier',
												'_tiers' => array(
													'columns' => array(
														__( 'Discount Type', 'wholesalex' ),
														__( 'Amount', 'wholesalex' ),
														__( 'Min Quantity', 'wholesalex' ),
														__( 'Product Filter', 'wholesalex' ),
													),
													'data' => array(
														'_discount_type'        => array(
															'type'    => 'select',
															'options' => array(
																''           => __( 'Choose Discount Type...', 'wholesalex' ),
																'amount'     => __( 'Discount Amount', 'wholesalex' ),
																'percentage' => __( 'Discount Percentage', 'wholesalex' ),
																'fixed'      => __( 'Fixed Price', 'wholesalex' ),
															),
															'default' => '',
															'label' => __( 'Discount Type', 'wholesalex' ),
														),
														'_discount_amount'      => array(
															'type'        => 'number',
															'placeholder' => '',
															'default'     => '',
															'label' => __( 'Amount', 'wholesalex' ),
														),
														'_min_quantity'         => array(
															'type'        => 'number',
															'placeholder' => '',
															'default'     => '',
															'label' => __( 'Min Quantity', 'wholesalex' ),
														),
														'_product_filter'       => array(
															'type'    => 'select',
															'options' => array(
																''                      => __( 'Choose Filter...', 'wholesalex' ),
																'all_products'          => __( 'All Products', 'wholesalex' ),
																'products_in_list'      => __( 'Product in list', 'wholesalex' ),
																'products_not_in_list'  => __( 'Product not in list', 'wholesalex' ),
																'cat_in_list'           => __( 'Categories in list', 'wholesalex' ),
																'cat_not_in_list'       => __( 'Categories not in list', 'wholesalex' ),
																'attribute_in_list'     => __( 'Attribute in list', 'wholesalex' ),
																'attribute_not_in_list' => __( 'Attribute not in list', 'wholesalex' ),
															),
															'default' => '',
															'label' => __( 'Product Filter', 'wholesalex' ),
														),
														'products_in_list'      => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'products_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Products to apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_products',
															'ajax_search' => true,
														),
														'products_not_in_list'  => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'products_not_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Products that wont apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_products',
															'ajax_search' => true,
														),
														'cat_in_list'           => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'cat_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Categories to apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_categories',
															'ajax_search' => true,
														),
														'cat_not_in_list'       => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'cat_not_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Categories that wont apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_categories',
															'ajax_search' => true,
														),
														'attribute_in_list'     => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'attribute_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Product Variations to apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_variation_products',
															'ajax_search' => true,
														),
														'attribute_not_in_list' => array(
															'type'        => 'multiselect',
															'depends_on'  => array(
																array(
																	'key'   => '_product_filter',
																	'value' => 'attribute_not_in_list',
																),
															),
															'options'     => array(),
															'placeholder' => __( 'Choose Product Variations that wont apply discounts', 'wholesalex' ),
															'default'     => array(),
															'is_ajax'     => true,
															'ajax_action' => 'get_variation_products',
															'ajax_search' => true,
														),
													),
													'add'  => array(
														'type' => 'button',
														'label' => __( 'Add Price Tier', 'wholesalex' ),
													),
													'upgrade_pro' => array(
														'type'  => 'button',
														'label' => __( 'Go For Unlimited Price Tiers', 'wholesalex' ),
													),
												),
											),
										)
									),
								),
							),
						),
						'_profile_user_settings_section' => array(
							/* translators: %s - Plugin Name */
							'label' => sprintf( __( '%s User Settings', 'wholesalex' ), wholesalex()->get_plugin_name() ),
							'attr'  => array(
								'_wholesalex_role' => array(
									'type'    => 'select',
									/* translators: %s - Plugin Name */
									'label'   => sprintf( __( '%s Role', 'wholesalex' ), wholesalex()->get_plugin_name() ),
									'options' =>
										/* translators: %s - Plugin Name */
										array( '' => sprintf( __( '--Select %s Role--', 'wholesalex' ), wholesalex()->get_plugin_name() ) ) +
										$__roles_options,
									'default' => '',
								),
								'__wholesalex_registration_role' => array(
									'type'       => 'text',
									'is_disable' => true,
									'label'      => __( 'Registration Role', 'wholesalex' ),
									'value'      => '',
									'default'    => '',
								),
								'_buttons'         => array(
									'type'         => 'buttons',

									'btn_approve'  => __( 'Approve Request', 'wholesalex' ),
									'btn_reject'   => __( 'Reject Request', 'wholesalex' ),
									'btn_delete'   => __( 'Delete User', 'wholesalex' ),
									'btn_active'   => __( 'Active User', 'wholesalex' ),
									'btn_deactive' => __( 'Deactivated User', 'wholesalex' ),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Change Free Shipping Title
	 *
	 * @param string $title Shiping Title.
	 * @since 1.1.7
	 */
	public function change_free_shipping_title( $title ) {
		if ( wholesalex()->get_setting( '_language_profile_force_free_shipping_text' ) ) {
			$title = wholesalex()->get_setting( '_language_profile_force_free_shipping_text' );
		}

		return $title;
	}


	public function add_custom_field_on_wc_myaccount() {

	}

}
