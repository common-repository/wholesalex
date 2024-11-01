<?php

/**
 * WholesaleX Dynamic Rules
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Data_Store;
use WC_Shipping_Free_Shipping;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Tax;
use WOPB_PRO\Currency_Switcher_Action;
use WP_User_Query;
use WOOMULTI_CURRENCY_F_Data;
use WP_Query;

/**
 * WholesaleX Dynamic Rules Class
 */
class WHOLESALEX_Dynamic_Rules {
	/**
	 * Contain Currently Applied Discount Source
	 *
	 * @var string
	 */
	public $discount_src = '';

	/**
	 * Dynamic Rules Min Order Quantity Session Name
	 *
	 * @var string
	 * @since 1.0.6
	 */
	public $min_order_qty_session_name = '';

	/**
	 * Contain The tier id, which is currently applied
	 *
	 * @var integer
	 */
	public $active_tier_id = 0;

	/**
	 * Contains sale price function name which run first
	 *
	 * @var string
	 */
	public $first_sale_price_generator = '';


	/**
	 * Contains dynamic rules products
	 *
	 * @var string
	 */
	public $dynamic_rules_products = array();

	/**
	 * Contains rulewise filtered products
	 *
	 * @var string
	 */
	public $rulewise_filtered_products = array();

	public $is_wholesalex_base_price_applied = false;

	public $price = '';

	/**
	 * Store All Valid Dynamic Rules For Current User/Given User ID
	 *
	 * @var array
	 */
	private $valid_dynamic_rules = array();

	private $product_page_notices = array();

	private $wholesale_prices       = array();
	private $rolewise_regular_price = array();

	private $active_tiers = array();

	private $rule_data = array();

	public static $cu_order_counts = 0;
	public static $cu_total_spent  = 0;

	private $current_shipping_zone     = '';
	private $cached_shipping_method_id = array();

	public static $total_cart_counts         = '';
	public static $total_unique_item_on_cart = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wholesalex_dynamic_rules_submenu_page' ) );

		add_action( 'rest_api_init', array( $this, 'dynamic_rule_restapi_callback' ) );

		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_custom_meta_on_wholesale_order' ), 10 );

		add_action( 'woocommerce_update_cart_action_cart_updated', array( $this, 'update_discounted_product' ) );

		add_filter( 'ppom_product_price', array( $this, 'product_price' ), 10, 2 );

		add_filter( 'ppom_product_price_on_cart', array( $this, 'set_price_on_ppom' ), 10, 2 );

		add_filter( 'wopb_query_args', array( $this, 'modify_wopb_query_args' ) );

		/**
		 * Rewrite Dynamic Rules: Cart Total
		 */
		add_action( 'wp_loaded', array( $this, 'get_valid_dynamic_rules' ) );

		add_action( 'woocommerce_blocks_loaded', array( $this, 'action_after_woo_block_loaded' ) );

		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'load_woo_checkout_block_script' ) );

		
		add_action( 'plugins_loaded', array( $this, 'dynamic_rules_handler' ) );

		add_filter( 'wopb_after_loop_image', array( $this, 'wopb_wholesalex_bogo_display_sale_badge' ), 10 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'wholesalex_bogo_display_sale_badge' ), 10 );
		add_action( 'woocommerce_before_single_product', array( $this, 'wholesalex_bogo_single_page_display_sale_badge' ), 10 );
		add_action('wp_head', array( $this, 'wholesalex_bogo_badge_add_custom_css' ) );

	}

	/**
	 * Dynamic Rules Handler
	 *
	 * @since 1.0.0
	 */
	public function dynamic_rules_handler() {
		

		if(!(function_exists('wholesalex_pro') && version_compare(WHOLESALEX_PRO_VER,'1.3.1','<='))) {
			return;
		}
		
		/**
		 * In Admin Interface Dynamic Rule Will Not apply.
		 *
		 * @since 1.0.8
		 */
		/**
		 * Add Ajax Check
		 *
		 * @since 1.1.5
		 */
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}
		$__user_id = apply_filters( 'wholesalex_dynamic_rule_user_id', get_current_user_id() );

		$__priorities = wholesalex()->get_quantity_based_discount_priorities();

		$this->discount_src = $__priorities[0];
		$__priorities       = array_reverse( $__priorities );

		foreach ( $__priorities as $key => $priority ) {
			if ( 0 == $key ) {
				$this->first_sale_price_generator = $priority . '_discounts';
			}

			delete_transient( 'wholesalex_pricing_tiers_' . $priority . '_' . $__user_id );

			$discount_status = apply_filters('wholesalex_'.$priority.'_discounts_enabled',true);
			if($discount_status) {
				$this->discounts_init( $priority . '_discounts' );
			}
		}


	}

	public function get_all_discounts( $user_id = '' ) {
		if ( '' !== $user_id ) {
			$user_id = get_current_user_id();
		}

		$this->rulewise_filtered_products = array();

		// get dynamic rules for given user id

		$user_role = wholesalex()->get_user_role( $user_id );

		$dynamic_rules = wholesalex()->get_dynamic_rules();
		if ( ! is_array( $dynamic_rules ) ) {
			return array();
		}

		$valid_rules = array();

		$valid_product_filters = array( 'all_products', 'products_in_list', 'products_not_in_list', 'cat_in_list', 'cat_not_in_list', 'attribute_in_list', 'attribute_not_in_list' );

		foreach ( $dynamic_rules as $discount ) {
			if ( isset( $discount['_rule_status'], $discount['_rule_for'], $discount['_product_filter'], $discount['_rule_type'], $discount[ $discount['_rule_type'] ] ) && $discount['_rule_status'] && in_array( $discount['_product_filter'], $valid_product_filters ) ) {

				$product_filter_data = array(
					$discount['_product_filter'] => array(),
				);

				if ( isset( $discount['limit'] ) && ! empty( $discount['limit'] ) ) {
					if ( ! self::has_limit( $discount['limit'] ) ) {
						continue;
					}
				}

				$__role_for = $discount['_rule_for'];
				switch ( $__role_for ) {
					case 'specific_roles':
						foreach ( $discount['specific_roles'] as $role ) {
							// Check The Discounts Rules In Valid or not.
							if ( (string) $role['value'] === (string) $user_role || 'role_' . $user_role === $role['value'] ) {
								array_push( $valid_rules, $discount );
								break;
							}
						}
						break;
					case 'specific_users':
						foreach ( $discount['specific_users'] as $user ) {
							if ( ( is_numeric( $user['value'] ) && (int) $user['value'] === (int) $user_id ) || 'user_' . $user_id === $user['value'] ) {
								array_push( $valid_rules, $discount );
								break;
							}
						}
						break;
					case 'all_roles':
						$__exclude_roles = apply_filters( 'wholesalex_dynamic_rules_exclude_roles', array( 'wholesalex_guest', 'wholesalex_b2c_users' ) );
						if ( is_array( $__exclude_roles ) && ! empty( $__exclude_roles ) ) {
							if (!in_array($user_role, $__exclude_roles)) { //phpcs:ignore
								array_push( $valid_rules, $discount );
								break;
							}
						} else {
							array_push( $valid_rules, $discount );
						}
						break;
					case 'all_users':
						$__exclude_users = apply_filters( 'wholesalex_dynamic_rules_exclude_users', array() );
						if ( is_array( $__exclude_users ) && ! empty( $__exclude_users ) ) {
							if (!in_array($user_id, $__exclude_users)) { //phpcs:ignore
								array_push( $valid_rules, $discount );
								break;
							}
						} else {
							if ( 0 !== $user_id ) {
								array_push( $valid_rules, $discount );
							}
						}
						break;
				}

				switch ( $discount['_product_filter'] ) {
					case 'all_products':
						$product_filter_data['all_products'] = true;
						break;
					case 'products_in_list':
						if ( ! isset( $discount['products_in_list'] ) ) {
							break;
						}
						foreach ( $discount['products_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['products_in_list'][] = $list['value'];
						}
						break;
					case 'products_not_in_list':
						if ( ! isset( $discount['products_not_in_list'] ) ) {
							break;
						}
						foreach ( $discount['products_not_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['products_not_in_list'][] = $list['value'];

						}

						break;
					case 'cat_in_list':
						if ( ! isset( $discount['cat_in_list'] ) ) {
							break;
						}
						foreach ( $discount['cat_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['cat_in_list'][] = $list['value'];
						}

						break;

					case 'cat_not_in_list':
						if ( ! isset( $discount['cat_not_in_list'] ) ) {
							break;
						}
						foreach ( $discount['cat_not_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['cat_not_in_list'][] = $list['value'];
						}
						break;
					case 'attribute_in_list':
						if ( ! isset( $discount['attribute_in_list'] ) ) {
							break;
						}
						foreach ( $discount['attribute_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['attribute_in_list'][] = $list['value'];
						}

						break;
					case 'attribute_not_in_list':
						if ( ! isset( $discount['attribute_not_in_list'] ) ) {
							break;
						}
						foreach ( $discount['attribute_not_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							$product_filter_data['attribute_not_in_list'][] = $list['value'];
						}
						break;
				}

				$this->rulewise_filtered_products[ $discount['id'] ] = $product_filter_data;
			}
		}
		return $valid_rules;

	}

	/**
	 * Apply WholesaleX Discount By WooCommerce Filter
	 *
	 * @param string $sale_price_generator WholesaleX Sale Price Generator.
	 * @since 1.0.0
	 */
	private function discounts_init( $sale_price_generator ) {
		// get single product sale price.
		add_filter( 'woocommerce_product_get_sale_price', array( $this, $sale_price_generator ), 9, 2 );

		// get variable product sale price.
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, $sale_price_generator ), 9, 2 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, $sale_price_generator ), 9, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, $sale_price_generator ), 9, 2 );

		
	}

	/**
	 * Single Product Discounts
	 *
	 * @param mixed      $sale_price Sale Price.
	 * @param WC_Product $product Product.
	 * @return mixed Sale Price.
	 * @since 1.0.0
	 * @since 1.0.4 Set Initial Sale Price to Session Added.
	 */
	public function single_product_discounts( $sale_price, $product ) {
		$__product_id = $product->get_id();
		/**
		 * Initial Sale Price Added to session For Further Processing
		 *
		 * @since 1.0.4
		 */
		$this->set_initial_sale_price_to_session( __FUNCTION__, $__product_id, $sale_price );
		$__single_product_show_tier = wholesalex()->get_single_product_setting( $product->get_ID(), '_settings_show_tierd_pricing_table' );
		if ( 'yes' !== $__single_product_show_tier ) {
			remove_filter( 'wholesalex_single_product_quantity_based_table', array( $this, 'quantity_based_pricing_table' ), 10, 2 );
			remove_filter( 'wholesalex_variation_product_quantity_based_table', array( $this, 'quantity_based_pricing_table' ), 10, 2 );
		}

		$__discounts_result = apply_filters(
			'wholesalex_single_product_discount_action',
			array(
				'sale_price' => $sale_price,
				'product'    => $product,
			)
		);

		$sale_price = $__discounts_result['sale_price'];
		if ( isset( $__discounts_result['discount_src'] ) ) {
			$this->discount_src = $__discounts_result['discount_src'];
		}
		if ( isset( $__discounts_result['active_tier_id'] ) ) {
			$this->active_tier_id = $__discounts_result['active_tier_id'];
		}

		if ( empty( $sale_price ) ) {
			return;
		} else {
			$this->set_discounted_product( $__product_id );
			$this->price = $sale_price;

			return $sale_price;
		}
	}
	/**
	 * Profile Discounts
	 *
	 * @param mixed      $sale_price Sale Price.
	 * @param WC_Product $product Product.
	 * @return mixed Sale Price.
	 * @since 1.0.0
	 * @since 1.0.4 Set Initial Sale Price to Session Added.
	 * @since 1.2.4 B2B Plugin Mode check added
	 */
	public function profile_discounts( $sale_price, $product ) {
		$__user_id    = apply_filters( 'wholesalex_dynamic_rule_user_id', get_current_user_id() );
		$__product_id = $product->get_id();
		/**
		 * Initial Sale Price Added to session For Further Processing
		 *
		 * @since 1.0.4
		 */
		$this->set_initial_sale_price_to_session( __FUNCTION__, $__product_id, $sale_price );

		$plugins_status = wholesalex()->get_setting( '_settings_status', 'b2b' );

		/**
		 * User activation status will be check only if plugin mode is b2b
		 *
		 * @since 1.2.4
		 */
		if ( 'b2b' === $plugins_status ) {
			if ( ! ( 'active' === wholesalex()->get_user_status( $__user_id ) ) ) {
				if ( empty( $sale_price ) ) {
					return;
				} else {
					$this->price = $sale_price;
					return $sale_price;
				}
			}
		}

		// Profile Discounts.
		$__discounts_result = apply_filters(
			'wholesalex_profile_discount_action',
			array(
				'sale_price' => $sale_price,
				'product'    => $product,
			)
		);

		$sale_price = $__discounts_result['sale_price'];
		if ( isset( $__discounts_result['discount_src'] ) ) {
			$this->discount_src = $__discounts_result['discount_src'];
		}
		if ( isset( $__discounts_result['active_tier_id'] ) ) {
			$this->active_tier_id = $__discounts_result['active_tier_id'];
		}

		// Retrieve User Profile Dynamic Rules Settings.
		$__profile_settings = get_user_meta( $__user_id, '__wholesalex_profile_settings', true );

		// Profile Tax Section.

		// If User has tax exemption status, then set tax exemption transient true. Which will be used to set tax exemption for current user.
		if ( isset( $__profile_settings['_wholesalex_profile_override_tax_exemption'] ) && 'yes' === $__profile_settings['_wholesalex_profile_override_tax_exemption'] ) {
			set_transient( 'wholesalex_tax_exemption_' . $__user_id, true );
		}

		// Profile Shipping Section.

		if ( isset( $__profile_settings['_wholesalex_profile_override_shipping_method'] ) && 'yes' === $__profile_settings['_wholesalex_profile_override_shipping_method'] ) {

			if ( isset( $__profile_settings['_wholesalex_profile_shipping_method_type'] ) ) {

				switch ( $__profile_settings['_wholesalex_profile_shipping_method_type'] ) {

					case 'force_free_shipping':
						set_transient( 'wholesalex_force_free_shipping_' . $__user_id, true );
						break;
					case 'specific_shipping_methods':
						if ( ! isset( $__profile_settings['_wholesalex_profile_shipping_zone'] ) || ! isset( $__profile_settings['_wholesalex_profile_shipping_zone_methods'] ) ) {
							break;
						}
						delete_transient( 'wholesalex_profile_shipping_methods_' . $__user_id );
						delete_transient( 'wholesalex_shipping_methods_' . $__user_id );

						$__zone_id               = $__profile_settings['_wholesalex_profile_shipping_zone'];
						$__shipping_zone_methods = $__profile_settings['_wholesalex_profile_shipping_zone_methods'];

						$__available_methods = array();

						if ( ! empty( $__shipping_zone_methods ) && is_array( $__shipping_zone_methods ) ) {
							foreach ( $__shipping_zone_methods as $method ) {
								$__available_methods[ $method['value'] ] = true;
							}
						}

						$__shipping_method_transient = get_transient( 'wholesalex_profile_shipping_methods_' . $__user_id );

						if ( ! $__shipping_method_transient && ! empty( $__zone_id ) ) {
							$__temp_shipping_data                                = array();
							$__temp_shipping_data[ $__zone_id ][ $__product_id ] = $__available_methods;
							set_transient( 'wholesalex_profile_shipping_methods_' . $__user_id, $__temp_shipping_data );
						}
						break;
					default:
						// code...
						break;
				}
			}
		}
		
		if ( empty( $sale_price ) ) {
			return;
		} else {
			$this->set_discounted_product( $__product_id );
			$this->price = $sale_price;
			return $sale_price;
		}
	}
	/**
	 * Category Discounts
	 *
	 * @param mixed      $sale_price Sale Price.
	 * @param WC_Product $product Product.
	 * @return mixed Sale Price.
	 * @since 1.0.0
	 * @since 1.0.4 Set Initial Sale Price to Session Added.
	 */
	public function category_discounts( $sale_price, $product ) {
		$__product_id = $product->get_id();
		/**
		 * Initial Sale Price Added to session For Further Processing
		 *
		 * @since 1.0.4
		 */
		$this->set_initial_sale_price_to_session( __FUNCTION__, $__product_id, $sale_price );

		$__discounts_result = apply_filters(
			'wholesalex_category_discount_action',
			array(
				'sale_price' => $sale_price,
				'product'    => $product,
			)
		);

		$sale_price = $__discounts_result['sale_price'];
		if ( isset( $__discounts_result['discount_src'] ) ) {
			$this->discount_src = $__discounts_result['discount_src'];
		}
		if ( isset( $__discounts_result['active_tier_id'] ) ) {
			$this->active_tier_id = $__discounts_result['active_tier_id'];
		}

		if ( empty( $sale_price ) ) {
			return;
		} else {
			$this->set_discounted_product( $__product_id );
			$this->price = $sale_price;
			return $sale_price;
		}
	}
	/**
	 * Dynamic Rule Discounts
	 *
	 * @param mixed      $sale_price Sale Price.
	 * @param WC_Product $product Product.
	 * @return mixed Sale Price.
	 * @since 1.0.0
	 * @since 1.0.4 Set Initial Sale Price to Session Added.
	 */
	public function dynamic_rule_discounts( $sale_price, $product ) {
		/**
		 * Initial Sale Price Added to session For Further Processing
		 *
		 * @since 1.0.4
		 */
		$this->set_initial_sale_price_to_session( __FUNCTION__, $product->get_id(), $sale_price );

		$__override_sale_price = false;
		$__user_id             = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );

		/**
		 * Activation Status Check if user is logged in
		 *
		 * @since 1.0.10
		 */
		/**
		 * User activation status will be check only if plugin mode is b2b
		 *
		 * @since 1.2.4
		 */
		if ( is_user_logged_in() ) {
			$plugins_status = wholesalex()->get_setting( '_settings_status', 'b2b' );
			if ( 'b2b' === $plugins_status ) {
				if ( ! ( $__user_id && 'active' === wholesalex()->get_user_status( $__user_id ) ) ) {
					if ( empty( $sale_price ) ) {
						return;
					} else {
						return $sale_price;
					}
				}
			}
		}

		// Remove All function associated with woocommerce_sale_flash filter.
		// remove_all_filters( 'woocommerce_sale_flash' );

		delete_transient( 'wholesalex_pricing_tiers_dynamic_rule_' . $__user_id );

		$__discounts = $this->get_all_discounts( $__user_id );
		$__role      = wholesalex()->get_current_user_role();

		if ( empty( $__discounts ) ) {
			if ( empty( $sale_price ) ) {
				return;
			} else {

				return $sale_price;
			}
		}

		$__discounts_for_me = array();

		foreach ( $__discounts as $discount ) {
			$__has_discount  = false;
			$__product_id    = $product->get_id();
			$__parent_id     = $product->get_parent_id();
			$__cat_ids       = wc_get_product_term_ids( 0 === $__parent_id ? $__product_id : $__parent_id, 'product_cat' );
			$__regular_price = $product->get_regular_price();
			$__for           = '';
			$__src_id        = '';

			if ( isset( $discount['_rule_status'] ) && $discount['_rule_status'] && ! empty( $discount['_rule_status'] ) && isset( $discount['_product_filter'] ) ) {
				switch ( $discount['_product_filter'] ) {
					case 'all_products':
						$__has_discount = true;
						$__for          = 'all_products';
						$__src_id       = -1;
						break;
					case 'products_in_list':
						if ( ! isset( $discount['products_in_list'] ) ) {
							break;
						}
						foreach ( $discount['products_in_list'] as $list ) {
							if ( ! isset( $list['value'] ) ) {
								continue;
							}
							if ( (int) $__product_id === (int) $list['value'] || (int) $__parent_id === (int) $list['value'] ) {
								$__has_discount = true;
								$__for          = 'product';
								$__src_id       = $__product_id;
								break;
							}
						}
						break;
					case 'products_not_in_list':
						if ( ! isset( $discount['products_not_in_list'] ) ) {
							break;
						}
						$__flag = true;
						foreach ( $discount['products_not_in_list'] as $list ) {
							if ( isset( $list['value'] ) && ( (int) $__product_id === (int) $list['value'] || (int) $__parent_id === (int) $list['value'] ) ) {
								$__flag = false;
							}
						}
						if ( $__flag ) {
							$__has_discount = true;
							$__for          = 'product';
							$__src_id       = $__product_id;
						}
						break;
					case 'cat_in_list':
						if ( ! isset( $discount['cat_in_list'] ) ) {
							break;
						}
						foreach ( $discount['cat_in_list'] as $list ) {
							if (in_array($list['value'], $__cat_ids)) { //phpcs:ignore
								$__has_discount = true;
								$__for          = 'cat';
								$__src_id       = $list['value'];
								break;
							}
						}

						break;

					case 'cat_not_in_list':
						if ( ! isset( $discount['cat_not_in_list'] ) ) {
							break;
						}
						$__flag = true;
						foreach ( $discount['cat_not_in_list'] as $list ) {
							if (in_array($list['value'], $__cat_ids)) { //phpcs:ignore
								$__flag = false;
							}
						}
						if ( $__flag ) {
							$__has_discount = true;
							$__for          = 'cat';
							$__src_id       = isset( $__cat_ids[0] ) ? $__cat_ids[0] : 0;
						}
						break;
					case 'attribute_in_list':
						if ( ! isset( $discount['attribute_in_list'] ) ) {
							break;
						}
						if ( 'product_variation' === $product->post_type ) {
							foreach ( $discount['attribute_in_list'] as $list ) {
								if ( isset( $list['value'] ) && (int) $__product_id === (int) $list['value'] ) {
									$__has_discount = true;
									$__for          = 'variation';
									$__src_id       = $__product_id;
									break;
								}
							}
						}
						break;
					case 'attribute_not_in_list':
						if ( ! isset( $discount['attribute_not_in_list'] ) ) {
							break;
						}
						if ( 'product_variation' === $product->post_type ) {
							$__flag = true;
							foreach ( $discount['attribute_not_in_list'] as $list ) {
								if ( isset( $list['value'] ) && (int) $__product_id === (int) $list['value'] ) {
									$__flag = false;
								}
							}
							if ( $__flag ) {
								$__has_discount = true;
								$__for          = 'variation';
								$__src_id       = $__product_id;
							}
						}
						break;
				}
			}

			if ( ! $__has_discount ) {
				continue;
			}
			if ( isset( $discount['limit'] ) && ! empty( $discount['limit'] ) ) {
				if ( ! self::has_limit( $discount['limit'] ) ) {
					continue;
				}
			}

			if ( ! isset( $discount['_rule_for'] ) ) {
				continue;
			}

			if ( isset( $discount['conditions']['tiers'] ) ) {
				$__conditions = $discount['conditions'];		
				if ( ! self::is_conditions_fullfiled( $__conditions['tiers'] ) ) {
					continue;
				}
			}

			if ( ! isset( $discount['_rule_type'] ) || ! isset( $discount[ $discount['_rule_type'] ] ) ) {
				continue;
			}

			switch ( $discount['_rule_type'] ) {
				case 'quantity_based':
					if ( ! isset( $discount['quantity_based']['tiers'] ) ) {
						break;
					}
					
					$data = apply_filters(
						'wholesalex_dynamic_rule_quantity_based_action',
						array(
							'id'            => $discount['id'],
							'tiers'         => $discount['quantity_based']['tiers'],
							'product_id'    => $__product_id,
							'cat_ids'       => $__cat_ids,
							'regular_price' => $__regular_price,
							'for'           => $__for,
							'src_id'        => $__src_id,
						)
					);
					if ( isset( $data['override_sale_price'] ) && $data['override_sale_price'] ) {
						$__override_sale_price = true;
					}
					if ( isset( $data['active_tier_id'] ) ) {
						$this->active_tier_id = $data['active_tier_id'];
					}
					if ( isset( $data['sale_price'] ) ) {
						$sale_price = $data['sale_price'];
					}					

					break;
				case 'extra_charge':
					delete_transient( 'wholesalex_profile_payment_gateways_' . $__user_id );

					$__discounts = $discount['extra_charge'];
					$__rule_id   = $discount['id'];

					do_action( 'wholesalex_dynamic_rules_extra_charge_action', $discount['extra_charge'], $discount['id'], $__product_id );

					break;
			}
		}

		if ( $__override_sale_price ) {
			$this->discount_src = 'dynamic_rule';
		}

		if ( empty( $sale_price ) ) {
			return;
		} else {
			$this->set_discounted_product( $__product_id );
			return $sale_price;
		}
	}
	/**
	 * WholesaleX Dynamic Rule Submenu Page
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function wholesalex_dynamic_rules_submenu_page() {
		$slug = apply_filters( 'wholesalex_dynamic_rules_submenu_slug', 'wholesalex_dynamic_rules' );
		add_submenu_page(
			wholesalex()->get_menu_slug(),
			__( 'Dynamic Rules', 'wholesalex' ),
			__( 'Dynamic Rules', 'wholesalex' ),
			apply_filters( 'wholesalex_capability_access', 'manage_options' ),
			$slug,
			array( $this, 'wholesalex_dynamic_rules_content' )
		);
	}

	/**
	 * Dynamic Rule Rest API Callback
	 *
	 * @since 1.0.0
	 */
	public function dynamic_rule_restapi_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/dynamic_rule_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'dynamic_rule_restapi_action' ),
					'permission_callback' => array( $this, 'dynamic_rules_restapi_permission_callback' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * WholesaleX Dynamic Rule RestAPI Permission Callback
	 */
	public function dynamic_rules_restapi_permission_callback() {
		$status = apply_filters( 'dynamic_rules_restapi_permission_callback', current_user_can( 'manage_options' ) );
		return $status;
	}


	/**
	 * Get Category actions
	 *
	 * @param object $server Server.
	 * @return void
	 * @since 1.0.0
	 */
	public function dynamic_rule_restapi_action( $server ) {
		$post = $server->get_params();

		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}

		$type = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';

		if ( 'get' === $type ) {
			if ( current_user_can( 'manange_options' ) && is_admin() ) {
				$__dynamic_rules = wholesalex()->get_dynamic_rules();
			} else {
				$__dynamic_rules = wholesalex()->get_dynamic_rules_by_user_id( get_current_user_id() );
			}
			$__dynamic_rules = apply_filters( 'wholesalex_get_all_dynamic_rules', array_values( $__dynamic_rules ) );
			if ( empty( $__dynamic_rules ) ) {
				$__dynamic_rules = array(
					array(
						'id'    => 1,
						'label' => __( 'New Rule', 'wholesalex' ),
					),
				);
			}

			// rest apis.
			// temp: Dynamic Rule -> must be changed before release.

			$ajax_action = isset( $post['ajax_action'] ) ? sanitize_text_field( $post['ajax_action'] ) : '';
			$query       = isset( $post['query'] ) ? sanitize_text_field( $post['query'] ) : '';
			switch ( $ajax_action ) {
				case 'get_users':
					wp_send_json( $this->get_users( $query ) );
					break;
				case 'get_roles':
					wp_send_json( $this->get_roles() );
					break;
				case 'get_products':
					if ( current_user_can( 'manage_options' ) ) {
						wp_send_json( $this->get_products( $query ) );
					} else {
						wp_send_json( $this->get_products( $query, get_current_user_id() ) );
					}
					break;
				case 'get_categories':
					wp_send_json( $this->get_categories( $query ) );
					break;
				case 'get_variation_products':
					if ( current_user_can( 'manage_options' ) ) {
						wp_send_json( $this->get_variation_products( $query ) );
					} else {
						wp_send_json( $this->get_variation_products( $query, get_current_user_id() ) );
					}
					break;
				case 'get_products_with_variations':
					if ( current_user_can( 'manage_options' ) ) {
						wp_send_json( $this->get_products_with_variations( $query ) );
					} else {
						wp_send_json( $this->get_products_with_variations( $query, get_current_user_id() ) );
					}
					break;
				case 'get_payment_gateways':
					wp_send_json( $this->get_payment_gateways() );
					break;
				case 'get_shipping_methods':
					$shipping_zone = isset( $post['depends'] ) ? sanitize_text_field( $post['depends'] ) : '';
					wp_send_json( $this->get_shipping_methods( $shipping_zone ) );
					break;
				case 'get_shipping_country':
					wp_send_json( self::get_shipping_country( ) );
					break;

				default:
					// code...
					break;
			}

			wp_send_json_success(
				array(
					'default' => self::get_dynamic_rules_field(),
					'value'   => $__dynamic_rules,
				)
			);
		} elseif ( 'post' === $type ) {
			$_id   = isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '';
			$_rule = isset( $post['rule'] ) ? wp_unslash( $post['rule'] ) : '';
			$_rule = json_decode( $_rule, true );
			$_rule = wholesalex()->sanitize( $_rule );
			$_flag = true;

			$is_frontend = isset( $post['isFrontend'] ) ? true : false;

			if ( isset( $post['check'] ) && empty( wholesalex()->get_dynamic_rules( $_id ) ) ) {
				$_flag = false;
			}
			if ( $_flag ) {
				wholesalex()->set_dynamic_rules( $_id, $_rule, ( isset( $post['delete'] ) && $post['delete'] ) ? 'delete' : '', $is_frontend );
				if ( isset( $post['delete'] ) && $post['delete'] ) {
					wp_send_json_success(
						array(
							'message' => __( 'Sucessfully Deleted.', 'wholesalex' ),
						)
					);
				} else {
					wp_send_json_success(
						array(
							'message' => __( 'Successfully Saved.', 'wholesalex' ),
						)
					);
				}
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Before Status Update, You Have to Save Rule Status.', 'wholesalex' ),
					)
				);
			}
		}
	}

	/**
	 * Get Users
	 *
	 * @param string $query Search Query
	 * @return array
	 * @since 1.2.4
	 * @since 1.2.4 B2B Plugin status check added
	 */
	public function get_users( $query ) {
		$user_fields    = array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' );
		$plugins_status = wholesalex()->get_setting( '_settings_status', 'b2b' );

		if ( 'b2b' === $plugins_status ) {
			$args = array(
				'meta_query'  => array(
					array(
						'key'     => '__wholesalex_status',
						'value'   => 'active',
						'compare' => '=',
					),
				),
				'orderby'     => 'registered',
				'order'       => 'DESC',
				'number'      => 10,
				'fields'      => $user_fields,
				'count_total' => true,
				'search'      => '*' . $query . '*',
			);
		} else {
			$args = array(
				'orderby'     => 'registered',
				'order'       => 'DESC',
				'number'      => 10,
				'fields'      => $user_fields,
				'count_total' => true,
				'search'      => '*' . $query . '*',
			);
		}

		$user_search = new WP_User_Query( $args );
		$users       = (array) $user_search->get_results();

		$user_options = array();
		foreach ( $users as $user ) {
			$user_options[] = array(
				'name'  => $user->user_login,
				'value' => 'user_' . $user->ID,
			);
		}

		return array(
			'status' => true,
			'data'   => $user_options,
		);
	}

	/**
	 * Get Products
	 *
	 * @param string $query Search Query.
	 * @return array
	 * @since 1.2.4
	 */
	public function get_products( $query = '', $user_id = '', $without_child = false ) {
		global $wpdb;
		$query = sanitize_text_field( $query );
		
		$sql = "
			SELECT ID
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
		";
	
		if ( $user_id ) {
			$sql .= " AND p.post_author = %d ";
			$sql = $wpdb->prepare( $sql, $user_id );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared	
		}
	
		if ( $query ) {
			$sql .= " AND (";
			$sql .= " EXISTS (
				SELECT 1
				FROM {$wpdb->postmeta} pm_sku
				WHERE pm_sku.post_id = p.ID
				AND pm_sku.meta_key = '_sku'
				AND pm_sku.meta_value LIKE %s
			)";
	
			$sql .= " OR p.post_title LIKE %s";
			$sql .= ")";
			$sql = $wpdb->prepare( $sql, '%' . $wpdb->esc_like( $query ) . '%', '%' . $wpdb->esc_like( $query ) . '%' );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared	
		}
	
		$product_ids = $wpdb->get_col( $sql );//phpcs:ignore
	
		$products = array();
		foreach ( $product_ids as $product_id ) {
			$__product = wc_get_product( $product_id );
			if ( $__product ) {
				$product = array(
					'value' => strval( $product_id ),
					'name'  => esc_attr( $__product->get_name() ),
				);
				if ( $without_child ) {
					$__childrens = $__product->get_children();
					if ( empty( $__childrens ) ) {
						$products[] = $product;
					}
				} else {
					$products[] = $product;
				}
			}
		}
	
		return array(
			'status' => true,
			'data'   => $products,
		);
	}

	/**
	 * Get Categories
	 *
	 * @param string $query
	 * @return array
	 */
	public function get_categories( $query = '' ) {
		 $args = array(
			 'taxonomy'   => array( 'product_cat' ),
			 'orderby'    => 'id',
			 'order'      => 'ASC',
			 'hide_empty' => true,
			 'fields'     => 'all',
			 'name__like' => $query,
		 );

		 $categories         = get_terms( $args );
		 $categories_options = array();

		 foreach ( $categories as $category ) {
			 $categories_options[] = array(
				 'value' => strval( $category->term_id ),
				 'name'  => $category->name,
			 );
		 }

		 return array(
			 'status' => true,
			 'data'   => $categories_options,
		 );
	}

	/**
	 * Get Variation Products
	 *
	 * @param string $query Search Query
	 * @return array
	 * @since 1.2.4
	 */
	public function get_variation_products( $query = '', $user_id = '' ) {
		$args = array(
			'post_type'   => 'product_variation',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		);

		if ( $user_id ) {
			$args['author'] = $user_id;
		}
		if ( $query ) {
			$args['s'] = $query;
		}
		$product_variations = get_posts(
			$args
		);

		// Variation products.....
		$variation_products = array();

		foreach ( $product_variations as $product ) {
			$productobj = wc_get_product( $product );

			if ( $productobj ) {
				$productobjname = $productobj->get_name();

				if ( is_a( $productobj, 'WC_Product_Variation' ) ) {
					$attributes           = $productobj->get_variation_attributes();
					$number_of_attributes = count( $attributes );
					if ( $number_of_attributes > 2 ) {
						$productobjname .= ' - ';
						foreach ( $attributes as $attribute ) {
							$productobjname .= $attribute . ', ';
						}
						$productobjname = substr( $productobjname, 0, -2 );
					}
				}
				$variation_products[] = array(
					'value' => strval( $product ),
					'name'  => $productobjname,
				);
			}
		}

		return array(
			'status' => true,
			'data'   => $variation_products,
		);
	}

	/**
	 * Get Products with variations
	 *
	 * @param string $query Search Query
	 * @param string $user_id User ID
	 * @return void
	 */
	public function get_products_with_variations( $query = '', $user_id = '' ) {
		$products           = $this->get_products( $query, $user_id, true );
		$variation_products = $this->get_variation_products( $query, $user_id );

		return array(
			'status' => true,
			'data'   => array_merge( $products['data'], $variation_products['data'] ),
		);
	}

	/**
	 * Get Available Payment Gateways Options
	 *
	 * @return array
	 * @since 1.2.4
	 */
	public function get_payment_gateways() {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		if ( ! is_array( $available_gateways ) ) {
			$available_gateways = array();
		}

		$payment_gateways = array();
		foreach ( $available_gateways as $key => $gateway ) {
			if ( $gateway->method_title ) {
				$payment_gateways[] = array(
					'value' => $key,
					'name'  => $gateway->method_title,
				);
			}
		}

		return array(
			'status' => true,
			'data'   => $payment_gateways,
		);
	}

	/**
	 * Get Available Tax Classes Options
	 *
	 * @return array
	 * @since 1.2.4
	 */
	public static function get_tax_classes() {
		$tax_rate_classes      = WC_Tax::get_tax_rate_classes();
		$__tax_classes_options = array(
			'' => 'Choose Tax Class..',
		);
		foreach ( $tax_rate_classes as  $tax_class ) {
			$__tax_classes_options[ $tax_class->slug ] = $tax_class->name;
		}
		return $__tax_classes_options;
	}

	/**
	 * Get WholesaleX Roles
	 *
	 * @return void
	 */
	public function get_roles() {
		$__roles_options = wholesalex()->get_roles( 'roles_option' );
		return array(
			'status' => true,
			'data'   => $__roles_options,
		);
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
		$__shipping_zones[0] = __( 'Choose Shipping Zone...', 'wholesalex' );
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
		if(! $zone ){
			return array(
				'status' => true,
				'data'   => array(),
			);
		}
		$zone_shipping_methods = $zone->get_shipping_methods();

		$__shipping_methods = array();

		foreach ( $zone_shipping_methods as $key => $method ) {
			if ( $method->is_enabled() ) {
				$method_instance_id = $method->get_instance_id();
				$method_title       = $method->get_title();
				if ( $method_title ) {
					$__shipping_methods[] = array(
						'value' => strval( $method_instance_id ),
						'name'  => $method_title,
					);
				}
			}
		}

		return array(
			'status' => true,
			'data'   => $__shipping_methods,
		);
	}

	public static function get_shipping_country(){
		$countryClass = new \WC_Countries();
		$countryList = $countryClass->get_shipping_countries();
		$result = array_map(function($name, $value){ return array('name' => $name, 'value' => $value);}, $countryList, array_keys($countryList));

		return array(
			'status' => true,
			'data'   => $result,
		);
	}
	/**
	 * WholesaleX Dynamic Rules Content
	 *
	 * @since 1.0.0
	 * @since 1.0.1 _conditions_operator Option Key Changed.
	 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
	 * @access public
	 */
	public function wholesalex_dynamic_rules_content() {
		wp_enqueue_script( 'wholesalex_dynamic_rules' );
		$__dynamic_rules = array_values( wholesalex()->get_dynamic_rules() );
		if ( empty( $__dynamic_rules ) ) {
			$__dynamic_rules = array(
				array(
					'id'    => floor( microtime( true ) * 1000 ),
					'label' => __( 'New Rule', 'wholesalex' ),
				),
			);
		}
		if ( is_admin() ) {
			$__dynamic_rules = wholesalex()->get_dynamic_rules();
		} else {
			$__dynamic_rules = wholesalex()->get_dynamic_rules_by_user_id( get_current_user_id() );
		}
		$__dynamic_rules = apply_filters( 'wholesalex_get_all_dynamic_rules', array_values( $__dynamic_rules ) );
		if ( empty( $__dynamic_rules ) ) {
			$__dynamic_rules = array(
				array(
					'id'    => floor( microtime( true ) * 1000 ),
					'label' => __( 'New Rule', 'wholesalex' ),
				),
			);
		}
		wp_localize_script(
			'wholesalex_dynamic_rules',
			'whx_dr',
			array(
				'fields' => self::get_dynamic_rules_field(),
				'rule'   => $__dynamic_rules,
				'nonce'  => wp_create_nonce( 'whx-export-dynamic-rules' ),
				'i18n'	 => array(
					'dynamic_rules' => __('Dynamic Rules','wholesalex'),
					'please_fill_all_fields' => __('Please Fill All Fields.','wholesalex'),
					'minimum_product_quantity_should_greater_then_free_product_qty' => __('Minimum Product Quantity Should Greater then Free Product Quantity.','wholesalex'),
					'rule_title' => __('Rule Title','wholesalex'),
					'create_dynamic_rule' => __('Create Dynamic Rule','wholesalex'),
					'import' => __('Import','wholesalex'),
					'export' => __('Export','wholesalex'),
					'untitled' => __('Untitled','wholesalex'),
					'duplicate_of' => __('Duplicate of ','wholesalex'),
					'delete_this_rule' => __('Delete this Rule.','wholesalex'),
					'duplicate_this_rule' => __('Duplicate this Rule.','wholesalex'),
					'show_hide_rule_details' => __('Show/Hide Rule Details.','wholesalex'),
					'vendor' => __('Vendor #','wholesalex'),
					'untitled_rule' => __('Untitled Rule','wholesalex'),
					'error_occured' => __('Error Occured!','wholesalex'),
					'map_csv_fields_to_dynamic_rules' => __('Map CSV Fields to Dynamic Rules','wholesalex'),
					'select_field_from_csv_msg' => __('Select fields from your CSV file to map against role fields, or to ignore during import.','wholesalex'),
					'column_name' => __('Column name','wholesalex'),
					'map_to_field' => __('Map to field','wholesalex'),
					'do_not_import' => __('Do not import','wholesalex'),
					'run_the_importer' => __('Run the importer','wholesalex'),
					'importing' => __('Importing','wholesalex'),
					'upload_csv' => __('Upload CSV','wholesalex'),
					'you_can_upload_only_csv_file_format' => __('You can upload only csv file format','wholesalex'),
					'your_dynamic_rules_are_now_being_importing' => __('Your Dynamic Rules are now being imported..','wholesalex'),
					'update_existing_rules' => __('Update Existing Rules','wholesalex'),
					'select_update_exising_rule_msg' => __('Selecting "Update Existing Rules" will only update existing rules. No new rules will be added.','wholesalex'),
					'continue' => __('Continue','wholesalex'),
					'dynamic_rule_imported' => __(' Dynamic Rules Imported.','wholesalex'),
					'dynamic_rule_updated' => __(' Dynamic Rules Updated.','wholesalex'),
					'dynamic_rule_skipped' => __(' Dynamic Rules Skipped.','wholesalex'),
					'dynamic_rule_failed' => __(' Dynamic Rules Failed.','wholesalex'),
					'view_error_logs' => __('View Error Logs','wholesalex'),
					'dynamic_rule' => __('Dynamic Rule','wholesalex'),
					'reason_for_failure' => __('Reason for failure','wholesalex'),
					'import_dynamic_rules' => __('Import Dynamic Rules','wholesalex'),
				)
			)
		);
		?>
		<div id="_wholesalex_dynamic_rules"></div>
		<?php
	}




	/**
	 * Get Dynamic Rules Fields
	 */
	public static function get_dynamic_rules_field() {
		return apply_filters(
			'wholesalex_dynamic_rules_field',
			array(
				'create_n_save_btn' => array(
					'type' => 'buttons',
					'attr' => array(
						'create' => array(
							'type'  => 'button',
							'label' => __( 'Create Dynamic Rule', 'wholesalex' ),
						),
					),
				),
				'_new_rule'         => array(
					'label' => __( 'New Dynamic Rule', 'wholesalex' ),
					'type'  => 'rule',
					'attr'  => array(
						'_rule_title_n_status_section' => array(
							'label' => '',
							'type'  => 'title_n_status',
							'_id'   => 1,
							'attr'  => array(
								'_rule_title'  => array(
									'type'        => 'text',
									'label'       => __( 'Rule Title', 'wholesalex' ),
									'placeholder' => __( 'Rule Title', 'wholesalex' ),
									'default'     => '',
									'help'        => '',
								),
								'_rule_status' => array(
									'type'    => 'switch',
									'label'   => __( 'Rule Status', 'wholesalex' ),
									'default' => false,
									'help'    => '',
								),
								'save_rule'    => array(
									'type'  => 'button',
									'label' => __( 'Save', 'wholesalex' ),
								),
							),
						),
						'_rule_section'                => array(
							'label' => '',
							'type'  => 'rules',
							'attr'  => array(
								'_rule_type'            => array(
									'type'    => 'select',
									'label'   => __( 'Rule Type', 'wholesalex' ),
									'options' => apply_filters(
										'wholesalex_dynamic_rules_rule_type_options',
										array(
											''         => __( 'Choose Rule...', 'wholesalex' ),
											'product_discount' => __( 'Product Discount', 'wholesalex' ),
											'cart_discount' => __( 'Cart Discount ', 'wholesalex' ),
											'payment_discount' => __( 'Payment Method Discount', 'wholesalex' ),
											'payment_order_qty' => __( 'Required Quantity for Payment Method', 'wholesalex' ),
											'buy_x_get_one' => __( 'BOGO Discounts (Buy X Get One Free)', 'wholesalex' ),
											'shipping_rule' => __( 'Shipping Rule', 'wholesalex' ),
											'min_order_qty' => __( 'Minimum Order Quantity', 'wholesalex' ),
											'tax_rule' => __( 'Tax Rule', 'wholesalex' ),
											'pro_restrict_checkout' => __( 'Checkout Restriction', 'wholesalex' ),
											'pro_quantity_based' => __( 'Quantity Based Discount (Pro)', 'wholesalex' ),
											'pro_extra_charge' => __( 'Extra Charge (Pro)', 'wholesalex' ),
											'pro_buy_x_get_y' => __( 'Buy X Get Y Free (Pro)', 'wholesalex' ),
											'pro_max_order_qty' => __( 'Maximum Order Quantity (Pro)', 'wholesalex' ),
											'pro_restrict_product_visibility' => __( 'Restrict Product Visibility (Pro)', 'wholesalex' ),
											'pro_hidden_price' => __( 'Hidden Price (Pro)', 'wholesalex' ),
											'pro_non_purchasable' => __( 'Non Purchasable (Pro)', 'wholesalex' ),
										),
										'rule_type'
									),
									'default' => '',
									'help'    => '',
								),
								'_rule_for'             => array(
									'type'    => 'select',
									'label'   => __( 'Select User/Role', 'wholesalex' ),
									'options' => apply_filters(
										'wholesalex_dynamic_rules_rule_for_options',
										array(
											''          => __( 'Select Users/Role...', 'wholesalex' ),
											'all'       => __( 'All (Registered and Guest Users)', 'wholesalex' ),
											'all_users' => __( 'All Registered Users', 'wholesalex' ),
											'all_roles' => __( 'All B2B Roles', 'wholesalex' ),
											'specific_users' => __( 'Specific Users', 'wholesalex' ),
											'specific_roles' => __( 'Specific Roles', 'wholesalex' ),
										),
										'rule_for'
									),
									'default' => '',
									'help'    => '',
								),
								'_product_filter'       => array(
									'type'    => 'select',
									'label'   => __( 'Product Filter', 'wholesalex' ),
									'options' => apply_filters(
										'wholesalex_dynamic_rules_product_filter_options',
										array(
											''             => __( 'Choose Filter...', 'wholesalex' ),
											'all_products' => __( 'All Products', 'wholesalex' ),
											'products_in_list' => __( 'Product in list', 'wholesalex' ),
											'products_not_in_list' => __( 'Product not in list', 'wholesalex' ),
											'cat_in_list'  => __( 'Categories in list', 'wholesalex' ),
											'cat_not_in_list' => __( 'Categories not in list', 'wholesalex' ),
											'attribute_in_list' => __( 'Attribute in list', 'wholesalex' ),
											'attribute_not_in_list' => __( 'Attribute not in list', 'wholesalex' ),
										),
										'product_filter'
									),
									'default' => '',
								),
								'specific_users'        => array(
									'label'       => __( 'Select Users', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_rule_for',
											'value' => 'specific_users',
										),
									),
									// 'options'     => $__users_options,
									'options'     => array(),
									'placeholder' => __( 'Choose Users...', 'wholesalex' ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_users',
									'ajax_search' => true,
								),
								'specific_roles'        => array(
									'label'       => __( 'Select Roles', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_rule_for',
											'value' => 'specific_roles',
										),
									),
									// 'options'     => $__roles_options,
									'options'     => array(),
									'placeholder' => __( 'Choose Roles...', 'wholesalex' ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_roles',
									'ajax_search' => false,
								),
								'products_in_list'      => array(
									'label'       => __( 'Select Multiple Products', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'products_in_list',
										),
									),
									// 'options'     => $products,
									'options'     => array(),
									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_product_in_list_placeholder', __( 'Choose Products to apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_products',
									'ajax_search' => true,
								),
								'products_not_in_list'  => array(
									'label'       => __( 'Select Multiple Products', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'products_not_in_list',
										),
									),
									// 'options'     => $products,
									'options'     => array(),

									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_product_not_in_list_placeholder', __( 'Choose Products that wont apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_products',
									'ajax_search' => true,
								),
								'cat_in_list'           => array(
									'label'       => __( 'Select Multiple Categories', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'cat_in_list',
										),
									),
									// 'options'     => $categories_options,
									'options'     => array(),

									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_cat_in_list_placeholder', __( 'Choose Categories to apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_categories',
									'ajax_search' => true,
								),
								'cat_not_in_list'       => array(
									'label'       => __( 'Select Multiple Categories', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'cat_not_in_list',
										),
									),
									// 'options'     => $categories_options,
									'options'     => array(),

									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_cat_not_in_list_placeholder', __( 'Choose Categories that wont apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_categories',
									'ajax_search' => true,
								),
								'attribute_in_list'     => array(
									'label'       => __( 'Select Multiple Attributes', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'attribute_in_list',
										),
									),
									// 'options'     => $variation_products,
									'options'     => array(),

									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_attribute_in_list_placeholder', __( 'Choose Product Variations to apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_variation_products',
									'ajax_search' => true,
								),
								'attribute_not_in_list' => array(
									'label'       => __( 'Select Multiple Attributes', 'wholesalex' ),
									'type'        => 'multiselect',
									'depends_on'  => array(
										array(
											'key'   => '_product_filter',
											'value' => 'attribute_not_in_list',
										),
									),
									// 'options'     => $variation_products,
									'options'     => array(),

									'placeholder' => apply_filters( 'wholesalex_dynamic_rules_attribute_not_in_list_placeholder', __( 'Choose Product Variations that wont apply discounts', 'wholesalex' ) ),
									'default'     => array(),
									'is_ajax'     => true,
									'ajax_action' => 'get_variation_products',
									'ajax_search' => true,
								),
							),
						),
						'product_discount'             => array(
							'label'      => __( 'Manage Discount', 'wholesalex' ),
							'type'       => 'manage_discount',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'product_discount',
								),
							),
							'attr'       => array(
								'_discount_type'   => array(
									'type'    => 'select',
									'label'   => __( 'Discount Type', 'wholesalex' ),
									'options' => array(
										'percentage' => __( 'Percentage', 'wholesalex' ),
										'amount'     => __( 'Amount', 'wholesalex' ),
										'fixed'      => __( 'Fixed Price', 'wholesalex' ),
									),
									'default' => 'percentage',
								),
								'_discount_amount' => array(
									'type'        => 'number',
									'label'       => __( 'Amount', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_discount_name'   => array(
									'type'        => 'text',
									'label'       => __( 'Disc. name(optional)', 'wholesalex' ),
									'default'     => '',
									'placeholder' => __( 'Add disc. Name herer', 'wholesalex' ),
									'help'        => '',
								),
							),
						),
						'payment_discount'             => array(
							'label'      => __( 'Payment Discount', 'wholesalex' ),
							'type'       => 'payment_discount',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'payment_discount',
								),
							),
							'attr'       => array(
								'_payment_gateways' => array(
									'type'        => 'multiselect',
									'label'       => __( 'Payment Gateways', 'wholesalex' ),
									// 'options'     => $payment_gateways,
									'options'     => array(),

									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_payment_gateways',
									'ajax_search' => false,
								),
								'_discount_type'    => array(
									'type'    => 'select',
									'label'   => __( 'Discount Type', 'wholesalex' ),
									'options' => array(
										'percentage' => __( 'Percentage', 'wholesalex' ),
										'amount'     => __( 'Amount', 'wholesalex' ),
										'fixed'      => __( 'Fixed Price', 'wholesalex' ),
									),
									'default' => 'percentage',
								),
								'_discount_amount'  => array(
									'type'        => 'number',
									'label'       => __( 'Amount', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_discount_name'    => array(
									'type'        => 'text',
									'label'       => __( 'Disc. name(optional)', 'wholesalex' ),
									'default'     => '',
									'placeholder' => __( 'Add disc. Name herer', 'wholesalex' ),
									'help'        => '',
								),
							),
						),
						'payment_order_qty'            => array(
							'label'      => __( 'Payment Order Qty', 'wholesalex' ),
							'type'       => 'payment_qty_discount',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'payment_order_qty',
								),
							),
							'attr'       => array(
								'_payment_gateways' => array(
									'type'        => 'multiselect',
									'label'       => __( 'Payment Gateways', 'wholesalex' ),
									// 'options'     => $payment_gateways,
									'options'     => array(),

									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_payment_gateways',
									'ajax_search' => false,
								),
								'_order_quantity'   => array(
									'type'        => 'number',
									'label'       => __( 'Order Qty', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
							),
						),
						'tax_rule'                     => array(
							'label'      => __( 'Tax Rule', 'wholesalex' ),
							'type'       => 'tax_rule',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'tax_rule',
								),
							),
							'attr'       => array(
								'_tax_exempted' => array(
									'type'    => 'select',
									'label'   => __( 'Tax Exempted?', 'wholesalex' ),
									'options' => array(
										''    => __( 'Choose Tax Exempted Status...', 'wholesalex' ),
										'yes' => __( 'Yes', 'wholesalex' ),
										'no'  => __( 'No', 'wholesalex' ),
									),
									'default' => '',
									'help'    => '',
								),
								'_tax_class'    => array(
									'type'       => 'select',
									'depends_on' => array(
										array(
											'key'   => '_tax_exempted',
											'value' => 'no',
										),
									),
									'label'      => __( 'Tax Class Mapping', 'wholesalex' ),
									'options'    => self::get_tax_classes(),
									'default'    => '',
									'help'       => '',

								),
								'_exempted_country' => array(
									'type'        => 'multiselect',
									'label'       => __( 'Country(optional)', 'wholesalex' ),
									'depends_on' => array(
										array(
											'key'   => '_tax_exempted',
											'value' => 'yes',
										),
									),
									'options'     => array(),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_shipping_country',
									'ajax_search' => false,
								),
							),
						),
						'shipping_rule'                => array(
							'label'      => __( 'Shipping Rule', 'wholesalex' ),
							'type'       => 'shipping_rule',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'shipping_rule',
								),
							),
							'attr'       => array(
								'__shipping_zone'        => array(
									'type'        => 'select',
									'label'       => __( 'Shipping Zone', 'wholesalex' ),
									'options'     => self::get_shipping_zones(),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
									'is_ajax'     => true,
									'ajax_action' => 'get_shipping_zones',
									'ajax_search' => false,
								),
								'_shipping_zone_methods' => array(
									'type'                 => 'multiselect',
									'label'                => __( 'Shipping Zone Methods', 'wholesalex' ),
									'options_dependent_on' => '__shipping_zone',
									// 'options'              => $__shipping_methods,
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
						'cart_discount'                => array(
							'label'      => __( 'Cart Discount', 'wholesalex' ),
							'type'       => 'manage_discount',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'cart_discount',
								),
							),
							'attr'       => array(
								'_discount_type'   => array(
									'type'    => 'select',
									'label'   => __( 'Discount Type', 'wholesalex' ),
									'options' => array(
										'percentage' => __( 'Percentage', 'wholesalex' ),
										'amount'     => __( 'Amount', 'wholesalex' ),
										'fixed'      => __( 'Fixed Price', 'wholesalex' ),
									),
									'default' => 'percentage',
								),
								'_discount_amount' => array(
									'type'        => 'number',
									'label'       => __( 'Amount', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_discount_name'   => array(
									'type'        => 'text',
									'label'       => __( 'Disc. name(optional)', 'wholesalex' ),
									'default'     => '',
									'placeholder' => __( 'Add disc. Name herer', 'wholesalex' ),
									'help'        => '',
								),
							),
						),
						'buy_x_get_one'                => array(
							'label'      => __( 'BOGO Discounts (Buy X Get One Free)', 'wholesalex' ),
							'type'       => 'buy_x_get_one',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'buy_x_get_one',
								),
							),
							'attr'       => array(
								'_minimum_purchase_count' => array(
									'type'        => 'number',
									'label'       => __( 'Product Quantity (X)', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_per_cart_once' => array(
									'type'        => 'switch',
									'label'		  => __('Restrict Free Quantity','wholesalex'),
									'desc'       => __( 'Restrict to one free product per order', 'wholesalex' ),
									'default'     => 'no',
									'placeholder' => '',
									'descTooltip'	  => __('Enabling this option will restrict shoppers from availing of more than one product by adding the required number of products more than one time to the cart.','wholesalex'),
								),
								'_buy_x_get_product_badge_enable' => array(
									'type'        => 'switch',
									'label'		  => __('Enable Discount Badge','wholesalex'),
									'desc'       => __( 'Show on both the shop and the single product page', 'wholesalex' ),
									'default'     => 'no',
									'placeholder' => '',
									'descTooltip'	  => __('Enable "Offer Badge" on Product Image for both the shop page and the single product page','wholesalex'),
								),
								'_product_badge_label' => array(
									'type'        => 'text',
									'label'       => __( 'Badge Label Text', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_product_badge_bg_color'        => array(
									'type'    => 'color',
									'label'   => __( 'Badge Background Color', 'wholesalex' ),
									'desc'    => '#5a40e8',
									'default' => '#5a40e8',
								),
								'_product_badge_text_color'        => array(
									'type'    => 'color',
									'label'   => __( 'Badge Text Color', 'wholesalex' ),
									'desc'    => '#000000',
									'default' => '#000000',
								),
								'_product_badge_position' => array(
									'type'    => 'select',
									'label'   => 'Badge Position (on image)',
									'options' => array(
										''     => __( 'Choose Badge Position...', 'wholesalex' ),
										'left' => __( 'Left', 'wholesalex' ),
										'right' => __( 'Right', 'wholesalex' ),
									),
									'default' => '',
									'placeholder' => '',
									'help'    => '',
								),
								'_product_badge_styles'          => array(
									'type'    => 'choosebox',
									'label'   => __( 'Badge Style', 'wholesalex' ),
									'options' => wholesalex()->Badge_image_display(),
									'default' => 'style_one',
								),
							),
						),
						'min_order_qty'                => array(
							'label'      => __( 'Minimum Order Quantity', 'wholesalex' ),
							'type'       => 'min_order_qty',
							'depends_on' => array(
								array(
									'key'   => '_rule_type',
									'value' => 'min_order_qty',
								),
							),
							'attr'       => array(
								'_min_order_qty' => array(
									'type'        => 'number',
									'label'       => __( 'Minimum Product Quantity', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_min_order_qty_disable' => array(
									'type'        => 'switch',
									'label'		  => __('Disable Quantity in Shop & Product Page','wholesalex'),
									'desc'       => __( 'Disable Minimum Product Limit', 'wholesalex' ),
									'default'     => 'no',
									'placeholder' => '',
									'descTooltip'	  => __('The minimum product order limit is disabled on the shop and product pages. Buyers can freely add products to the cart.','wholesalex'),
								),
							),
						),
						'conditions'                   => array(
							'label' => __( 'Conditions: (optional)', 'wholesalex' ),
							'type'  => 'tiers',
							'attr'  =>
							array(
								'_quantity_based_tier' => array(
									'type'   => 'tier',
									'_tiers' => array(
										'data' => array(
											'_conditions_for' => array(
												'type'    => 'select',
												'label'   => '',
												'options' => apply_filters(
													'wholesalex_dynamic_rules_condition_options',
													array(
														'' => __( 'Choose Conditions...', 'wholesalex' ),
														'cart_total_qty' => __( 'Cart - Total Qty', 'wholesalex' ),
														'cart_total_value' => __( 'Cart - Total Value', 'wholesalex' ),
														'cart_total_weight' => __( 'Cart - Total Weight', 'wholesalex' ),
														'pro_order_count' => __( 'User Order Count (Pro)', 'wholesalex' ),
														'pro_total_purchase' => __( 'Total Purchase Amount (Pro)', 'wholesalex' ),
													),
													'conditions'
												),
												'default' => '',
												'placeholder' => '',
												'help'    => '',
											),
											'_conditions_operator' => array(
												'type'    => 'select',
												'label'   => '',
												'options' => array(
													''     => __( 'Choose Operators...', 'wholesalex' ),
													'less' => __( 'Less than (<)', 'wholesalex' ),
													'less_equal' => __( 'Less than or equal (<=)', 'wholesalex' ),
													'greater_equal' => __( 'Greater than or equal (>=)', 'wholesalex' ),
													'greater' => __( 'Greater than (>)', 'wholesalex' ),
												),
												'default' => '',
												'placeholder' => '',
												'help'    => '',
											),
											'_conditions_value' => array(
												'type'    => 'number',
												'label'   => '',
												'default' => '',
												'placeholder' => __( 'Amount', 'wholesalex' ),
												'help'    => '',
											),
										),
									),
								),
							),
						),
						'limit'                        => array(
							'label'          => __( 'Date & Limit Rule', 'wholesalex' ),
							'type'           => 'date_n_usages_limit',
							'attr'           => array(
								// '_usage_limit' => array(
								// 	'type'           => 'number',
								// 	'label'          => __( 'Usages Limit', 'wholesalex' ),
								// 	'default'        => '',
								// 	'placeholder'    => '',
								// 	'help'           => '',
								// 	'not_visible_on' => array(
								// 		'_rule_type' => array( 'restrict_product_visibility' ),
								// 	),
								// ),
								'_start_date'  => array(
									'type'        => 'date',
									'label'       => __( 'Start Date', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
								'_end_date'    => array(
									'type'        => 'date',
									'label'       => __( 'End Data', 'wholesalex' ),
									'default'     => '',
									'placeholder' => '',
									'help'        => '',
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Generate Hash for Variation Product
	 *
	 * @param array $hash Hash for Variation Products.
	 * @since 1.0.0
	 * @access public
	 */
	public function variation_price_hash( $hash, $product ) {
		$user_id = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );
		$hash[]  = apply_filters( 'wholesalex_variation_prices_hash', strval( $user_id ) . strval( time() ), $product );
		return $hash;
	}
	/**
	 * Show Formated WholesaleX Prices
	 *
	 * @param WC_Price|string $price_html Product Sale Price HTML.
	 * @param WC_Product      $product Woocommerce Product Object.
	 * @since 1.0.0
	 * @access public
	 * @return WC_Price $price_html Formated Regular and Sale Price.
	 * @since 1.0.7 is_wholesalex_sale_price_applied Condition Added
	 * @since 1.0.8 Variable Product Pricing, Include, Exclude Tax Issue Fixed.
	 * @since 1.1.4 Duplicate Price (Empty Sale Price) Issue Fixed.
	 * @since 1.2.13 Login to see price Login Url Setting Added
	 */
	public function get_price_html( $price_html, $product ) {
		if ( is_admin() ) {
			return $price_html;
		}

		do_action( 'wholesalex_dynamic_rule_get_price_html' );

		$__product_id         = $product->get_id();
		$__wholesale_products = array();

		if ( ! ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) ) {
			return $price_html;
		}

		/** Login To View Price Section */
		$__view_price_product_list   = wholesalex()->get_setting( '_settings_login_to_view_price_product_list' );
		$__view_price_product_single = wholesalex()->get_setting( '_settings_login_to_view_price_product_page' );

		$__hide_login_to_see_price_message = false;

		$login_to_view_price_login_url = wholesalex()->get_setting( '_settings_login_to_view_price_login_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
		$redirect_url = esc_url(add_query_arg('redirect', isset($_SERVER['REQUEST_URI']) ? esc_url($_SERVER['REQUEST_URI']) : '', $login_to_view_price_login_url)); //phpcs:ignore
		if ( 'yes' === $__view_price_product_list && ! is_user_logged_in() && ! ( is_single() ) ) {
			$price_html = '<div><a href="' . $redirect_url . '">' . esc_html( wholesalex()->get_language_n_text( '_language_login_to_see_prices', __( 'Login to see prices', 'wholesalex' ) ) ) . '</a></div>';

			// hide add to cart button also.
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

			remove_all_actions( 'woocommerce_' . $product->get_type() . '_add_to_cart' );

			add_filter( 'woocommerce_is_purchasable', '__return_false' );

			if ( $__hide_login_to_see_price_message ) {
				return;
			}
			return $price_html;
		}

		if ( 'yes' === $__view_price_product_single && ! is_user_logged_in() && is_single() ) {
			$price_html = '<div><a href="' . $redirect_url . '">' . esc_html( wholesalex()->get_language_n_text( '_language_login_to_see_prices', __( 'Login to see prices', 'wholesalex' ) ) ) . '</a></div>';

			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
			// hide add to cart button also.
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

			remove_all_actions( 'woocommerce_' . $product->get_type() . '_add_to_cart' );

			add_filter( 'woocommerce_is_purchasable', '__return_false' );

			if ( $__hide_login_to_see_price_message ) {
				return;
			}
			return $price_html;
		}

		$is_wholesalex_sale_price_applied = true;
		if ( isset( WC()->session ) && ! is_admin() ) {
			$__wholesale_products = WC()->session->get( '__wholesalex_wholesale_products' );
			if ( $product->is_type( 'simple' ) || $product->is_type( 'variation' ) ) {
				if ( isset( $__wholesale_products[ $__product_id ] ) && $__wholesale_products[ $__product_id ] == $product->get_sale_price() ) {
					$is_wholesalex_sale_price_applied = false;
				}
			}
		}

		if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
			$variations               = $product->get_children();
			$regular_prices           = array();
			$regular_price            = '';
			$sale_prices              = array();
			$sale_price               = '';
			$has_sale_price           = false;
			$has_wholesale_sale_price = false;

			foreach ( $variations as $variation_id ) {
				$single_variation = wc_get_product( $variation_id );
				
				if ( ! ( is_object( $single_variation ) && is_a( $single_variation, 'WC_Product' ) ) ) {
					return $price_html;
				}
				if($single_variation->get_status( 'edit' ) != 'publish') {
					continue;
				}
				array_push( $regular_prices, wc_get_price_to_display( $single_variation, array( 'price' => $single_variation->get_regular_price() ) ) );

				if ( isset( $__wholesale_products[ $variation_id ] ) && $__wholesale_products[ $variation_id ] != $single_variation->get_sale_price() ) {
					$has_wholesale_sale_price = true;
				}

				if ( ! empty( $single_variation->get_sale_price() ) ) {
					$has_sale_price = true;
					array_push( $sale_prices, wc_get_price_to_display( $single_variation, array( 'price' => $single_variation->get_sale_price() ) ) );
				} else {
					array_push( $sale_prices, wc_get_price_to_display( $single_variation, array( 'price' => $single_variation->get_regular_price() ) ) );
				}
			}

			sort( $sale_prices );
			sort( $regular_prices );

			if ( empty( $sale_prices ) || empty( $regular_prices ) ) {
				return $price_html;
			}

			if ( $regular_prices[0] === $regular_prices[ count( $regular_prices ) - 1 ] ) {
				$regular_price = wc_price( $regular_prices[0] );
			} else {
				$regular_price = wc_format_price_range( $regular_prices[0], $regular_prices[ count( $regular_prices ) - 1 ] );
			}
			if ( $sale_prices[0] === $sale_prices[ count( $sale_prices ) - 1 ] ) {
				$sale_price = wc_price( $sale_prices[0] );
			} else {
				$sale_price = wc_format_price_range( $sale_prices[0], $sale_prices[ count( $sale_prices ) - 1 ] );
			}

			if ( $has_sale_price ) {
				if ( ! is_single() ) {
					$__product_list_page_price = wholesalex()->get_setting( '_settings_price_product_list_page', 'pricing_range' );
					$__product_list_page_price = isset( $__product_list_page_price ) ? $__product_list_page_price : '';
					// 2-1-24 -> WHO609 -> Variable Price alaways show price range
					switch ( $__product_list_page_price ) {
						case 'pricing_range':
						case 'minimum_pricing':
						case 'maximum_pricing':
							$price_html = $this->format_sale_price( $regular_price, $sale_price, $has_wholesale_sale_price ) . $product->get_price_suffix();
							break;
						// case 'minimum_pricing':
						// $price_html = $this->format_sale_price( $regular_price, $sale_prices[0], $has_wholesale_sale_price ) . $product->get_price_suffix();
						// break;
						// case 'maximum_pricing':
						// $price_html = $this->format_sale_price( $regular_price, $sale_prices[ count( $sale_prices ) - 1 ], $has_wholesale_sale_price ) . $product->get_price_suffix();
						// break;
						default:
							$price_html = $this->format_sale_price( $regular_price, $sale_price, $has_wholesale_sale_price ) . $product->get_price_suffix();
							break;
					}
				}
				if ( is_single() ) {
					$price_html = $this->format_sale_price( $regular_price, $sale_price, $has_wholesale_sale_price ) . $product->get_price_suffix();
				}
			} else {
				$price_html = $regular_price . $product->get_price_suffix();
			}

			/**
			 * Hide Prices on Variable Products.
			 *
			 * @since 1.0.2
			 */
			$__hide_regular_price = wholesalex()->get_setting( '_settings_hide_retail_price' ) ?? '';

			$__hide_wholesale_price = wholesalex()->get_setting( '_settings_hide_wholesalex_price' ) ?? '';

			if ( ! is_admin() ) {
				if ( 'yes' === (string) $__hide_wholesale_price && 'yes' === (string) $__hide_regular_price ) {
					return apply_filters( 'wholesalex_regular_sale_price_hidden_text', wholesalex()->get_language_n_text( '_language_price_is_hidden', 'Price is hidden!' ) );
				}
			}

			$price_html = apply_filters( 'wholesalex_variable_product_price_html', $price_html, $product );

			return $price_html;
		}

		$product_sale_price = $product->get_sale_price();

		if ( ( empty( $product_sale_price ) || (float) $product->get_sale_price() === (float) 0.0 ) && $product->get_regular_price() ) {
			$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), '', $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
			return $price_html;
		}

		if ( empty( $product_sale_price ) || (float) $product->get_sale_price() === (float) 0.0 ) {
			return $price_html;
		}

		if ( is_shop() || is_product_category() ) {
			$__product_list_page_price = wholesalex()->get_setting( '_settings_price_product_list_page', 'pricing_range' );
			$__product_list_page_price = isset( $__product_list_page_price ) ? $__product_list_page_price : '';

			$__min_sale_price = wc_get_price_to_display( $product, array( 'price' => $product->get_sale_price() ) );
			$__max_sale_price = wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );

			switch ( $__product_list_page_price ) {
				case 'pricing_range':
					if ( $__min_sale_price === $__max_sale_price ) {
						$__max_sale_price = wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
					}
					$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), wc_format_price_range( $__min_sale_price, $__max_sale_price ), $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
					break;
				case 'minimum_pricing':
					$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), $__min_sale_price, $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
					break;
				case 'maximum_pricing':
					$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), $__max_sale_price, $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
					break;
				default:
					$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), wc_get_price_to_display( $product, array( 'price' => $product->get_sale_price() ) ), $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
					break;
			}

			return $price_html;
		}

		if ( $is_wholesalex_sale_price_applied || $product->is_on_sale() ) {
			$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), wc_get_price_to_display( $product, array( 'price' => $product->get_sale_price() ) ), $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
		}

		if ( $this->is_wholesalex_base_price_applied && $product->get_date_on_sale_to() && ! $product->is_on_sale() ) {
			$price_html = $this->format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), '', $is_wholesalex_sale_price_applied ) . $product->get_price_suffix();
		}

		return $price_html;
	}
	/**
	 * Formate Wholesale Price.
	 *
	 * @param mixed $regular_price Regular Price.
	 * @param mixed $sale_price Sale Price.
	 * @param bool  $is_wholesalex_sale_price_applied Is Applied.
	 * @return mixed Formatted Price
	 * @since 1.0.0
	 * @since 1.0.7 Is WholesaleX Price Applied Condition Added
	 */
	public function format_sale_price( $regular_price, $sale_price, $is_wholesalex_sale_price_applied ) {
		global $product;
		$sale_text = '';
		if ( is_shop() || is_product_category() ) {
			$sale_text = wholesalex()->get_setting( '_settings_price_text_product_list_page', __( 'Wholesale Price:', 'wholesalex' ) );
		} else {
			$sale_text = wholesalex()->get_setting( '_settings_price_text', __( 'Wholesale Price:', 'wholesalex' ) );
		}

		$__hide_regular_price = wholesalex()->get_setting( '_settings_hide_retail_price' ) ?? '';

		$__hide_wholesale_price = wholesalex()->get_setting( '_settings_hide_wholesalex_price' ) ?? '';
		if ( $this->is_enable_subscriptions_product_woo( $product ) ) {
			$sale_text = '';
		}
		if ( ! $is_wholesalex_sale_price_applied ) {
			$sale_text = '';
		}
		if ( ! is_admin() ) {
			if ( 'yes' === (string) $__hide_wholesale_price && 'yes' === (string) $__hide_regular_price ) {
				return apply_filters( 'wholesalex_regular_sale_price_hidden_text', wholesalex()->get_language_n_text( '_language_price_is_hidden', 'Price is hidden!' ) );
			}
			if ( 'yes' === (string) $__hide_regular_price && ! empty( $sale_price ) ) {
				return $sale_text . wc_price( floatval( $sale_price ) );
			}
			if ( 'yes' === (string) $__hide_wholesale_price && ! empty( $regular_price ) && $is_wholesalex_sale_price_applied ) {
				return wc_price( floatval( $regular_price ) );
			}
		}

		if ( ! empty( $sale_price ) && ! empty( $regular_price ) ) {
			return '<del aria-hidden="true">' . ( is_numeric( $regular_price ) ? wc_price( $regular_price ) : $regular_price ) . '</del> <ins>' . $sale_text . ( ( is_numeric( $sale_price ) ? wc_price( $sale_price ) : $sale_price ) ) . '</ins>';
		}

		if ( ! empty( $sale_price ) ) {
			return '<ins>' . $sale_text . wc_price( floatval( $sale_price ) ) . '</ins>';
		}
		if ( ! empty( $regular_price ) ) {
			return wc_price( floatval( $regular_price ) );
		}
	}


	/**
	 * Wholesalex Product Price Tablew
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function wholesalex_product_price_table() {
		global $post;
		$product_id = $post->ID;
		$product    = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		if ( !($product_id && 'yes'==wholesalex()->get_single_product_setting( $product_id, '_settings_show_tierd_pricing_table' )) ) {
			return;
		}

		$tiers = isset( $this->active_tiers[ $product_id ] ) ? $this->active_tiers[ $product_id ] : array( 'tiers' => array() );

		$tiers['tiers'] = $this->filter_empty_tier( $tiers['tiers'] );		
		
		$table_data = false;
		if ( ! empty( $tiers['tiers'] ) ) {
			$table_data = $this->quantity_based_pricing_table( '', $product_id );
		}

		if((function_exists('wholesalex_pro') && version_compare(WHOLESALEX_PRO_VER,'1.3.1','<=')) && !$table_data) {
			
			$table_data = $this->quantity_based_pricing_table( '', $product_id );
		}

		do_action( 'wholesalex_tier_pricing_table', $product );

		$allowed_tags = array(
			'table' => array(),
			'thead' => array(),
			'tbody' => array(),
			'th'    => array(),
			'tr'    => array( 'id' => array() ),
			'td'    => array(),
			'div'   => array(
				'class' => array(),
				'id'    => array(),
				'style' => array(),
			),
			'span'  => array(
				'class' => array(),
				'id'    => array(),
			),
			'style' => array(),
			'pre'   => array(),
		);
		if($table_data) {
			echo wp_kses( $table_data, $allowed_tags );
		}
	}

	/**
	 * WholesaleX product variation price table
	 *
	 * @param Object $data WC Product Data.
	 * @param Object $product WC Product Object.
	 * @param Object $variation WC Variation Product Data.
	 * @since 1.0.0
	 * @since 1.0.5 In Stock Message Display Multiple Times Issue Fixed.
	 */
	public function wholesalex_product_variation_price_table( $data, $product, $variation ) {
		$product      = $product;
		$variation_id = $variation->get_id();
		$product_id = $product->get_id();

		if ( !($product_id && 'yes'==wholesalex()->get_single_product_setting( $product_id, '_settings_show_tierd_pricing_table' )) ) {
			return $data;
		}
		$tiers = isset( $this->active_tiers[ $variation_id ] ) ? $this->active_tiers[ $variation_id ] : array();

		if ( isset( $tiers['tiers'] ) ) {
			$tiers['tiers'] = $this->filter_empty_tier( $tiers['tiers'] );
		}


		if ( ! empty( $tiers ) ) {
			$tier_table                 = $this->quantity_based_pricing_table( '', $variation_id );
			$data['availability_html'] .= $tier_table;
		}

		if((function_exists('wholesalex_pro') && version_compare(WHOLESALEX_PRO_VER,'1.3.1','<=')) && empty($tiers)) {
			$tier_table                 = $this->quantity_based_pricing_table( '', $variation_id );
			$data['availability_html'] .= $tier_table;
		}
		return $data;
	}

	/**
	 * Quantity Based Pricing Table
	 *
	 * @param array $data Quantity Based Prices.
	 * @param mixed $id Product Id.
	 * @return mixed Table Data.
	 * @since 1.0.0
	 */
	public function quantity_based_pricing_table( $data, $id ) {
		$product = wc_get_product( $id );

		$product_price = $product->get_regular_price();

		ob_start();
		$this->wholesalex_price_table_generator( $product_price, $product );
		$data .= ob_get_clean();

		return $data;
	}

	/**
	 * Remove Empty Tiers
	 *
	 * @param array $tiers Tiers.
	 * @since 1.0.0
	 */
	private function filter_empty_tier( $tiers ) {
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
	 * Price Table Layout CSS
	 *
	 * @return void
	 */
	public function price_table_layout_css() {
		$__primary_color                  = wholesalex()->get_setting( '_settings_primary_color', '#5a40e8' );
		$__primary_hover_color            = wholesalex()->get_setting( 'settings_primary_hover_color', '#24A88F' );
		$__text_color                     = wholesalex()->get_setting( '_settings_text_color', '#272727' );
		$__border_color                   = wholesalex()->get_setting( '_settings_border_color', '#E5E5E5' );
		$__active_tier_color              = wholesalex()->get_setting( '_settings_active_tier_color', '#E5E5E5' );
		$__tire_layout_before_add_to_cart = 'yes' === wholesalex()->get_setting( '_settings_tier_position' );
		?>
		<style>
			#_active_row {
				color: white;
				background-color: <?php echo esc_attr( $__active_tier_color ); ?>;
			}

			.wholesalex-price-table {
				color: <?php echo esc_attr( $__text_color ); ?>;
				padding-top: <?php echo esc_attr( ! $__tire_layout_before_add_to_cart ? '25px' : '0px' ); ?>;
				padding-bottom: <?php echo esc_attr( $__tire_layout_before_add_to_cart ? '25px' : '0px' ); ?>;
			}

			.wholesalex-price-table tbody,
			thead {
				text-align: center;
			}

			.wholesalex-price-table table tr {
				background-color: #fdfdfd;
			}

			.wholesalex-price-table table th {
				background-color: #f8f8f8;
			}

			.wholesalex-price-table table th,
			.wholesalex-price-table table td {
				padding: 15px;
			}

			.wholesalex-price-table table,
			.wholesalex-price-table tr,
			.wholesalex-price-table td {
				border-collapse: collapse;
			}

			/**
			 * Tire Layout Two, Five
			 */


			.wholesalex-price-table.layout-two .layout_two_title,
			.wholesalex-price-table.layout-five .layout_five_title,
			.wholesalex-price-table.layout-six .layout_six_title,
			.wholesalex-price-table.layout-seven .layout_seven_title {
				font-size: 20px;
				color: <?php echo esc_attr( $__text_color ); ?>;
				font-weight: 500;
				line-height: 1.4;
			}

			.wholesalex-price-table.layout-two .layout-two-tiers,
			.wholesalex-price-table.layout-five .layout-five-tiers,
			.wholesalex-price-table.layout-seven .layout-seven-tiers {
				display: flex;
				padding: 20px;
				gap: 20px;
				flex-wrap: wrap;
				background-color: #f8f8f8;
			}

			.wholesalex-price-table.layout-two .prices_heading,
			.wholesalex-price-table.layout-five .prices_heading,
			.wholesalex-price-table.layout-six .prices_heading,
			.wholesalex-price-table.layout-seven .prices_heading {
				font-size: 16px;
				font-weight: bold;
				line-height: 1.75;
				color: <?php echo esc_attr( $__text_color ); ?>;
			}

			.wholesalex-price-table.layout-two .quantities_heading,
			.wholesalex-price-table.layout-five .quantities_heading,
			.wholesalex-price-table.layout-six .quantities_heading,
			.wholesalex-price-table.layout-seven .quantities_heading {
				font-weight: bold;
				font-size: 14px;
				line-height: 2;
				color: <?php echo esc_attr( $__text_color ); ?>;
			}

			.wholesalex-price-table.layout-two .price,
			.wholesalex-price-table.layout-five .price,
			.wholesalex-price-table.layout-six .price,
			.wholesalex-price-table.layout-seven .price {
				font-size: 16px;
				font-weight: 500;
				color: #f4a019;
				line-height: 1.75;
			}

			.wholesalex-price-table.layout-two,
			.wholesalex-price-table.layout-five,
			.wholesalex-price-table.layout-six,
			.wholesalex-price-table.layout-seven {
				display: flex;
				flex-direction: column;
				gap: 15px;
				padding-bottom: <?php echo esc_attr( $__tire_layout_before_add_to_cart ? '25px' : '0px' ); ?>;
			}

			.wholesalex-price-table.layout-two .sale_amount,
			.wholesalex-price-table.layout-five .sale_amount,
			.wholesalex-price-table.layout-six .sale_amount,
			.wholesalex-price-table.layout-seven .sale_amount {
				background-color: black;
				color: white;
				font-size: 12px;
				font-weight: 500;
				line-height: 2.33;
				padding: 2px 5px;
				border-radius: 2px;
			}

			.layout-two-tiers .tier:not(:last-child),
			.layout-five-tiers .tier:not(:last-child) {
				padding-right: 15px;
				border-right: solid 1px <?php echo esc_attr( $__border_color ); ?>;
			}

			.wholesalex-price-table.layout-two .product_quantity,
			.wholesalex-price-table.layout-five .product_quantity,
			.wholesalex-price-table.layout-six .product_quantity,
			.wholesalex-price-table.layout-seven .product_quantity,
			.layout-three-tiers .product_quantity,
			.layout-eight-tiers .product_quantity {
				font-size: 14px;
				line-height: 28px;
				color: <?php echo esc_attr( $__text_color ); ?>;
				display: flex;
				gap: 5px;
			}

			.wholesalex-price-table.layout-two .quantities,
			.wholesalex-price-table.layout-five .quantities,
			.wholesalex-price-table.layout-six .quantities,
			.wholesalex-price-table.layout-seven .quantities,
			.layout-three-tiers .quantities,
			.layout-eight-tiers .quantities {
				color: <?php echo esc_attr( $__text_color ); ?>;
				font-weight: bold;
			}

			/** Layout Three */

			.wholesalex-price-table.layout-three {
				margin-bottom: <?php echo esc_attr( $__tire_layout_before_add_to_cart ? '25px' : '0px' ); ?>;
			}

			.layout-three-tiers .tier .price,
			.layout-eight-tiers .tier .price {
				color: #f4a019;
				font-size: 24px;
				font-weight: 500;
				line-height: 1.17;
			}

			.layout-three-tiers .product_quantity::before,
			.layout-eight-tiers .product_quantity::before {
				content: "/";
				color: <?php echo esc_attr( $__text_color ); ?>;
				padding-right: 5px;
			}

			.layout-three-tiers .tier,
			.layout-eight-tiers .tier {
				display: flex;
				gap: 15px;
			}

			.wholesalex-price-table.layout-three,
			.wholesalex-price-table.layout-eight {
				border-top: 1px solid <?php echo esc_attr( $__border_color ); ?>;
				border-bottom: 1px solid <?php echo esc_attr( $__border_color ); ?>;
				padding-top: 20px;
				padding-bottom: 20px;
			}

			/** Tire Layout Four */
			.wholesalex-price-table.layout_four table tr {
				background-color: #f9f9f9;
			}

			.wholesalex-price-table.layout_four table tr:nth-child(even) {
				background-color: #f0f0f0;
			}

			.wholesalex-price-table.layout_four table th {
				background-color: #e2e2e2;
			}


			/** Layout Six */

			.wholesalex-price-table.layout-six .heading,
			.wholesalex-price-table.layout-six .tier {
				background-color: white;
			}

			/* .wholesalex-price-table.layout-six .layout-six-tiers {
				padding: 0px;
				gap: 0px;
			} */

			.wholesalex-price-table.layout-six .layout-six-tiers {
				display: flex;
				flex-wrap: wrap;
			}

			.wholesalex-price-table.layout-six .heading,
			.wholesalex-price-table.layout-six .tier {
				border: 1px solid <?php echo esc_html( $__border_color ); ?>;
			}

			/* .heading,.tier:not(:last-child){
				border-right: none;
			} */
			.wholesalex-price-table.layout-six .quantities_heading,
			.wholesalex-price-table.layout-six .product_quantity {
				border-top: 1px solid <?php echo esc_html( $__border_color ); ?>;
			}

			.wholesalex-price-table.layout-six .prices_heading,
			.wholesalex-price-table.layout-six .quantities_heading,
			.wholesalex-price-table.layout-six .quantities,
			.wholesalex-price-table.layout-six .quantity_text,
			.wholesalex-price-table.layout-six .price {
				padding: 15px;
			}

			.wholesalex-price-table.layout-six .quantities {
				padding-right: 0px;
			}

			.wholesalex-price-table.layout-six .quantity_text {
				padding-left: 5px;
			}

			/** Layout Seven */
			.wholesalex-price-table.layout-seven .layout-seven-tiers {
				background-color: white;
				border-top: 1px solid <?php echo esc_html( $__border_color ); ?>;
				border-bottom: 1px solid <?php echo esc_html( $__border_color ); ?>;
				width: fit-content;
			}

			/* .wholesalex-price-table.layout-seven .product_quantity,
			.wholesalex-price-table.layout-eight .product_quantity{
				gap:5px;
			} */

			/** Layout Eight */

			.wholesalex-price-table.layout-eight .layout-eight-tiers {
				display: flex;
				column-gap: 20px;
				row-gap: 15px;
				flex-wrap: wrap;
			}

			.wholesalex-price-table.layout-eight {
				display: flex;
				flex-direction: column;
				gap: 15px;
				margin-bottom: <?php echo esc_attr( $__tire_layout_before_add_to_cart ? '25px' : '0px' ); ?>;
			}
		</style>
		<?php
	}
	/**
	 * WholesaleX Price Table generator
	 *
	 * @param float  $regular_price Product Regular Price.
	 * @param object $product Product Object.
	 * @return void
	 * @since 1.0.0
	 */
	public function wholesalex_price_table_generator( $regular_price, $product ) {		
		/**
		   * Tier Layout Style Deprecated Warning Fixed
		   *
		   * @since 1.0.4
		   */
		$layout_css = $this->price_table_layout_css(); // Call the method once and store the result

		if ( ! is_null( $layout_css ) && is_string( $layout_css ) && trim( $layout_css ) !== '' ) {
			wp_add_inline_style( 'wholesalex', $layout_css );
		}

		$quantity_prices = array();

		$tier_data = isset( $this->active_tiers[ $product->get_id() ] ) ? $this->active_tiers[ $product->get_id() ] : array( 'tiers' => array() );

		$tier_data['tiers'] = $this->filter_empty_tier( $tier_data['tiers'] );

		$tiers       = isset( $tier_data['tiers'] ) ? $tier_data['tiers'] : array();
		$active_tier = isset( $tier_data['id'] ) ? $tier_data['id'] : '';

		$__show_source = apply_filters( 'wholesalex_pricing_tier_source', WP_DEBUG );
		array_multisort( array_column( $tiers, '_min_quantity' ), SORT_ASC, $tiers );
		$classes = apply_filters( 'wholesalex_tier_layout_custom_classes', '' );

		$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

		$tier_layout = 'layout_one';

		if ( wholesalex()->get_single_product_setting( $product_id, '_settings_tier_layout_single_product' ) ) {
			$tier_layout = wholesalex()->get_single_product_setting( $product_id, '_settings_tier_layout_single_product' );
		} else {
			$tier_layout = wholesalex()->get_setting( '_settings_tier_layout', 'layout_one' );
		}

		$tier_layout = apply_filters( 'wholesalex_tier_layout', $tier_layout, $product_id );

		$quantity_prices = $tiers;

		if((function_exists('wholesalex_pro') && version_compare(WHOLESALEX_PRO_VER,'1.3.1','<=')) ) {
			$__priorities = wholesalex()->get_quantity_based_discount_priorities();

			$__user_id         = apply_filters( 'wholesalex_set_current_user', get_current_user_id() );

			$quantity_prices = get_transient( 'wholesalex_pricing_tiers_' . $this->discount_src . '_' . $__user_id );

			
			if ( empty( $quantity_prices ) ) {
				foreach ( $__priorities as $priority ) {
					$__temp_quantity_prices = get_transient( 'wholesalex_pricing_tiers_' . $priority . '_' . $__user_id );
					if ( $__temp_quantity_prices && ! empty( $__temp_quantity_prices ) ) {
						$quantity_prices = $__temp_quantity_prices;
						break;
					}
				}
			}
			if ( empty( $quantity_prices ) ) {
				return;
			}
			if ( isset( $quantity_prices['_min_quantity'] ) ) {
				$__sort_colum = array_column( $quantity_prices, '_min_quantity' );
				array_multisort( $__sort_colum, SORT_ASC, $quantity_prices );
			}
			
		}

		if ( empty( $quantity_prices ) ) {
			return;
		}
		if ( isset( $quantity_prices['_min_quantity'] ) ) {
			$__sort_colum = array_column( $quantity_prices, '_min_quantity' );
			array_multisort( $__sort_colum, SORT_ASC, $quantity_prices );
		}
		$__show_source = apply_filters( 'wholesalex_pricing_tier_source', WP_DEBUG );

		$__wc_currency = get_option( 'woocommerce_currency' );

		$__show_heading = apply_filters( 'wholesalex_tier_layout_six_show_heading', false );

		

		switch ( $tier_layout ) {
			case 'layout_one':
				?>
				<div class="wholesalex-price-table">
					<table>
						<thead>
							<tr>
								<th>
									<?php esc_html_e( 'Product Quantity', 'wholesalex' ); ?>
								</th>
								<th>
									<?php esc_html_e( 'Discount', 'wholesalex' ); ?>
								</th>
								<th>
									<?php esc_html_e( 'Price per Unit', 'wholesalex' ); ?>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$__tier_size = count( $quantity_prices );

							for ( $i = 0; $i < $__tier_size; $i++ ) {
								$__current_tier = $quantity_prices[ $i ];
								$__next_tier    = '';
								if ( ( $__tier_size ) - 1 !== $i ) {
									$__next_tier = $quantity_prices[ $i + 1 ];
								}

								$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
								$__discount   = $regular_price - $__sale_price;

								$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

								?>
								<tr id=<?php echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' ); ?>>
									<td>
										<?php
										if ( isset( $__current_tier['_min_quantity'] ) ) {
											if ( ! empty( $__next_tier ) && ( $__next_tier['_min_quantity'] - 1 ) > $__current_tier['_min_quantity'] ) {

												if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
													echo esc_html( $__current_tier['_min_quantity'] );
												} else {
													echo ( esc_html( $__current_tier['_min_quantity'] ) ) . '-' . esc_html( $__next_tier['_min_quantity'] - 1 );
												}
											} else {
												echo ( esc_html( $__current_tier['_min_quantity'] ) ) . '+';
											}
										}

										?>
									</td>
									<td>
										<?php echo wp_kses_post( wc_price( $__discount ) ); ?>
									</td>
									<td>
										<?php echo wp_kses_post( wc_price( $__sale_price ) ); ?>
									</td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>
				<?php

				break;
			case 'layout_two':
				$__show_discount_amount = apply_filters( 'wholesalex_tier_layout_two_show_discount_amount', true );
				?>
				<div class="wholesalex-price-table layout-two">
					<div class="layout_two_title"><?php esc_html_e( 'Tier Purchase', 'wholesalex' ); ?></div>
					<div class="layout-two-tiers">
						<?php

						$__tier_size    = count( $quantity_prices );
						$__show_heading = false;

						if ( $__show_heading ) {
							$__prices_heading     = apply_filters( 'wholesalex_tier_layout_two_prices_heading', __( 'Price per Unit', 'wholesalex' ) );
							$__quantities_heading = apply_filters( 'wholesalex_tier_layout_two_quantities_heading', __( 'Quantity (Pieces)', 'wholesalex' ) );
							?>
							<div class="heading">
								<div class="prices_heading">
									<?php echo esc_html( $__prices_heading ); ?>
								</div>
								<div class="quantities_heading">
									<?php echo esc_html( $__quantities_heading ); ?>
								</div>
							</div>
							<?php
						}

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, floatval($regular_price) );
							$__discount   = floatval($regular_price) - floatval($__sale_price);

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( isset($__current_tier['_id']) && $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php
									if ( $__show_discount_amount ) {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' <span class="sale_amount">' . - ( (float) $__discount / (float) $regular_price ) * 100.00 . '% </span>' );
									} else {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' ' );
									}
									?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php

				break;
			case 'layout_three':
				?>
				<div class="wholesalex-price-table layout-three">
					<div class="layout-three-tiers">
						<?php

						$__tier_size = count( $quantity_prices );

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
							$__discount   = $regular_price - $__sale_price;

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php echo wp_kses_post( wc_price( $__sale_price ) ); ?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php
				break;
			case 'layout_four':
				?>
				<div class="wholesalex-price-table layout_four">
					<table>
						<thead>
							<tr>
								<th>
									<?php esc_html_e( 'Product Quantity', 'wholesalex' ); ?>
								</th>
								<th>
									<?php esc_html_e( 'Discount', 'wholesalex' ); ?>
								</th>
								<th>
									<?php esc_html_e( 'Price per Unit', 'wholesalex' ); ?>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$__tier_size = count( $quantity_prices );

							for ( $i = 0; $i < $__tier_size; $i++ ) {
								$__current_tier = $quantity_prices[ $i ];
								$__next_tier    = '';
								if ( ( $__tier_size ) - 1 !== $i ) {
									$__next_tier = $quantity_prices[ $i + 1 ];
								}

								$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
								$__discount   = $regular_price - $__sale_price;

								$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

								?>
								<tr id=<?php echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' ); ?>>
									<td>
										<?php
										if ( isset( $__current_tier['_min_quantity'] ) ) {
											if ( ! empty( $__next_tier ) && ( $__next_tier['_min_quantity'] - 1 ) > $__current_tier['_min_quantity'] ) {

												if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
													echo esc_html( $__current_tier['_min_quantity'] );
												} else {
													echo ( esc_html( $__current_tier['_min_quantity'] ) ) . '-' . esc_html( $__next_tier['_min_quantity'] - 1 );
												}
											} else {
												echo ( esc_html( $__current_tier['_min_quantity'] ) ) . '+';
											}
										}

										?>
									</td>
									<td>
										<?php echo wp_kses_post( wc_price( $__discount ) ); ?>
									</td>
									<td>
										<?php echo wp_kses_post( wc_price( $__sale_price ) ); ?>
									</td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>
				<?php

				break;
			case 'layout_five':
				$__show_discount_amount = apply_filters( 'wholesalex_tier_layout_five_show_discount_amount', false );
				?>
				<div class="wholesalex-price-table layout-five">
					<div class="layout_five_title"><?php esc_html_e( 'Tier Purchase', 'wholesalex' ); ?></div>
					<div class="layout-five-tiers">
						<?php

						$__tier_size    = count( $quantity_prices );
						$__show_heading = false;

						if ( $__show_heading ) {
							$__prices_heading     = apply_filters( 'wholesalex_tier_layout_five_prices_heading', __( 'Price per Unit', 'wholesalex' ) );
							$__quantities_heading = apply_filters( 'wholesalex_tier_layout_five_quantities_heading', __( 'Quantity (Pieces)', 'wholesalex' ) );
							?>
							<div class="heading">
								<div class="prices_heading">
									<?php echo esc_html( $__prices_heading ); ?>
								</div>
								<div class="quantities_heading">
									<?php echo esc_html( $__quantities_heading ); ?>
								</div>
							</div>
							<?php
						}

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
							$__discount   = $regular_price - $__sale_price;

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php
									if ( $__show_discount_amount ) {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' <span class="sale_amount">' . - ( (float) $__discount / (float) $regular_price ) * 100.00 . '% </span>' );
									} else {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' ' );
									}
									?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php

				break;
			case 'layout_six':
				$__show_discount_amount = apply_filters( 'wholesalex_tier_layout_six_show_discount_amount', false );
				?>
				<div class="wholesalex-price-table layout-six">
					<div class="layout_six_title"><?php esc_html_e( 'Tier Purchase', 'wholesalex' ); ?></div>
					<div class="layout-six-tiers">
						<?php

						$__tier_size = count( $quantity_prices );

						if ( $__show_heading ) {
							$__prices_heading     = apply_filters( 'wholesalex_tier_layout_six_prices_heading', __( 'Price per Unit', 'wholesalex' ) );
							$__quantities_heading = apply_filters( 'wholesalex_tier_layout_six_quantities_heading', __( 'Quantity (Pieces)', 'wholesalex' ) );
							?>
							<div class="heading">
								<div class="prices_heading">
									<?php echo esc_html( $__prices_heading ); ?>
								</div>
								<div class="quantities_heading">
									<?php echo esc_html( $__quantities_heading ); ?>
								</div>
							</div>
							<?php
						}

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
							$__discount   = $regular_price - $__sale_price;

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php
									if ( $__show_discount_amount ) {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' <span class="sale_amount">' . - ( (float) $__discount / (float) $regular_price ) * 100.00 . '% </span>' );
									} else {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' ' );
									}
									?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php

				break;
			case 'layout_seven':
				$__show_discount_amount = apply_filters( 'wholesalex_tier_layout_seven_show_discount_amount', false );
				?>
				<div class="wholesalex-price-table layout-seven">
					<div class="layout_seven_title"><?php esc_html_e( 'Tier Purchase', 'wholesalex' ); ?></div>
					<div class="layout-seven-tiers">
						<?php

						$__tier_size = count( $quantity_prices );

						$__show_heading = false;

						if ( $__show_heading ) {
							$__prices_heading     = apply_filters( 'wholesalex_tier_layout_seven_prices_heading', __( 'Price per Unit', 'wholesalex' ) );
							$__quantities_heading = apply_filters( 'wholesalex_tier_layout_seven_quantities_heading', __( 'Quantity (Pieces)', 'wholesalex' ) );
							?>
							<div class="heading">
								<div class="prices_heading">
									<?php echo esc_html( $__prices_heading ); ?>
								</div>
								<div class="quantities_heading">
									<?php echo esc_html( $__quantities_heading ); ?>
								</div>
							</div>
							<?php
						}

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
							$__discount   = $regular_price - $__sale_price;

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php
									if ( $__show_discount_amount ) {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' <span class="sale_amount">' . - ( (float) $__discount / (float) $regular_price ) * 100.00 . '% </span>' );
									} else {
										echo wp_kses_post( $__wc_currency . ' ' . wc_price( $__sale_price ) . ' ' );
									}
									?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php

				break;
			case 'layout_eight':
				?>
				<div class="wholesalex-price-table layout-eight">
					<div class="layout-eight-tiers">
						<?php

						$__tier_size = count( $quantity_prices );

						for ( $i = 0; $i < $__tier_size; $i++ ) {
							$__current_tier = $quantity_prices[ $i ];
							$__next_tier    = '';
							if ( ( $__tier_size ) - 1 !== $i ) {
								$__next_tier = $quantity_prices[ $i + 1 ];
							}

							$__sale_price = wholesalex()->calculate_sale_price( $__current_tier, $regular_price );
							$__discount   = $regular_price - $__sale_price;

							$__sale_price = wc_get_price_to_display( $product, array( 'price' => $__sale_price ) );

							$__quantities = '';

							if ( isset( $__current_tier['_min_quantity'] ) ) {
								if ( ! empty( $__next_tier ) ) {

									if ( $__current_tier['_min_quantity'] === $__next_tier['_min_quantity'] ) {
										$__quantities = $__current_tier['_min_quantity'];
									} else {
										$__quantities = $__current_tier['_min_quantity'] . '-' . ( (int) $__next_tier['_min_quantity'] - 1 );
									}
								} else {
									$__quantities = $__current_tier['_min_quantity'] . '+';
								}
							}

							?>
							<div class="tier" id=
							<?php
													echo esc_attr( ( $__current_tier['_id'] === $active_tier ) ? '_active_row' : '' );
							?>
													>
								<div class="price">
									<?php echo wp_kses_post( wc_price( $__sale_price ) ); ?>
								</div>
								<div class="product_quantity">
									<span class="quantities">
										<?php echo esc_html( $__quantities ); ?>
									</span>
									<span class="quantity_text">
										<?php esc_html_e( 'Pieces', 'wholesalex' ); ?>
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<?php
				break;

			default:
				// code...
				break;
		}

		?>

		<?php

	}

	/**
	 * Check the dynamic rule is valid or not
	 *
	 * @param array|empty $__limits Limits.
	 * @return boolean
	 * @since 1.0.0
	 * @since 1.0.8 Make Static To WholesaleX Pro
	 */
	public static function has_limit( $__limits ) {
		if ( is_array( $__limits ) && ! empty( $__limits ) ) {
			// Check if the discount has any usages limit, if have , then check is any remaining.
			$__is_remaining = true;
			if ( isset( $__limits['_usage_limit'] ) && ! empty( $__limits['_usage_limit'] ) && isset( $__limits['usages_count'] ) ) {
				$__limit        = (int) $__limits['_usage_limit'];
				$__usages_count = (int) $__limits['usages_count'];
				$__remaining    = $__limit - $__usages_count;
				if ( $__remaining < 1 ) {
					$__is_remaining = false;
				}
			}

			// Check if rule has any start and end date limit or not. If have then check is the rule is valid for today.
			$__today        = gmdate( 'Y-m-d' );
			$__has_duration = true;
			if ( isset( $__limits['_start_date'] ) && ! empty( $__limits['_start_date'] ) ) {

				$__start_date = gmdate( 'Y-m-d', strtotime( $__limits['_start_date'] . ' +1 day' ) );

				if ( $__today < $__start_date ) {
					$__has_duration = false;
				}
			}
			if ( isset( $__limits['_end_date'] ) && ! empty( $__limits['_end_date'] ) ) {

				$__end_date = gmdate( 'Y-m-d', strtotime( $__limits['_end_date'] . ' +1 day' ) );

				if ( $__today > $__end_date ) {
					$__has_duration = false;
				}
			}

			return $__has_duration && $__is_remaining;
		}
		return true;
	}

	/**
	 * Check Single Conditions
	 *
	 * @param float  $conditions_value Conditions Value.
	 * @param string $operator Compare Operator.
	 * @param float  $value Value.
	 * @return boolean
	 * @since 1.0.0
	 * @since 1.0.1 Fixed Less Than Not Working Issue With Backward Compability.
	 */
	public static function is_condition_passed( $conditions_value, $operator, $value ) {
		if ( '>' === $operator || 'greater' === $operator ) {
			if ( $value > $conditions_value ) {
				return true;
			}
		} elseif ( '<' === $operator || 'less' === $operator ) {
			if ( $value < $conditions_value ) {
				return true;
			}
		} elseif ( '>=' === $operator || 'greater_equal' === $operator ) {
			if ( $value >= $conditions_value ) {
				return true;
			}
		} elseif ( '<=' === $operator || 'less_equal' === $operator ) {
			if ( $value <= $conditions_value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check the dynamic rule has any conditions and if has then check the rules meets the conditions or not
	 *
	 * @param array $conditions Conditions Array.
	 * @since 1.0.0
	 * @since 1.0.4 Order Count Coditions Added.
	 * @since 1.0.4 Total Purchase Amount Added.
	 * @since 1.0.8 Make Static to Use on WholesaleX Pro
	 */
	public static function is_conditions_fullfiled( $conditions ) {
		if ( is_admin() || null === WC()->cart ) {
			return true;
		}

		$__status            = true;
		$__total_cart_weight = wholesalex()->get_cart_total_weight();

		$__total_cart_total = wholesalex()->get_cart_total();

		foreach ( $conditions as $condition ) {
			$__conditions_value = isset( $condition['_conditions_value'] ) ? (float) $condition['_conditions_value'] : 0;
			$__conditions_for   = isset( $condition['_conditions_for'] ) ? $condition['_conditions_for'] : '';

			if ( ! isset( $condition['_conditions_operator'] ) ) {
				continue;
			}

			if ( 'order_count' === $__conditions_for && ! wholesalex()->is_pro_active() ) {
				continue;
			}
			if ( 'total_purchase' === $__conditions_for && ! wholesalex()->is_pro_active() ) {
				continue;
			}
			switch ( $__conditions_for ) {
				case 'cart_total_value':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], $__total_cart_total );
					break;
				case 'cart_total_qty':
					$__is_unique_cart_count  = apply_filters( 'wholesalex_is_unique_cart_count', false );
					self::$total_cart_counts = 0;
					if ( $__is_unique_cart_count ) {
						self::$total_cart_counts = count( WC()->cart->get_cart() );
					} else {
						if ( ! self::$total_cart_counts ) {
							self::$total_cart_counts = wholesalex()->cart_count();
						}
					}
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], self::$total_cart_counts );
					break;
				case 'cart_total_weight':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], $__total_cart_weight );
					break;
				case 'order_count':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], self::$cu_order_counts );
					break;
				case 'total_purchase':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], self::$cu_total_spent );
					break;
			}
			if ( ! $__status ) {
				return false;
			}
		}
		return $__status;
	}
	/**
	 * Check the dynamic rule has user order count and total purchase amount if have
	 *
	 * @param array $conditions Conditions Array.
	 * @since
	 */
	public static function is_user_order_count_purchase_amount_condition_passed( $conditions ) {
		if ( is_admin() ) {
			return true;
		}
		$__status = true;

		foreach ( $conditions as $condition ) {
			$__conditions_value = isset( $condition['_conditions_value'] ) ? (float) $condition['_conditions_value'] : 0;
			$__conditions_for   = isset( $condition['_conditions_for'] ) ? $condition['_conditions_for'] : '';

			if ( ! isset( $condition['_conditions_operator'] ) ) {
				continue;
			}

			if ( 'order_count' === $__conditions_for && ! wholesalex()->is_pro_active() ) {
				continue;
			}
			if ( 'total_purchase' === $__conditions_for && ! wholesalex()->is_pro_active() ) {
				continue;
			}
			switch ( $__conditions_for ) {
				case 'order_count':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], self::$cu_order_counts );
					break;
				case 'total_purchase':
					$__status = self::is_condition_passed( $__conditions_value, $condition['_conditions_operator'], self::$cu_total_spent );
					break;
			}
			if ( ! $__status ) {
				return false;
			}
		}
		return $__status;
	}

	/**
	 * Set WholesaleX Discounted Products in WC Session
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function set_discounted_product( $product_id ) {
		if ( is_admin() || null === WC()->session ) {
			return;
		}
		$__discounted_product = null !== WC()->session ? WC()->session->get( '__wholesalex_discounted_products' ) : '';

		if ( ! ( isset( $__discounted_product ) && is_array( $__discounted_product ) ) ) {
			$__discounted_product = array();
		}
		$__discounted_product[ $product_id ] = true;

		WC()->session->set( '__wholesalex_discounted_products', $__discounted_product );
	}

	/**
	 * Add Custom Meta on Wholesale Order
	 *
	 * @param object $order Order Data.
	 * @since 1.0.0
	 * @since 1.0.1 Dynamic Rule Usages Count and Discounted Product Empty Bug Fixed.
	 */
	public function add_custom_meta_on_wholesale_order($order) {
		if (is_admin() || null === WC()->session) {
			return;
		}
	
		$__discounted_product = WC()->session->get('__wholesalex_discounted_products');
		$__dynamic_rule_id = WC()->session->get('__wholesalex_used_dynamic_rule');
	
		// Handle dynamic rules
		if (!empty($__dynamic_rule_id)) {
			$order->update_meta_data('__wholesalex_dynamic_rule_ids', $__dynamic_rule_id);
	
			if (is_array($__dynamic_rule_id)) {
				foreach ($__dynamic_rule_id as $key => $value) {
					if (1 == $value) {
						$__rule = wholesalex()->get_dynamic_rules($key);
						$__rule['limit']['usages_count'] = isset($__rule['limit']['usages_count']) ? (int) $__rule['limit']['usages_count'] + 1 : 1;
						wholesalex()->set_dynamic_rules($key, $__rule);
					}
				}
			}
		}
	
		// Handle discounted products
		$__ordered_discounted_product = array();
		$items = $order->get_items();
	
		foreach ($items as $item) {
			$product_id = $item->get_product_id();
			$product_variation_id = $item->get_variation_id();
	
			if (isset($__discounted_product[$product_id])) {
				$__ordered_discounted_product[] = $product_id;
			}
	
			if (isset($__discounted_product[$product_variation_id])) {
				$__ordered_discounted_product[] = $product_variation_id;
			}
		}
	
		if (!empty($__ordered_discounted_product)) {
			$order->update_meta_data('__wholesalex_discounted_products', array_unique($__ordered_discounted_product));
		}
	
		// Determine order type
		$__user_role = wholesalex()->get_current_user_role();
	
		$order_type = in_array($__user_role, array('', 'wholesalex_guest', 'wholesalex_b2c_users')) ? 'b2c' : 'b2b';
		$order->update_meta_data('__wholesalex_order_type', $order_type);
	
		// Clear session data
		WC()->session->set('__wholesalex_discounted_products', array());
	}


	/**
	 * Reset Wholesale Discount Product on Cart update
	 */
	public function update_discounted_product( $cart_updated ) {
		if ( is_admin() || null === WC()->session ) {
			return $cart_updated;
		}
		WC()->session->set( '__wholesalex_discounted_products', array() );
		WC()->session->set( '__wholesalex_used_dynamic_rule', array() );
		return $cart_updated;
	}

	/**
	 * Price After Currency Changed
	 * For Any Currency Switcher Compability Issue, Add Necessary Codes In This Function.
	 *
	 * @param float $price Price.
	 * @return float $price.
	 * @since 1.0.3
	 */
	public function price_after_currency_changed( $price ) {
		$price = floatval( $price );
		// ProductX Currency Switcher Compatibility.
		if ( defined( 'WOPB_VER' ) && defined( 'WOPB_PRO_VER' ) && class_exists( 'WOPB_PRO\Currency_Switcher_Action' ) ) {
			$current_currency_code = wopb_function()->get_setting( 'wopb_current_currency' );
			$default_currency      = wopb_function()->get_setting( 'wopb_default_currency' );
			$current_currency      = Currency_Switcher_Action::get_currency( $current_currency_code );
			if ( ! $current_currency ) {
				$current_currency = $default_currency;
			}

			if ( $current_currency_code !== $default_currency ) {
				$wopb_current_currency_rate = floatval( ( isset( $current_currency['wopb_currency_rate'] ) && $current_currency['wopb_currency_rate'] > 0 && ! ( $current_currency['wopb_currency_rate'] == '' ) ) ? $current_currency['wopb_currency_rate'] : 1 );
				$wopb_current_exchange_fee  = floatval( ( isset( $current_currency['wopb_currency_exchange_fee'] ) && $current_currency['wopb_currency_exchange_fee'] >= 0 && ! ( $current_currency['wopb_currency_exchange_fee'] == '' ) ) ? $current_currency['wopb_currency_exchange_fee'] : 0 );
				$total_rate                 = ( $wopb_current_currency_rate + $wopb_current_exchange_fee );
				return $price / $total_rate;
			}
		}
		/**
		 * CURCY Currency Switcher Compatibility.
		 *
		 * @since 1.2.6
		 */
		if ( defined( 'WOOMULTI_CURRENCY_F_VERSION' ) && function_exists( 'wmc_revert_price' ) ) {
			$curcy = WOOMULTI_CURRENCY_F_Data::get_ins();
			if ( $curcy->get_enable() ) {
				$price = wmc_revert_price( $price );
			}
		}

		if ( defined( 'YAY_CURRENCY_VERSION' ) && function_exists( 'Yay_Currency\\plugin_init' ) ) {
			if ( method_exists( '\Yay_Currency\Helpers\Helper', 'default_currency_code' ) && method_exists( '\Yay_Currency\Helpers\YayCurrencyHelper', 'detect_current_currency' ) ) {

				$applied_currency    = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
				$is_default_currency = \Yay_Currency\Helpers\Helper::default_currency_code() == $applied_currency['currency'];

				if ( ! $is_default_currency ) {
					$total_rate = \Yay_Currency\Helpers\YayCurrencyHelper::get_rate_fee( $applied_currency );
					return ( floatval( $price / $total_rate ) );
				}
			}
		}

		return $price;
	}
	/**
	 * Woo All Products For Subscriptions Compatibility
	 *
	 * @param [array] $product
	 * @return boolean
	 * @since 1.4.2
	 */
	public function is_enable_subscriptions_product_woo( $product ) {
		if ( in_array( 'woocommerce-all-products-for-subscriptions/woocommerce-all-products-for-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			if (  class_exists( 'WCS_ATT_Product_Schemes' ) ) {
				if (  \WCS_ATT_Product_Schemes::get_subscription_schemes( $product ) ) {
					return true;
				}else{
					return false;
				}
			}
		}
	}

	/**
	 * Set Initial Sell Price to session
	 *
	 * @param string              $__function_name Function Name.
	 * @param float|double|string $id Product ID.
	 * @param float|double|string $sale_price Product Initital Sale Price.
	 * @return void
	 * @since 1.0.4
	 */
	public function set_initial_sale_price_to_session( $__function_name, $id, $sale_price ) {
		if ( isset( WC()->session ) && ! is_admin() ) {
			if ( $__function_name === $this->first_sale_price_generator ) {
				$__wholesale_products        = WC()->session->get( '__wholesalex_wholesale_products' );
				$__wholesale_products[ $id ] = $this->price_after_currency_changed( $sale_price );
				WC()->session->set( '__wholesalex_wholesale_products', $__wholesale_products );
			}
		}
	}





	public function product_price( $price, $product ) {
		if ( ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) ) {
			if ( empty( $product->get_sale_price() ) ) {
				$price = wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
			} else {
				$price = wc_get_price_to_display( $product, array( 'price' => $product->get_sale_price() ) );
			}
		}
		return $price;
	}

	public function set_price_on_ppom( $price, $cart_item ) {
		$__product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$__product    = wc_get_product( $__product_id );
		return $__product->get_sale_price();
	}


	/**
	 * Modify ProductX Query to exclude hidden product ids
	 *
	 * @param array $query_args Query args.
	 * @return array
	 */
	public function modify_wopb_query_args( $query_args ) {
		 $query_args['post__not_in'] = isset( $query_args['post__not_in'] ) ? array_merge( $query_args['post__not_in'], (array) wholesalex()->hidden_product_ids() ) : (array) wholesalex()->hidden_product_ids();
		return $query_args;
	}

	/**
	 * Set Discounted Price
	 *
	 * @param [type] $data
	 * @return void
	 */
	public function set_price_on_extra_product_addon_plugin( $data ) {

		if ( '' !== $this->price ) {
			$data['Product']['Price'] = $this->price;
		}
		return $data;
	}

	/**
	 * Get All Valid Dynamic Rules and Process Cart Discount
	 *
	 * @param string $user_id user id
	 * @return void
	 */
	public function get_valid_dynamic_rules( $user_id = '' ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$user_id = ( isset( $user_id ) && ! empty( $user_id ) ) ? $user_id : get_current_user_id();

		$user_id = apply_filters( 'wholesalex_set_current_user', $user_id );

		if ( ! isset( $GLOBALS['wholesalex_rule_data'] ) ) {
			$GLOBALS['wholesalex_rule_data'] = array();
		}

		/**
		 * Set Current User Order Counts and Total Spent
		 */
		if ( is_user_logged_in() ) {
			self::$cu_order_counts = wc_get_customer_order_count( $user_id );
			self::$cu_total_spent  = wc_get_customer_total_spent( $user_id );
		}

		self::$total_cart_counts = false;

		do_action( 'wholesalex_before_dynamic_rules_loaded', $user_id );

		$plugins_status = wholesalex()->get_setting( '_settings_status', 'b2b' );

		$is_eligible = true;
		if ( 'b2b' === $plugins_status && 'active' != wholesalex()->get_user_status( $user_id ) ) {
			$is_eligible = false;
		}

		$__discounts = wholesalex()->get_dynamic_rules();

		$__role = wholesalex()->get_user_role( $user_id );

		$__discounts_for_me = array();

		foreach ( $__discounts as $discount ) {

			if ( isset( $discount['_rule_status'] ) && $discount['_rule_status'] && ! empty( $discount['_rule_status'] ) && isset( $discount['_product_filter'] ) ) {

				// Check User order count and Purchase amount conditions to narrow down userbase
				if ( isset( $discount['conditions']['tiers'] ) && ! empty( $discount['conditions']['tiers'] ) && ! self::is_user_order_count_purchase_amount_condition_passed( $discount['conditions']['tiers'] ) ) {
					continue;
				}

				$__role_for       = $discount['_rule_for'];
				$__for_me         = false;
				$who_priority     = 10;
				$product_priority = 10;
				switch ( $__role_for ) {
					case 'specific_roles':
						foreach ( $discount['specific_roles'] as $role ) {
							// Check The Discounts Rules In Valid or not.
							if ( (string) $role['value'] === (string) $__role || 'role_' . $__role === $role['value'] ) {
								array_push( $__discounts_for_me, $discount );
								$__for_me     = true;
								$who_priority = 20;
								break;
							}
						}
						break;
					case 'specific_users':
						foreach ( $discount['specific_users'] as $user ) {
							if ( ( is_numeric( $user['value'] ) && (int) $user['value'] === (int) $user_id ) || ( 'user_' . $user_id === $user['value'] ) ) {
								array_push( $__discounts_for_me, $discount );
								$__for_me     = true;
								$who_priority = 10;
								break;
							}
						}

						break;
					case 'all_roles':
                        if(empty($__role)) {
                            break;
                        }
						$__exclude_roles = apply_filters( 'wholesalex_dynamic_rules_exclude_roles', array( 'wholesalex_guest', 'wholesalex_b2c_users' ) );
						if ( is_array( $__exclude_roles ) && ! empty( $__exclude_roles ) ) {
							if (!in_array($__role, $__exclude_roles)) { //phpcs:ignore
								array_push( $__discounts_for_me, $discount );
								$__for_me     = true;
								$who_priority = 30;
								break;
							}
						} else {
							array_push( $__discounts_for_me, $discount );
							$__for_me     = true;
							$who_priority = 30;
						}
						break;
					case 'all_users':
						$__exclude_users = apply_filters( 'wholesalex_dynamic_rules_exclude_users', array() );
						if ( is_array( $__exclude_users ) && ! empty( $__exclude_users ) ) {
							if (!in_array($user_id, $__exclude_users)) { //phpcs:ignore
								array_push( $__discounts_for_me, $discount );
								$__for_me     = true;
								$who_priority = 40;
								break;
							}
						} else {
							if ( 0 !== $user_id ) {
								array_push( $__discounts_for_me, $discount );
								$__for_me     = true;
								$who_priority = 40;
							}
						}
                        break;

					case 'all':
						array_push( $__discounts_for_me, $discount );
						$__for_me     = true;
						$who_priority = 50;

						break;
				}
				if ( ! $__for_me ) {
					continue;
				}
    
				$include_products   = array();
				$include_cats       = array();
				$include_variations = array();
				$exclude_products   = array();
				$exclude_cats       = array();
				$exclude_variations = array();
				$is_all_products    = false;

				extract( $this->get_filtered_rules( $discount ) );
			} else {
				continue;
			}

			if ( isset( $discount['limit'] ) && ! empty( $discount['limit'] ) ) {
				if ( ! ( method_exists( self::class, 'has_limit' ) && self::has_limit( $discount['limit'] ) ) ) {
					continue;
				}
			}

			if ( ! isset( $discount['_rule_for'] ) ) {
				continue;
			}
			if ( ! isset( $discount['_rule_type'] ) || ( 'restrict_product_visibility' !== $discount['_rule_type'] && ! isset( $discount[ $discount['_rule_type'] ] ) ) ) {
				continue;
			}

			$rule_id = $discount['id'];

			if ( ! ( isset( $this->valid_dynamic_rules[ $discount['_rule_type'] ] ) && is_array( $this->valid_dynamic_rules[ $discount['_rule_type'] ] ) ) ) {
				$this->valid_dynamic_rules[ $discount['_rule_type'] ] = array();
			}
			
			$frule = array();
			switch ( $discount['_rule_type'] ) {
				case 'quantity_based':
					$frule = array( 'tiers' => $this->filter_empty_tier( $discount[ $discount['_rule_type'] ]['tiers'] ) );
					break;
				case 'min_order_qty':
					if ( ! empty( $discount['min_order_qty']['_min_order_qty'] ) ) {
						$frule = $discount['min_order_qty'];
					}
					break;
				case 'max_order_qty':
					if ( ! empty( $discount['max_order_qty']['_max_order_qty'] ) ) {
						$frule = $discount['max_order_qty'];
					}
					break;
				case 'hidden_price':
					if ( ! empty( $discount['hidden_price'] ) ) {
						$frule = $discount['hidden_price'];
					}
					break;
				default:
					$frule = $discount[ $discount['_rule_type'] ];
					break;
			}

			if ( ! empty( $frule ) || in_array($discount['_rule_type'],array('hidden_price','non_purchasable','restrict_product_visibility', 'restrict_checkout') ) ) {

				$this->valid_dynamic_rules[ $discount['_rule_type'] ][] = array(
					'id'                  => $discount['id'],
					'filter'              => array(
						'include_products'   => $include_products,
						'include_cats'       => $include_cats,
						'include_variations' => $include_variations,
						'exclude_products'   => $exclude_products,
						'exclude_cats'       => $exclude_cats,
						'exclude_variations' => $exclude_variations,
						'is_all_products'    => $is_all_products,
					),
					'rule'                => $frule,
					'conditions'          => array( 'tiers' => isset( $discount['conditions']['tiers'] ) ? wholesalex()->filter_empty_conditions( $discount['conditions']['tiers'] ) : array() ),
					'who_priority'        => $who_priority,
					'applied_on_priority' => $product_priority,
					'end_date'            => isset( $discount['limit']['_end_date'] ) ? $discount['limit']['_end_date'] : false,

				);
			}
		}

		// Sort According to priority
		foreach ( $this->valid_dynamic_rules as $key => $value ) {
			usort( $this->valid_dynamic_rules[ $key ], array( $this, 'compare_by_priority' ) );
		}

		do_action( 'wholesalex_valid_dynamic_rules', $this->valid_dynamic_rules );

		// User Profile

		$profile_settings = get_user_meta( $user_id, '__wholesalex_profile_settings', true );

		$user_profile_tiers = get_user_meta( $user_id, '__wholesalex_profile_discounts', true );

		$user_profile_data = array();

		$user_profile_filter_map = array();

		if ( isset( $user_profile_tiers['_profile_discounts']['tiers'] ) && ! empty( $user_profile_tiers['_profile_discounts']['tiers'] ) ) {

			$user_profile_tiers = $user_profile_tiers['_profile_discounts']['tiers'];

			foreach ( $user_profile_tiers as $upt ) {

				extract( $this->get_filtered_rules( $upt ) );

				$user_profile_filter = array(
					'include_products'   => $include_products,
					'include_cats'       => $include_cats,
					'include_variations' => $include_variations,
					'exclude_products'   => $exclude_products,
					'exclude_cats'       => $exclude_cats,
					'exclude_variations' => $exclude_variations,
					'is_all_products'    => $is_all_products,
				);

				$idx = md5( serialize( $user_profile_filter ) );

				if ( ! isset( $user_profile_data[ $idx ] ) ) {
					$user_profile_data[ $idx ] = array();
				}

				$user_profile_data[ $idx ][] = array(
					'_id'                 => $upt['_id'],
					'_discount_type'      => $upt['_discount_type'],
					'_discount_amount'    => $upt['_discount_amount'],
					'_min_quantity'       => $upt['_min_quantity'],
					'applied_on_priority' => $product_priority,
				);

				$user_profile_filter_map[ $idx ] = $user_profile_filter;
			}
		}

		$all_rules_data = array();

		// Tax Related Stuffs
		$is_tax_exempt = '';

		if ( isset( $profile_settings['_wholesalex_profile_override_tax_exemption'] ) ) {
			$is_tax_exempt = $profile_settings['_wholesalex_profile_override_tax_exemption'];
		}

		if ( isset( $this->valid_dynamic_rules['tax_rule'] ) && ! empty( $this->valid_dynamic_rules['tax_rule'] ) ) {
			usort( $this->valid_dynamic_rules['tax_rule'], array( $this, 'compare_by_priority' ) );
		}

		$this->valid_dynamic_rules['tax_rule'] = isset( $this->valid_dynamic_rules['tax_rule'] ) ? $this->valid_dynamic_rules['tax_rule'] : array();

		if ( ! empty( $this->valid_dynamic_rules['tax_rule'] ) || $is_tax_exempt ) {
			$this->handle_tax(
				array(
					'profile_exemption' => $is_tax_exempt,
					'rules'             => $this->valid_dynamic_rules['tax_rule'],
				)
			);
		}

		// Shipping Related Rules and Discounts
		if ( isset( $this->valid_dynamic_rules['shipping_rule'] ) && ! empty( $this->valid_dynamic_rules['shipping_rule'] ) ) {
			usort( $this->valid_dynamic_rules['shipping_rule'], array( $this, 'compare_by_priority' ) );
		}
		$profile_shipping_data = array();
		if ( isset( $profile_settings['_wholesalex_profile_override_shipping_method'] ) && 'yes' === $profile_settings['_wholesalex_profile_override_shipping_method'] ) {
			$profile_shipping_data['method_type'] = isset( $profile_settings['_wholesalex_profile_shipping_method_type'] ) ? $profile_settings['_wholesalex_profile_shipping_method_type'] : '';
			$profile_shipping_data['zone']        = isset( $profile_settings['_wholesalex_profile_shipping_zone'] ) ? $profile_settings['_wholesalex_profile_shipping_zone'] : '';
			$profile_shipping_data['methods']     = isset( $profile_settings['_wholesalex_profile_shipping_zone_methods'] ) ? $profile_settings['_wholesalex_profile_shipping_zone_methods'] : array();
		}

		$__role_content     = wholesalex()->get_roles( 'by_id', $__role );
		$__shipping_methods = array();
		if ( isset( $__role_content['_shipping_methods'] ) && ! empty( $__role_content['_shipping_methods'] ) ) {
			$__shipping_methods = $__role_content['_shipping_methods'];
		}

		/**
		 *  Allowed Shipping Methods for current role
		 *  this array contains allowed shipping method ids
		 *  NB: Each shipping method has different ids, so zone id does not matter.
		 *  Ex: Zone A: Flat Rate -> ID 1
		 *  Zone B: Flat Rate -> ID 2
		 */

		$__shipping_methods = array_filter( $__shipping_methods );

		$this->valid_dynamic_rules['shipping_rule'] = isset( $this->valid_dynamic_rules['shipping_rule'] ) ? $this->valid_dynamic_rules['shipping_rule'] : array();

		if ( ! empty( $profile_shipping_data ) || ! empty( $__shipping_methods ) || ! empty( $this->valid_dynamic_rules['shipping_rule'] ) ) {
			$this->handle_shipping(
				array(
					'profile' => $profile_shipping_data,
					'roles'   => $__shipping_methods,
					'rules'   => $this->valid_dynamic_rules['shipping_rule'],
				)
			);
		}

		// Payment Related Rules and Discounts
		$profile_gateway_data = array();

		if ( isset( $profile_settings['_wholesalex_profile_override_payment_gateway'] ) && 'yes' === $profile_settings['_wholesalex_profile_override_payment_gateway'] ) {
			if ( isset( $profile_settings['_wholesalex_profile_payment_gateways'] ) && ! empty( $profile_settings['_wholesalex_profile_payment_gateways'] ) ) {
				$profile_gateway_data = $profile_settings['_wholesalex_profile_payment_gateways'];
			}
		}

		$payment_related_rules = array();
		if ( isset( $this->valid_dynamic_rules['payment_order_qty'] ) && ! empty( $this->valid_dynamic_rules['payment_order_qty'] ) ) {
			usort( $this->valid_dynamic_rules['payment_order_qty'], array( $this, 'compare_by_priority' ) );
			$payment_related_rules = $this->valid_dynamic_rules['payment_order_qty'];
		}

		$role_payment_methods = array();
		if ( isset( $__role_content['_payment_methods'] ) && ! empty( $__role_content['_payment_methods'] ) ) {
			$role_payment_methods = $__role_content['_payment_methods'];
			$role_payment_methods = array_filter( $role_payment_methods );
		}

		$this->handle_payment_gateways(
			array(
				'profile' => $profile_gateway_data,
				'rules'   => $payment_related_rules,
				'roles'   => $role_payment_methods,
			)
		);

		// Cart Related Rules and Discounts

		// Buy X Get One
		// Payment Discount
		// Cart Discount
		// Extra Charge
		$cart_related_data = array();
		if ( isset( $this->valid_dynamic_rules['buy_x_get_one'] ) && ! empty( $this->valid_dynamic_rules['buy_x_get_one'] ) ) {
			usort( $this->valid_dynamic_rules['buy_x_get_one'], array( $this, 'compare_by_priority' ) );
			$cart_related_data['buy_x_get_one'] = $this->valid_dynamic_rules['buy_x_get_one'];
		}
		if ( isset( $this->valid_dynamic_rules['cart_discount'] ) && ! empty( $this->valid_dynamic_rules['cart_discount'] ) ) {
			usort( $this->valid_dynamic_rules['cart_discount'], array( $this, 'compare_by_priority' ) );
			$cart_related_data['cart_discount'] = $this->valid_dynamic_rules['cart_discount'];
		}
		if ( isset( $this->valid_dynamic_rules['payment_discount'] ) && ! empty( $this->valid_dynamic_rules['payment_discount'] ) ) {
			usort( $this->valid_dynamic_rules['payment_discount'], array( $this, 'compare_by_priority' ) );
			$cart_related_data['payment_discount'] = $this->valid_dynamic_rules['payment_discount'];
		}

		$cart_related_data = apply_filters( 'wholesalex_dr_cart_related_data', $cart_related_data );
		if ( ! empty( $cart_related_data ) ) {
			$this->handle_cart( $cart_related_data );
		}

		// Min-Max Order Quantity
		$min_max_data = array(
			'min_order_qty' => array(),
			'max_order_qty' => array(),
		);
		if ( isset( $this->valid_dynamic_rules['min_order_qty'] ) && ! empty( $this->valid_dynamic_rules['min_order_qty'] ) ) {
			usort( $this->valid_dynamic_rules['min_order_qty'], array( $this, 'compare_by_priority' ) );
			$min_max_data['min_order_qty'] = $this->valid_dynamic_rules['min_order_qty'];
		}

		$min_max_data = apply_filters( 'wholesalex_dr_min_max_rules', $min_max_data, $this->valid_dynamic_rules );
		
		/**
		 * Min/Max Quantities for WooCommerce Plugin Compatibility
		 */
		if ( ! is_plugin_active( 'woocommerce-min-max-quantities/woocommerce-min-max-quantities.php' ) ) {
			$this->min_max_order_quantity( $min_max_data );
		}

		// Discounts
		$discounts_releated_data = array(
			'user_id'                 => $user_id,
			'role_id'                 => $__role,
			'plugin_status'           => $plugins_status,
			'eligible'                => $is_eligible,
			'product_discount'        => array(),
			'quantity_based'          => array(),
			'user_profile'            => array(),
			'user_profile_filter_map' => array(),
		);

		if ( isset( $this->valid_dynamic_rules['product_discount'] ) && ! empty( $this->valid_dynamic_rules['product_discount'] ) ) {
			usort( $this->valid_dynamic_rules['product_discount'], array( $this, 'compare_by_priority' ) );
			$discounts_releated_data['product_discount'] = $this->valid_dynamic_rules['product_discount'];
		}

		if ( ! empty( $user_profile_data ) ) {
			$discounts_releated_data['user_profile']            = $user_profile_data;
			$discounts_releated_data['user_profile_filter_map'] = $user_profile_filter_map;
		}

		$discounts_releated_data = apply_filters( 'wholesalex_dr_discounts', $discounts_releated_data );

		
        $this->handle_discounts( $discounts_releated_data );

		if ( ! empty( $this->valid_dynamic_rules['shipping_rule'] ) && ! is_null( WC()->cart ) ) {
			$shipping_packages = WC()->cart->get_shipping_packages();
			if ( ! empty( $shipping_packages ) && is_array( $shipping_packages ) ) {
				// Get the WC_Shipping_Zones instance object for the first package
				$first_package = reset( $shipping_packages );
				if ( is_array( $first_package ) && ! empty( $first_package ) ) {
					$shipping_zone = wc_get_shipping_zone( $first_package );
					if ( is_object( $shipping_zone ) ) {
						$this->current_shipping_zone = $shipping_zone->get_id();
					}
				}
			}
		}

		add_action(
			'woocommerce_before_add_to_cart_form',
			function() use ( $cart_related_data, $payment_related_rules, $profile_shipping_data, $min_max_data, $is_tax_exempt ) {
				global $product;
				if ( $product->is_type( 'simple' ) ) {
					$this->handle_single_product_page_promo( $product, $cart_related_data, $payment_related_rules, $profile_shipping_data, $min_max_data, $is_tax_exempt, true );
				}
			}
		);

		add_action(
			'woocommerce_available_variation',
			function ( $data, $product, $variation ) use ( $cart_related_data, $payment_related_rules, $profile_shipping_data, $min_max_data, $is_tax_exempt ) {

				$data['availability_html'] .= $this->handle_single_product_page_promo( $variation, $cart_related_data, $payment_related_rules, $profile_shipping_data, $min_max_data, $is_tax_exempt, false );
				return $data;
			},
			10,
			3
		);

		if ( 'yes' === wholesalex()->get_setting( '_settings_hide_retail_price' ) && 'yes' === wholesalex()->get_setting( '_settings_hide_wholesalex_price' ) ) {
			// If Both Price hidden, then make products not purchasable.
			$this->make_product_non_purchasable_and_remove_add_to_cart();
		}

		
	}

	/**
	 * Undocumented function
	 *
	 * @param [type]  $product
	 * @param [type]  $cart_related_data
	 * @param [type]  $payment_related_rules
	 * @param [type]  $profile_shipping_data
	 * @param [type]  $min_max_data
	 * @param [type]  $is_tax_exempt
	 * @param boolean $is_echo
	 * @return void
	 */
	public function handle_single_product_page_promo( $product, $cart_related_data, $payment_related_rules, $profile_shipping_data, $min_max_data, $is_tax_exempt, $is_echo = false ) {
		do_action( 'wholesalex_before_add_to_cart_form', $product );

		if ( 'yes' === wholesalex()->get_setting( 'show_promotions_on_sp', 'no' ) ) {

			$this->check_for_cart_releated_discounts( $product, $cart_related_data );

			$this->check_for_free_shipping( $product, $profile_shipping_data );
		}

		if ( ! empty( $min_max_data ) ) {
			// Minimum
			if ( 'yes' == wholesalex()->get_setting( 'show_order_qty_text_on_sp', 'no' ) && isset( $min_max_data['min_order_qty'] ) && ! empty( $min_max_data['min_order_qty'] ) ) {
				foreach ( $min_max_data['min_order_qty'] as $rule ) {
					if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
						continue;
					}
					$is_all_products = $rule['filter']['is_all_products'];

					if ( self::is_eligible_for_rule( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), $product->get_parent_id() ? $product->get_id() : 0, $rule['filter'] ) || $is_all_products ) {
						wholesalex()->set_rule_data(
							$rule['id'],
							$product->get_id(),
							'min_order_qty',
							array(
								'conditions'          => $rule['conditions'] ? $rule['conditions'] : array(),
								'minimum_qty'         => $rule['rule']['_min_order_qty'],
								'who_priority'        => $rule['who_priority'],
								'applied_on_priority' => $rule['applied_on_priority'],
								'end_date'            => $rule['end_date'],
							)
						);
					}
				}
			}
			// Maximum
			if ( 'yes' == wholesalex()->get_setting( 'show_order_qty_text_on_sp', 'no' ) && isset( $min_max_data['max_order_qty'] ) && ! empty( $min_max_data['max_order_qty'] ) ) {
				foreach ( $min_max_data['max_order_qty'] as $rule ) {
					if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
						continue;
					}
					$is_all_products = $rule['filter']['is_all_products'];

					if ( self::is_eligible_for_rule( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), $product->get_parent_id() ? $product->get_id() : 0, $rule['filter'] ) || $is_all_products ) {
						wholesalex()->set_rule_data(
							$rule['id'],
							$product->get_id(),
							'max_order_qty',
							array(
								'conditions'          => $rule['conditions'] ? $rule['conditions'] : array(),
								'maximum_qty'         => $rule['rule']['_max_order_qty'],
								'who_priority'        => $rule['who_priority'],
								'applied_on_priority' => $rule['applied_on_priority'],
								'end_date'            => $rule['end_date'],
							)
						);
					}
				}
			}
		}

		?>


		<?php
		$modal_content = '';
		if ( ! $is_echo ) {
			ob_start();
		}

		if ( ! empty( wholesalex()->get_rule_data( $product->get_id() ) ) ) {

			ob_start();
			
			if ( 'yes' === wholesalex()->get_setting( 'show_product_discounts_text', 'no' ) ) {

				$product_discounts = wholesalex()->get_rule_data( $product->get_id(), 'product_discount' );


				if ( ! empty( $product_discounts ) ) {

					usort( $product_discounts, array( $this, 'compare_by_priority_reverse' ) );
					?>
					<div class="wsx-sp-product-discounts">
						<?php
						if ( 'yes' == wholesalex()->get_setting( 'product_discount_rule_sp_show_rule_info', 'yes' ) ) {
							?>
								<div class="wsx-sp-rule-info">
									<div class="wsx-sp-rule-info_type"><?php echo esc_html( wholesalex()->get_setting( 'product_discount_rule_info_rule_type_text', __( 'Product Discount', 'wholesalex' ) ) ); ?></div>
								<?php
								if ( count( $product_discounts ) > 1 ) {
									?>
										<div class="wsx-sp-rule-info_desc"><?php echo esc_html( wholesalex()->get_setting( 'dynamic_rule_promotional_explainer_text_single_discount', __( 'You can avail one of the following offers by completing the requirements.', 'wholesalex' ) ) ); ?>  </div>
										<?php
								}
								?>
								</div>
								<?php
						}

						?>
						
						<div class="wsx-sp-discounts-cards">
							<?php
							foreach ( $product_discounts as $cd ) {

								if ( 'percentage' == $cd['type'] ) {
									$heading_text = $cd['value'] . __( ' % OFF', 'wholesalex' );
								} elseif ( 'amount' == $cd['type'] ) {
									$heading_text = wc_price( $cd['value'] ) . __( ' OFF', 'wholesalex' );
								} elseif ( 'fixed' == $cd['type'] ) {
									$heading_text = '<del>' . wc_price( $product->get_price() ) . '</del>. to <ins>' . wc_price( $cd['value'] ) . '</ins>';
								}

								$conditions = 'yes' === wholesalex()->get_setting( 'show_discount_conditions_on_sp', 'no' ) && isset( $cd['conditions']['tiers'] ) ? $this->generate_rule_conditions_markup( $cd['conditions']['tiers'] ) : '';
								$validity   = '';
								if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {
									$validity = $cd['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $cd['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
								}
								?>
								
									<div class="wsx-single-product-discount-card wsx-cart-discount-card">
										<div class="wsx-discount-card-heading"> <?php echo wp_kses_post( $heading_text ); ?></div>
										<div class="wsx-discount-card-desc"><?php echo wp_kses_post( $conditions . $validity ); ?></div>
									</div>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
			}
				if ( 'yes' === wholesalex()->get_setting( 'show_cart_discount_text', 'no' ) ) {
					$cart_discounts = wholesalex()->get_rule_data( $product->get_id(), 'cart_discount' );

					if ( ! empty( $cart_discounts ) ) {
						usort( $cart_discounts, array( $this, 'compare_by_priority_reverse' ) );

						?>
						<div class="wsx-sp-cart-discounts">
							<?php
							if ( 'yes' == wholesalex()->get_setting( 'cart_discount_rule_sp_show_rule_info', 'yes' ) ) {
								?>
									<div class="wsx-sp-rule-info">
										<div class="wsx-sp-rule-info_type"><?php echo esc_html( wholesalex()->get_setting( 'cart_discount_rule_info_rule_type_text', __( 'Cart Discount', 'wholesalex' ) ) ); ?></div>
									<?php
									if ( count( $cart_discounts ) > 1 ) {
										?>
											<div class="wsx-sp-rule-info_desc"><?php echo esc_html( wholesalex()->get_setting( 'dynamic_rule_promotional_explainer_text_multiple_discount', __( 'You can avail following offers by completing the requirements.', 'wholesalex' ) ) ); ?>  </div>
											<?php
									}
									?>
									</div>
									<?php
							}

							?>
							
							<div class="wsx-sp-discounts-cards">
								<?php
								foreach ( $cart_discounts as $cd ) {
									if ( 'percentage' == $cd['type'] ) {
										$heading_text = $cd['value'] . __( ' % OFF', 'wholesalex' );
									} elseif ( 'amount' == $cd['type'] ) {
										$heading_text = wc_price( $cd['value'] ) . __( ' OFF', 'wholesalex' );
									} elseif ( 'fixed' == $cd['type'] ) {
										$heading_text = '<del>' . wc_price( $product->get_price() ) . '</del>. to <ins>' . wc_price( $cd['value'] ) . '</ins>';
									}

									$desc       = '<span class="wsx-discount-desc">' . wholesalex()->get_setting( 'cart_discount_promo_sp_desc_text', __( 'After adding to the cart', 'wholesalex' ) ) . ' </span>';
									$conditions = 'yes' === wholesalex()->get_setting( 'show_discount_conditions_on_sp', 'no' ) && isset( $cd['conditions']['tiers'] ) ? $this->generate_rule_conditions_markup( $cd['conditions']['tiers'] ) : '';
									$validity   = '';
									if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {

										$validity = $cd['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $cd['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
									}
									?>
										<div class="wsx-single-product-discount-card wsx-cart-discount-card">
											<div class="wsx-discount-card-heading"> <?php echo wp_kses_post( $heading_text ); ?></div>
											<div class="wsx-discount-card-desc"><?php echo wp_kses_post( $desc . $conditions . $validity ); ?></div>
										</div>
									<?php
								}
								?>
							</div>
						</div>
						<?php
					}
				}
				if ( 'yes' === wholesalex()->get_setting( 'show_payment_method_discount_promo_text_sp', 'no' ) ) {

					$payment_discount = wholesalex()->get_rule_data( $product->get_id(), 'payment_discount' );

					if ( ! empty( $payment_discount ) ) {
						usort( $payment_discount, array( $this, 'compare_by_priority_reverse' ) );
						?>
						<div class="wsx-sp-payment-discounts">
							<?php
							if ( 'yes' == wholesalex()->get_setting( 'payment_discount_rule_sp_show_rule_info', 'yes' ) ) {
								?>
									<div class="wsx-sp-rule-info">
										<div class="wsx-sp-rule-info_type"><?php echo esc_html(wholesalex()->get_setting('payment_method_discount_label_text',__('Payment Method Discount','wholesalex'))); ?></div>
									<?php
									if ( count( $payment_discount ) > 1 ) {
										?>
											<div class="wsx-sp-rule-info_desc"><?php echo esc_html( wholesalex()->get_setting( 'dynamic_rule_promotional_explainer_text_single_discount', __( 'You can avail one of the following offers by completing the requirements.', 'wholesalex' ) ) ); ?>  </div>
											<?php
									}
									?>
									</div>
									<?php
							}

							?>
							
							<div class="wsx-sp-discounts-cards">
								<?php
								foreach ( $payment_discount as $pd ) {
									if ( 'percentage' == $pd['type'] ) {
										$heading_text = $pd['value'] . __( ' % OFF', 'wholesalex' );
									} elseif ( 'amount' == $pd['type'] ) {
										$heading_text = wc_price( $pd['value'] ) . __( ' OFF', 'wholesalex' );
									} elseif ( 'fixed' == $pd['type'] ) {
										$heading_text = '<del>' . wc_price( $product->get_price() ) . '</del>. to <ins>' . wc_price( $pd['value'] ) . '</ins>';
									}
									$desc       = '<span class="wsx-discount-desc">' . __( 'Use ', 'wholesalex' ) . implode( ',', $pd['gateways'] ) . ' </span>';
									$conditions = 'yes' === wholesalex()->get_setting( 'show_discount_conditions_on_sp', 'no' ) && isset( $pd['conditions']['tiers'] ) ? $this->generate_rule_conditions_markup( $pd['conditions']['tiers'] ) : '';
									$validity   = '';
									if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {
										$validity = $pd['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $pd['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
									}
									?>
									<div class="wsx-single-product-discount-card wsx-payment-discount-card">
										<div class="wsx-discount-card-heading"><?php echo wp_kses_post( $heading_text ); ?></div>
										<div class="wsx-discount-card-desc"><?php echo wp_kses_post( $desc . $conditions . $validity ); ?> </div>
									</div>
									<?php
								}
								?>
							</div>
						</div>
						<?php
					}
				}
				if ( 'yes' === wholesalex()->get_setting( 'show_bogo_discount_promo_text_on_sp', 'no' ) ) {

					$buy_x_get_one = wholesalex()->get_rule_data( $product->get_id(), 'buy_x_get_one' );

					if ( ! empty( $buy_x_get_one ) ) {
						usort( $buy_x_get_one, array( $this, 'compare_by_priority_reverse' ) );

						?>
						<div class="wsx-sp-bogo-discounts">
							<?php
							if ( 'yes' == wholesalex()->get_setting( 'bogo_discount_rule_sp_show_rule_info', 'yes' ) ) {
								?>
									<div class="wsx-sp-rule-info">
										<div class="wsx-sp-rule-info_type"><?php echo esc_html( wholesalex()->get_setting( 'bogo_discount_rule_info_rule_type_text', __( 'BOGO Discount', 'wholesalex' ) ) ); ?></div>
									<?php
									if ( count( $buy_x_get_one ) > 1 ) {
										?>
											<div class="wsx-sp-rule-info_desc"><?php echo esc_html( wholesalex()->get_setting( 'dynamic_rule_promotional_explainer_text_multiple_discount', __( 'You can avail following offers by completing the requirements.', 'wholesalex' ) ) ); ?>  </div>
											<?php
									}
									?>
									</div>
									<?php
							}
							?>
							
							<div class="wsx-sp-discounts-cards">
									<?php
									foreach ( $buy_x_get_one as $pd ) {
										$min_qty      = $pd['minimum_qty'];
										$heading_text = wholesalex()->get_setting( 'bogo_discount_free_text_on_sp', __( 'Get 1 Free', 'wholesalex' ) );
										$desc         = '<span class="wsx-discount-desc">' . $this->restore_smart_tags(
											array(
												'{required_quantity}' => $min_qty,
												'{product_title}' => $product->get_title(),
											),
											wholesalex()->get_setting( 'bogo_discounts_promo_sp_desc_text_on_sp', __( 'Buy at least {required_quantity} products', 'wholesalex' ) )
										) . ' </span>';
										$conditions   = 'yes' === wholesalex()->get_setting( 'show_discount_conditions_on_sp', 'no' ) && isset( $pd['conditions']['tiers'] ) ? $this->generate_rule_conditions_markup( $pd['conditions']['tiers'] ) : '';
										$validity     = '';
										if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {

											$validity = $pd['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $pd['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
										}
										?>
											<div class="wsx-single-product-discount-card wsx-sp-bogo-discount-cart">
												<div class="wsx-discount-card-heading"><?php echo wp_kses_post( $heading_text ); ?></div>
												<div class="wsx-discount-card-desc"><?php echo wp_kses_post( $desc . $conditions . $validity ); ?></div>
											</div>
										<?php
									}
									?>
							</div>
						</div>
						<?php
					}
				}
				if ( 'yes' === wholesalex()->get_setting( 'show_free_shipping_promo_text_on_sp', 'no' ) ) {

					$free_shipping = wholesalex()->get_rule_data( $product->get_id(), 'free_shipping' );

					if ( ! empty( $free_shipping ) ) {
						usort( $free_shipping, array( $this, 'compare_by_priority_reverse' ) );

						ob_start();
						foreach ( $free_shipping as $pd ) {
							if ( isset( $pd['conditions']['tiers'] ) && ! empty( $pd['conditions']['tiers'] ) ) {
								$heading_text = wholesalex()->get_setting( 'free_shipping_heading_text_on_sp', __( 'Free Shipping', 'wholesalex' ) );
								$conditions   = 'yes' === wholesalex()->get_setting( 'show_discount_conditions_on_sp', 'no' ) && isset( $pd['conditions']['tiers'] ) ? $this->generate_rule_conditions_markup( $pd['conditions']['tiers'] ) : '';
								$validity     = '';
								if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {
									$validity = $pd['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $pd['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
								}
								?>
								<div class="wsx-single-product-discount-card  wsx-sp-free-shipping-cart">
									<div class="wsx-discount-card-heading"><?php echo wp_kses_post( $heading_text ); ?></div>
									<div class="wsx-discount-card-desc"> <?php echo wp_kses_post( $conditions . $validity ); ?></div>
								</div>
								<?php
							}
						}
						$conditional_free_shipping_content = ob_get_clean();
						if ( $conditional_free_shipping_content ) {
							?>
							<div class="wsx-free-shipping-discounts">
								<div class="wsx-sp-discounts-cards">
									<?php
										echo wp_kses_post( $conditional_free_shipping_content );
									?>
								</div>
							</div>
							<?php
						}
					}
				}

				$modal_content = ob_get_clean();
			

			$modal_content = trim(apply_filters( 'wholesalex_promotions_frontend_popup_modal_content', $modal_content ));
			if ( $modal_content ) {
				do_action( 'wholesalex_promotions_popup_header' );
				?>
				<div class="wsx-single-product-offers">

					<div class="wsx-sp-promo-heading"> <?php echo esc_html__( 'Promotions:', 'wholesalex' ); ?></div>

					<div class="wsx-sp-promotions">
						<span class="wsx-single-product-offers-view-more" id="wsx-sp-dr-view-more" data-product-id="<?php esc_attr( $product->get_id() ); ?>"> <?php echo esc_html( wholesalex()->get_setting( 'promo_button_text_on_sp', __( 'Get exclusive offers', 'wholesalex' ) ) ); ?> </span>

						<div class="wsx-dr-single-product-discounts-modal" id="wsx-dr-single-product-discounts-modal-<?php esc_attr( $product->get_id() ); ?>" style="display:none;">
							<div class="wsx-dr-single-product-discounts-modal-body">
								<div class="wsx-dr-modal-heading"><?php echo esc_html__( 'Conditional Discount Offers', 'wholesalex' ); ?></div>
								<?php echo wp_kses_post( $modal_content ); ?>
							</div>

						</div>
					</div>
				</div>
				<?php
				do_action( 'wholesalex_promotions_popup_footer' );
			}
			if ( 'yes' == wholesalex()->get_setting( 'show_order_qty_text_on_sp', 'no' ) && ( ! empty( wholesalex()->get_rule_data( $product->get_id() )['min_order_qty'] ) || ! empty( wholesalex()->get_rule_data( $product->get_id() )['max_order_qty'] ) ) ) {
				$min_qty = '';
				if(isset(wholesalex()->get_rule_data( $product->get_id() )['min_order_qty'] )) {
					foreach ( wholesalex()->get_rule_data( $product->get_id() )['min_order_qty'] as $rule ) {
						$min_qty = $rule['minimum_qty'];
					}
				}
				$max_qty = '';
				if(isset(wholesalex()->get_rule_data( $product->get_id() )['max_order_qty'] )) {
					foreach ( wholesalex()->get_rule_data( $product->get_id() )['max_order_qty'] as $rule ) {
						$max_qty = $rule['maximum_qty'];
					}
				}

				if ( $max_qty && $min_qty ) {
					$message = $this->restore_smart_tags(
						array(
							'{minimum_qty}'   => $min_qty,
							'{maximum_qty}'   => $max_qty,
							'{product_title}' => $product->get_title(),
						),
						wholesalex()->get_setting( 'min_max_both_order_qty_promo_text', __( 'You can add minimum {minimum_qty} and maximum {maximum_qty} quantity of this product', 'wholesalex' ) )
					);
				} elseif ( $min_qty ) {
					$message = $this->restore_smart_tags(
						array(
							'{minimum_qty}'   => $min_qty,
							'{product_title}' => $product->get_title(),
						),
						wholesalex()->get_setting( 'only_minimum_order_qty_promo_text', __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ) )
					);
				} elseif ( $max_qty ) {
					$message = $this->restore_smart_tags(
						array(
							'{maximum_qty}'   => $max_qty,
							'{product_title}' => $product->get_title(),
						),
						wholesalex()->get_setting( 'only_maximum_order_qty_promo_text', __( 'You can add maximum {maximum_qty} quantity', 'wholesalex' ) )
					);
				}
				?>
				<div class="wsx-single-product-discount-card wsx-mt-10 wsx-min-max-sp-card"><?php echo esc_html( $message ); ?> </div>
				<?php
			}

			if ( 'yes' === wholesalex()->get_setting( 'show_free_shipping_promo_text_on_sp', 'no' ) ) {

				$free_shipping    = wholesalex()->get_rule_data( $product->get_id(), 'free_shipping' );
				$is_free_shipping = false;

				if ( ! empty( $free_shipping ) ) {
					foreach ( $free_shipping as $pd ) {
						if ( ! isset( $pd['conditions']['tiers'] ) || empty( $pd['conditions']['tiers'] ) ) {
							$is_free_shipping = true;
							break;
						}
					}
				}

				if ( $is_free_shipping ) {
					$shipping_text = wholesalex()->get_setting( 'free_shipping_text_on_sp', __( 'Free Shipping', 'wholesalex' ) );
					?>
				<div class="wsx-single-product-discount-card wsx-mt-10 wsx-free-shipping-sp-card"><?php echo esc_html( $shipping_text ); ?> </div>
					<?php
				}
			}

			if ( 'yes' === wholesalex()->get_setting( '_settings_show_bxgy_free_products_on_single_product_page', 'no' ) ) {
				$bxgy_rules = wholesalex()->get_rule_data( $product->get_id(), 'buy_x_get_y' );

				foreach ( $bxgy_rules as $rule ) {

					if ( ! isset( $rule['conditions']['tiers'] ) || empty( $rule['conditions']['tiers'] ) ) {
						$validity = '';
						if ( 'yes' === wholesalex()->get_setting( 'show_discounts_validity_text_on_sp', 'no' ) ) {
							$validity = $rule['end_date'] ? '<div class="wsx-single-product-discount-card-validity">' . $this->restore_smart_tags( array( '{end_date}' => gmdate( 'Y-m-d', strtotime( $rule['end_date'] . ' +1 day' ) ) ), wholesalex()->get_setting( 'discounts_validity_text', 'Valid till: {end_date}' ) ) . '</div>' : '';
						}

						$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
						$variation_id = $product->get_parent_id() ? $product->get_id() : 0;

						extract( $rule['filter'] );

						if ( ! empty( $include_cats ) || ! empty( $exclude_cats ) ) {
							$cats = wc_get_product_term_ids( $product_id, 'product_cat' );
						}

						$heading_text = false;

						if ( ! empty( $include_products ) && in_array( $product_id, $include_products ) ) {
							$heading_text = sprintf( 'Buy %s Quantity to Get These Item Free!', $rule['min_purchase_count'] );
						} elseif ( ! empty( $exclude_products ) && ! in_array( $product_id, $exclude_products ) ) {
							$heading_text = sprintf( 'Buy %s Quantity to Get These Item Free!', $rule['min_purchase_count'] );
						} elseif ( ! empty( $include_cats ) && array_intersect( $cats, $include_cats ) ) {
							$cat_names = array();
							foreach ( $include_cats as $cat_id ) {
								$term        = get_term_by( 'id', $cat_id, 'product_cat' );
								$cat_names[] = $term->name;
							}
							$heading_text = sprintf( 'Buy %s Quantity From %s Categories to Get These Item Free!', $rule['min_purchase_count'], implode( ',', $cat_names ) );
						} elseif ( ! empty( $exclude_cats ) && ! array_intersect( $cats, $exclude_cats ) ) {
							$cat_names = array();
							foreach ( $exclude_cats as $cat_id ) {
								$term        = get_term_by( 'id', $cat_id, 'product_cat' );
								$cat_names[] = $term->name;
							}
							$heading_text = sprintf( 'Buy %s Quantity Excluding These %s Categories to Get These Item Free!', $rule['min_purchase_count'], implode( ',', $cat_names ) );
						} elseif ( ! empty( $include_variations ) && in_array( $variation_id, $include_variations ) ) {
							$heading_text = sprintf( 'Buy %s Quantity to Get These Item Free!', $rule['min_purchase_count'] );
						} elseif ( ! empty( $exclude_variations ) && ! in_array( $variation_id, $exclude_variations ) ) {
							$heading_text = sprintf( 'Buy %s Quantity to Get These Item Free!', $rule['min_purchase_count'] );
						} elseif ( $is_all_products ) {
							$heading_text = sprintf( 'Buy %s Quantity From All Products to Get These Item Free!', $rule['min_purchase_count'] );
						}

						if ( $heading_text ) {
							$this->bxgy_free_items_template( $heading_text, $rule['free_items'], $rule['free_item_quantity'] );
						}
					}
				}
			}

			if ( $modal_content ) {
				?>
			<script type="text/javascript">
				(function($) {
					'use strict';
					let view_more = $("#wsx-sp-dr-view-more");
					view_more.on('click', function(e) {
						const product_id = view_more.data('product-id');
						$("#wsx-dr-single-product-discounts-modal-" + product_id).slideToggle(100);
					});

					$(document).click(function(e) {
						if ($(e.target).closest('.wsx-dr-single-product-discounts-modal').length != 0) return false;
						if ($(e.target).closest('#wsx-sp-dr-view-more').length != 0) return false;
						$('.wsx-dr-single-product-discounts-modal').hide(100);
					});

				})(jQuery);
			</script>
				<?php
			}
		}

		if ( ! $is_echo ) {
			return ob_get_clean();
		}
	}


	/**
	 * Buy X Get Y Free Items Templates for Single Product Page.
	 *
	 * @param string     $min_purchase_text Text that visible before free items.
	 * @param array      $free_items Free Items.
	 * @param int|string $free_item_quantity Free Items quantity.
	 * @return void
	 * @since 1.0.9
	 */
	public function bxgy_free_items_template( $min_purchase_text, $free_items, $free_item_quantity ) {
		?>
		<div class="wholesalex_free_items wsx-single-product-discount-card">
			<div class="wsx-bxgy-min-purchase-text"> <?php echo esc_html( $min_purchase_text ); ?> </div>
			<?php
			foreach ( $free_items as $item ) {
				$free_item_id = $item['value'];
				$product      = wc_get_product( $free_item_id );
				$image        = wp_get_attachment_image_src( get_post_thumbnail_id( $free_item_id ), 'thumbnail' );
				?>

				<div class="wsx-bxgy-free-promo-card">
					<img src="<?php echo esc_url( $image[0] ); ?>">
					<div class="wsx-bxgy-free-item-meta">
						<div class="wsx-bxgy-free-item-title"><?php echo esc_html( $product->get_title() ); ?> </div>
						<?php
						if ( $free_item_quantity > 1 ) {
							?>
							 <div class="wsx-bxgy-free-item-qty"><?php echo sprintf( __( 'Quantity: %s', 'wholesalex' ), $free_item_quantity ); //phpcs:ignore ?> </div> <?php } //phpcs:ignore ?>
						<div class="wsx-bxgy-free-item-price"> <?php echo wc_price( $product->get_price( 'edit' ) * esc_html( $free_item_quantity ) );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?> </div>
					</div>
					<div class="wsx-free-item-tag"><?php echo esc_html__( 'FREE', 'wholesalex' ); ?> </div>
				</div>
				<?php
			}
			?>
		</div>
		<?php

	}
	public function restore_smart_tags( $smart_tags, $string ) {
		foreach ( $smart_tags as $key => $value ) {
			$string = str_replace( $key, $value, $string );
		}
		return $string;
	}
	public function generate_rule_conditions_markup( $conditions ) {
		$data = array();

		$markup = '<div>';
		foreach ( $conditions as $condition ) {
			if ( isset( $condition['_conditions_for'], $condition['_conditions_operator'], $condition['_conditions_value'] ) ) {
				$con_value = floatval( $condition['_conditions_value'] );
				if ( 'less_equal' === $condition['_conditions_operator'] || 'less' === $condition['_conditions_operator'] ) {
					if ( 'less_equal' === $condition['_conditions_operator'] ) {
						$con_value = $con_value + 1;
					}
					if ( ! isset( $data[ $condition['_conditions_for'] ]['less'] ) ) {
						$data[ $condition['_conditions_for'] ] = array( 'less' => $con_value );
					}
					$data[ $condition['_conditions_for'] ]['less'] = min( $data[ $condition['_conditions_for'] ]['less'], $con_value );
				}
				if ( 'greater_equal' === $condition['_conditions_operator'] || 'greater' === $condition['_conditions_operator'] ) {
					if ( 'greater' === $condition['_conditions_operator'] ) {
						$con_value = $con_value + 1;
					}
					if ( ! isset( $data[ $condition['_conditions_for'] ]['greater'] ) ) {
						$data[ $condition['_conditions_for'] ] = array( 'greater' => $con_value );
					}
					$data[ $condition['_conditions_for'] ]['greater'] = max( $data[ $condition['_conditions_for'] ]['greater'], $con_value );
				}
			}
		}

		if ( isset( $data['cart_total_weight'] ) ) {
			$weight_unit = get_option( 'woocommerce_weight_unit' );
		}
		foreach ( $data as $con_name => $cons ) {
			switch ( $con_name ) {
				case 'cart_total_value':
					if ( isset( $cons['greater'], $cons['less'] ) ) {
						$cons['greater'] = wc_price( $cons['greater'] );
						$cons['less']    = wc_price( $cons['less'] );
						$markup         .= '<div>' . $this->restore_smart_tags(
							array(
								'{min_value}' => $cons['less'],
								'{max_value}' => $cons['greater'],
							),
							wholesalex()->get_setting( 'cart_total_value_min_max_conditions_text', __( 'Spend {min_value} to {max_value}', 'wholesalex' ) )
						) . '</div>';
					} elseif ( isset( $cons['greater'] ) ) {
						$cons['greater'] = wc_price( $cons['greater'] );
						$markup         .= '<div>' . $this->restore_smart_tags( array( '{max_value}' => $cons['greater'] ), wholesalex()->get_setting( 'cart_total_value_min_conditions_text', __( 'Spend min {max_value}', 'wholesalex' ) ) ) . '</div>';
					} elseif ( isset( $cons['less'] ) ) {
						$cons['less'] = wc_price( $cons['less'] );
						$markup      .= '<div>' . $this->restore_smart_tags( array( '{min_value}' => $cons['less'] ), wholesalex()->get_setting( 'cart_total_value_max_conditions_text', __( 'Spend upto {min_value}', 'wholesalex' ) ) ) . '</div>';
					}
					break;
				case 'cart_total_qty':
					if ( isset( $cons['greater'], $cons['less'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags(
							array(
								'{min_value}' => $cons['less'],
								'{max_value}' => $cons['greater'],
							),
							wholesalex()->get_setting( 'cart_total_qty_min_max_conditions_text', __( 'Add {min_value} to {max_value} product(s) to cart', 'wholesalex' ) )
						) . '</div>';
					} elseif ( isset( $cons['greater'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags( array( '{max_value}' => $cons['greater'] ), wholesalex()->get_setting( 'cart_total_qty_min_conditions_text', __( 'Add min {max_value} product(s) to cart', 'wholesalex' ) ) ) . '</div>';
					} elseif ( isset( $cons['less'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags( array( '{min_value}' => $cons['less'] ), wholesalex()->get_setting( 'cart_total_qty_max_conditions_text', __( 'Add {min_value} or more product(s) to cart', 'wholesalex' ) ) ) . '</div>';
					}
					break;
				case 'cart_total_weight':
					if ( isset( $cons['greater'], $cons['less'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags(
							array(
								'{min_value}' => $cons['less'],
								'{max_value}' => $cons['greater'],
								'{unit}'      => $weight_unit,
							),
							wholesalex()->get_setting( 'cart_total_weight_min_max_conditions_text', __( 'Add {min_value} to {max_value} {unit} to cart', 'wholesalex' ) )
						) . '</div>';
					} elseif ( isset( $cons['greater'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags(
							array(
								'{max_value}' => $cons['greater'],
								'{unit}'      => $weight_unit,
							),
							wholesalex()->get_setting( 'cart_total_weight_min_conditions_text', __( 'Add min {max_value} {unit} to cart', 'wholesalex' ) )
						) . '</div>';
					} elseif ( isset( $cons['less'] ) ) {
						$markup .= '<div>' . $this->restore_smart_tags(
							array(
								'{min_value}' => $cons['less'],
								'{unit}'      => $weight_unit,
							),
							wholesalex()->get_setting( 'cart_total_weight_max_conditions_text', __( 'Add up to {min_value} {unit} to cart', 'wholesalex' ) )
						) . '</div>';
					}
					break;

				default:
					// code...
					break;
			}
		}
		$markup .= '</div>';
		return $markup;
	}

	public function check_for_free_shipping( $product, $profile_shipping_data ) {
		$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->get_parent_id() ? $product->get_id() : 0;

		$is_profile_free_shipping = false;
		if ( ! empty( wholesalex()->get_rule_data( $variation_id ? $variation_id : $product_id, 'free_shipping', 'profile' ) ) ) {
			$is_profile_free_shipping = true;
		}

		if ( ! empty( $profile_shipping_data ) ) {
			if ( isset( $profile_shipping_data['method_type'] ) && 'force_free_shipping' == $profile_shipping_data['method_type'] ) {
				// Free Shipping
				$is_profile_free_shipping = true;
			}

			if ( ! $is_profile_free_shipping && isset( $profile_shipping_data['method_type'] ) && 'specific_shipping_methods' == $profile_shipping_data['method_type'] ) {
				// $all_methods = $package_rates;

				foreach ( $profile_shipping_data['methods'] as $method ) {

					if ( ! isset( $this->cached_shipping_method_id[ $method['value'] ] ) ) {
						$zone = WC_Shipping_Zones::get_shipping_method( $method['value'] );
						$this->cached_shipping_method_id[ $method['value'] ] = $zone->id;
					}

					if ( 'free_shipping' == $this->cached_shipping_method_id[ $method['value'] ] ) {
						// Found Free Shipping
						$is_profile_free_shipping = true;
						break;
					}
				}
			}
		}

		if ( $is_profile_free_shipping ) {
			wholesalex()->set_rule_data(
				'profile',
				$variation_id ? $variation_id : $product_id,
				'free_shipping',
				array(
					'conditions'          => array(),
					'end_date'            => false,
					'who_priority'        => 10,
					'applied_on_priority' => 10,
				),
			);
		} else {

			if ( ! empty( $this->valid_dynamic_rules['shipping_rule'] ) ) {
				foreach ( $this->valid_dynamic_rules['shipping_rule'] as $sr ) {
					if ( empty( wholesalex()->get_rule_data( $variation_id ? $variation_id : $product_id, 'free_shipping', $sr['id'] ) ) ) {
						if ( self::is_eligible_for_rule( $product_id, $variation_id, $sr['filter'] ) ) {

							if ( $sr['rule']['__shipping_zone'] == $this->current_shipping_zone && ! $is_profile_free_shipping ) {
								$methods = $sr['rule']['_shipping_zone_methods'];
								foreach ( $methods as $method ) {
									if ( ! isset( $this->cached_shipping_method_id[ $method['value'] ] ) ) {
										$zone = WC_Shipping_Zones::get_shipping_method( $method['value'] );
										$this->cached_shipping_method_id[ $method['value'] ] = $zone->id;
									}
									if ( 'free_shipping' == $this->cached_shipping_method_id[ $method['value'] ] ) {

										// Found Free Shipping
										$is_profile_free_shipping = true;
										// if(isset( $sr['conditions']['tiers']) && !empty( $sr['conditions']['tiers'])) {
											wholesalex()->set_rule_data(
												$sr['id'],
												$variation_id ? $variation_id : $product_id,
												'free_shipping',
												array(
													'conditions' => $sr['conditions'] ? $sr['conditions'] : array(),
													'who_priority' => $sr['who_priority'],
													'applied_on_priority' => $sr['applied_on_priority'],
													'end_date' => $sr['end_date'],
												),
											);
										// }
										break;
									}
								}
							}
						}
					}
				}
			}
		}

	}

	public function check_for_cart_releated_discounts( $product, $cart_related_data ) {
		$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->get_parent_id() ? $product->get_id() : 0;
		$rules        = apply_filters( 'wholesalex_dr_cart_related_data', $cart_related_data );

		if ( ! empty( $rules ) ) {

			if ( 'yes' === wholesalex()->get_setting( 'show_cart_discount_text', 'no' ) && isset( $rules['cart_discount'] ) && ! empty( $rules['cart_discount'] ) ) {
				$hash = array();
				foreach ( $rules['cart_discount'] as $rule ) {
					if ( empty( wholesalex()->get_rule_data( $variation_id ? $variation_id : $product_id, 'cart_discount', $rule['id'] ) ) ) {
						$discount_amount = $rule['rule']['_discount_amount'];
						$discount_type   = $rule['rule']['_discount_type'];

						if ( self::is_eligible_for_rule( $product_id, $variation_id, $rule['filter'] ) ) {

							wholesalex()->set_rule_data(
								$rule['id'],
								$variation_id ? $variation_id : $product_id,
								'cart_discount',
								array(
									'type'                => $discount_type,
									'value'               => $discount_amount,
									'conditions'          => $rule['conditions'],
									'who_priority'        => $rule['who_priority'],
									'applied_on_priority' => $rule['applied_on_priority'],
									'end_date'            => $rule['end_date'],
								)
							);
						}
					}
				}
			}
			if ( 'yes' === wholesalex()->get_setting( 'show_payment_method_discount_promo_text_sp', 'no' ) && isset( $rules['payment_discount'] ) && ! empty( $rules['payment_discount'] ) ) {
				$hash = array();
				foreach ( $rules['payment_discount'] as $rule ) {

					if ( empty( wholesalex()->get_rule_data( $variation_id ? $variation_id : $product_id, 'payment_discount', $rule['id'] ) ) ) {
						$discount_amount = $rule['rule']['_discount_amount'];
						$discount_type   = $rule['rule']['_discount_type'];

						$gateways_name = self::get_multiselect_values( $rule['rule']['_payment_gateways'], 'name' );
						if ( self::is_eligible_for_rule( $product_id, $variation_id, $rule['filter'] ) ) {

							wholesalex()->set_rule_data(
								$rule['id'],
								$variation_id ? $variation_id : $product_id,
								'payment_discount',
								array(
									'type'                => $discount_type,
									'value'               => $discount_amount,
									'conditions'          => $rule['conditions'],
									'gateways'            => $gateways_name,
									'who_priority'        => $rule['who_priority'],
									'applied_on_priority' => $rule['applied_on_priority'],
									'end_date'            => $rule['end_date'],
								)
							);
						}
					}
				}
			}
			if ( 'yes' === wholesalex()->get_setting( 'show_bogo_discount_promo_text_on_sp', 'no' ) && isset( $rules['buy_x_get_one'] ) && ! empty( $rules['buy_x_get_one'] ) ) {
				$hash = array();
				foreach ( $rules['buy_x_get_one'] as $rule ) {
					if ( empty( wholesalex()->get_rule_data( $variation_id ? $variation_id : $product_id, 'buy_x_get_one', $rule['id'] ) ) ) {

						if ( self::is_eligible_for_rule( $product_id, $variation_id, $rule['filter'] ) ) {

							wholesalex()->set_rule_data(
								$rule['id'],
								$variation_id ? $variation_id : $product_id,
								'buy_x_get_one',
								array(
									'minimum_qty'         => $rule['rule']['_minimum_purchase_count'],
									'conditions'          => $rule['conditions'] ? $rule['conditions'] : array(),
									'who_priority'        => $rule['who_priority'],
									'applied_on_priority' => $rule['applied_on_priority'],
									'end_date'            => $rule['end_date'],

								)
							);
						}
					}
				}
			}
		}
	}

	public function get_filtered_rules( $discount ) {
		$include_products   = array();
		$include_cats       = array();
		$include_variations = array();
		$exclude_products   = array();
		$exclude_cats       = array();
		$exclude_variations = array();
		$is_all_products    = false;
		$product_priority   = 10;

		switch ( $discount['_product_filter'] ) {
			case 'all_products':
				$is_all_products  = true;
				$product_priority = 50;
				break;
			case 'products_in_list':
				if ( ! isset( $discount['products_in_list'] ) ) {
					break;
				}
				foreach ( $discount['products_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $include_products, (int) $list['value'] );
						$product_priority = 20;
					}
				}
				break;
			case 'products_not_in_list':
				if ( ! isset( $discount['products_not_in_list'] ) ) {
					break;
				}
				foreach ( $discount['products_not_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $exclude_products, (int) $list['value'] );
						$product_priority = 20;
					}
				}
				break;
			case 'cat_in_list':
				if ( ! isset( $discount['cat_in_list'] ) ) {
					break;
				}
				foreach ( $discount['cat_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $include_cats, (int) $list['value'] );
						$product_priority = 30;
					}
				}

				break;

			case 'cat_not_in_list':
				if ( ! isset( $discount['cat_not_in_list'] ) ) {
					break;
				}
				foreach ( $discount['cat_not_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $exclude_cats, (int) $list['value'] );
						$product_priority = 30;
					}
				}
				break;
			case 'attribute_in_list':
				if ( ! isset( $discount['attribute_in_list'] ) ) {
					break;
				}

				foreach ( $discount['attribute_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $include_variations, (int) $list['value'] );
						$product_priority = 10;
					}
				}

				break;
			case 'attribute_not_in_list':
				if ( ! isset( $discount['attribute_not_in_list'] ) ) {
					break;
				}
				foreach ( $discount['attribute_not_in_list'] as $list ) {
					if ( isset( $list['value'] ) ) {
						array_push( $exclude_variations, (int) $list['value'] );
						$product_priority = 10;
					}
				}
				break;
		}

		return array(
			'include_products'   => $include_products,
			'include_cats'       => $include_cats,
			'include_variations' => $include_variations,
			'exclude_products'   => $exclude_products,
			'exclude_cats'       => $exclude_cats,
			'exclude_variations' => $exclude_variations,
			'is_all_products'    => $is_all_products,
			'product_priority'   => $product_priority,
		);
	}

	public function compare_by_priority( $a, $b ) {
		 // Compare the applied_on_priority values
		if ( $a['applied_on_priority'] == $b['applied_on_priority'] ) {
			// If they are equal, compare the who_priority values
			if ( $a['who_priority'] == $b['who_priority'] ) {
				// If they are also equal, return 0 (no change)
				return 0;
			}
			// If they are not equal, return -1 or 1 depending on which is smaller
			return ( $a['who_priority'] > $b['who_priority'] ) ? -1 : 1;
		}
		// If they are not equal, return -1 or 1 depending on which is smaller
		return ( $a['applied_on_priority'] > $b['applied_on_priority'] ) ? -1 : 1;
	}
	public function compare_by_priority_reverse( $a, $b ) {
		 // Compare the applied_on_priority values
		if ( $a['applied_on_priority'] == $b['applied_on_priority'] ) {
			// If they are equal, compare the who_priority values
			if ( $a['who_priority'] == $b['who_priority'] ) {
				// If they are also equal, return 0 (no change)
				return 0;
			}
			// If they are not equal, return -1 or 1 depending on which is smaller
			return ( $a['who_priority'] < $b['who_priority'] ) ? -1 : 1;
		}
		// If they are not equal, return -1 or 1 depending on which is smaller
		return ( $a['applied_on_priority'] < $b['applied_on_priority'] ) ? -1 : 1;
	}


	/**
	 * Handle Tax Related Rules and Other Stuffs
	 * First will check For profile exemption, if found then do it
	 * otherwise will check for dynamic rules.
	 *
	 * Priority: Profile > Dynamic Rules
	 */
	public function handle_tax( $data ) {
		if ( is_admin() || null === WC()->customer ) {
			return;
		}

		$status                 = false;
		$is_customer_vat_exempt = false;

		if ( isset( $data['profile_exemption'] ) && 'yes' == $data['profile_exemption'] ) {
			$is_customer_vat_exempt = true;
			$status                 = true;
		}
		if ( isset( $data['profile_exemption'] ) && 'no' == $data['profile_exemption'] ) {
			$is_customer_vat_exempt = false;
			$status                 = true;
		}

		if ( ! $status && isset( $data['rules'] ) && is_array( $data['rules'] ) && ! empty( $data['rules'] ) ) {
			// Now Handle Dynamic Rule Tax
			$is_customer_vat_exempt = '';

			foreach ( $data['rules'] as $rule ) {
				if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
					continue;
				}

				if ( isset( $rule['filter']['is_all_products'] ) && $rule['filter']['is_all_products'] ) {
					$is_tax_exempt          = isset( $rule['rule']['_tax_exempted'] ) ? $rule['rule']['_tax_exempted'] : '';
					$is_based_on_country    = isset( $rule['rule']['_exempted_country'] ) ? $rule['rule']['_exempted_country'] : false;
					$is_customer_vat_exempt = 'yes' == $is_tax_exempt ? true : false;
					if ( $is_based_on_country ) {
						$allowed_country = self::get_multiselect_values( $is_based_on_country );
						$user_country    = '';

						if ( is_a( WC()->customer, 'WC_Customer' ) ) {
							$tax_setting = get_option( 'woocommerce_tax_based_on' );
							if ( $tax_setting === 'shipping' ) {
								$user_country = WC()->customer->get_shipping_country();
							} else {
								$user_country = WC()->customer->get_billing_country();
							}
						} else {
							$user_country = 'NAC';
						}

						if ( ! in_array( $user_country, $allowed_country, true ) ) {
							$is_customer_vat_exempt = false;
						}
					}

					wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
				} else {
					add_filter(
						'woocommerce_product_get_tax_class',
						function ( $tax_class, $product ) use ( $rule ) {
							$rule_tax_class = isset( $rule['rule']['_tax_class'] ) ? $rule['rule']['_tax_class'] : '';
							$is_tax_exempt  = isset( $rule['rule']['_tax_exempted'] ) ? $rule['rule']['_tax_exempted'] : '';
							if ( self::is_eligible_for_rule( $product->get_id(), 0, $rule['filter'] ) ) {
								$is_based_on_country = isset( $rule['rule']['_exempted_country'] ) ? $rule['rule']['_exempted_country'] : false;
								if ( $is_based_on_country ) {
									$allowed_country = self::get_multiselect_values( $is_based_on_country );
									$user_country    = '';
									if ( is_a( WC()->customer, 'WC_Customer' ) ) {
										$tax_setting = get_option( 'woocommerce_tax_based_on' );
										if ( $tax_setting === 'shipping' ) {
											$user_country = WC()->customer->get_shipping_country();
										} else {
											$user_country = WC()->customer->get_billing_country();
										}
									} else {
										$user_country = 'NAC';
									}

									if ( ! in_array( $user_country, $allowed_country, true ) ) {
										return $tax_class;
									}
								}
								if ( 'yes' == $is_tax_exempt ) {
									$tax_class = 'Zero Rate';
									if ( ! isset( $this->product_page_notices[ $product->get_id() ] ) ) {
										$this->product_page_notices[ $product->get_id() ] = array();
									}
									$this->product_page_notices[ $product->get_id() ]['tax_free'] = true;
								} else {
									$tax_class = $rule_tax_class;
								}
								wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
							}
							return $tax_class;
						},
						10,
						2
					);

					add_filter(
						'woocommerce_product_variation_get_tax_class',
						function ( $tax_class, $product ) use ( $rule ) {
							$rule_tax_class = isset( $rule['rule']['_tax_class'] ) ? $rule['rule']['_tax_class'] : '';
							$is_tax_exempt  = isset( $rule['rule']['_tax_exempted'] ) ? $rule['rule']['_tax_exempted'] : '';
							if ( self::is_eligible_for_rule( $product->get_id(), 0, $rule['filter'] ) ) {
								$is_based_on_country = isset( $rule['rule']['_exempted_country'] ) ? $rule['rule']['_exempted_country'] : false;
								if ( $is_based_on_country ) {
									$allowed_country = self::get_multiselect_values( $is_based_on_country );
									$user_country    = '';
									if ( is_a( WC()->customer, 'WC_Customer' ) ) {
										$tax_setting = get_option( 'woocommerce_tax_based_on' );
										if ( $tax_setting === 'shipping' ) {
											$user_country = WC()->customer->get_shipping_country();
										} else {
											$user_country = WC()->customer->get_billing_country();
										}
									} else {
										$user_country = 'NAC';
									}

									if ( ! in_array( $user_country, $allowed_country ) ) {
										return $tax_class;
									}
								}
								if ( 'yes' == $is_tax_exempt ) {
									$tax_class = 'Zero Rate';
									if ( ! is_array( $this->product_page_notices[ $product->get_id() ] ) ) {
										$this->product_page_notices[ $product->get_id() ] = array();
									}
									$this->product_page_notices[ $product->get_id() ]['tax_free'] = true;
								} else {
									$tax_class = $rule_tax_class;
								}
							}
							return $tax_class;
						},
						10,
						2
					);
				}
			}
		}
		if ( $is_customer_vat_exempt ) {
			add_filter(
				'woocommerce_product_get_tax_class',
				function () {
					return 'Zero Rate';
				}
			);
			add_filter(
				'woocommerce_product_variation_get_tax_class',
				function () {
					return 'Zero Rate';
				}
			);
		}
	}

	/**
	 * Handle Shipping related all kind of rules and options
	 *
	 * Priority: Profile > > User Role> Dynamic Rules
	 */
	public function handle_shipping( $data ) {
		add_filter(
			'woocommerce_package_rates',
			function ( $package_rates, $package ) use ( $data ) {
				// Force Free Shipping For Specific
				if ( isset( $data['profile']['method_type'] ) && 'force_free_shipping' == $data['profile']['method_type'] ) {
					// Create WholesaleX Free Shipping Method.
					$free_shipping = new WC_Shipping_Free_Shipping( 'wholesalex_free_shipping' );
					/* translators: %s: Plugin Name */
					$free_shipping->title = apply_filters( 'wholesalex_free_shipping_title', sprintf( __( '%s Free Shipping', 'wholesalex' ), wholesalex()->get_plugin_name() ) );

					$free_shipping->calculate_shipping( $package );

					return apply_filters( 'wholesalex_available_shipping_methods', $free_shipping->rates, $package_rates, $package, $data );
				}

				if ( isset( $data['profile']['method_type'] ) && 'specific_shipping_methods' == $data['profile']['method_type'] ) {
					$all_methods     = $package_rates;
					$allowed_methods = array();

					foreach ( $data['profile']['methods'] as $method ) {
						$allowed_methods[] = $method['value'];
					}

					foreach ( $package_rates as $rate_key => $rate ) {
						if ( ! in_array( $rate->instance_id, $allowed_methods ) ) {
							unset( $package_rates[ $rate_key ] );
						}
					}
					return apply_filters( 'wholesalex_available_shipping_methods', $package_rates, $all_methods, $package, $data );
				}

				/**
				 * If Rolewise Shippings methods is empty, then it means all shippings methods will be available for this role
				 */
				if ( empty( $data['roles'] ) ) {
					foreach ( $package_rates as $rate_key => $rate ) {
						$data['roles'][] = $rate->instance_id;
					}
				}

				if ( ! empty( $data['rules'] ) ) {
					$allowed_rates = array();
					foreach ( $data['rules'] as $rule ) {
						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}

						$available_methods = array();

						$temp_available_methods = $data['roles'];

						if ( ! empty( $rule['rule']['_shipping_zone_methods'] ) ) {
							foreach ( $rule['rule']['_shipping_zone_methods'] as $method ) {
								$available_methods[] = $method['value'];
							}
						}

						if ( isset( $rule['filter']['is_all_products'] ) && $rule['filter']['is_all_products'] ) {
							$temp_available_methods = array_intersect( $temp_available_methods, $available_methods );
							wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
						} else {
							if ( isset( $package['contents'] ) ) {
								foreach ( $package['contents'] as $item ) {
									if ( self::is_eligible_for_rule( $item['product_id'], $item['variation_id'], $rule['filter'] ) ) {
										$temp_available_methods = array_intersect( $temp_available_methods, $available_methods );
										wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
									} else {
										if ( apply_filters( 'wholesalex_shipping_rule_merged_all_methods', false ) ) {
											$temp_available_methods = array_merge( $temp_available_methods, $data['roles'] );
										} else {
											$temp_available_methods = array_diff( $data['roles'], $temp_available_methods );
										}
									}
								}
							}
						}

						// Now its time for user role
						foreach ( $package_rates as $rate_key => $rate ) {

							if ( in_array( $rate->instance_id, $temp_available_methods ) ) {
								$allowed_rates[ $rate_key ] = $package_rates[ $rate_key ];
							}
						}
					}

					if ( ! empty( $allowed_rates ) ) {
						return apply_filters( 'wholesalex_available_shipping_methods', $allowed_rates, $package_rates, $package, $data );
					}
				}

				$allowed_rates = array();

				foreach ( $package_rates as $rate_key => $rate ) {
					if ( in_array( $rate->instance_id, $data['roles'] ) ) {
						$allowed_rates[ $rate_key ] = $package_rates[ $rate_key ];
					}
				}

				return apply_filters( 'wholesalex_available_shipping_methods', $allowed_rates, $package_rates, $package, $data );
			},
			10,
			2
		);
	}

	/**
	 * Handle Payment Gateway
	 * Priority: User Profile >> Role > Dynamic Rule
	 * payment_order_qty
	 */
	public function handle_payment_gateways( $data ) {
		add_filter(
			'woocommerce_available_payment_gateways',
			function ( $gateways ) use ( $data ) {
				if ( ! is_array( $gateways ) ) {
					return $gateways;
				}
				$allowed_gateways = array();
				if ( ! empty( $data['profile'] ) ) {
					$allowed_profile_gateways = self::get_multiselect_values( $data['profile'] );

					foreach ( $gateways as $gateway_id => $gateway ) {
						if ( in_array( $gateway_id, $allowed_profile_gateways ) ) {
							$allowed_gateways[ $gateway_id ] = $gateway;
						}
					}

					if ( ! empty( $allowed_gateways ) ) {
						return $allowed_gateways;
					}
				}

				/**
				 * If Rolewise Payment methods is empty, then it means all payment methods will be available for this role
				 */

				if ( empty( $data['roles'] ) ) {
					$data['roles'] = array_keys( $gateways );
				}

				if ( ! empty( $data['rules'] ) && WC() && null != WC()->cart ) {
					$cart_contents = WC()->cart->cart_contents;

					$rule_allowed_gateways = $data['roles'];

					foreach ( $data['rules'] as $rule ) {
						$rule_gateway = isset( $rule['rule']['_payment_gateways'] ) ? self::get_multiselect_values( $rule['rule']['_payment_gateways'] ) : array();
						if ( ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) || empty( $rule_gateway ) ) {
							continue;
						}

						foreach ( $cart_contents as $item ) {
							$required_qty = isset( $rule['rule']['_order_quantity'] ) ? (int) $rule['rule']['_order_quantity'] : 999999999;
							if ( self::is_eligible_for_rule( $item['product_id'], $item['variation_id'], $rule['filter'] ) ) {
								$item_qty_in_cart = (int) wholesalex()->cart_count( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
								if ( $item_qty_in_cart < $required_qty ) {
									$rule_allowed_gateways = array_diff( $rule_allowed_gateways, $rule_gateway );
								}
							}
						}

						if ( empty( $rule_allowed_gateways ) ) {
							$rule_allowed_gateways = $data['roles'];
						}
					}

					foreach ( $gateways as $gateway_id => $gateway ) {
						if ( in_array( $gateway_id, $rule_allowed_gateways ) ) {
							$allowed_gateways[ $gateway_id ] = $gateway;
						}
					}

					return $allowed_gateways;
				}

				$allowed_gateways = array();

				foreach ( $gateways as $gateway_id => $gateway ) {
					if ( in_array( $gateway_id, $data['roles'] ) ) {
						$allowed_gateways[ $gateway_id ] = $gateway;
					}
				}

				return $allowed_gateways;
			}
		);
	}

	public function wholesalex_bogo_badge_add_custom_css() {
		if ( is_shop() || is_product() ) {
			$bogo_css = $this->bogo_badge_css(); // Call the method once and store the result
			if ( ! is_null( $bogo_css ) && is_string( $bogo_css ) && trim( $bogo_css ) !== '' ) {
				wp_add_inline_style( 'wholesalex', $bogo_css );
			}
		}
	}

	/**
	 * Badge CSS Style For Badge
	 *
	 * @return void
	 */
	public function bogo_badge_css() {
		if (isset($this->valid_dynamic_rules['buy_x_get_one'])) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_one'];
			$this->wholesalex_bogo_display_markup_css_generate( $bogo_badge_dynamic_rule );
		}
		if (isset($this->valid_dynamic_rules['buy_x_get_y'])) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_y'];
			$this->wholesalex_bogo_display_markup_css_generate( $bogo_badge_dynamic_rule );
		}
	}
	
	/**
	 * Generate Dynamic CSS For Badge
	 *
	 * @param [array] $bogo_badge_dynamic_rule
	 * @return void
	 */
	public function wholesalex_bogo_display_markup_css_generate( $bogo_badge_dynamic_rule ) {
		foreach ( $bogo_badge_dynamic_rule as $badge_dynamic_rule ) {
			$badge_roles 			= $badge_dynamic_rule['rule'];
			$badge_label_text_color = isset( $badge_roles['_product_badge_text_color'] ) ? $badge_roles['_product_badge_text_color'] : '';
			$badge_style 			= isset( $badge_roles['_product_badge_styles'] ) ? $badge_roles['_product_badge_styles'] : '';
			$badge_label_bg_color 	= isset( $badge_roles['_product_badge_bg_color'] ) ? $badge_roles['_product_badge_bg_color'] : '';
			$badge_position 		= isset( $badge_roles['_product_badge_position'] ) ? $badge_roles['_product_badge_position'] : 'right'; // Defaulting to 'right' if not set
	
			?>
			<style>
				<?php if ( $badge_style === 'style_one' || $badge_style === '' || empty( $badge_style )) { ?>
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?>::before {
						border-right: 15px solid <?php echo esc_attr( $badge_label_bg_color ); ?>;
					}
					.wholesalex-bogo-badge-style-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?>::after, .wholesalex-bogo-badge-style-single-product-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?>::after {
						background-color: <?php echo esc_attr( $badge_label_text_color ); ?>;
						content: '';
						position: absolute;
						display: block;
						top: calc(100% / 2 - 4px);
						width: 7px;
						height: 7px;
						border-radius: 10px;
						right: auto;
						left: 0px;
					}
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?> {
						right: <?php echo $badge_position == 'left' ? 'auto' : '0px'; ?>;
						left: <?php  echo $badge_position == 'left' ? '0px' : 'auto'; ?>;
						margin-left: <?php echo $badge_position == 'left' ? '14px' : '0'; ?>;
					}
				<?php } elseif ( $badge_style === 'style_two' ) { ?>
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?> {
						right: <?php echo $badge_position == 'left' ? 'auto' : '0px'; ?>;
						left: <?php  echo $badge_position == 'left' ? '0px' : 'auto'; ?>;
						border-radius: 4px;
					}
				<?php } elseif ( $badge_style === 'style_three' ) { ?>
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?> {
						right: <?php echo $badge_position == 'left' ? 'auto' : '0px'; ?>;
						left: <?php  echo $badge_position == 'left' ? '0px' : 'auto'; ?>;
						border-top-left-radius: 40px;
						border-bottom-left-radius: 5px;
					}
				<?php } elseif ( $badge_style === 'style_four' ) { ?>
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?> {
						right: <?php echo $badge_position == 'left' ? 'auto' : '0px'; ?>;
						left: <?php  echo $badge_position == 'left' ? '0px' : 'auto'; ?>;
						border-top-left-radius: 30px;
						border-bottom-right-radius: 30px;
					}
				<?php } elseif ( $badge_style === 'style_five' ) { ?>
					.wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?> {
						right: <?php echo $badge_position == 'left' ? 'auto' : '0px'; ?>;
						left: <?php  echo $badge_position == 'left' ? '0px' : 'auto'; ?>;
						border-top-right-radius: 30px;
						border-bottom-left-radius: 30px;
					}
					
				<?php } ?>
			</style>
			<?php
		}
	}
	
	

	/**
	 * Generate HTML Markup For Showing Badge
	 *
	 * @param [array] $bogo_badge_dynamic_rule
	 * @param [bool] $is_single
	 * @return void
	 */
	public function wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule, $is_single ) {
		global $product;
		if ( ! isset($product) && ! is_a($product, 'WC_Product') ) {
			return;
		}
		ob_start();
		foreach ( $bogo_badge_dynamic_rule as $badge_dynamic_rule ){
			$bogo_badge_type = $badge_dynamic_rule['rule'];
			$bogo_badge_filter = $badge_dynamic_rule['filter'];
			$enable_bogo_badge = $bogo_badge_type['_buy_x_get_product_badge_enable'];
			if ( $enable_bogo_badge == 'yes' ) {
				$badge_label = $bogo_badge_type['_product_badge_label'];
				$badge_label_bg_color = $bogo_badge_type['_product_badge_bg_color'];
				$badge_label_text_color = $bogo_badge_type['_product_badge_text_color'];
				if ( self::is_eligible_for_rule( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), $product->get_id(), $bogo_badge_filter ) ) {
					if ( isset( $badge_label ) ) : ?>
						<div class="wholesalex-bogo-badge<?php echo esc_attr( $is_single ) ? '-single-product' : '' ?>-container wholesalex-bogo-badge-<?php echo $is_single ? 'single' : 'shop' ?>">
							<div class="wholesalex-bogo-badge<?php echo esc_attr( $is_single ? '-single-product' : '' ); ?> wholesalex-bogo-badge-style-<?php echo esc_attr( $is_single ? 'single-product-' . $badge_dynamic_rule['id'] : $badge_dynamic_rule['id'] ); ?> wholesalex-bogo-badge-<?php echo esc_attr( $is_single ? '-product' : '' ); ?> wholesalex-bogo-badge-<?php echo esc_attr( $badge_dynamic_rule['id'] ); ?>" 
							style="background-color: <?php echo esc_attr( $badge_label_bg_color ); ?>; color: <?php echo esc_attr( $badge_label_text_color ); ?>;">
							<?php echo esc_html( $badge_label ); ?>
							</div>
						</div>
					<?php endif; ?>
				<?php			
				}
			}
		}
		return ob_get_clean();
	}

	/**
	 * Dynamic Bogo Badge For Shop Product
	 *
	 * @return void
	 */
	public function wholesalex_bogo_display_sale_badge() {
		if ( wholesalex()->get_setting( 'bogo_discount_bogo_badge_enable', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_one'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_one'] ) ) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_one'];
			echo $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule, false ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	
		}
		if ( wholesalex()->is_pro_active() && wholesalex()->get_setting( '_settings_show_bxgy_free_products_badge', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_y'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_y'] ) ) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_y'];
			echo $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule, false );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	/**
	 * Dynamic Bogo Badge For Shop Product -- WowStore Compatibility
	 *
	 * @return void
	 */
	public function wopb_wholesalex_bogo_display_sale_badge() {
		if ( wholesalex()->get_setting( 'bogo_discount_bogo_badge_enable', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_one'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_one'] ) ) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_one'];
			return $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule, false );
		}
		if ( wholesalex()->is_pro_active() && wholesalex()->get_setting( '_settings_show_bxgy_free_products_badge', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_y'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_y'] ) ) {
			$bogo_badge_dynamic_rule = $this->valid_dynamic_rules['buy_x_get_y'];
			return $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule, false );
		}
	}

	/**
	 * Dynamic Bogo Badge For Single Product
	 *
	 * @return void
	 */
	public function wholesalex_bogo_single_page_display_sale_badge() {
					   $localized_content = array();
		   if ( wholesalex()->get_setting( 'bogo_discount_bogo_badge_enable', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_one'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_one'] ) ) {
				$bogo_badge_dynamic_rule_get_one = $this->valid_dynamic_rules['buy_x_get_one'];
			   $localized_content['buy_x_get_one'] = $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule_get_one, true );
		   }
		   if ( wholesalex()->is_pro_active() && wholesalex()->get_setting( '_settings_show_bxgy_free_products_badge', 'yes' ) === 'yes' && isset( $this->valid_dynamic_rules['buy_x_get_y'] ) && !empty( $this->valid_dynamic_rules['buy_x_get_y'] ) ) {
				$bogo_badge_dynamic_rule_get_y = $this->valid_dynamic_rules['buy_x_get_y'];
			   	$localized_content['buy_x_get_y'] = $this->wholesalex_bogo_display_markup( $bogo_badge_dynamic_rule_get_y, true );
			}
		   wp_localize_script('wholesalex', 'wholesalex_bogo_single', array('content' => $localized_content));
	}

	/**
	 * Handle all Cart Related rules
	 */
	public function handle_cart( $rules ) {
		add_action(
			'woocommerce_cart_calculate_fees',
			function ( $cart ) use ( $rules ) {
				if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
					return;
				}

				$selected_gateway = WC()->session->get( 'chosen_payment_method' );

				$cart_fees = array();
				if ( isset( $rules['buy_x_get_one'] ) && ! empty( $rules['buy_x_get_one'] ) ) {
					$hash = array();
					foreach ( $rules['buy_x_get_one'] as $rule ) {

						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}

						if ( ! isset( WC()->cart ) || null === WC()->cart->get_cart() ) {
							return;
						}

						$min_qty    = isset( $rule['rule']['_minimum_purchase_count'] ) ? (int) $rule['rule']['_minimum_purchase_count'] : 99999999;
						$apply_once = isset( $rule['rule']['_per_cart_once'] ) && 'yes'===$rule['rule']['_per_cart_once'] ? true : false;

						$cart_item_count = 0;
						foreach ( WC()->cart->get_cart() as $cart_item ) {
							if ( self::is_eligible_for_rule( $cart_item['product_id'], $cart_item['variation_id'], $rule['filter'] ) ) {
								$cart_item_count = wholesalex()->cart_count( $cart_item['product_id'] );
								if ( $min_qty > 1 && $cart_item_count >= $min_qty ) {
									$free_quantity = (int) ( $cart_item_count / $min_qty ) * 1;
									if ( $apply_once ) {
										$free_quantity = 1;
									}
									$free_quantity = apply_filters( 'wholesalex_dr_buy_x_get_one_free_quantity', $free_quantity );

									$price = '';
									if ( $cart_item['data']->get_sale_price() ) {
										$price = $cart_item['data']->get_sale_price();
									} else {
										$price = $cart_item['data']->get_regular_price();
									}
									if ( 'incl' == $cart->get_tax_price_display_mode() ) {
										wc_get_price_excluding_tax(
											$cart_item['data'],
											array(
												'qty'   => $free_quantity,
												'price' => $price,
											)
										);
									}
									$smart_tags         = array(
										'{product_title}' => $cart_item['data']->get_title(),
										'{x}'             => $min_qty,
										'{y}'             => $free_quantity,
									);
									$bogo_discount_text = wholesalex()->get_setting( '_settings_bogo_discount_text', '{product_title} (BOGO Discounts)' );
									if ( $bogo_discount_text == '' ) {
										$bogo_discount_text = apply_filters( 'wholesalex_dynamic_rules_bogo_discount_text', $cart_item['data']->get_title() . __( '(BOGO Discounts)', 'wholesalex' ) );
									}
									foreach ( $smart_tags as $key => $value ) {
										$bogo_discount_text = str_replace( $key, $value, $bogo_discount_text );
									}

									$hash[ wp_unique_id( md5( serialize( $rule['filter'] ) ) ) ] = array(
										'discount' => $price,
										'name'     => $bogo_discount_text,
									);
								}
							}
						}
					}
					$cart_fees['buy_x_get_one'] = $hash;
				}
				if ( isset( $rules['cart_discount'] ) && ! empty( $rules['cart_discount'] ) ) {
					$hash = array();
					foreach ( $rules['cart_discount'] as $rule ) {
						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}
						$discount_amount = 0;
						$discount_name   = '';
						$is_all_products = $rule['filter']['is_all_products'];
						$discount_type   = $rule['rule']['_discount_type'];
						$hash_key        = md5( serialize( array( $rule['id'], $rule['filter'] ) ) );

						if ( $is_all_products ) {
							$total_value     = wholesalex()->get_cart_total();
							$discount_amount = ( 'percentage' == $discount_type ) ? ( $total_value * floatval( $rule['rule']['_discount_amount'] ) ) / 100 : floatval( $rule['rule']['_discount_amount'] );
							$discount_name   = apply_filters( 'wholesalex_cart_discount_title', isset( $rule['rule']['_discount_name'] ) ? $rule['rule']['_discount_name'] : __( 'Cart Discounts', 'wholesalex' ) );
							if ( isset( $hash[ $hash_key ] ) && is_array( $hash[ $hash_key ] ) ) {
								if ( $discount_amount >= $hash[ $hash_key ]['discount'] ) {
									$hash[ $hash_key ] = array(
										'discount' => $discount_amount,
										'name'     => $discount_name,
									);
									wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
								}
							} else {
								$hash[ $hash_key ] = array(
									'discount' => $discount_amount,
									'name'     => $discount_name,
								);
								wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
							}
						} else {
							if ( ! isset( WC()->cart ) || null === WC()->cart->get_cart() ) {
								return;
							}
							$rule_total_amount = 0;
							foreach ( WC()->cart->get_cart() as $cart_item ) {
								if ( self::is_eligible_for_rule( $cart_item['product_id'], $cart_item['variation_id'], $rule['filter'] ) ) {
									$rule_total_amount += $cart_item['line_total'];

									if ( apply_filters( 'wholesalex_dr_cart_discount_on_tax', false ) ) {
										$rule_total_amount += $cart_item['line_tax'];
									}
								}
							}

							$discount_amount = ( 'percentage' == $discount_type ) ? ( $rule_total_amount * floatval( $rule['rule']['_discount_amount'] ) ) / 100 : floatval( $rule['rule']['_discount_amount'] );
							$discount_name   = apply_filters( 'wholesalex_cart_discount_title', isset( $rule['rule']['_discount_name'] ) ? $rule['rule']['_discount_name'] : __( 'Cart Discounts', 'wholesalex' ) );
							if ( isset( $hash[ $hash_key ] ) && is_array( $hash[ $hash_key ] ) ) {
								if ( $discount_amount >= $hash[ $hash_key ]['discount'] ) {
									$hash[ $hash_key ] = array(
										'discount' => $discount_amount,
										'name'     => $discount_name,
									);
									wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
								}
							} else {
								$hash[ $hash_key ] = array(
									'discount' => $discount_amount,
									'name'     => $discount_name,
								);
								wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
							}
						}
					}
					$cart_fees['cart_discount'] = $hash;
				}

				if ( isset( $rules['payment_discount'] ) && ! empty( $rules['payment_discount'] ) ) {
					$hash = array();
					foreach ( $rules['payment_discount'] as $rule ) {

						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}

						$allowed_gateways = self::get_multiselect_values( $rule['rule']['_payment_gateways'] );
						if ( in_array( $selected_gateway, $allowed_gateways ) ) {

							$discount_amount = 0;
							$discount_name   = '';
							$is_all_products = $rule['filter']['is_all_products'];
							$discount_type   = $rule['rule']['_discount_type'];
							$hash_key        = md5( serialize( array( $rule['id'], $rule['filter'] ) ) );
							if ( $is_all_products ) {
								$total_value     = wholesalex()->get_cart_total();
								$discount_amount = ( 'percentage' == $discount_type ) ? ( $total_value * floatval( $rule['rule']['_discount_amount'] ) ) / 100 : floatval( $rule['rule']['_discount_amount'] );
								$discount_name   = apply_filters( 'wholesalex_payment_gateway_default_discount_name', isset( $rule['rule']['_discount_name'] ) ? $rule['rule']['_discount_name'] : __( 'Payment Discount!', 'wholesalex' ) );
								if ( isset( $hash[ $hash_key ] ) && is_array( $hash[ $hash_key ] ) ) {
									if ( $discount_amount >= $hash[ $hash_key ]['discount'] ) {
										$hash[ md5( serialize( $rule['filter'] ) ) ] = array(
											'discount' => $discount_amount,
											'name'     => $discount_name,
										);
										wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
									}
								} else {
									$hash[ $hash_key ] = array(
										'discount' => $discount_amount,
										'name'     => $discount_name,
									);
									wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
								}
							} else {
								if ( ! isset( WC()->cart ) || null === WC()->cart->get_cart() ) {
									return;
								}
								$rule_total_amount = 0;
								foreach ( WC()->cart->get_cart() as $cart_item ) {
									if ( self::is_eligible_for_rule( $cart_item['product_id'], $cart_item['variation_id'], $rule['filter'] ) ) {
										$rule_total_amount += $cart_item['line_total'];
										if ( apply_filters( 'wholesalex_dr_payment_discount_on_tax', false ) ) {
											$rule_total_amount += $cart_item['line_tax'];
										}
									}
								}
								$discount_amount = ( 'percentage' == $discount_type ) ? ( $rule_total_amount * floatval( $rule['rule']['_discount_amount'] ) ) / 100 : floatval( $rule['rule']['_discount_amount'] );

								$discount_name = apply_filters( 'wholesalex_payment_gateway_default_discount_name', isset( $rule['rule']['_discount_name'] ) ? $rule['rule']['_discount_name'] : __( 'Payment Discount!', 'wholesalex' ) );
								if ( isset( $hash[ $hash_key ] ) && is_array( $hash[ $hash_key ] ) ) {
									if ( $discount_amount >= $hash[ $hash_key ]['discount'] ) {
										$hash[ $hash_key ] = array(
											'discount' => $discount_amount,
											'name'     => $discount_name,
										);
										wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
									}
								} else {
									$hash[ $hash_key ] = array(
										'discount' => $discount_amount,
										'name'     => $discount_name,
									);
									wholesalex()->set_usages_dynamic_rule_id( $rule['id'] );
								}
							}
						}
					}
					$cart_fees['payment_discount'] = $hash;
				}
				// Pro

				$cart_fees = apply_filters( 'wholesalex_dr_cart_fees', $cart_fees, $rules, $selected_gateway );

				if ( ! empty( $cart_fees ) ) {
					$coupon_names = array();
					foreach ( $cart_fees as $fees ) {
						foreach ( $fees as $discount ) {
							if ( isset( $discount['discount'] ) && 0 != $discount['discount'] ) {
								$__is_taxable = apply_filters( 'wholesalex_payment_gateway_discount_is_taxable', false );

								if ( ! isset( $coupon_names[ $discount['name'] ] ) ) {
									$coupon_names[ $discount['name'] ] = true;
								} else {
									$discount['name']                  = wp_unique_id( $discount['name'] );
									$coupon_names[ $discount['name'] ] = true;
								}
								$cart->add_fee( $discount['name'], -1 * floatval( $discount['discount'] ), $__is_taxable );
							} elseif ( isset( $discount['charge'] ) && 0 != $discount['charge'] ) {
								$__is_taxable = apply_filters( 'wholesalex_extra_charge_is_taxable', true );

								if ( ! isset( $coupon_names[ $discount['name'] ] ) ) {
									$coupon_names[ $discount['name'] ] = true;
								} else {
									$discount['name']                  = wp_unique_id( $discount['name'] );
									$coupon_names[ $discount['name'] ] = true;
								}
								$cart->add_fee( $discount['name'], floatval( $discount['charge'] ), $__is_taxable );
							}
						}
					}
					if ( isset( $rules['payment_discount'] ) && ! empty( $rules['payment_discount'] ) || isset( $rules['extra_charge'] ) && ! empty( $rules['extra_charge'] ) ) {
						add_action(
							'woocommerce_review_order_before_payment',
							function () {
								// Only on checkout page.
								if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) :
									?>
							<script type="text/javascript">
								jQuery(function($) {
									// On payment method change.
									$('form.woocommerce-checkout').on('change', 'input[name="payment_method"]', function() {
										// Refresh checkout.
										$('body').trigger('update_checkout');
									});
								})
							</script>
									<?php
								endif;
							}
						);
					}
				}
			}
		);
	}

	/**
	 * Handle Min Max Product Quantity
	 */
	public function min_max_order_quantity( $data ) {
		add_filter(
			'woocommerce_quantity_input_args',
			function ( $args, $product ) use ( $data ) {

				if ( ! ( $product->is_type( 'simple' ) || $product->is_type( 'variation' ) ) ) {
					return $args;
				}

				if ( ! empty( $data['min_order_qty'] ) ) {
					foreach ( $data['min_order_qty'] as $rule ) {
						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}

						if ( self::is_eligible_for_rule( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), $product->get_id(), $rule['filter'] ) ) {
							if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) { 
								$args['min_value'] = $rule['rule']['_min_order_qty'];
							}
							if ( isset( $rule['rule']['step'] ) && $rule['rule']['step'] ) {
								$args['step'] = $rule['rule']['step'];
							}
						}
					}
				}

				$args = apply_filters( 'wholesalex_dr_min_max_qty_input_args', $args, $data, $product );

				return $args;
			},
			10,
			2
		);

		add_filter(
			'woocommerce_loop_add_to_cart_args',
			function ( $args, $product ) use ( $data ) {
				if ( ! $product->is_type( 'simple' ) ) {
					return $args;
				}

				foreach ( $data['min_order_qty'] as $rule ) {
					if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
						continue;
					}
					if ( self::is_eligible_for_rule( $product->get_id(), $product->get_id(), $rule['filter'] ) ) {
						if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) {
							$args['quantity'] = $rule['rule']['_min_order_qty'];
						}
					}
				}

				return $args;
			},
			10,
			2
		);

		add_filter(
			'woocommerce_add_to_cart_validation',
			function ( $status, $product_id, $quantity ) use ( $data ) {
				$variation_id = 0;
				$product      = wc_get_product( $product_id );
				$variation    = false;
				if ( ! $product->is_type( 'simple' ) ) {
					$args         = func_get_args();
					$variation_id = isset( $args[3] ) ? $args[3] : 0;
					if ( $variation_id ) {
						$variation = wc_get_product( $variation_id );
					}
				}

				$min_qty_data = array();
				$min          = '';
				foreach ( $data['min_order_qty'] as $rule ) {
					if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
						continue;
					}
					if ( self::is_eligible_for_rule( $product_id, $variation_id, $rule['filter'] ) ) {
						if ( $variation_id ) {
							$existing_qty = wholesalex()->cart_count( $variation_id );
							$new_qty      = $existing_qty + $quantity;
							if ( $new_qty < $rule['rule']['_min_order_qty'] ) {
								if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) {
									$status = false;
								}
								if ( ! $min ) {
									$min = $rule['rule']['_min_order_qty'];
								}
								$min = min( $rule['rule']['_min_order_qty'], $min );
							}
						}

						if ( $status ) {
							$existing_qty = wholesalex()->cart_count( $product_id );
							$new_qty      = $existing_qty + $quantity;
							if ( $new_qty < $rule['rule']['_min_order_qty'] ) {
								if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) {
									$status = false;
								}
								if ( ! $min ) {
									$min = $rule['rule']['_min_order_qty'];
								}
								$min = min( $rule['rule']['_min_order_qty'], $min );
							}
						}
					}
				}
				if ( ! $status ) {
					wc_add_notice(
						apply_filters(
							'wholesalex_dynamic_rules_min_quantity_error_message',
							$this->restore_smart_tags(
								array(
									'{minimum_qty}'   => $min,
									'{product_title}' => $variation_id ? $variation->get_name() : $product->get_name(),
								),
								wholesalex()->get_setting( 'only_minimum_order_qty_promo_text', __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ) )
							)
						),
						'error'
					);
					return $status;
				}
				$status = apply_filters( 'wholesalex_dr_min_max_add_to_cart_validation', $status, $data, $product_id, $variation_id, $quantity, $product, $variation );

				return $status;
			},
			10,
			5
		);

		add_filter(
			'woocommerce_update_cart_validation',
			function ( $status, $cart_item_key, $values, $quantity ) use ( $data ) {
				$product_id   = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];
				$variation_id = $values['variation_id'];
				$product      = wc_get_product( $product_id );

				$min_qty_data = array();
				$min_data     = array(
					'variation' => '',
					'product'   => '',
				);
				foreach ( $data['min_order_qty'] as $rule ) {
					if ( isset( $rule['conditions']['tiers'] ) && ! empty( $rule['conditions']['tiers'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
						continue;
					}
					if ( self::is_eligible_for_rule( $product_id, $variation_id, $rule['filter'] ) ) {

						if ( $variation_id ) {
							if ( ! $min_data['variation'] ) {
								if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) {
									$min_data['variation'] = $rule['rule']['_min_order_qty'];
								}
							}
							$min_data['variation'] = min( $rule['rule']['_min_order_qty'], $min_data['variation'] );
						}

						if ( ! $min_data['variation'] ) {
							if ( ! $min_data['product'] ) {
								if ( isset($rule['rule']['_min_order_qty_disable']) && $rule['rule']['_min_order_qty_disable'] == 'no' ) {
									$min_data['product'] = $rule['rule']['_min_order_qty'];
								}
							}
							$min_data['product'] = min( $rule['rule']['_min_order_qty'], $min_data['product'] );
						}
					}
				}
				$min = $min_data['variation'] ? $min_data['variation'] : $min_data['product'];
				if ( $min && $quantity < $min ) {
					$status = false;
				}

				if ( ! $status ) {
					/* translators: 1: minimum quantity, 2: product name */

					wc_add_notice(
						apply_filters(
							'wholesalex_dynamic_rules_min_quantity_error_message',
							$this->restore_smart_tags(
								array(
									'{minimum_qty}'   => $min,
									'{product_title}' => $product->get_title(),
								),
								wholesalex()->get_setting( 'only_minimum_order_qty_promo_text', __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ) )
							)
						),
						'error'
					);
					return $status;
				}

				$status = apply_filters( 'wholesalex_dr_min_max_update_cart_validation', $status, $data, $product_id, $variation_id, $quantity, $product );

				return $status;
			},
			10,
			4
		);

		add_action(
			'woocommerce_check_cart_items',
			function () use ( $data ) {
				if ( ! ( isset( WC()->cart ) && ! empty( WC()->cart ) ) ) {
					return;
				}
				foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
					if ( isset( $cart_item['free_product'] ) && $cart_item['free_product']  ){
						continue;
					}
					$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
					$product       = wc_get_product( $product_id );
					$product_title = $product->get_name();
					$status        = true;

					$min_data = array(
						'variation' => '',
						'product'   => '',
					);
					foreach ( $data['min_order_qty'] as $rule ) {
						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}
						$min = $rule['rule']['_min_order_qty'];
						if ( self::is_eligible_for_rule( $cart_item['variation_id'] ? 0 : $cart_item['product_id'], $cart_item['variation_id'], $rule['filter'] ) ) {
							if ( ! $min_data['variation'] ) {
								$min_data['variation'] = $rule['rule']['_min_order_qty'];
							}
							$min_data['variation'] = min( $rule['rule']['_min_order_qty'], $min_data['variation'] );
						}
						if ( self::is_eligible_for_rule( $cart_item['product_id'], 0, $rule['filter'] ) && ! $min_data['variation'] ) {

							if ( ! $min_data['product'] ) {
								$min_data['product'] = $rule['rule']['_min_order_qty'];
							}
							$min_data['product'] = min( $rule['rule']['_min_order_qty'], $min_data['product'] );
						}
					}

					$min = $min_data['variation'] ? $min_data['variation'] : $min_data['product'];
					if ( $min && $cart_item['quantity'] < $min ) {
						$status = false;
						wc_add_notice(
							apply_filters(
								'wholesalex_dynamic_rules_min_quantity_error_message',
								$this->restore_smart_tags(
									array(
										'{minimum_qty}'   => $min,
										'{product_title}' => $product_title,
									),
									wholesalex()->get_setting( 'only_minimum_order_qty_promo_text', __( 'You have to add minimun {minimum_qty} quantity', 'wholesalex' ) )
								)
							),
							'error'
						);
					}

					do_action( 'wholesalex_dr_min_max_check_cart_items', $status, $data, $cart_item, $product_title );
				}
			}
		);

		add_action(
			'woocommerce_store_api_product_quantity_minimum',
			function ( $value, $product ) use ( $data ) {
				if ( ! empty( $data['min_order_qty'] ) ) {
					foreach ( $data['min_order_qty'] as $rule ) {
						if ( isset( $rule['conditions'] ) && ! self::check_rule_conditions( $rule['conditions'] ) ) {
							continue;
						}
						if ( self::is_eligible_for_rule( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), $product->get_id(), $rule['filter'] ) ) {
							$value = $rule['rule']['_min_order_qty'];
						}
					}
				}
				return $value;
			},
			10,
			2
		);
	}

	/**
	 * Handle Discounts
	 *
	 * @param array $data Discount Related Data.
	 * @return void
	 */
	public function handle_discounts( $data ) {		
		$global_show_tier_table = wholesalex()->get_setting( '_settings_show_table', 'yes' );
		
			if ( 'yes' === $global_show_tier_table ) {
				$tier_position = 'yes' === wholesalex()->get_setting( '_settings_tier_position' ) ? 'before' : 'after';
				add_action( 'woocommerce_' . $tier_position . '_add_to_cart_form', array( $this, 'wholesalex_product_price_table' ) );
				add_filter( 'woocommerce_available_variation', array( $this, 'wholesalex_product_variation_price_table' ), 10, 3 );
			}

		add_filter('woocommerce_product_get_price',
			function ( $price, $product ) use ( $data ) {

				$product_id    = $product->get_id();
				$sale_price    = floatval( $this->calculate_sale_price( '', $product, $data ) );
				$regular_price = floatval( $this->calculate_regular_price( $price, $product, $data ) );

				$orginal_base_price = floatval( get_post_meta( $product->get_id(), '_regular_price', true ) );
				$orginal_sale_price = floatval( get_post_meta( $product->get_id(), '_sale_price', true ) );

				$to_be_display_price = $sale_price && 0!= $sale_price? $sale_price : $regular_price;

				if ( apply_filters( 'wholesalex_compatibility_with_extra_options_plugin', false ) ) {
					// Check Price is modified by anyone rather than wholesalex.
					$to_be_displayed_orginal_price = $orginal_sale_price ? $orginal_sale_price : $orginal_base_price;
					$option_price                  = 0;

					if ( $to_be_displayed_orginal_price != $price ) { // That means, someone modify the price rather than wholesalex.

						if ( wholesalex()->get_wholesalex_wholesale_prices($product_id) ) {
							$option_price = abs(  wholesalex()->get_wholesalex_wholesale_prices($product_id) - $price );
						} elseif ( wholesalex()->get_wholesalex_regular_prices($product_id) ) {
							$option_price = abs(  wholesalex()->get_wholesalex_regular_prices($product_id) - $price );
						}
					}
					$to_be_display_price = $to_be_display_price + $option_price;
				}
				return $to_be_display_price;
			},
			9,
			2
		);

		add_filter(
			'woocommerce_product_get_regular_price',
			function ( $regular_price, $product ) use ( $data ) {
				$regular_price = $this->calculate_regular_price( $regular_price, $product, $data );
				return ( !empty($regular_price) ) ? (float)$regular_price : $regular_price;
			},
			9,
			2
		);

		add_filter(
			'woocommerce_product_variation_get_regular_price',
			function ( $regular_price, $product ) use ( $data ) {
				$regular_price = $this->calculate_regular_price( $regular_price, $product, $data );
				return ( !empty($regular_price) ) ? (float)$regular_price : $regular_price;
			},
			9,
			2
		);
		add_filter(
			'woocommerce_variation_prices_regular_price',
			function ( $regular_price, $product ) use ( $data ) {
				$regular_price = $this->calculate_regular_price( $regular_price, $product, $data );
				return ( !empty($regular_price) ) ? (float)$regular_price : $regular_price;
			},
			9,
			2
		);

		add_filter(
			'woocommerce_product_get_sale_price',
			function ( $sale_price, $product ) use ( $data ) {
				$sale_price = $this->calculate_sale_price( $sale_price, $product, $data );
				return ( !empty($sale_price) ) ? (float)$sale_price : $sale_price;
			},
			9,
			2
		);

		add_filter(
			'woocommerce_product_variation_get_sale_price',
			function ( $sale_price, $product ) use ( $data ) {
				$sale_price = $this->calculate_sale_price( $sale_price, $product, $data );
				return ( !empty($sale_price) ) ? (float)$sale_price : $sale_price;
			},
			9,
			2
		);
		add_filter(
			'woocommerce_variation_prices_sale_price',
			function ( $sale_price, $product ) use ( $data ) {
				$sale_price = $this->calculate_sale_price( $sale_price, $product, $data );
				return ( !empty($sale_price) ) ? (float)$sale_price : $sale_price;
			},
			9,
			2
		);
		add_filter(
			'woocommerce_variation_prices_price',
			function ( $price, $product ) use ( $data ) {
				$product_id    			= $product->get_id();
				$sale_price    			= floatval( $this->calculate_sale_price( '', $product, $data ) );
				$regular_price 			= floatval( $this->calculate_regular_price( $price, $product, $data ) );
				$to_be_display_price 	= $sale_price ? $sale_price : $regular_price;
				return ( !empty($to_be_display_price) ) ? (float)$to_be_display_price : $to_be_display_price;
			},
			9,
			2
		);

		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_price_hash' ), 9, 2 );

		add_filter(
			'woocommerce_get_price_html',
			function ( $price_html, $product ) {
				
				if ( ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) ||  ! ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) ) {
					return $price_html;
				}

				do_action( 'wholesalex_dynamic_rule_get_price_html' );

				// Actions when user is logged out
				if ( ! is_user_logged_in() ) {
					// Login to view price on product listing page
					$lvp_pl = wholesalex()->get_setting( '_settings_login_to_view_price_product_list' );

					// Login to view price on product single page
					$lvp_sp = wholesalex()->get_setting( '_settings_login_to_view_price_product_page' );

					if ( ( is_product() && 'yes' == $lvp_sp ) || ( ! is_product() && 'yes' == $lvp_pl ) ) {

						$lvp_url = wholesalex()->get_setting( '_settings_login_to_view_price_login_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
						$lvp_url = esc_url(add_query_arg('redirect', isset($_SERVER['REQUEST_URI']) ? esc_url($_SERVER['REQUEST_URI']) : '', $lvp_url)); //phpcs:ignore

						$this->make_product_non_purchasable_and_remove_add_to_cart( $product );
						$price_html = '<div><a href="' . $lvp_url . '">' . esc_html( wholesalex()->get_language_n_text( '_language_login_to_see_prices', __( 'Login to see prices', 'wholesalex' ) ) ) . '</a></div>';

						return $price_html;
					}
				}

				$rp = $product->get_regular_price();  
				if($rp) {
					$rp = wc_get_price_to_display( $product, array( 'price' => $rp ) );
				}
				$sp = $product->get_sale_price();  
				if($sp) {
					$sp = wc_get_price_to_display( $product, array( 'price' => $sp) );
				}

				if ( ! ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) ) {
					$is_wholesale_price_applied = wholesalex()->get_wholesalex_wholesale_prices($product->get_id()) ? true : false;
					$price_html                 = $this->format_sale_price( $rp, $sp, $is_wholesale_price_applied ) . $product->get_price_suffix();
					
					if ( is_shop() || is_product_category() ) {
						$__product_list_page_price = wholesalex()->get_setting( '_settings_price_product_list_page', 'pricing_range' );
						$__product_list_page_price = isset( $__product_list_page_price ) ? $__product_list_page_price : '';
			
						$__min_sale_price = $sp;
						$__max_sale_price = $rp;
			
						switch ( $__product_list_page_price ) {
							case 'pricing_range':
								if ( $__min_sale_price === $__max_sale_price ) {
									$__max_sale_price = $rp;
								}
								$__sp = wc_format_price_range( $__min_sale_price, $__max_sale_price );
								$__sp = ( $rp != $__sp ) ? $__sp = $__min_sale_price : $__sp;
								$price_html = $this->format_sale_price( $rp, $__sp, $is_wholesale_price_applied ) . $product->get_price_suffix();
								break;
							case 'minimum_pricing':
								$price_html = $this->format_sale_price( $rp, $__min_sale_price, $is_wholesale_price_applied ) . $product->get_price_suffix();
								break;
							case 'maximum_pricing':
								$price_html = $this->format_sale_price( $rp, $__max_sale_price, $is_wholesale_price_applied ) . $product->get_price_suffix();
								break;
							default:
							$price_html  = $this->format_sale_price( $rp, $sp, $is_wholesale_price_applied ) . $product->get_price_suffix();								break;
						}
					}
				}

				if ( $product->is_type( 'variable' ) ) {
					$variations_ids             = $product->get_children();
					$variation_sale_prices      = array();
					$variation_regular_prices   = array();
					$is_wholesale_price_applied = true;
				
					foreach ( $variations_ids as $variation_id ) {
						$variation_obj = wc_get_product($variation_id);
						$regular_price = $variation_obj->get_regular_price();
						if ( !empty( $regular_price ) ) {
							$variation_regular_prices[] = $regular_price;
							$is_wholesale_price_applied &= wholesalex()->get_wholesalex_wholesale_prices( $variation_id ) ? true : false;
							$sale_price = $variation_obj->get_sale_price();
							if ( !empty( $sale_price ) ) {
								$variation_sale_prices[] = $sale_price;
							}
						}
					}
				
					// If variations exist and wholesale price is applied
					if ( !empty( $variations_ids ) && $is_wholesale_price_applied && !empty( $variation_sale_prices ) ) {
						$min_sp = min( $variation_sale_prices );
						$max_sp = max( $variation_sale_prices );
						$min_rp = min( $variation_regular_prices );
						$max_rp = max( $variation_regular_prices );
						if ( $min_sp !== "" && $max_sp !== "" && $min_rp !== "" && $max_rp !== "" ) {
							$min_sp = wc_get_price_to_display( $product, array( 'price' => $min_sp ) );
							$max_sp = wc_get_price_to_display( $product, array( 'price' => $max_sp ) );
							$min_rp = wc_get_price_to_display( $product, array( 'price' => $min_rp ) );
							$max_rp = wc_get_price_to_display( $product, array( 'price' => $max_rp ) );
						}
						$sp = ( $min_sp != $max_sp ) ? wc_format_price_range( $min_sp, $max_sp ) : $min_sp;
						$rp = ( $min_rp != $max_rp ) ? wc_format_price_range( $min_rp, $max_rp ) : $min_rp;
						$price_html = $this->format_sale_price($rp, $sp, $is_wholesale_price_applied) . $product->get_price_suffix();
					}
				}
				return apply_filters( 'wholesalex_get_price_html', $price_html, $product );
			},
			9,
			2
		);

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_price' ) );
		
	}

	public function update_cart_price( $cart ) {
		if ( ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) || ! is_object( $cart ) || did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product->is_type( 'simple' ) ) {
				$price = $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price();
				$product->set_price( max( 0, $this->price_after_currency_changed( $price ) ) );
			}
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $item
	 * @return void
	 */
	public function filter_empty_items( $item ) {
		foreach ( $item as $key => $value ) {
			if ( empty( $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $data
	 * @param string $type
	 * @return array
	 */
	public static function get_multiselect_values( $data, $type = 'value' ) {
		$allowed_methods = array();
		foreach ( $data as $method ) {
			if ( $type == 'name' ) {
				$allowed_methods[] = $method['name'];
			} else {
				$allowed_methods[] = $method['value'];
			}
		}
		return $allowed_methods;
	}



	/**
	 * Check is given product is eligible for discount
	 *
	 * @param int|string $product_id
	 * @param int|string $variation_id
	 * @param array      $filter
	 * @return boolean
	 */
	public static function is_eligible_for_rule( $product_id, $variation_id, $filter ) {
		$cats   = wc_get_product_term_ids( $product_id, 'product_cat' );
		$status = false;

		if ( isset( $filter['is_all_products'] ) && $filter['is_all_products'] ) {
			return true;
		}

		if ( ! empty( $filter['include_variations'] ) && in_array( $variation_id, $filter['include_variations'] ) ) {
			$status = true;
		} elseif ( ! empty( $filter['exclude_variations'] ) && ! in_array( $variation_id, $filter['exclude_variations'] ) ) {
			$status = true;
		} elseif ( ! empty( $filter['include_products'] ) && in_array( $product_id, $filter['include_products'] ) ) {
			$status = true;
		} elseif ( ! empty( $filter['exclude_products'] ) && ! in_array( $product_id, $filter['exclude_products'] ) ) {
			$status = true;
		} elseif ( ! empty( $filter['include_cats'] ) && ! empty( array_intersect( $cats, $filter['include_cats'] ) ) ) {
			$status = true;
		} elseif ( ! empty( $filter['exclude_cats'] ) && empty( array_intersect( $cats, $filter['exclude_cats'] ) ) ) {
			$status = true;
		}

		return $status;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function action_after_woo_block_loaded() {
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'wholesalex-payment-discount',
				'callback'  => function ( $data ) {
					if ( isset( $data['selected_gateway'] ) ) {
						$selected_gateway = $data['selected_gateway'];
						WC()->session->set( 'chosen_payment_method', $selected_gateway );
					}
				},
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function load_woo_checkout_block_script() {
		wp_enqueue_script( 'wholesalex_gateway_discounts', WHOLESALEX_URL . 'assets/js/whx_gateway_discounts.js', array( 'jquery' ), WHOLESALEX_VER, true );
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $conditions
	 * @return void
	 */
	public static function check_rule_conditions( $conditions ) {
		if ( isset( $conditions, $conditions['tiers'] ) ) {
			if ( ! ( method_exists( self::class, 'is_conditions_fullfiled' ) && self::is_conditions_fullfiled( $conditions['tiers'] ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param array  $tiers
	 * @param string $base_price
	 * @param string $cart_qty
	 * @return void
	 */
	public function apply_individual_tier( $tiers = array(), $base_price = '', $cart_qty = '' ) {
		$res = array(
			'id'    => false,
			'price' => false,
		);
		foreach ( $tiers as $tier ) {
			if(!isset($tier['_discount_type'],$tier['_discount_amount'],$tier['_min_quantity'])) {
				continue;
			}
			
			if ( $cart_qty >= $tier['_min_quantity'] ) {
				$res['price'] = wholesalex()->calculate_sale_price( $tier, $base_price );
				$res['id']    = isset( $tier['_id'] ) ? $tier['_id'] : ( isset( $tier['id'] ) ? $tier['id'] : '' );
			}
		}
		return $res;
	}


	/**
	 * Undocumented function
	 *
	 * @param array  $tiers
	 * @param string $base_price
	 * @param string $cart_qty
	 * @return void
	 */
	public function calculate_tier_pricing( $tiers = array(), $base_price = '', $cart_qty = '' ) {
		$res = false;
		if ( ! empty( $tiers ) ) {
			array_multisort( array_column( $tiers, '_min_quantity' ), SORT_ASC, $tiers );
			$res = $this->apply_individual_tier( $tiers, $base_price, $cart_qty );
		}
		return $res;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type]  $priority
	 * @param [type]  $data
	 * @param [type]  $product_id
	 * @param [type]  $parent_id
	 * @param [type]  $base_price
	 * @param [type]  $cart_qty
	 * @param boolean $first_tier
	 * @return void
	 */
	public function get_priority_wise_tier_price( $priority, $data, $product_id, $parent_id, $base_price, $cart_qty, $first_tier = false ) {
		$tier_res    = array(
			'src'   => false,
			'price' => false,
			'tiers' => array(),
		);
		
		$active_tier = array();
		switch ( $priority ) {
			case 'single_product':
				
				$single_product_tier = get_post_meta( $product_id, $data['role_id'] . '_tiers', true );				
				if ( is_array( $single_product_tier ) && ! empty( $single_product_tier ) ) {
					$active_tier = $single_product_tier;
					$res         = $this->calculate_tier_pricing( $single_product_tier, $base_price, $cart_qty );

					if ( $res ) {
						$tier_res = array(
							'src'   => 'single_product_tier',
							'price' => $res['price'],
							'tiers' => $single_product_tier,
							'id'    => $res['id'],
						);
					}
				}

				break;
			case 'dynamic_rule':
				$dynamic_rule_tiers = array();
				if ( ! empty( $data['quantity_based'] ) ) {
					foreach ( $data['quantity_based'] as $qbd ) {
						if ( isset( $qbd['conditions'] ) && ! self::check_rule_conditions( $qbd['conditions'] ) ) {
							continue;
						}
						if ( self::is_eligible_for_rule( $parent_id ? $parent_id : $product_id, $product_id, $qbd['filter'] ) ) {
							$dynamic_rule_tiers = array_merge( $dynamic_rule_tiers, $qbd['rule']['tiers'] );
						}
					}
				}
				$active_tier = $dynamic_rule_tiers;

				$res = $this->calculate_tier_pricing( $dynamic_rule_tiers, $base_price, $cart_qty );

				if ( $res ) {
					$tier_res = array(
						'src'   => 'quantity_based_tier',
						'price' => $res['price'],
						'tiers' => $dynamic_rule_tiers,
						'id'    => $res['id'],
					);
				}

				break;
			case 'profile':
				if ( isset( $data['user_profile'] ) ) {
					$user_profile_tiers = array();
					foreach ( $data['user_profile_filter_map'] as $key => $upf ) {
						if ( self::is_eligible_for_rule( $parent_id ? $parent_id : $product_id, $product_id, $upf ) ) {
							$user_profile_tiers = array_merge( $user_profile_tiers, $data['user_profile'][ $key ] );
						}
					}
					$active_tier = $user_profile_tiers;

					$res = $this->calculate_tier_pricing( $user_profile_tiers, $base_price, $cart_qty );

					if ( $res ) {
						$tier_res = array(
							'src'   => 'user_profile_tier',
							'price' => $res['price'],
							'tiers' => $user_profile_tiers,
							'id'    => $res['id'],
						);
					}
				}
				break;
			case 'category':
				$cat_ids   = wc_get_product_term_ids( $parent_id ? $parent_id : $product_id, 'product_cat' );

				$cat_ids = array_reverse($cat_ids);
				
				$cat_tiers = array();
				$cart_qty  = 0;
				foreach ( $cat_ids as $cat_id ) {
					$cat_tier = get_term_meta( $cat_id, $data['role_id'] . '_tiers', true );
					if ( ! empty( $cat_tier ) && is_array( $cat_tier ) ) {
						$cat_tiers = array_merge( $cat_tiers, $cat_tier );
						$cart_qty+=intval(wholesalex()->category_cart_count($cat_id));
					}
				}
				
				$active_tier = $cat_tiers;

				$res = $this->calculate_tier_pricing( $cat_tiers, $base_price, $cart_qty );
				if ( $res ) {
					$tier_res = array(
						'src'   => 'category_tier',
						'price' => $res['price'],
						'tiers' => $cat_tiers,
						'id'    => $res['id'],
					);
				}
				break;

			default:
				// code...
				break;
		}

		if ( ! $tier_res['price'] && $first_tier ) {
			$tier_res['tiers'] = $active_tier;
		}
		
		return $tier_res;
	}


	/**
	 * Calculate Regular Price
	 *
	 * @param float  $regular_price Regular Price.
	 * @param object $product Product.
	 * @param array  $data Data.
	 * @return floatval
	 */
	public function calculate_regular_price( $regular_price, $product, $data ) {
		// Check Rolewise base price.
		if ( isset( $data['role_id'] ) && ! empty( $data['role_id'] ) && $data['eligible'] ) {
			$rrp = get_post_meta( $product->get_id(), $data['role_id'] . '_base_price', true );

			$regular_price = $rrp ? floatval( $rrp ) : $regular_price;

			if ( $rrp ) {
				wholesalex()->set_wholesalex_regular_prices($product->get_id(),$rrp);
			}
		}
		return $regular_price;
	}

	/**
	 * Calculate Sale Price
	 *
	 * @param float  $sale_price Sale Price.
	 * @param object $product Product.
	 * @param array  $data Data.
	 * @return floatval
	 */
	public function calculate_sale_price( $sale_price, $product, $data ) {
		$parent_id  = $product->get_parent_id();
		$product_id = $product->get_id();
		// Product Base Price for further calculation.
		$base_price = $product->get_regular_price();

		$base_price = $this->price_after_currency_changed( $base_price );

		$previous_sp = $sale_price;

        $used_rule_id = '';

		if ( $data['eligible'] ) {
			// Priorities.
			$priority         = wholesalex()->get_quantity_based_discount_priorities();
			$flipped_priority = array_flip( wholesalex()->get_quantity_based_discount_priorities() );
            if(isset( $data['role_id'] ) && ! empty( $data['role_id'] )) {
                $rrs          = get_post_meta( $product_id, $data['role_id'] . '_sale_price', true );
            } else {
                $rrs          = false;
            }

			$applied_discount_src = '';

			if ( $flipped_priority['dynamic_rule'] < $flipped_priority['single_product'] ) {

				if ( ! empty( $data['product_discount'] ) ) {

					foreach ( $data['product_discount'] as $pd ) {

						if ( self::is_eligible_for_rule( $parent_id ? $parent_id : $product_id, $product_id, $pd['filter'] ) ) {
							if ( ! empty( $pd['conditions']['tiers'] ) ) {

								wholesalex()->set_rule_data(
									$pd['id'],
									$product_id,
									'product_discount',
									array(
										'value'        => $pd['rule']['_discount_amount'],
										'type'         => $pd['rule']['_discount_type'],
										'conditions'   => $pd['conditions'],
										'who_priority' => $pd['who_priority'],
										'applied_on_priority' => $pd['applied_on_priority'],
										'end_date'     => $pd['end_date'],
									)
								);
							}
							if ( isset( $pd['conditions'] ) && ! self::check_rule_conditions( $pd['conditions'] ) ) {
								continue;
							}
                            $used_rule_id = $pd['id'];
							$sale_price           = wholesalex()->calculate_sale_price( $pd['rule'], $base_price );
							
							$applied_discount_src = 'product_discount';
						}
					}
				}

				if ( '' == $applied_discount_src && $rrs ) {
					// Single Product Rolewise.
					$sale_price = floatval( $rrs );					
				}
			} else {
				if ( ! $rrs && ! empty( $data['product_discount'] ) ) {
					foreach ( $data['product_discount'] as $pd ) {

						if ( self::is_eligible_for_rule( $parent_id ? $parent_id : $product_id, $product_id, $pd['filter'] ) ) {
							if ( ! empty( $pd['conditions']['tiers'] ) ) {
								wholesalex()->set_rule_data(
									$pd['id'],
									$product_id,
									'product_discount',
									array(
										'value'        => $pd['rule']['_discount_amount'],
										'type'         => $pd['rule']['_discount_type'],
										'conditions'   => $pd['conditions'],
										'who_priority' => $pd['who_priority'],
										'applied_on_priority' => $pd['applied_on_priority'],
										'end_date'     => $pd['end_date'],
									)
								);
							}
							if ( isset( $pd['conditions'] ) && ! self::check_rule_conditions( $pd['conditions'] ) ) {
								continue;
							}
                            $used_rule_id = $pd['id'];
							$sale_price           = wholesalex()->calculate_sale_price( $pd['rule'], $base_price );
							$applied_discount_src = 'product_discount';							
						}
					}
				} else {
					$sale_price = floatval( $rrs );
				}
			}
			$__is_parent_rule_apply = apply_filters( 'wholesalex_apply_parent_rule_to_variations', false ); // Add This Filter TO Work Dynamic Rule For Combine Variation Product Like Quantity Base Discount
			if ( $product->is_type('variation') && $__is_parent_rule_apply ) {
				$cart_qty = wholesalex()->cart_count( $parent_id );
			}else {
				$cart_qty = wholesalex()->cart_count( $product_id );
			}
			$tier_res = array();						
			foreach ( $priority as $pr ) {
				$tier_res = $this->get_priority_wise_tier_price( $pr, $data, $product_id, $parent_id, $base_price, $cart_qty, true );
				
				if ( ! empty( $tier_res['tiers'] ) && ! isset( $this->active_tiers[ $product_id ] ) ) {
					$tier_res['base_price']            = $base_price;
					$this->active_tiers[ $product_id ] = $tier_res;
				}

				if ( $tier_res['price'] && $tier_res['src'] &&  0.00!=$tier_res['price']  ) {					
					$sale_price = $tier_res['price'];
					break;
				}
			}
			

			if ( isset( $tier_res['tiers'] ) && ! empty( $tier_res['tiers'] ) && $tier_res['price'] ) {
				$tier_res['base_price']            = $base_price;
				$this->active_tiers[ $product_id ] = $tier_res;
			}
		}

		

		// If Previous Sale Price Not Equal To Current Sale Price, It Means WholesaleX Price applied.
		if ( $previous_sp != $sale_price && $sale_price ) {
			wholesalex()->set_wholesalex_wholesale_prices($product_id,$sale_price);
			// $this->wholesale_prices[ $product_id ] = $sale_price;
		}

		// If Rolewise Regular Price applied and does not have any wholesalex sale price, then sale price will be empty, and rolewise base will be applied.
		if ( wholesalex()->get_wholesalex_regular_prices($product_id) && ! wholesalex()->get_wholesalex_wholesale_prices($product_id) ) {
			$previous_sp = '';
		}

        if(wholesalex()->get_wholesalex_wholesale_prices($product_id)) {
            $this->set_discounted_product($product_id);
        }
		
		return $sale_price && 0.00!=$sale_price ?$sale_price:$previous_sp;
	}


	/**
	 * Apply Login to View Prices
	 *
	 * @param object $product Product.
	 * @return void
	 */
	public function make_product_non_purchasable_and_remove_add_to_cart( $product = false ) {
		add_filter( 'woocommerce_is_purchasable', '__return_false' );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		if ( $product ) {
			remove_all_actions( 'woocommerce_' . $product->get_type() . '_add_to_cart' );
		}
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	}

}
