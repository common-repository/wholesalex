<?php
/**
 * Common Functions.
 *
 * @package WHOLESALEX\Functions
 * @since v.1.0.0
 */

namespace WHOLESALEX;

use WP_Query;
use WP_User;
use WHOLESALEX\WHOLESALEX_Email;


defined( 'ABSPATH' ) || exit;

/**
 * Functions class.
 */
class Functions {

	/**
	 * Setup class.
	 *
	 * @since v.1.0.0
	 */
	public function __construct() {

		if ( ! isset( $GLOBALS['wholesalex_settings'] ) ) {
			$GLOBALS['wholesalex_settings'] = get_option( 'wholesalex_settings' );
		}
		if ( ! isset( $GLOBALS['wholesalex_single_product_settings'] ) ) {
			$GLOBALS['wholesalex_single_product_settings'] = get_option( '__wholesalex_single_product_settings', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_single_product_discounts'] ) ) {
			$GLOBALS['wholesalex_single_product_discounts'] = get_option( '__wholesalex_single_product_discounts', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_dynamic_rules'] ) ) {
			$GLOBALS['wholesalex_dynamic_rules'] = get_option( '__wholesalex_dynamic_rules', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_roles'] ) ) {
			$GLOBALS['wholesalex_roles'] = get_option( '_wholesalex_roles', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_category_settings'] ) ) {
			$GLOBALS['wholesalex_category_settings'] = get_option( '__wholesalex_category_settings', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_category_discounts'] ) ) {
			$GLOBALS['wholesalex_category_discounts'] = get_option( '__wholesalex_category_discounts', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_profile_settings'] ) ) {
			$GLOBALS['wholesalex_profile_settings'] = get_option( '__wholesalex_profile_settings', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_profile_discounts'] ) ) {
			$GLOBALS['wholesalex_profile_discounts'] = get_option( '__wholesalex_profile_discounts', array() );
		}
		if ( ! isset( $GLOBALS['wholesalex_registration_fields'] ) ) {
			$GLOBALS['wholesalex_registration_fields'] = $this->get_form_fields();
		}

		$this->update_single_product_database();

	}

	public function get_form_fields() {
		$woo_custom_fields   = array();
		$registration_fields = array();
		$billing_fields      = array();
		$myaccount_fields    = array();

		$fields = $this->get_new_form_builder_data();

		if ( isset( $fields['registrationFields'] ) && is_array( $fields['registrationFields'] ) ) {
			foreach ( $fields['registrationFields'] as $row ) {
				if ( isset( $row['columns'] ) && is_array( $row['columns'] ) ) {
					foreach ( $row['columns'] as $field ) {
						if ( ( isset( $field['status'] ) && $field['status'] ) ) {
							if ( isset( $field['isAddToWooCommerceRegistration'] ) && $field['isAddToWooCommerceRegistration'] ) {
								$woo_custom_fields[] = $field;
							}
							if ( isset( $field['enableForBillingForm'] ) && $field['enableForBillingForm'] ) {
								$billing_fields[] = $field;
							}
							if ( isset( $field['isEditableByUser'] ) && $field['isEditableByUser'] ) {
								$myaccount_fields[] = $field;
							}
							$registration_fields[] = $field;
						}
					}
				}
			}
		}

		return array(
			'woo_custom_fields' => $woo_custom_fields,
			'wholesalex_fields' => $registration_fields,
			'billing_fields'    => $billing_fields,
			'myaccount_fields'  => $myaccount_fields,
		);
	}
	/**
	 * Get All WholesaleX Roles
	 *
	 * @param string $type Type.
	 * @param mixed  $id ID.
	 * @return array $roles_options.
	 */
	public function get_roles( $type = '', $id = '' ) {
		$__roles = $GLOBALS['wholesalex_roles'];

		$__plugin_status = wholesalex()->get_setting( '_settings_status' );
		$__plugin_status = empty( $__plugin_status ) ? 'b2b' : $__plugin_status;

		$__enable_guest = apply_filters( 'wholesalex_enable_guest', true );
		if ( 'ids' === $type ) {
			return array_keys( $__roles );
		}
		if ( '' === $id && ( '' === $type || 'all' === $type ) ) {
			return isset( $__roles ) ? $__roles : array();
		} else {
			$roles_option      = array();
			$mapped_roles      = array();
			$b2b_roles_option  = array();
			$b2b_mapped_roles  = array();
			$b2c_roles_option  = array();
			$b2c_mapped_roles  = array();
			$guest_role_option = array(
				'value' => 'wholesalex_guest',
				'name'  => 'Guest Users',
			);
			if ( isset( $GLOBALS['wholesalex_roles'] ) ) {
				foreach ( $__roles as $role ) {
					if ( ! ( isset( $role['id'] ) && isset( $role['_role_title'] ) ) ) {
						continue;
					}
					$roles_option[]              = array(
						'value' => $role['id'],
						'name'  => $role['_role_title'],
					);
					$mapped_roles[ $role['id'] ] = $role['_role_title'];

					if ( ! ( 'wholesalex_b2c_users' === $role['id'] || 'wholesalex_guest' === $role['id'] ) ) {
						$b2b_roles_option[]              = array(
							'value' => $role['id'],
							'name'  => $role['_role_title'],
						);
						$b2b_mapped_roles[ $role['id'] ] = $role['_role_title'];
					} elseif ( ( 'wholesalex_b2c_users' === $role['id'] || ( $__enable_guest && 'wholesalex_guest' === $role['id'] ) ) ) {
						$b2c_roles_option[]              = array(
							'value' => $role['id'],
							'name'  => $role['_role_title'],
						);
						$b2c_mapped_roles[ $role['id'] ] = $role['_role_title'];
					}
				}
			}
			if ( '' === $id ) {
				switch ( $type ) {
					case 'roles_option':
						return $roles_option;
					case 'mapped_roles':
						return $mapped_roles;
					case 'b2b_roles_option':
						if ( 'b2b' === $__plugin_status || 'b2b_n_b2c' === $__plugin_status ) {
							return $b2b_roles_option;
						} else {
							return array();
						}
					case 'b2b_mapped_roles':
						if ( 'b2b' === $__plugin_status || 'b2b_n_b2c' === $__plugin_status ) {
							return $b2b_mapped_roles;
						} else {
							return array();
						}
					case 'b2c_roles_option':
						if ( 'b2c' === $__plugin_status || 'b2b_n_b2c' === $__plugin_status ) {
							return $b2c_roles_option;
						} else {
							return array( $guest_role_option );
						}
					case 'b2c_mapped_roles':
						if ( 'b2c' === $__plugin_status || 'b2b_n_b2c' === $__plugin_status ) {
							return $b2c_mapped_roles;
						} else {
							return array();
						}
				}
			}
			if ( 'by_id' === $type && '' !== $id ) {
				return isset( $__roles[ $id ] ) ? $__roles[ $id ] : array();
			}
		}
	}

	/**
	 * Get All Users
	 *
	 * @since 1.0.0
	 * @since 1.0.9 wholesalex_get_users_query filter added
	 */
	public function get_users() {
		$users        = get_users(
			apply_filters(
				'wholesalex_get_users_query',
				array(
					'fields' => array( 'ID', 'user_login' ),
				)
			)
		);
		$user_options = array();
		$mapped_users = array();
		foreach ( $users as $user ) {
			$user_options[]                      = array(
				'name'  => $user->user_login,
				'value' => 'user_' . $user->ID,
			);
			$mapped_users[ 'user_' . $user->ID ] = $user->user_login;
		}
		return array(
			'user_options' => $user_options,
			'mapped_users' => $mapped_users,
		);
	}

	/**
	 * Assign New Role To User
	 *
	 * @param int    $user_id User ID.
	 * @param string $new_role_id New Role ID.
	 * @param string $prev_role_id Previous Role ID.
	 * @return void
	 */
	public function change_role( $user_id, $new_role_id, $prev_role_id = '' ) {
		if ( ! ( isset( $new_role_id ) && ! empty( $new_role_id ) ) ) {
			return;
		}
        $wholesalex_roles = wholesalex()->get_roles( 'ids' );
        if(!in_array($new_role_id,$wholesalex_roles)) {
            return;
        }
		$user = new WP_User( $user_id );
		do_action( 'wholesalex_before_role_update', $user_id, $new_role_id, $prev_role_id );
		if ( '' !== $prev_role_id ) {
			$user->remove_role( $prev_role_id );
			$user->remove_cap( $prev_role_id );
		}
		$user->add_role( $new_role_id );
		/**
		 * Add User Cap as wholesalex role.
		 *
		 * @since 1.1.2
		 */
		$user->add_cap( $new_role_id );

		update_user_meta( $user_id, '__wholesalex_role', $new_role_id );

		do_action( 'wholesalex_user_role_updated', $user_id, $prev_role_id, $new_role_id );
	}

	/**
	 * Set Link with the Parameters
	 *
	 * @param STRING $url Url.
	 * @since v.1.0.0
	 * @return STRING | URL with Arg
	 */
	public function get_premium_link( $url = '', $tag = 'go_premium' ) {
		$url          = $url ? $url : 'https://www.wpxpo.com/wholesalex/pricing/';
		$affiliate_id = apply_filters( 'wholesalex_affiliate_id', false );
		$arg          = array( 'utm_source' => $tag );
		if ( ! empty( $affiliate_id ) ) {
			$arg['ref'] = esc_attr( $affiliate_id );
		}
		return add_query_arg( $arg, $url );
	}

	/**
	 * Get Global Plugin Settings
	 *
	 * @since 1.0.0
	 * @param STRING $key Key of the Option.
	 * @return ARRAY | STRING
	 * @since 1.2.4 Add Default Option
	 */
	public function get_setting( $key = '', $default = '' ) {
		$data = $GLOBALS['wholesalex_settings'];
		if ( '' !== $key ) {
			return isset( $data[ $key ] ) ? $data[ $key ] : $default;
		} else {
			return $data;
		}
	}


	/**
	 * Set Option Settings
	 *
	 * @since 1.0.0
	 * @param STRING $key Key of the Option .
	 * @param STRING $val Value of the Option .
	 */
	public function set_setting( $key = '', $val = '' ) {
		if ( '' !== $key ) {
			$data         = $GLOBALS['wholesalex_settings'];
			$data[ $key ] = $val;
			update_option( 'wholesalex_settings', $data );
			$GLOBALS['wholesalex_settings'] = $data;
		}
	}

	/**
	 * Set Single Product Discounts
	 *
	 * @param mixed $id Product ID.
	 * @param array $discounts Single Product Discounts.
	 * @since 1.0.0
	 * @since 1.1.5 Add rolewise price on product meta.
	 */
	public function save_single_product_discount( $id = '', $discounts = array() ) {
		if ( '' !== $id && ! empty( $discounts ) ) {
			foreach ( $discounts as $role_name => $value ) {
				$base_price_meta_name = $role_name . '_base_price';
				$sale_price_meta_name = $role_name . '_sale_price';
				$price_meta_name 	  = $role_name . '_price';

				if ( isset( $value['wholesalex_sale_price'] ) && ! empty( $value['wholesalex_sale_price'] ) ) {
					update_post_meta( $id, $price_meta_name, $value['wholesalex_sale_price'] );
				} elseif( ! empty( $value['wholesalex_base_price'] ) ) {
					update_post_meta( $id, $price_meta_name, $value['wholesalex_base_price'] );
				}

				// Update Base Price
				if ( isset( $value['wholesalex_base_price'] ) ) {
					update_post_meta( $id, $base_price_meta_name, $value['wholesalex_base_price'] );
				}
				// Update Sale Price
				if ( isset( $value['wholesalex_sale_price'] ) ) {
					update_post_meta( $id, $sale_price_meta_name, $value['wholesalex_sale_price'] );
				}

				if ( isset( $value['tiers'] ) ) {
					$meta_name = $role_name . '_tiers';
					update_post_meta( $id, $meta_name, $value['tiers'] );
				}
			}
		}
	}

	/**
	 * Get Single Product Discounts
	 *
	 * @param mixed $id Product ID.
	 * @return array
	 * @since 1.0.0
	 * @since 1.1.5 Updated Meta key on single product discounts
	 */
	public function get_single_product_discount( $id = '' ) {
		$is_db_updated = get_option( '__wholesalex_database_update_v2', false );
		if ( ! $is_db_updated ) {
			$this->update_single_product_database();
		}

		$data     = array();
		$role_ids = wholesalex()->get_roles( 'ids' );
		foreach ( $role_ids as $role_id ) {
			$sale_price                                = get_post_meta( $id, $role_id . '_sale_price', true );
			$base_price                                = get_post_meta( $id, $role_id . '_base_price', true );
			$tiers                                     = get_post_meta( $id, $role_id . '_tiers', true );
			$data[ $role_id ]['wholesalex_sale_price'] = $sale_price ? $sale_price : '';
			$data[ $role_id ]['wholesalex_base_price'] = $base_price ? $base_price : '';
			$data[ $role_id ]['tiers']                 = $tiers ? $tiers : array();
		}
		return $data;

	}
	/**
	 * Save Single Product Settings
	 *
	 * @param mixed $id Product ID.
	 * @param array $settings Single Product WholesaleX Setting Data.
	 * @return void
	 */
	public function save_single_product_settings( $id = '', $settings = array() ) {
		if ( '' !== $id && ! empty( $settings ) ) {
			$data        = $GLOBALS['wholesalex_single_product_settings'];
			$data[ $id ] = $settings;
			update_option( '__wholesalex_single_product_settings', $data );
			$GLOBALS['wholesalex_single_product_settings'] = $data;
		}
	}

	/**
	 * Update Single Product Rolewise Price
	 *
	 * @return void
	 */
	public function update_single_product_database() {

		if ( ! get_option( '__wholesalex_single_product_db_update_v2', false ) && is_array( $GLOBALS['wholesalex_single_product_discounts'] ) ) {
			$data = $GLOBALS['wholesalex_single_product_discounts'];

			foreach ( $data as $id => $discounts ) {

				foreach ( $discounts as $role_name => $value ) {
					if ( isset( $value['wholesalex_base_price'] ) ) {
						$meta_name = $role_name . '_base_price';
						update_post_meta( $id, $meta_name, $value['wholesalex_base_price'] );
					}
					if ( isset( $value['wholesalex_sale_price'] ) ) {
						$meta_name = $role_name . '_sale_price';
						update_post_meta( $id, $meta_name, $value['wholesalex_sale_price'] );
					}
					if ( isset( $value['tiers'] ) ) {
						$meta_name = $role_name . '_tiers';
						update_post_meta( $id, $meta_name, $value['tiers'] );
					}
				}
			}
			update_option( '__wholesalex_single_product_db_update_v2', true );
		}
	}
	/**
	 * Save Category Visibiltiy Settings
	 *
	 * @param mixed $id Category ID.
	 * @param array $settings Category WholesaleX Setting Data.
	 * @return void
	 */
	public function save_category_visibility_settings( $id = '', $settings = array() ) {
		if ( '' !== $id && ! empty( $settings ) ) {
			$data        = $GLOBALS['wholesalex_category_settings'];
			$data[ $id ] = $settings;
			update_option( '__wholesalex_category_settings', $data );
			$GLOBALS['wholesalex_category_settings'] = $data;
		}
	}
	/**
	 * Get Category Visibiltiy Settings
	 *
	 * @param mixed $id Category ID.
	 * @return array
	 */
	public function get_category_visibility_settings( $id = '' ) {
		$data = $GLOBALS['wholesalex_category_settings'];
		if ( '' !== $id ) {
			return isset( $data[ $id ] ) ? $data[ $id ] : array();
		} else {
			return $data;
		}
	}
	/**
	 * Save Category Discounts
	 *
	 * @param mixed $id Category ID.
	 * @param array $discounts Category WholesaleX Discounts Data.
	 * @return void
	 */
	public function save_category_discounts( $id = '', $discounts = array() ) {
		if ( '' !== $id && ! empty( $discounts ) ) {
			// $data        = $GLOBALS['wholesalex_category_discounts'];
			// $data[ $id ] = $discounts;
			// update_option( '__wholesalex_category_discounts', $data );
			// $GLOBALS['wholesalex_category_discounts'] = $data;

			// save category discounts on product meta
			foreach ( $discounts as $role_id => $discount ) {
				if ( $discount['tiers'] ) {
					$tiers = $discount['tiers'];
					update_term_meta( $id, $role_id . '_tiers', $tiers );
				}
			}
		}
	}

	/**
	 * Get Category Visibiltiy Settings
	 *
	 * @param mixed $id Category ID.
	 * @return array
	 */
	public function get_category_discounts( $id = '' ) {
		$data = $GLOBALS['wholesalex_category_discounts'];

		if ( '' !== $id ) {
			$roles         = wholesalex()->get_roles( 'ids' );
			$discounts     = array();
			$discount_data = array();
			foreach ( $roles as $role_id ) {
				$discounts['tiers'] = array();
				$discount           = get_term_meta( $id, $role_id . '_tiers', true );
				if ( $discount && is_array( $discount ) ) {
					$discounts['tiers']        = $discount;
					$discount_data[ $role_id ] = $discounts;
				}
			}

			if ( empty( $discount_data ) && isset( $data[ $id ] ) && ! empty( $data[ $id ] ) ) {
				$discount_data = $data[ $id ];
			}
			return $discount_data;
		} else {
			return array();
		}
	}


	/**
	 * Get Single Product Settings
	 *
	 * @param mixed $id Product ID.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_single_product_setting( $id = '', $key = '' ) {
		$data = $GLOBALS['wholesalex_single_product_settings'];
		if ( '' !== $id ) {
			if ( '' !== $key ) {
				return isset( $data[ $id ][ $key ] ) ? $data[ $id ][ $key ] : wholesalex()->get_single_product_default_settings( $key );
			}
			return isset( $data[ $id ] ) ? $data[ $id ] : array();
		} else {
			return $data;
		}
	}


	/**
	 * Get Dynamic Rules
	 *
	 * @param mixed $id Rule ID.
	 * @return array If Rule ID specify, return specific rule otherwise return all roles.
	 */
	public function get_dynamic_rules( $id = '' ) {
		$__rules = $GLOBALS['wholesalex_dynamic_rules'];
		if ( '' === $id ) {
			return ( isset( $__rules ) && is_array( $__rules ) ) ? $__rules : array();
		} else {
			return isset( $__rules[ $id ] ) ? $__rules[ $id ] : array();
		}
	}


	/**
	 * Get Dynamic Rules By User ID
	 *
	 * @param string $user_id User ID
	 * @return array
	 * @since 1.2.4 (With Dokan Integration)
	 */
	public function get_dynamic_rules_by_user_id( $user_id = '' ) {
		if ( '' == $user_id ) {
			$user_id = get_current_user_id();
		}

		$rules      = $this->get_dynamic_rules();
		$user_rules = array();

		foreach ( $rules as $rule ) {
			if ( isset( $rule['created_by'] ) && $rule['created_by'] == $user_id ) {
				$user_rules[] = $rule;
			}
		}

		return $user_rules;
	}

	/**
	 * Set Dynamic Rules
	 *
	 * @param string  $_id Rule ID.
	 * @param array   $_rule Rule.
	 * @param string  $_type Type.
	 * @param boolean $is_frontend Is the request come from fronend.
	 * @return void
	 * @since 1.0.0
	 * @since 1.0.1 Usages Count Added
	 * @since 1.2.4 Is frontend check added
	 */
	public function set_dynamic_rules( $_id = '', $_rule = array(), $_type = '', $is_frontend = false ) {
		if ( '' !== $_id && ! empty( $_rule ) ) {

			$__rules          = wholesalex()->get_dynamic_rules();
			$__for_all        = ( ( 'all_users' === $_rule['_rule_for'] ) || ( 'all_roles' === $_rule['_rule_for'] ) ) ? true : false;
			$__previous_count = ( isset( $__rules[ $_id ]['limit']['usages_count'] ) && ! empty( $__rules[ $_id ]['limit']['usages_count'] ) ) ? $__rules[ $_id ]['limit']['usages_count'] : '';
			$__usages_count   = isset( $_rule['limit']['usages_count'] ) ? (int) $_rule['limit']['usages_count'] : ( $__previous_count ? $__previous_count : '' );
			$__formated_rule  = array(
				'id'                      => $_id,
				'_rule_status'            => isset( $_rule['_rule_status'] ) ? $_rule['_rule_status'] : '',
				'_rule_title'             => isset( $_rule['_rule_title'] ) ? $_rule['_rule_title'] : '',
				'limit'                   => isset($_rule['limit']) && is_array($_rule['limit'])? array_merge( $_rule['limit'], array( 'usages_count' => $__usages_count ) ):array(),
				'_rule_type'              => isset( $_rule['_rule_type'] ) ? $_rule['_rule_type'] : '',
				$_rule['_rule_type']      => isset( $_rule[ $_rule['_rule_type'] ] ) ? $_rule[ $_rule['_rule_type'] ] : array(),
				'_rule_for'               => isset( $_rule['_rule_for'] ) ? $_rule['_rule_for'] : '',
				$_rule['_rule_for']       => $__for_all ? '' : ( isset( $_rule[ $_rule['_rule_for'] ] ) ? $_rule[ $_rule['_rule_for'] ] : '' ),
				'_product_filter'         => isset( $_rule['_product_filter'] ) ? $_rule['_product_filter'] : '',
				$_rule['_product_filter'] => 'all_products' === $_rule['_product_filter'] ? '' : ( isset( $_rule[ $_rule['_product_filter'] ] ) ? $_rule[ $_rule['_product_filter'] ] : '' ),
				'conditions'              => isset( $_rule['conditions'] ) ? $_rule['conditions'] : '',
				'created_by'              => get_current_user_id(),
			);

			$__formated_rule = apply_filters( 'wholesalex_save_dynamic_rules', $__formated_rule, $is_frontend );

			$__rules[ $_id ] = wholesalex()->sanitize( $__formated_rule );
			if ( 'delete' === $_type ) {
				unset( $__rules[ $_id ] );
			}
			update_option( '__wholesalex_dynamic_rules', $__rules );
			delete_transient( 'wholesalex_available_dynamic_rules' );
			$GLOBALS['wholesalex_dynamic_rules'] = $__rules;

			do_action( 'wholesalex_dynamic_rules_updated', $_id );
		}
	}
	/**
	 * Set wholesalex roles
	 *
	 * @param string $_id Role ID.
	 * @param array  $_role Role.
	 * @param string $_type Type.
	 * @return void
	 */
	public function set_roles( $_id = '', $_role = array(), $_type = '' ) {
		if ( '' !== $_id && ! empty( $_role ) ) {
			$__roles = $GLOBALS['wholesalex_roles'];
			if ( isset( $__roles[ $_id ] ) && ! empty( $__roles[ $_id ] ) ) {
				// update.
				$__roles[ $_id ] = wholesalex()->sanitize( $_role );

			} else {
				$__roles[ $_id ] = wholesalex()->sanitize( $_role );
				add_role( $_id, $_role['_role_title'], array( 'read' => true ) );
			}
			if ( 'delete' === $_type ) {
				unset( $__roles[ $_id ] );
				if ( wp_roles()->is_role( $_id ) ) {
					remove_role( $_id );
				}
			}
			update_option( '_wholesalex_roles', $__roles );
			$GLOBALS['wholesalex_roles'] = $__roles;
		}
	}


	public function get_user_role( $user_id = '' ) {
		if ( '' === $user_id || ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! is_user_logged_in() ) {
			return 'wholesalex_guest';
		}

		$__user_role = get_user_meta( $user_id, '__wholesalex_role', true );

		if ( isset( $__user_role ) && ! empty( $__user_role ) ) {
			return $__user_role;
		}

		if(empty($__user_role)) {
			return 'wholesalex_b2c_users';
		}
	}


	public function get_license_status() {
		$status = get_option( 'edd_wholesalex_license_status', '' );
		if ( 'invalid' === $status ) {
			$license_data = get_option( 'edd_wholesalex_license_data', array() );
			if ( isset( $license_data['error'] ) ) {
				$status = $license_data['error'];
			}
		}

		return $status;
	}


	/**
	 * Get Form Data
	 *
	 * @param string $role_id Role ID.
	 * @param string $type Form Data Type.
	 * @param bool   $is_only_b2b  Does Dropdown Contain All B2B Roles
	 * @return array Form Data.
	 * @since 1.0.0
	 * @since 1.0.3 Billing and Registration Field Merged At Checkout Registration Issue Fixed
	 * @since 1.1.10 Get Only B2B Role in Form Dropdown option Added
	 */
	public function get_form_data( $role_id = '', $type = 'registration', $is_only_b2b = false ) {
		$__form_data = get_option( '__wholesalex_registration_form' );
		if ( empty( $__form_data ) ) {
			return array();
		}

		$__form_data = json_decode( $__form_data, true );

		if ( ! is_array( $__form_data ) ) {
			return array();
		}

		if ( ! empty( $role_id ) ) {
			$__registration_form_data = array();
			$__billing_form_data      = array();

			foreach ( $__form_data as $field ) {
				$__exclude_status = false;
				if ( isset( $field['excludeRoles'] ) && ! empty( $field['excludeRoles'] ) ) {
					foreach ( $field['excludeRoles'] as $role ) {
						if ( isset( $role['value'] ) && $role_id === $role['value'] ) {
							$__exclude_status = true;
							break;
						}
					}
				}
				if ( $__exclude_status ) {
					continue;
				}

				if ( isset( $field['enableForRegistration'] ) && $field['enableForRegistration'] ) {
					array_push( $__registration_form_data, $field );
				}
				if ( isset( $field['enableForBillingForm'] ) && $field['enableForBillingForm'] ) {
					array_push( $__billing_form_data, $field );
				}
			}
			if ( 'registration' === $type ) {
				return $__registration_form_data;
			}
			if ( 'billing' === $type ) {
				return $__billing_form_data;
			}
		} else {
			$__registration_form_data = array();
			$__fields                 = array();
			$__billing_form_data      = array();

			$__roles        = wholesalex()->get_roles( 'roles_option' );
			$__roles_option = array();
			foreach ( $__roles as $id => $role ) {
				if ( $is_only_b2b && isset( $role['value'] ) && 'wholesalex_b2c_users' === $role['value'] ) {
					continue;
				}
				if ( isset( $role['value'] ) && 'wholesalex_guest' !== $role['value'] ) {
					array_push( $__roles_option, $role );
				}
			}

			foreach ( $__form_data as $field ) {
				if ( 'user_email' === $field['name'] || 'user_pass' === $field['name'] ) {
					array_push( $__registration_form_data, $field );
				} else {
					$field['dependsOn'] = isset( $field['excludeRoles'] ) ? $field['excludeRoles'] : $field['excludeRoles'];
					if ( isset( $field['enableForRegistration'] ) && $field['enableForRegistration'] ) {
						array_push( $__fields, $field );
					}
					if ( isset( $field['enableForBillingForm'] ) && $field['enableForBillingForm'] ) {
						array_push( $__billing_form_data, $field );
					}
				}
			}

			$__select_role_dropdown = array(
				'id'       => 9999999,
				'type'     => 'select',
				'title'    => apply_filters( 'wholesalex_global_registration_form_select_roles_title', __( 'Select Registration Roles', 'wholesalex' ) ),
				'name'     => 'registration_role',
				'option'   => $__roles_option,
				'empty'    => true,
				'required' => true,
			);
			array_push( $__registration_form_data, $__select_role_dropdown );
			if ( 'registration' === $type ) {
				return array_merge( $__registration_form_data, $__fields );
			}
			if ( 'billing' === $type ) {
				return $__billing_form_data;
			}
		}

	}

	/**
	 * Get Default Registration Form Fields
	 */
	public function get_default_registration_form_fields() {
		$is_woo_username = get_option('woocommerce_registration_generate_username');
		$registrationFields = [
				...(isset($is_woo_username) && $is_woo_username === 'no' ? [
					[
						"id" => "regi_3",
						"type" => "row",
						"columns" => [
							[
								"status" => true,
								"type" => "text",
								"label" => "Username",
								"name" => "user_login",
								"isLabelHide" => false,
								"placeholder" => "",
								"columnPosition" => "left",
								"parent" => "regi_3",
								"required" => true,
								"conditions" => [
									"status" => "show",
									"relation" => "all",
									"tiers" => [
										["_id" => strval(time()), "condition" => "", "field" => "", "value" => "", "src" => "registration_form"]
									]
								]
							]
						],
						"isMultiColumn" => false
					]
				] : []),
				[
					"id" => "regi_1",
					"type" => "row",
					"columns" => [
						[
							"status" => true,
							"type" => "email",
							"label" => "Email",
							"name" => "user_email",
							"isLabelHide" => false,
							"placeholder" => "",
							"columnPosition" => "left",
							"parent" => "regi_1",
							"required" => true,
							"conditions" => [
								"status" => "show",
								"relation" => "all",
								"tiers" => [
									["_id" => strval(time()), "condition" => "", "field" => "", "value" => "", "src" => "registration_form"]
								]
							]
						]
					],
					"isMultiColumn" => false
				],
				[
					"id" => "regi_2",
					"type" => "row",
					"columns" => [
						[
							"status" => true,
							"type" => "password",
							"label" => "Password",
							"name" => "user_pass",
							"isLabelHide" => false,
							"placeholder" => "",
							"columnPosition" => "left",
							"parent" => "regi_2",
							"required" => true,
							"conditions" => [
								"status" => "show",
								"relation" => "all",
								"tiers" => [
									["_id" => strval(time()), "condition" => "", "field" => "", "value" => "", "src" => "registration_form"]
								]
							]
						]
					],
					"isMultiColumn" => false
				]
			];
	
		return $registrationFields;
	}

	/**
	 * Set Option Settings Multiple
	 *
	 * @since 1.0.0
	 * @param array $settings_data An array of settings keys and value.
	 */
	public function set_setting_multiple( $settings_data ) {
		$data = $GLOBALS['wholesalex_settings'];
		foreach ( $settings_data as $key => $val ) {
			if ( '' !== $key ) {
				$data[ $key ] = $val;
			}
		}
		update_option( 'wholesalex_settings', $data );
		$GLOBALS['wholesalex_settings'] = $data;
	}

	/**
	 * Get Current User Role
	 *
	 * @return mixed Role ID | false
	 * @since 1.0.0
	 */
	public function get_current_user_role() {
		if ( ! is_user_logged_in() ) {
			return 'wholesalex_guest';
		}

		$__current_user_id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );
		$__user_role       = get_user_meta( $__current_user_id, '__wholesalex_role', true );

		if ( isset( $__user_role ) && ! empty( $__user_role ) ) {
			return $__user_role;
		}
		if ( empty($__user_role) ){
			return 'wholesalex_b2c_users';
		}
	}



	/**
	 * Get Quantity Based Discount Priority
	 *
	 * @return array Quantity Based Discount Priorities
	 * @since 1.2.4 Settings Default Value Added
	 */
	public function get_quantity_based_discount_priorities() {
		$__priorities = wholesalex()->get_setting( '_settings_quantity_based_discount_priority', array( 'profile', 'single_product', 'category', 'dynamic_rule' ) );

		if ( ! isset( $__priorities ) || empty( $__priorities ) ) {
			$__priorities = array(
				'single_product',
				'profile',
				'category',
				'dynamic_rule',
			);
		}
		return $__priorities;
	}

	/**
	 * WholesaleX Sanitizer
	 *
	 * @param array $data .
	 * @since 1.0.0
	 * @return array $data Sanitized Array
	 */
	public function sanitize( $data ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->sanitize( $value );
			} else {
				$data[ $key ] = sanitize_text_field( $value );
			}
		}
		return $data;
	}

	/**
	 * Get WholesaleX Role Name by role Id
	 *
	 * @param string $role_id WholesaleX Role ID.
	 * @return string Role Name.
	 */
	public function get_role_name_by_role_id( $role_id = '' ) {
		$__role_content = wholesalex()->get_roles( 'by_id', $role_id );
		$__title        = isset( $__role_content['_role_title'] ) ? $__role_content['_role_title'] : '';
		return $__title;
	}

	/**
	 * Insert Into Array at specific position
	 *
	 * @param array $array Initial Array.
	 * @param array $insert new element of array with key.
	 * @param int   $position The Position where new elements are inserted.
	 * @return array Updated Array.
	 */
	public function insert_into_array( $array, $insert, $position = '' ) {
		if ( empty( $position ) || '' === $position ) {
			$position = count( $array );
		}
		return array_slice( $array, 0, $position, true ) + $insert + array_slice( $array, $position, null, true );
	}

	/**
	 * Get Hidden Product Ids For Current User
	 *
	 * @since 1.0.0
	 * @since 1.0.1 Bug Fixed.
	 * @since 1.2.4 Specific User Product Visibility Control Will Work Non WholesaleX Users
	 */
	public function hidden_product_ids() {
		$__role = wholesalex()->get_current_user_role();

		$__single_products = wholesalex()->get_single_product_setting();

		$__product_ids_hidden_for_current_user = array();
		$__product_ids_hidden_for_guest        = array();
		$__product_ids_hidden_for_b2c          = array();
		$__product_ids_hidden_for_b2b          = array();

		foreach ( $__single_products as $id => $data ) {

			if ( isset( $data['_hide_for_visitors'] ) && 'yes' === $data['_hide_for_visitors'] ) {
				array_push( $__product_ids_hidden_for_guest, $id );
			}

			if ( isset( $data['_hide_for_b2c'] ) && 'yes' === $data['_hide_for_b2c'] ) {
				array_push( $__product_ids_hidden_for_b2c, $id );
			}
			if ( is_user_logged_in() && isset( $data['_hide_for_b2b_role_n_user'] ) ) {
				$__user_id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );

				if ( 'user_specific' === $data['_hide_for_b2b_role_n_user'] ) {
					if ( isset( $data['_hide_for_users'] ) && is_array( $data['_hide_for_users'] ) ) {
						$__hide_for_users = $data['_hide_for_users'];
						foreach ( $__hide_for_users as $users ) {
							if ( isset( $users['value'] ) && 'user_' . $__user_id === $users['value'] ) {
								array_push( $__product_ids_hidden_for_current_user, $id );
								break;
							}
						}
					}
				}

				if ( 'b2b_specific' === $data['_hide_for_b2b_role_n_user'] ) {
					if ( isset( $data['_hide_for_roles'] ) && is_array( $data['_hide_for_roles'] ) ) {
						$__hide_for_roles = $data['_hide_for_roles'];
						foreach ( $__hide_for_roles as $roles ) {
							if ( isset( $roles['value'] ) && $roles['value'] === $__role ) {
								array_push( $__product_ids_hidden_for_b2b, $id );
								break;
							}
						}
					}
				}

				if ( 'b2b_all' === $data['_hide_for_b2b_role_n_user'] ) {
					array_push( $__product_ids_hidden_for_b2b, $id );
				}
			}
		}

		switch ( $__role ) {
			case 'wholesalex_guest':
				return $__product_ids_hidden_for_guest;
			case 'wholesalex_b2c_users':
				return array_unique( array_merge( $__product_ids_hidden_for_current_user, $__product_ids_hidden_for_b2c ) );
			case '':
				return array();
			default:
				// if ( ! empty( wholesalex()->get_roles( 'by_id', $__role ) ) ) {
				return array_unique( array_merge( $__product_ids_hidden_for_current_user, $__product_ids_hidden_for_b2b ) );
				// }
		}

		return array();
	}

	/**
	 * Get WholesaleX Hidden Product and Categories ID
	 *
	 * @param string $type Type of Hidden Items. product | category.
	 * @return array Hidden Items id.
	 * @since 1.0.0
	 * @since 1.2.4 Specific User Product Visibility Control Will Work Non WholesaleX Users
	 */
	public function hidden_ids( $type = 'product' ) {
		$__role = wholesalex()->get_current_user_role();

		$__visibility_settings = array();
		switch ( $type ) {
			case 'product':
				$__visibility_settings = wholesalex()->get_single_product_setting();
				break;
			case 'category':
				$__visibility_settings = wholesalex()->get_category_visibility_settings();
				break;
		}

		$__ids_hidden_for_current_user = array();
		$__ids_hidden_for_guest        = array();
		$__ids_hidden_for_b2c          = array();
		$__ids_hidden_for_b2b          = array();

		if ( is_array( $__visibility_settings ) ) {

			foreach ( $__visibility_settings as $id => $data ) {

				if ( isset( $data['_hide_for_visitors'] ) && 'yes' === $data['_hide_for_visitors'] ) {
					array_push( $__ids_hidden_for_guest, $id );
				}

				if ( isset( $data['_hide_for_b2c'] ) && 'yes' === $data['_hide_for_b2c'] ) {
					array_push( $__ids_hidden_for_b2c, $id );
				}
				if ( is_user_logged_in() && isset( $data['_hide_for_b2b_role_n_user'] ) ) {
					$__user_id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );

					if ( 'user_specific' === $data['_hide_for_b2b_role_n_user'] ) {
						if ( isset( $data['_hide_for_users'] ) && is_array( $data['_hide_for_users'] ) ) {
							$__hide_for_users = $data['_hide_for_users'];
							foreach ( $__hide_for_users as $users ) {
								if ( isset( $users['value'] ) && 'user_' . $__user_id === $users['value'] ) {
									array_push( $__ids_hidden_for_current_user, $id );
									break;
								}
							}
						}
					}

					if ( 'b2b_specific' === $data['_hide_for_b2b_role_n_user'] ) {
						if ( isset( $data['_hide_for_roles'] ) && is_array( $data['_hide_for_roles'] ) ) {
							$__hide_for_roles = $data['_hide_for_roles'];
							foreach ( $__hide_for_roles as $roles ) {
								if ( isset( $roles['value'] ) && $roles['value'] === $__role ) {
									array_push( $__ids_hidden_for_b2b, $id );
									break;
								}
							}
						}
					}

					if ( 'b2b_all' === $data['_hide_for_b2b_role_n_user'] ) {
						array_push( $__ids_hidden_for_b2b, $id );
					}
				}
			}
		}

		switch ( $__role ) {
			case 'wholesalex_guest':
				return $__ids_hidden_for_guest;
			case 'wholesalex_b2c_users':
				return array_unique( array_merge( $__ids_hidden_for_current_user, $__ids_hidden_for_b2c ) );
			case '':
				return array();
			default:
				// if ( ! empty( wholesalex()->get_roles( 'by_id', $__role ) ) ) {
				return array_unique( array_merge( $__ids_hidden_for_current_user, $__ids_hidden_for_b2b ) );
				// }
				break;
		}

		return array();
	}

	/**
	 * Filter Empty Tiers
	 *
	 * @param array $tiers Discounts Tier.
	 * @return array Updated Tiers.
	 */
	public function filter_empty_tier( $tiers ) {
		$__tiers = array();
		if ( ! ( is_array( $tiers ) && ! empty( $tiers ) ) ) {
			return array();
		}
		foreach ( $tiers as $tier ) {
			if ( isset( $tier['_discount_type'] ) && ! empty( $tier['_discount_type'] ) && isset( $tier['_discount_amount'] ) && ! empty( $tier['_discount_amount'] ) && isset( $tier['_min_quantity'] ) && ! empty( $tier['_min_quantity'] ) ) {
				array_push( $__tiers, $tier );
			}
		}
		return $__tiers;
	}
	/**
	 * Filter Empty Tiers
	 *
	 * @param array $tiers Discounts Tier.
	 * @return array Updated Tiers.
	 */
	public function filter_empty_conditions( $tiers ) {
		$__tiers = array();
		if ( ! ( is_array( $tiers ) && ! empty( $tiers ) ) ) {
			return array();
		}
		foreach ( $tiers as $tier ) {
			if ( isset( $tier['_conditions_for'] ) && ! empty( $tier['_conditions_for'] ) && isset( $tier['_conditions_operator'] ) && ! empty( $tier['_conditions_operator'] ) && isset( $tier['_conditions_value'] ) && ! empty( $tier['_conditions_value'] ) ) {
				array_push( $__tiers, $tier );
			}
		}
		return $__tiers;
	}
	/**
	 * Get Discounts For Current User
	 *
	 * @param string $type Type.
	 * @param mixed  $product_id Product ID.
	 * @since 1.2.4 Active status check if plugin status is only b2b mode
	 */
	public function get_discounts( $type = '', $product_id = -1 ) {

		if ( '' !== $type && -1 !== $product_id ) {

			$__role            = wholesalex()->get_current_user_role();
			$__current_user_id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );
			$__discounts       = array();

			$__eligible_for_discounts = true;
			$plugins_status           = wholesalex()->get_setting( '_settings_status', 'b2b' );

			if ( 'b2b' === $plugins_status ) {
				if ( ! ( ( $__current_user_id && 'active' === wholesalex()->get_user_status( $__current_user_id ) ) ) ) {
					$__eligible_for_discounts = false;
				}
			}

			if ( 'wholesalex_guest' === wholesalex()->get_current_user_role() ) {
				$__eligible_for_discounts = true;
			}

			switch ( $type ) {
				case 'category':
					$__cat_ids           = wc_get_product_term_ids( $product_id, 'product_cat' );
					$__discounted_cat_id = '';
					// If product associated with multiple categories, take the first one which has discounts.
					foreach ( $__cat_ids as $cat_id ) {
						$__discounts = wholesalex()->get_category_discounts( $cat_id );
						if ( isset( $__discounts[ $__role ] ) && ! empty( $__discounts[ $__role ] ) ) {
							$__discounts         = $__discounts[ $__role ];
							$__discounted_cat_id = $cat_id;
							break;
						}
					}

					// Remove Empty Tiers.
					$__tiers = isset( $__discounts['tiers'] ) ? $this->filter_empty_tier( $__discounts['tiers'] ) : array();

					if ( isset( $__tiers['_min_quantity'] ) ) {
						// Sort tiers by Min Quantity.
						$__sort_column = array_column( $__tiers, '_min_quantity' );
						array_multisort( $__sort_column, SORT_ASC, $__tiers );
					}

					return array(
						'cat_id' => $__eligible_for_discounts ? $__discounted_cat_id : 0,
						'tiers'  => $__eligible_for_discounts ? $__tiers : array(),
					);
				case 'product':
					$__discounts = wholesalex()->get_single_product_discount( $product_id );
					if ( ! isset( $__discounts[ $__role ] ) ) {
						return;
					}
					$__discounts = $__discounts[ $__role ];

					$__regular_price = isset( $__discounts['wholesalex_base_price'] ) ? $__discounts['wholesalex_base_price'] : '';

					$__sale_price = isset( $__discounts['wholesalex_sale_price'] ) ? $__discounts['wholesalex_sale_price'] : '';

					$__tiers = isset( $__discounts['tiers'] ) ? $this->filter_empty_tier( $__discounts['tiers'] ) : array();

					if ( isset( $__tiers['_min_quantity'] ) ) {
						// Sort tiers by Min Quantity.
						$__sort_colum = array_column( $__tiers, '_min_quantity' );
						array_multisort( $__sort_colum, SORT_ASC, $__tiers );
					}

					return array(
						'regular_price' => $__eligible_for_discounts ? $__regular_price : '',
						'sale_price'    => $__eligible_for_discounts ? $__sale_price : '',
						'tiers'         => $__eligible_for_discounts ? $__tiers : array(),
					);

				default:
					// code...
					break;
			}
		}
	}

	/**
	 * Category Cart Count. It count how many product are in cart of given cat id.
	 *
	 * @param mixed $cat_id Category ID.
	 * @return int Product Count.
	 * @since 1.0.0
	 */
	public function category_cart_count( $cat_id ) {
		$cat_count = 0;
		if ( isset( WC()->cart ) && ! empty( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$__product_id = $cart_item['product_id'];
				$__product    = wc_get_product( $__product_id );

				if ( 'product_variation' === $__product->post_type ) {
					$__product_id = $__product->get_parent_id();
				}
				if ( has_term( $cat_id, 'product_cat', $__product_id ) ) {
					$cat_count += $cart_item['quantity'];
				}
			}
		}

		$cat_count = apply_filters('wholesalex_category_cart_count',$cat_count,$cat_id);
		return $cat_count;
	}

	/**
	 * Get Product Count at cart
	 *
	 * @param int $product_id Product or Variation ID.
	 * @return int Product count at cart.
	 * @since 1.0.0
	 */
	public function cart_count( $product_id=false ) {
		$__quantity = 0;
		if ( ! is_null( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if($product_id) {
					if ( ! empty( $cart_item['data'] ) && in_array( $product_id, array( $cart_item['product_id'], $cart_item['variation_id'] ), true ) ) {
						$__is_parent_rule_apply = apply_filters( 'wholesalex_apply_parent_rule_to_variations', false );  // Add This Filter TO Work Dynamic Rule For Combine Variation Product Like Quantity Base Discount
						if ( $__is_parent_rule_apply ) {
							$__quantity += $cart_item['quantity'];
						} else {
							$__quantity = $cart_item['quantity'];
							break; // stop the loop if product is found.
						}
					}
				} else {
					if(!isset($cart_item['free_product'])) {
						$__quantity += $cart_item['quantity'];
					}
					
				}
			}
		} 

		$__quantity = apply_filters('wholesalex_cart_count',$__quantity,$product_id);

		return $__quantity;
	}

	/**
	 * Get Cart Total Amount
	 *
	 * @param string $product_id Product ID.
	 * @return int If product id specified then return cart subtotal price of specific product otherwise return total cart content price.
	 * @since 1.0.0
	 */
	public function get_cart_total( $product_id = '' ) {
		if ( ! isset( WC()->cart ) || null === WC()->cart->get_cart() ) {
			return 0;
		}

		if ( ! empty( $product_id ) && isset( WC()->cart ) ) {

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset($cart_item['product_id']) && isset($cart_item['variation_id']) && isset($cart_item['line_total']) &&  in_array( $product_id, array( $cart_item['product_id'], $cart_item['variation_id'] ) ) ) { //phpcs:ignore
					return $cart_item['line_total'];
				}
			}
		} else {
			$__is_enable_tax = ( 'yes' === wholesalex()->get_setting( '_settings_allow_tax_with_cart_total_amount' ) );
			$__total    = 0.0;
			$__with_tax = apply_filters( 'wholesalex_get_cart_total_with_tax', ( $__is_enable_tax ? true : false ) );
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['line_total'] ) ) {
					if ( $__with_tax && isset( $cart_item['line_tax'] ) ) {
						$__total = $__total + $cart_item['line_total'] + $cart_item['line_tax'];
					} else {
						$__total = $__total + $cart_item['line_total'];
					}
				}
			}
			return (float) $__total;
		}

		return 0;

	}

	/**
	 * Get Cart Total Weight
	 *
	 * @return float Total Weight of cart.
	 * @since 1.0.0
	 */
	public function get_cart_total_weight() {
		$__total = 0.0;
		if ( ! isset( WC()->cart ) ) {
			return $__total;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$__item_weight = $cart_item['data']->get_weight();
			if ( ! empty( $__item_weight ) ) {
				$__total = $__total + ( $__item_weight * $cart_item['quantity'] );
			}
		}
		return (float) $__total;
	}


	/**
	 * Get WholesaleX User Status
	 *
	 * @param string $id User ID.
	 * @return string user status.
	 */
	public function get_user_status( $id = '' ) {
		if ( empty( $id ) ) {
			$id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );
		}

		$__user_status = get_user_meta( $id, '__wholesalex_status', true );
		$__user_status = ( $__user_status && ! empty( $__user_status ) ) ? $__user_status : '';
		return $__user_status;
	}

	/**
	 * Get Language and Text Settings
	 *
	 * @param string $from_setting Settings Text.
	 * @param string $default Default Text.
	 * @return string
	 * @since 1.0.0
	 * @since 1.0.2 Updated and Backward Compatibility Added.
	 */
	public function get_language_n_text( $from_setting, $default ) {

		if ( version_compare( WHOLESALEX_VER, '1.0.2', '>=' ) > 0 ) {
			if ( isset( $GLOBALS['wholesalex_settings'][ $from_setting ] ) ) {
				return $this->get_setting( $from_setting );
			} else {
				return $default;
			}
		} else {
			if ( ! empty( $from_setting ) ) {
				return $from_setting;
			} else {
				return $default;
			}
		}
	}

	public function is_pro_enabled() {
		return ( function_exists( 'wholesalex_pro' ) && wholesalex_pro()->is_active() );
	}

	/**
	 * Is Pro Active.
	 */
	public function is_pro_active() {
		// For Pro Check.
		$__is_pro_active = false;
		if ( function_exists( 'wholesalex_pro' ) && wholesalex_pro()->is_active() && 'valid' === wholesalex()->get_license_status() ) {
			$__is_pro_active = true;
		}
		return $__is_pro_active;
	}


	/**
	 * Calculate Per Unit Sale Price
	 *
	 * @param array        $tier Tier Array.
	 * @param string|float $regular_price Regular Price.
	 * @since 1.0.0
	 * @since 1.0.3 Fixed Price Issue Fixed
	 */
	public function calculate_sale_price( $tier, $regular_price ) {
		if ( ! ( isset( $tier['_discount_type'] ) && isset( $tier['_discount_amount'] ) && ! empty( $regular_price ) ) ) {
			return;
		}
		$__sale_price = '';
		switch ( $tier['_discount_type'] ) {
			case 'percentage':
				$__sale_price = max( 0, (float) $regular_price - ( ( (float) $regular_price * floatval($tier['_discount_amount']) ) / 100.00 ) );
				break;
			case 'amount':
				$__sale_price = max( 0, (float) $regular_price - floatval($tier['_discount_amount']) );
				break;
			case 'fixed_price':
				$__sale_price = max( 0, (float) floatval($tier['_discount_amount']) );
				break;
			case 'fixed':
				$__sale_price = max( 0, (float) floatval($tier['_discount_amount']) );
				break;
		}
		return number_format((float)$__sale_price, 2, '.', '');
	}

	/**
	 * Dynamic Rules Usages Count Handle
	 *
	 * @param int $rule_id Dynamic Rule ID.
	 * @return void
	 */
	public function set_usages_dynamic_rule_id( $rule_id ) {
		if ( is_admin() || null === WC()->session ) {
			return;
		}
		$__dynamic_rule_id = WC()->session->get( '__wholesalex_used_dynamic_rule' );
		if ( ! ( isset( $__dynamic_rule_id ) && is_array( $__dynamic_rule_id ) ) ) {
			$__dynamic_rule_id = array();
		}
		$__dynamic_rule_id[ $rule_id ] = true;

		WC()->session->set( '__wholesalex_used_dynamic_rule', $__dynamic_rule_id );
	}


	/**
	 * Get Single Product Default Settings
	 *
	 * @param string $key Setting name.
	 * @return string
	 */
	public function get_single_product_default_settings( $key = '' ) {
		if ( '' !== $key ) {
			switch ( $key ) {
				case '_settings_tier_layout':
					return 'layout_one';
				case '_settings_show_tierd_pricing_table':
					return 'yes';
				case '_settings_override_tax_extemption':
					return 'disable';
				case '_settings_override_shipping_role':
					return 'disable';
				case '_settings_override_tax_extemption':
					return 'disable';
				default:
					return '';
			}
		}
	}

	/**
	 * Get All Product Ids
	 *
	 * @since 1.0.2
	 */
	public function get_all_product_ids() {
		$ids = new WP_Query(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'fields'      => 'ids',
			)
		);

		return $ids;
	}

	/**
	 * Get WholesaleX License Type
	 *
	 * @return string
	 */
	public function get_license_type() {
		$__status = get_option( 'edd_wholesalex_license_status', true );
		if ( 'valid' !== $__status ) {
			return '';
		}
		return get_option( '__wholesalex_license_type', '' );
	}


	/**
	 * Get Upgrade Pro Popup HTML
	 *
	 * @param string $heading Heading.
	 * @param string $subheading Subheading.
	 * @param string $desc Description.
	 * @return void
	 * @since 1.0.10
	 */
	public function get_upgrade_pro_popup_html( $heading = '', $subheading = '', $desc = '', $url = '' ) {
		if ( '' == $url ) {
			$url = wholesalex()->get_premium_link();
		}
		?>
		<div id="wholesalex-pro-popup" class="wholesalex-popup-container popup-center display-none">
			<div class="wholesalex-unlock-popup wholesalex-unlock-modal">
				<img src="<?php echo esc_url( WHOLESALEX_URL ) . 'assets/img/unlock.svg'; ?>" alt="Unlock Icon"/>
				<h4 class="wholesalex-md-heading wholesalex-mt25"><?php echo esc_html( $heading ); ?></h4>
				<?php
				if ( $subheading ) {
					?>
					<span class="wholesalex-unlock-subheading"><?php echo esc_html( $subheading ); ?> </span>
					<?php
				}
				?>
				<div class="wholesalex-popup-desc">
					<?php echo esc_html( $desc ); ?>
				</div>
				<a href="<?php echo esc_url( $url ); ?>" class="wholesalex-btn wholesalex-btn-warning wholesalex-mt25"><?php echo esc_html__( 'Get WholesaleX Pro', 'wholesalex' ); ?></a>
				<button class="wholesalex-popup-close pro-popup" id="wholesalex-close-pro-popup" onclick="closeWholesaleXGetProPopUp()"></button>
			</div>
		</div>
		<?php
	}


	/**
	 * Check Any String Start With
	 *
	 * @param string $str Main String.
	 * @param string $begin_with Begin With String.
	 * @return bool
	 * @since 1.0.10
	 */
	public function start_with( $str, $begin_with ) {
		$len = strlen( $begin_with );
		return ( substr( $str, 0, $len ) === $begin_with );
	}


	/**
	 * Get WholesaleX Rolewise Sale and Base Price
	 *
	 * @param string|int $user_id User Id.
	 * @param string|int $product_id Product ID.
	 * @return array
	 */
	public function get_wholesalex_rolewise_single_product_price( $user_id = '', $product_id = '' ) {
		if ( ! ( $user_id && $product_id ) ) {
			return false;
		}
		$user_role_id = get_user_meta( $user_id, '__wholesalex_role', true );
		$product      = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}
		$sale_price = $product->get_sale_price();
		$base_price = $product->get_regular_price();
		if ( $user_role_id && function_exists( 'wholesalex' ) ) {
			// All Roles Price.
			$prices = wholesalex()->get_single_product_discount( $product_id );

			// Price For Current User Role.
			$price = isset( $prices[ $user_role_id ] ) ? $prices[ $user_role_id ] : array();

			$sale_price = isset( $price['wholesalex_sale_price'] ) ? $price['wholesalex_sale_price'] : $sale_price;
			$base_price = isset( $price['wholesalex_base_price'] ) ? $price['wholesalex_base_price'] : $base_price;
		}

		return array(
			'base_price' => $base_price,
			'sale_price' => $sale_price,
		);
	}


	/**
	 * Check is ppop plugin active or not
	 *
	 * @return boolean
	 * @since 1.1.7
	 */
	public function is_ppop_active() {
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		if ( file_exists( WP_PLUGIN_DIR . '/woocommerce-product-addon/woocommerce-product-addon.php' ) && in_array( 'woocommerce-product-addon/woocommerce-product-addon.php', $active_plugins, true ) ) {
			return true;
		} else {
			return false;
		}
	}



	public function is_wholesalex_page( $page ) {
		$status = false;
		switch ( $page ) {
			case 'wholesalex-settings':
			case 'wholesalex-users':
			case 'wholesalex-addons':
			case 'wholesalex_role':
			case 'wholesalex-email':
			case 'wholesalex_dynamic_rules':
			case 'wholesalex-registration':
			case 'wsx_conversation':
			case 'wholesalex-overview':
			case 'wholesalex-setup-wizard':
			case 'wholesalex-features':
			case 'wholesalex-help':
			case 'wholesalex-conversation':
			case 'wholesalex-license':
			case 'wholesalex-setup-wizard':
			case 'wholesalex-support':
			case 'wholesalex-migration':
				$status = true;
				break;

			default:
				break;
		}
		if ( $page == wholesalex()->get_setting( 'plugin_menu_slug' ) || $page == wholesalex()->get_setting( 'dynamic_rule_submenu_slug' ) || $page == wholesalex()->get_setting( 'emails_submenu_slug' ) ||
			$page == wholesalex()->get_setting( 'registration_form_buidler_submenu_slug' ) || $page == wholesalex()->get_setting( 'role_submenu_slug' ) || $page == wholesalex()->get_setting( 'settings_submenu_slug' ) || $page == wholesalex()->get_setting( 'users_submenu_slug' )
			|| $page == wholesalex()->get_setting( 'addons_submenu_slug' )
		) {
			$status = true;
		}

		if ( ! $page ) {
			$status = false;
		}

		return $status;
	}

	public function get_email_template( $email_subject, $email_content, $css ) {
		ob_start();
		wc_get_template(
			'email_template.php',
			array(
				'subject'       => $email_subject,
				'css'           => $css,
				'email_content' => $email_content,
			),
			WHOLESALEX_PATH . 'includes/',
			WHOLESALEX_PATH . 'includes/'
		);
		return ob_get_clean();
	}


	public function send_email( $template, $to, $smart_tags ) {

		$email_templates = WHOLESALEX_EMAIL::get_email_templates();
		$email_template  = $email_templates[ $template ];

		$subject = $email_template['subject'];
		$content = $email_template['content'];
		foreach ( $smart_tags as $key => $value ) {
			$subject = str_replace( $key, $value, $subject );
			$content = str_replace( $key, $value, $content );
		}

		// ob_start();
		// wc_get_template( 'emails/email-styles.php' );
		// $css = apply_filters( 'woocommerce_email_styles', ob_get_clean() );
		$css     = '';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, wholesalex()->get_email_template( $subject, $content, $css ), $headers );

		if ( isset( $email_template['admin_notification'] ) && $email_template['admin_notification'] ) {
			wp_mail( get_option( 'admin_email' ), $subject, wholesalex()->get_email_template( $subject, $content, $css ), $headers );
		}

		return true;
	}

	/**
	 * Unlock Options that are locked for different pricing plans.
	 *
	 * @param array $locked_options Locked Options.
	 * @return array Unlocked Options
	 * @since 1.0.6
	 */
	public function unlock_options( $locked_options ) {

		$unlocked_options = array();
		if ( wholesalex()->is_pro_active() && is_array( $locked_options ) ) {
			foreach ( $locked_options as $key => $value ) {
				$temp       = $key;
				$search_key = '#^pro_(.*)$#i';
				if ( preg_match( $search_key, $key ) ) {
					$option_key                      = str_replace( 'pro_', '', $key );
					$option_name                     = str_replace( '(Pro)', '', $value );
					$unlocked_options[ $option_key ] = $option_name;
					$temp                            = $option_key;
				}
				if ( $key === $temp ) {
					$unlocked_options[ $key ] = $value;
				}
			}
		}

		return $unlocked_options;
	}
	/**
	 * Unlock Tier Layouts that are locked for different pricing plans.
	 *
	 * @param array $locked_options Locked Options.
	 * @return array Unlocked Options
	 * @since 1.0.6
	 */
	public function unlock_layouts( $locked_options ) {
		$unlocked_options = array();
		if ( wholesalex()->is_pro_active() && is_array( $locked_options ) ) {
			foreach ( $locked_options as $key => $value ) {
				$temp       = $key;
				$search_key = '#^pro_(.*)$#i';
				if ( preg_match( $search_key, $key ) ) {
					$option_key                      = str_replace( 'pro_', '', $key );
					$unlocked_options[ $option_key ] = $value;
					$temp                            = $option_key;
				}
				if ( $key === $temp ) {
					$unlocked_options[ $key ] = $value;
				}
			}
		}

		return $unlocked_options;
	}


	/**
	 * Helper function for logging
	 *
	 * For valid levels, see `WC_Log_Levels` class
	 *
	 * Description of levels:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function log( $message, $level = 'debug' ) {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wholesalex' );

		$logger->log( $level, $message, $context );
	}

	public function is_valid_user() {
		$user_id        = get_current_user_id();
		$plugins_status = wholesalex()->get_setting( '_settings_status', 'b2b' );
		$status         = true;
		if ( 'b2b' === $plugins_status ) {
			$status = 'active' === $this->get_user_status( $user_id );

		}
		return $status;
	}


	/**
	 * Get Plugin Name
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		$plugin_name = apply_filters( 'wholesalex_plugin_name', __( 'WholesaleX', 'wholesalex' ) );
		return $plugin_name;
	}

	/**
	 * Get Menu Slug
	 *
	 * @return string
	 */
	public function get_menu_slug() {
		$menu_slug = apply_filters( 'wholesalex_plugin_menu_slug', 'wholesalex-overview' );
		return $menu_slug;
	}

	 /**
	  * Get Option Value bypassing cache
	  * Inspired By WordPress Core get_option
	  *
	  * @param string  $option Option Name.
	  * @param boolean $default_value option default value.
	  * @return mixed
	  */
	public function get_option_without_cache( $option, $default_value = false ) {
		global $wpdb;

		if ( is_scalar( $option ) ) {
			$option = trim( $option );
		}

		if ( empty( $option ) ) {
			return false;
		}

		$value = $default_value;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) ); // @codingStandardsIgnoreLine.

		if ( is_object( $row ) ) {
			$value = $row->option_value;
		} else {
			return apply_filters( "wholesalex_default_option_{$option}", $default_value, $option );
		}

		return apply_filters( "wholesalex_option_{$option}", maybe_unserialize( $value ), $option );
	}

	public function Badge_image_display() {
		$badge_image = apply_filters(
			'wholesalex_settings_product_tier_layouts',
			array(
				'style_one'    => WHOLESALEX_URL . '/assets/img/badge-style-one.png',
				'style_two'    => WHOLESALEX_URL . '/assets/img/badge-style-two.png',
				'style_three'  => WHOLESALEX_URL . '/assets/img/badge-style-three.png',
				'style_four'   => WHOLESALEX_URL . '/assets/img/badge-style-four.png',
				'style_five'   => WHOLESALEX_URL . '/assets/img/badge-style-five.png',
			)
		);
		return $badge_image;
	}
	

	/**
	 * Get Transient Value bypassing cache
	 * Inspired By WordPress Core get_transient
	 *
	 * @param string $transient Transient Name.
	 * @return mixed
	 */
	public function get_transient_without_cache( $transient ) {
		$transient_option  = '_transient_' . $transient;
		$transient_timeout = '_transient_timeout_' . $transient;
		$timeout           = $this->get_option_without_cache( $transient_timeout );

		if ( false !== $timeout && $timeout < time() ) {
			delete_option( $transient_option );
			delete_option( $transient_timeout );
			$value = false;
		}

		if ( ! isset( $value ) ) {
			$value = $this->get_option_without_cache( $transient_option );
		}

		return apply_filters( "wholesalex_transient_{$transient}", $value, $transient );
	}

	/**
	 * Set transient without adding to the cache
	 * Inspired By WordPress Core set_transient
	 *
	 * @param string  $transient Transient Name.
	 * @param mixed   $value Transient Value.
	 * @param integer $expiration Time until expiration in seconds.
	 * @return bool
	 */
	public function set_transient_without_cache( $transient, $value, $expiration = 0 ) {
		$expiration = (int) $expiration;

		$transient_timeout = '_transient_timeout_' . $transient;
		$transient_option  = '_transient_' . $transient;

		$result = false;

		if ( false === $this->get_option_without_cache( $transient_option ) ) {
			$autoload = 'yes';
			if ( $expiration ) {
				$autoload = 'no';
				$this->add_option_without_cache( $transient_timeout, time() + $expiration, 'no' );
			}
			$result = $this->add_option_without_cache( $transient_option, $value, $autoload );
		} else {
			/*
			 * If expiration is requested, but the transient has no timeout option,
			 * delete, then re-create transient rather than update.
			 */
			$update = true;

			if ( $expiration ) {
				if ( false === $this->get_option_without_cache( $transient_timeout ) ) {
					delete_option( $transient_option );
					$this->add_option_without_cache( $transient_timeout, time() + $expiration, 'no' );
					$result = $this->add_option_without_cache( $transient_option, $value, 'no' );
					$update = false;
				} else {
					update_option( $transient_timeout, time() + $expiration );
				}
			}

			if ( $update ) {
				$result = update_option( $transient_option, $value );
			}
		}

		return $result;

	}

	/**
	 * Add option without adding to the cache
	 * Inspired By WordPress Core set_transient
	 *
	 * @param string $option option name.
	 * @param string $value option value.
	 * @param string $autoload whether to load WordPress startup.
	 * @return bool
	 */
	public function add_option_without_cache( $option, $value = '', $autoload = 'yes' ) {
		global $wpdb;

		if ( is_scalar( $option ) ) {
			$option = trim( $option );
		}

		if ( empty( $option ) ) {
			return false;
		}

		wp_protect_special_option( $option );

		if ( is_object( $value ) ) {
			$value = clone $value;
		}

		$value = sanitize_option( $option, $value );

		/*
		 * Make sure the option doesn't already exist.
		 */

		if ( apply_filters( "wholesalex_default_option_{$option}", false, $option, false ) !== $this->get_option_without_cache( $option ) ) {
			return false;
		}

		$serialized_value = maybe_serialize( $value );
		$autoload         = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';

		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) ); // @codingStandardsIgnoreLine.
		if ( ! $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Check current user is a b2b user or has admin access
	 *
	 * @return boolean
	 * @since 1.1.3
	 */
	public function is_active_b2b_user( $user_id = '' ) {
		$account_role = wholesalex()->get_current_user_role();
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$__current_user_id = apply_filters( 'wholesalex_set_current_user', $user_id );
		$__account_status  = get_user_meta( $__current_user_id, '__wholesalex_status', true );
		return ( ! ( ( 'wholesalex_guest' === $account_role ) || ( 'wholesalex_b2c_users' === $account_role ) ) && 'active' === $__account_status ) || current_user_can( 'manage_options' );
	}


	// For Migration Plugin
	/**
	 * Update Registration Form
	 *
	 * @param array $data Data.
	 * @return void
	 */
	public function update_registration_form($data) {
		update_option( '__wholesalex_registration_form', wp_json_encode( $data ) );
	}

	/**
	 * Update Dynamic Rules
	 *
	 * @param array $data Dynamic Rules.
	 * @return void
	 */
	public function update_dynamic_rules($data) {
		update_option( '__wholesalex_dynamic_rules', $data );
	}

	// Get Empty
	public function get_empty_form() {
		$defaultForm = array(
			'registrationFormHeader' =>
			array(
				'isShowFormTitle'   => true,
				'isHideDescription' => false,
				'title'             => 'Register',
				'description'       => "Don't have an account? Sign up now!",
				'styles'            =>
				array(
					'title'       =>
						array(
							'color'     => '#343A46',
							'size'      => 24,
							'weight'    => 500,
							'transform' => '',
							'padding'   => '',
						),
					'description' =>
						array(
							'color'     => '#343A46',
							'size'      => 14,
							'weight'    => 400,
							'transform' => '',
							'padding'   => '',
						),
				),
			),
			'loginFormHeader'        =>
			array(
				'isShowFormTitle'   => true,
				'isHideDescription' => false,
				'title'             => 'Login',
				'description'       => 'Sign In to Your Account',
				'styles'            =>
				array(
					'title'       =>
					array(
						'color'     => '#343A46',
						'size'      => 24,
						'weight'    => 500,
						'transform' => '',
						'padding'   => '',
					),
					'description' =>
					array(
						'color'     => '#343A46',
						'size'      => 14,
						'weight'    => 400,
						'transform' => '',
						'padding'   => '',
					),
				),
			),
			'settings'               =>
			array(
				'inputVariation'  => 'variation_1',
				'isShowLoginForm' => false,
			),
			'fieldsName'             =>
			array(),
			'loginFields'            =>
			array(
				0 =>
				 array(
					 'id'            => 'login_row_1',
					 'type'          => 'row',
					 'columns'       =>
					 array(
						 0 =>
						  array(
							  'type'           => 'text',
							  'label'          => __('Username or email address','wholesalex'),
							  'name'           => 'username',
							  'isLabelHide'    => false,
							  'placeholder'    => '',
							  'columnPosition' => 'left',
							  'parent'         => 'login_row_1',
							  'isRequired'     => true,
						  ),
					 ),
					 'isMultiColumn' => false,
				 ),
				1 =>
				 array(
					 'id'            => 'login_row_2',
					 'type'          => 'row',
					 'columns'       =>
					 array(
						 0 =>
						  array(
							  'type'           => 'password',
							  'label'          => __('Password','wholesalex'),
							  'name'           => 'password',
							  'isLabelHide'    => false,
							  'placeholder'    => '',
							  'columnPosition' => 'left',
							  'parent'         => 'login_row_2',
							  'isRequired'     => true,
						  ),
					 ),
					 'isMultiColumn' => false,
				 ),
				2 =>
				array(
					'id'            => 'login_row_3',
					'type'          => 'row',
					'columns'       =>
					array(
						0 =>
						 array(
							 'type'           => 'checkbox',
							 'label'          => '',
							 'name'           => 'rememberme',
							 'isLabelHide'    => true,
							 'columnPosition' => 'left',
							 'option'         =>
							 array(
								 0 =>
								  array(
									  'name'  => __('Remember me','wholesalex'),
									  'value' => 'rememberme',
								  ),
							 ),
							 'parent'         => 'row_3438998',
							 'excludeRoles'   =>
							 array(),
						 ),
					),
					'isMultiColumn' => false,
				),
			),
			'registrationFields' => $this->get_default_registration_form_fields(),
			'registrationFormButton' =>
			array(
				'title' => 'Register',
			),
			'loginFormButton'        =>
			array(
				'title' => 'Log in',
			),
			'style'                  =>
			array(
				'color'       =>
				array(
					'field'     =>
					array(
						'signIn' =>
						array(
							'normal'  =>
							array(
								'label'       => '#343A46',
								'text'        => '#343A46',
								'background'  => '#FFF',
								'border'      => '#E9E9F0',
								'placeholder' => '#6C6E77',
							),
							'active'  =>
							array(
								'label'       => '#343A46',
								'text'        => '#343A46',
								'background'  => '#FFF',
								'border'      => '#6C6CFF',
								'placeholder' => '#6C6E77',
							),
							'warning' =>
							array(
								'label'       => '#343A46',
								'text'        => '#FF6C6C',
								'background'  => '#FFF',
								'border'      => '#FF6C6C',
								'placeholder' => '#6C6E77',
							),
						),
						'signUp' =>
						array(
							'normal'  =>
							array(
								'label'       => '#343A46',
								'text'        => '#343A46',
								'background'  => '#FFF',
								'border'      => '#E9E9F0',
								'placeholder' => '#6C6E77',
							),
							'active'  =>
							array(
								'label'       => '#343A46',
								'text'        => '#343A46',
								'background'  => '#FFF',
								'border'      => '#6C6CFF',
								'placeholder' => '#6C6E77',
							),
							'warning' =>
							array(
								'label'       => '#343A46',
								'text'        => '#FF6C6C',
								'background'  => '#FFF',
								'border'      => '#FF6C6C',
								'placeholder' => '#6C6E77',
							),
						),
					),
					'button'    =>
					array(
						'signIn' =>
						array(
							'normal' =>
							array(
								'text'       => '#fff',
								'background' => '#6C6CFF',
								'border'     => '',
							),
							'hover'  =>
							array(
								'text'       => '#fff',
								'background' => '#1a1ac3',
								'border'     => '',
							),
						),
						'signUp' =>
						array(
							'normal' =>
							array(
								'text'       => '#fff',
								'background' => '#6C6CFF',
								'border'     => '',
							),
							'hover'  =>
							array(
								'text'       => '#fff',
								'background' => '#1a1ac3',
								'border'     => '',
							),
						),
					),
					'container' =>
					array(
						'main'   =>
						array(
							'background' => '#FFF',
							'border'     => '#E9E9F0',
						),
						'signIn' =>
						array(
							'background' => '#FFF',
							'border'     => '',
						),
						'signUp' =>
						array(
							'background' => '#FFF',
							'border'     => '',
						),
					),
				),
				'typography'  =>
				array(
					'field'  =>
					array(
						'label' =>
						array(
							'size'      => 14,
							'weight'    => 500,
							'transform' => '',
						),
						'input' =>
						array(
							'size'      => 14,
							'weight'    => 400,
							'transform' => '',
						),
					),
					'button' =>
					array(
						'size'      => 14,
						'weight'    => 500,
						'transform' => '',
					),
				),
				'sizeSpacing' =>
				array(
					'input'     =>
					array(
						'width'        => 395,
						'border'       => 1,
						'borderRadius' => 2,
						'padding'      => 16,
					),
					'button'    =>
					array(
						'width'        => 50,
						'border'       => 0,
						'borderRadius' => 2,
						'padding'      => 13,
						'align'        => 'left',
					),
					'container' =>
					array(
						'main'   =>
						array(
							'width'        => '1200',
							'border'       => 1,
							'borderRadius' => 16,
							'padding'      => 0,
							'align'        => '',
							'separator'    => 1,
						),
						'signIn' =>
						array(
							'width'        => '',
							'border'       => 0,
							'borderRadius' => 16,
							'padding'      => 54,
							'align'        => '',
							'separator'    => '',
						),
						'signUp' =>
						array(
							'width'        => '',
							'border'       => 0,
							'borderRadius' => 16,
							'padding'      => 54,
							'align'        => '',
							'separator'    => '',
						),
					),
				),
			),
		);
		return $defaultForm;
	}

	public function get_new_form_builder_data() {
		$formData = '';

		$oldForm = get_option( '__wholesalex_registration_form' );

		$newForm = get_option( 'wholesalex_registration_form' );

		$defaultForm = $this->get_empty_form();
		if ( ! $newForm && $oldForm ) {
			$oldForm = json_decode( $oldForm, true );
			foreach ( $oldForm as $field ) {
				$field['columnPosition'] = 'left';
				$field['parent']         = wp_unique_id( 'whx_form' );
				$field['label']          = $field['title'];
				$field['status']         = true;
				$field['conditions']     = array(
					'status'   => 'show',
					'relation' => 'all',
					'tiers'    => array(
						array(
							'_id'       => '1',
							'condition' => '',
							'value'     => '',
							'field'     => '',
							'src'       => 'registration_form',
						),
					),
				);
				if(!isset($field['option'])) {
					$field['option'] = array(array('name'=>'Select Option','value'=>''));
				}
				unset( $field['title'] );
				$field['migratedFromOldBuilder']     = true;
				$defaultForm['registrationFields'][] =
				array(
					'id'            => $field['parent'],
					'type'          => 'row',
					'columns'       => array( $field ),
					'isMultiColumn' => false,
				);

			}

			$formData = $defaultForm;

		} else {
			$formData = json_decode( $newForm, true );
		}

		return is_array( $formData ) ? $formData : $defaultForm;
	}


	/**
	 * Set Productwise Rule Data.
	 *
	 * @param int|string $rule_id Rule ID.
	 * @param int|string $product_id Product ID.
	 * @param string $type Rule Type.
	 * @param array $data Rule Data.
	 * @return void
	 */
	public function set_rule_data($rule_id, $product_id, $type, $data)
	{
		if (!isset($GLOBALS['wholesalex_rule_data'][$product_id])) {
			$GLOBALS['wholesalex_rule_data'][$product_id] = array();
		}
		if (!isset($GLOBALS['wholesalex_rule_data'][$product_id][$type])) {
			$GLOBALS['wholesalex_rule_data'][$product_id][$type][$rule_id] = array();
		}
		$GLOBALS['wholesalex_rule_data'][$product_id][$type][$rule_id] = $data;
	}

	/**
	 * Get Productwise Rule Data
	 *
	 * @param int|string $product_id Product ID.
	 * @param string $type Product Type.
	 * @return array
	 */
	public function get_rule_data($product_id, $type = '', $rule_id = '')
	{
		if ($type && $rule_id) {
			if (isset($GLOBALS['wholesalex_rule_data'][$product_id][$type][$rule_id])) {
				return $GLOBALS['wholesalex_rule_data'][$product_id][$type][$rule_id];
			} else {
				return array();
			}
		}
		if ($type) {
			if (isset($GLOBALS['wholesalex_rule_data'][$product_id][$type])) {
				return $GLOBALS['wholesalex_rule_data'][$product_id][$type];
			} else {
				return array();
			}
		}
		if (isset($GLOBALS['wholesalex_rule_data'][$product_id])) {
			return $GLOBALS['wholesalex_rule_data'][$product_id];
		}
		return array();
	}

	public function get_wholesalex_wholesale_prices($product_id) {
		if(!isset($GLOBALS['wholesalex_wholesale_prices'][$product_id])) {
			return false;
		}
		return $GLOBALS['wholesalex_wholesale_prices'][$product_id];
	}

	public function set_wholesalex_wholesale_prices($product_id,$price) {
		if(!isset($GLOBALS['wholesalex_wholesale_prices']) || !is_array($GLOBALS['wholesalex_wholesale_prices'])) {
			$GLOBALS['wholesalex_wholesale_prices'] = array();
		}
		$GLOBALS['wholesalex_wholesale_prices'][$product_id] = $price; 
	}
	public function get_wholesalex_regular_prices($product_id) {
		if(!isset($GLOBALS['wholesalex_regular_prices'][$product_id])) {
			return false;
		}
		return $GLOBALS['wholesalex_regular_prices'][$product_id];
	}

	public function set_wholesalex_regular_prices($product_id,$price) {
		if(!isset($GLOBALS['wholesalex_regular_prices']) || !is_array($GLOBALS['wholesalex_regular_prices'])) {
			$GLOBALS['wholesalex_regular_prices'] = array();
		}
		$GLOBALS['wholesalex_regular_prices'][$product_id] = $price; 
	}

	/**
	 * Smart Tag Generate for Email Template
	 *
	 * @param [type] ...$args
	 * @return void
	 */
	function smart_tag_name(...$args) {
		$current_date 	= gmdate('F j, Y');
		$admin_email 	= get_option('admin_email');
		$admin_user 	= get_user_by('email', $admin_email);
		$admin_name 	= $admin_user ? $admin_user->display_name : '';
		$site_name 		= get_bloginfo('name');
		$result 		= [];

		foreach ($args as $arg) {
			switch ($arg) { 
				case '{date}':
					$result[$arg] = $current_date;
					break;
				case '{site_name}':
					$result[$arg] = $site_name;
					break;
				case '{admin_name}':
					$result[$arg] = $admin_name;
					break;
			}
		}
		return $result;
	}
	/**
	 * Install Wholesalex Migration Tool Plugin With Ajax
	 *
	 * @return void
	 */
	public function wsx_migration_install_callback() {
        if ( ! file_exists( WP_PLUGIN_DIR . '/wholesalex-migration-tool/wholesalex-migration-tool.php' ) ) {
            include ABSPATH . 'wp-admin/includes/plugin-install.php';
            include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			
			if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
				include ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
			}
            if ( ! class_exists( 'Plugin_Upgrader' ) ) {
                include ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            }
            $plugin = 'wholesalex-migration-tool';
            $api = plugins_api( 'plugin_information', array(
                'slug' => $plugin,
                'fields' => array(
                    'short_description' => false,
                    'sections' => false,
                    'requires' => false,
                    'rating' => false,
                    'ratings' => false,
                    'downloaded' => false,
                    'last_updated' => false,
                    'added' => false,
                    'tags' => false,
                    'compatibility' => false,
                    'homepage' => false,
                    'donate_link' => false,
                ),
            ) );
			// Translators: %s is the plugin name and version.
            $title = sprintf( __( 'Installing Plugin: %s', 'wholesalex-pro' ), $api->name . ' ' . $api->version );
            $nonce = 'install-plugin_' . $plugin;
            $url = 'update.php?action=install-plugin&plugin=' . urlencode( $plugin );
            $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin( compact( 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
            $upgrader->install( $api->download_link );
            activate_plugin( '/wholesalex-migration-tool/wholesalex-migration-tool.php' );
			wp_redirect( admin_url( 'admin.php?page=wholesalex-migration' ) );
			exit;
        } else if ( file_exists( WP_PLUGIN_DIR . '/wholesalex-migration-tool/wholesalex-migration-tool.php' ) && ! is_plugin_active( 'wholesalex-migration-tool/wholesalex-migration-tool.php' ) ) {
            activate_plugin( '/wholesalex-migration-tool/wholesalex-migration-tool.php' );
			wp_redirect( admin_url( 'admin.php?page=wholesalex-overview' ) );
			exit;
        }
        
    }

}
