<?php
/**
 * WholesaleX Users
 *
 * @package WHOLESALEX
 * @since v.1.0.0
 */

namespace WHOLESALEX;

use WP_User_Query;

/**
 * WholesaleX Users Class
 */
class WHOLESALEX_Users {

	/**
	 * WholesaleX User Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'users_page_submenu' ) );
		add_action( 'rest_api_init', array( $this, 'register_users_restapi' ) );
	}

	/**
	 * Users Page Submenu
	 *
	 * @return void
	 */
	public function users_page_submenu() {
		$slug = apply_filters( 'wholesalex_users_submenu_slug', 'wholesalex-users' );
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__( 'Users', 'wholesalex' ),
			__( 'Users', 'wholesalex' ),
			apply_filters( 'wholesalex_capability_access', 'manage_options' ),
			$slug,
			array( $this, 'users_page_content' )
		);
	}

	/**
	 * Users Submenu Page Content
	 *
	 * @return void
	 */
	public function users_page_content() {
		wp_enqueue_script( 'whx_users' );
		wp_enqueue_script( 'wholesalex_node_vendors' );
		wp_enqueue_script( 'wholesalex_components' );

		$heading_data = array();

		// Prepare as heading data.
		foreach ( self::get_wholesalex_users_columns() as $key => $value ) {
			$data               = array();
			$data['all_select'] = '';
			$data['name']       = $key;
			$data['title']      = $value;
			if ( 'action' == $key ) {
				$data['type'] = '3dot';
			} elseif ( 'wallet_balance' == $key || 'account_type' == $key ) {
				$data['type'] = 'html';
			} else {
				$data['type'] = 'text';
			}

			$heading_data[ $key ] = $data;
		}

		$heading_data['user_id']['status']          = 'yes';
		$heading_data['username']['status']          = 'yes';
		$heading_data['full_name']['status']         = 'yes';
		$heading_data['email']['status']             = 'yes';
		$heading_data['registration_date']['status'] = 'yes';
		$heading_data['wholesalex_role']['status']   = 'yes';
		$heading_data['wholesalex_status']['status'] = 'yes';
		$heading_data['action']['status']            = 'yes';

		wp_localize_script(
			'whx_users',
			'whx_users',
			array(
				'heading'            => $heading_data,
				'user_per_page'      => 10,
				'bulk_actions'       => $this->get_wholesalex_users_bulk_actions(),
				'statuses'           => wholesalex()->insert_into_array(
					array( '' => __( 'Select Status', 'wholesalex' ) ),
					$this->get_user_statuses(),
					0
				),
				'exportable_columns' => ImportExport::exportable_user_columns(),
				'roles' => $this->get_role_options(),
				'i18n' => array(
					'users' => __('Users','wholesalex'),
					'edit' => __('Edit','wholesalex'),
					'active' => __('Active','wholesalex'),
					'reject' => __('Reject','wholesalex'),
					'pending' => __('Pending','wholesalex'),
					'delete' => __('Delete','wholesalex'),
					'selected_users' => __('Selected Users','wholesalex'),
					'apply' => __('Apply','wholesalex'),
					'import' => __('Import','wholesalex'),
					'export' => __('Export','wholesalex'),
					'columns' => __('Columns','wholesalex'),
					'no_users_found' => __('No Users Found!','wholesalex'),
					'showing' => __('Showing','wholesalex'),
					'pages' => __('Pages','wholesalex'),
					'of' => __('of','wholesalex'),
					'please_select_valid_csv_file' => __('Please Select a valid csv file to process import!','wholesalex'),
					'please_wait_to_complete_existing_import_request' => __('Please Wait to complete existing import request!','wholesalex'),
					'error_occured' => __('Error Occured!','wholesalex'),
					'import_successful' => __('Import Sucessful','wholesalex'),
					'users_updated' => __('Users Updated','wholesalex'),
					'users_inserted' => __('Users Inserted','wholesalex'),
					'users_skipped' => __('Users Skipped','wholesalex'),
					'download' => __('Download','wholesalex'),
					'log_for_more_info' => __('Log For More Info','wholesalex'),
					'close' => __('Close','wholesalex'),
					'username' => __('Username','wholesalex'),
					'email' => __('Email','wholesalex'),
					'upload_csv' => __('Upload CSV','wholesalex'),
					'you_can_upload_only_csv_file' => __('You can upload only csv file format','wholesalex'),
					'update_existing_users' => __('Update Existing Users','wholesalex'),
					'update_existing_users_message' => __('Selecting "Update Existing Users" will only update existing users. No new user will be added.','wholesalex'),
					'find_existing_user_by' => __('Find Existing Users By:','wholesalex'),
					'option_to_detect_user' => __("Option to detect user from the uploaded CSV's email or username field.",'wholesalex'),
					'process_per_iteration' => __("Process Per Iteration",'wholesalex'),
					'low_process_ppi' => __("Low process per iteration (PPI) increases the import's accuracy and success rate. A (PPI) higher than your server's maximum execution time might fail the import.",'wholesalex'),
					'import' => __("Import",'wholesalex'),
					'import_users' => __("Import Users",'wholesalex'),
					'select_fields_to_export' => __("Select Fields to Export",'wholesalex'),
					'csv_comma_warning' => __("Warning: If any of the fields contain a comma (,), it might break the CSV file. Ensure the selected column value contains no comma(,).",'wholesalex'),
					'download_csv' => __("Download CSV",'wholesalex'),
					'export_users' => __("Export Users",'wholesalex'),
				)
			)
		);

		?>
		<div id="wholeslex_users_root"></div>
		<?php
	}

	public function get_role_options() {
		$roles = get_option('_wholesalex_roles',array());
		$roles_option=array(''=>__('--Select Role--','wholesalex'));
		foreach ($roles as $role) {
			if('wholesalex_guest'==$role['id']){
				continue;
			}
			$roles_option[$role['id']]= $role['_role_title'];
		}
		return $roles_option;
	}


	/**
	 * Register Users RestAPI Scripts
	 *
	 * @return void
	 */
	public function register_users_restapi() {
		register_rest_route(
			'wholesalex/v1',
			'/users/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'users_restapi_callback' ),
					'permission_callback' => function () {
						return current_user_can( apply_filters( 'wholesalex_capability_access', 'manage_options' ) );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Users RestAPI Callback
	 *
	 * @param object $server Server
	 * @return void
	 */
	public function users_restapi_callback( $server ) {
		$post = $server->get_params();
		
		// Nonce validation
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh the page and try again.', 'wholesalex' ) ) );
			return;
		}
	
		$type = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';
	
		$response = array(
			'status' => false,
			'data'   => array(),
		);
	
		switch ( $type ) {
			case 'get':
				$page         = isset( $post['page'] ) ? sanitize_text_field( $post['page'] ) : 1;
				$user_status  = isset( $post['status'] ) ? sanitize_text_field( $post['status'] ) : '';
				$user_role    = isset( $post['role'] ) ? sanitize_text_field( $post['role'] ) : '';
				$search_query = isset( $post['search'] ) ? sanitize_text_field( $post['search'] ) : '';
	
				$response['status'] = true;
				$response['data']   = $this->get_wholesale_users( 10, $page, $user_status, $search_query, $user_role );
				break;
	
			case 'update_status':
				$action = isset( $post['user_action'] ) ? sanitize_text_field( $post['user_action'] ) : '';
				$id     = isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '';
	
				// Validate action and user ID
				if ( empty( $action ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid Action!', 'wholesalex' ) ) );
					return;
				}
				if ( empty( $id ) || ! get_userdata( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid User ID!', 'wholesalex' ) ) );
					return;
				}
	
				// Perform the action
				$this->handle_user_action( $action, $id );
	
				$response['status'] = true;
				$response['data']   = ( 'delete' === $action ) ? __( 'Successfully Deleted', 'wholesalex' ) : __( 'Successfully Updated', 'wholesalex' );
				break;
	
			case 'bulk_action':
				$action = isset( $post['user_action'] ) ? sanitize_text_field( $post['user_action'] ) : '';
				$ids    = isset( $post['ids'] ) ? wholesalex()->sanitize( $post['ids'] ) : array();
	
				// Validate action
				if ( empty( $action ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid Action!', 'wholesalex' ) ) );
					return;
				}
	
				// Validate user IDs
				if ( empty( $ids ) || ! is_array( $ids ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid User IDs!', 'wholesalex' ) ) );
					return;
				}
	
				foreach ( $ids as $id ) {
					if ( ! get_userdata( $id ) ) {
						wp_send_json_error( array( 'message' => __( 'One or more User IDs are invalid.', 'wholesalex' ) ) );
						return;
					}
				}
	
				// Perform bulk actions
				$this->bulk_actions( $action, $ids );
	
				$response['status'] = true;
				if ( 'delete' == $action ) {
					$response['data'] = __( 'Successfully Deleted', 'wholesalex' );
				} elseif ( wholesalex()->start_with( $action, 'change_role_to_' ) ) {
					$response['data'] = __( 'Role Successfully Changed.', 'wholesalex' );
				} else {
					$response['data'] = __( 'Successfully Updated', 'wholesalex' );
				}
				break;
	
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid request type.', 'wholesalex' ) ) );
				return;
		}
	
		wp_send_json( $response );
	}

	/**
	 * Get User Statuses
	 *
	 * @return array
	 */
	public function get_user_statuses() {
		$statuses = array(
			'active'  => 'Active',
			'pending' => 'Pending',
			'reject'  => 'Reject',
			'decline' => 'Decline',
		);

		$statuses = apply_filters( 'wholesalex_users_statuses', $statuses );

		return $statuses;
	}

	/**
	 * Get Bulk User Actions
	 *
	 * @return array
	 */
	public function get_wholesalex_users_bulk_actions() {
		
		$actions = array(
			'delete'  => 'Delete Users',
			'pending' => 'Set Status to Pending',
			'active'  => 'Set Status to Active',
			'reject'  => 'Set Status to Reject',
		);
		$actions = apply_filters( 'wholesalex_users_bulk_actions', $actions );

		$optionsGroups = array(
			'action' => array(
				'label'=> 'Status',
				'options' => $actions
			)
		);

		$roles = get_option('_wholesalex_roles',array());
		$roles_option = array();
		foreach ($roles as $role) {
			if('wholesalex_guest'==$role['id']){
				continue;
			}
			$roles_option['change_role_to_'.$role['id']]= __('Change Role to ','wholesalex').$role['_role_title'];
		}

		$optionsGroups['roles_action'] = array('label'=>__('Roles','wholesalex'),'options'=> $roles_option);


		return $optionsGroups;
	}

	/**
	 * Get Users Columns
	 *
	 * @return array
	 */
	public function get_wholesalex_users_columns() {
		$columns = array(
			'user_id'          	=> __( 'ID', 'wholesalex' ),
			'username'          => __( 'Username', 'wholesalex' ),
			'full_name'         => __( 'Full Name', 'wholesalex' ),
			'email'             => __( 'Email', 'wholesalex' ),
			'registration_date' => __( 'Date', 'wholesalex' ),
			'wholesalex_role'   => __( 'Role', 'wholesalex' ),
			'wholesalex_status' => __( 'Status', 'wholesalex' ),
		);

		$columns = apply_filters( 'wholesalex_users_columns', $columns );

		$columns = wholesalex()->insert_into_array( $columns, array( 'action' => __( 'Action', 'wholesalex' ) ) );

		return $columns;
	}

	/**
	 * Get Wholesale Users
	 *
	 * @param integer $user_per_page User Per Page.
	 * @param integer $page Page.
	 * @param string  $status Account Status.
	 * @param string  $search_query Search Query.
	 * @return array
	 */
	public function get_wholesale_users( $user_per_page = -1, $page = 1, $status = '', $search_query = '',$role='' ) {
		$user_fields = array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' );
		$meta_query = array(
			'relation'=> 'OR',
			array(
				'key'     => '__wholesalex_status',
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'key'     => '__wholesalex_role',
				'value'   => '',
				'compare' => '!=',
			),
		);

		if(''!=$status && ''!=$role) {
			$meta_query = array(
				'relation'=> 'AND',
				array(
					'key'     => '__wholesalex_status',
					'value'   => $status,
					'compare' => '=',
				),
				array(
					'key'     => '__wholesalex_role',
					'value'   => $role,
					'compare' => '=',
				),
			);
		}

		if ( '' !== $status && ''==$role ) {
			$meta_query = array(
				array(
					'key'     => '__wholesalex_status',
					'value'   => $status,
					'compare' => '=',
				)
			);
		}
		if ( '' !== $role && ''==$status ) {
			$meta_query = array(
				array(
					'key'     => '__wholesalex_role',
					'value'   => $role,
					'compare' => '=',
				)
			);
		}

		$args = array(
			'meta_query'  => $meta_query,
			'orderby'     => 'registered',
			'order'       => 'DESC',
			'number'      => $user_per_page,
			'paged'       => $page,
			'fields'      => 'all',
			'count_total' => true,
			'search'      => '*' . $search_query . '*',
		);
		

		$user_search = new WP_User_Query( $args );

		$users = (array) $user_search->get_results();

		$total_users = $user_search->get_total();

		$column_value = array();

		foreach ( $users as $key => $user ) {
			$user_id            = $user->ID;
			$wholesalex_role_id = get_user_meta( $user_id, '__wholesalex_role', true );

			$user_data       = array();
			$user_data['ID'] = $user_id;

			foreach ( $this->get_wholesalex_users_columns() as $column_id => $column_name ) {
				switch ( $column_id ) {
					case 'user_id':
						$user_data[ $column_id ] = $user->ID;
						break;
					case 'username':
						$user_data[ $column_id ] = $user->user_login;
						break;
					case 'full_name':
						$user_data[ $column_id ] = $user->display_name;
						break;
					case 'email':
						$user_data[ $column_id ] = $user->user_email;
						break;
					case 'registration_date':
						$user_data[ $column_id ] = $user->user_registered;
						break;
					case 'wholesalex_role':
						$__user_role = get_user_meta( $user_id, '__wholesalex_registration_role', true );
						$wholesalex_role_id = ( ! empty( $wholesalex_role_id ) ? $wholesalex_role_id : $__user_role );
						$user_data[ $column_id ] = wholesalex()->get_role_name_by_role_id( $wholesalex_role_id );
						break;
					case 'wholesalex_status':
						$user_data[ $column_id ] = wholesalex()->get_user_status( $user_id );
						break;

					default:
						$user_data[ $column_id ] = apply_filters( 'wholesalex_users_column_value', $column_id, (array) $user, $column_name );
						break;
				}
			}

			$user_data['edit_profile'] = get_edit_user_link( $user_id );

			$column_value[] = $user_data;

		}

		return array(
			'users'       => $column_value,
			'total_users' => $total_users,
		);
	}


	/**
	 * Users Bulk Actions
	 *
	 * @param string $action Action.
	 * @param array  $ids User IDs.
	 * @return void
	 */
	public function bulk_actions( $action, $ids ) {
		switch ( $action ) {
			case 'active':
			case 'pending':
			case 'reject':
			case 'delete':
				if ( is_array( $ids ) ) {
					foreach ( $ids as $id ) {
						$this->handle_user_action( $action, $id );
					}
				}
				break;
			default:
				// code...
				break;
		}

		if(wholesalex()->start_with($action,'change_role_to_')) {
			$role = str_replace('change_role_to_','',$action);
			if( wp_roles()->is_role( $role )) {
				if ( is_array( $ids ) ) {
					foreach ( $ids as $id ) {
						$__user_role = get_user_meta( $id, '__wholesalex_role', true );
						if ( empty( $__user_role ) ) {
							$__user_role = get_user_meta( $id, '__wholesalex_registration_role', true );
						}
						wholesalex()->change_role( $id,$role, $__user_role );
					}
				}
			}
		}
	}

	/**
	 * Handle User Action
	 *
	 * @param string $action Action.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	public function handle_user_action( $action, $user_id ) {

		switch ( $action ) {
			case 'active':
			case 'pending':
			case 'reject':
				$old_status = get_user_meta( $user_id, '__wholesalex_status', true );

				if ( $old_status !== $action ) {
					// proceed
					update_user_meta( $user_id, '__wholesalex_status', $action );
					do_action( 'wholesalex_set_status_' . $action, $user_id, $old_status );
				}

				$__user_role = get_user_meta( $user_id, '__wholesalex_role', true );
				if ( empty( $__user_role ) ) {
					$__registration_role = get_user_meta( $user_id, '__wholesalex_registration_role', true );
					if ( ! empty( $__registration_role ) ) {
						wholesalex()->change_role( $user_id, $__registration_role );
					}
				}

				break;
			case 'delete':
				require_once (ABSPATH.'wp-admin/includes/user.php');
				wp_delete_user( $user_id );
				break;

			default:
				// code...
				break;
		}
	}
}
