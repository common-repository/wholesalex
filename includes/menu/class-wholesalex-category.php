<?php
/**
 * Category Action.
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Category Class.
 */
class WHOLESALEX_Category {

	/**
	 * Category Constructor
	 */
	public function __construct() {

		add_action( 'edited_product_cat', array( $this, 'save_category_fields_action' ) );
		add_action( 'create_product_cat', array( $this, 'save_category_fields_action' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'category_content_html' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'category_content_edit_html' ) );

		/**
		 * Use of Pre Get Posts Hook instead of woocommerce_product_query.
		 *
		 * @since 1.0.2
		 */
		add_action( 'pre_get_posts', array( $this, 'control_category_visibilty' ) );
		/**
		 * Woocommerce Product Query Is Back Due to pre get post failed to hide categories on shop page.
		 *
		 * @since 1.0.3
		 */
		add_action( 'woocommerce_product_query', array( $this, 'control_category_visibilty' ) );
		add_action( 'template_redirect', array( $this, 'redirect_from_hidden_products' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'prevent_hidden_category_product_at_checkout' ) );
		add_action( 'rest_api_init', array( $this, 'save_category_callback' ) );

	}

	/**
	 * Category Rest API Callback
	 *
	 * @since 1.0.0
	 */
	public function save_category_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/category_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'category_action_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Get Category actions
	 *
	 * @param object $server Server.
	 * @return void
	 */
	public function category_action_callback( $server ) {
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}

		$type    = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';
		$term_id = isset( $post['term_id'] ) ? sanitize_text_field( $post['term_id'] ) : '';

		if ( 'get' === $type ) {

			$__visibility_settings = wholesalex()->get_category_visibility_settings( $term_id );
			$__tiers               = wholesalex()->get_category_discounts( $term_id );

			wp_send_json_success(
				array(
					'default'    => $this->get_category_fields(),
					'tiers'      => $__tiers,
					'visibility' => $__visibility_settings,
				)
			);
		}
	}

	/**
	 * Save WholesaleX Category Fields
	 *
	 * @param int $term_id .
	 * @since 1.0.0
	 */
	public function save_category_fields_action( $term_id ) {
		if ( ! ( isset( $_POST['_wpnonce_add_update_cat'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce_add_update_cat'] ), 'wholesalex_cat_add_update' ) ) ) {
			return;
		}

		if ( isset( $_POST['wholesalex_category_visibility_settings'] ) && ! empty( $_POST['wholesalex_category_visibility_settings'] ) ) {
			$__visibility_settings = wholesalex()->sanitize( json_decode( wp_unslash( $_POST['wholesalex_category_visibility_settings'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wholesalex()->save_category_visibility_settings( $term_id, $__visibility_settings );

		}
		if ( isset( $_POST['wholesalex_category_tiers'] ) && ! empty( $_POST['wholesalex_category_tiers'] ) ) {
			$__tiers = wholesalex()->sanitize( json_decode( wp_unslash( $_POST['wholesalex_category_tiers'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wholesalex()->save_category_discounts( $term_id, $__tiers );

		}

	}


	/**
	 * WholesaleX Category Script Callback
	 *
	 * @return void
	 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
	 */
	public function category_content_html() {
		wp_enqueue_script( 'wholesalex_category' );
		wp_localize_script(
			'wholesalex_category',
			'wholesalex_category',
			array(
				'visibility_settings' => wholesalex()->get_category_visibility_settings(),
				'fields'              => $this->get_category_fields(),
				'discounts'           => wholesalex()->get_category_discounts(),
				'i18n' => array(
					'unlock' => __("UNLOCK",'wholesalex'),
					'unlock_heading' => __("Unlock All Features",'wholesalex'),
					'unlock_desc' => __("We are sorry, but unfortunately, this feature is unavailable in the free version. Please upgrade to a pro plan to unlock all features.",'wholesalex'),
					'upgrade_to_pro' => __("Upgrade to Pro  ➤",'wholesalex'),
				)
			),
		);
		wp_nonce_field( 'wholesalex_cat_add_update', '_wpnonce_add_update_cat' ); ?>

		<div id="_wholesalex_edit_category"></div>
		<?php
	}
	/**
	 * WholesaleX Category Script Callback
	 *
	 * @return void
	 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
	 */
	public function category_content_edit_html($term) {
		wp_enqueue_script( 'wholesalex_category' );
		wp_localize_script(
			'wholesalex_category',
			'wholesalex_category',
			array(
				'visibility_settings' => wholesalex()->get_category_visibility_settings(),
				'fields'              => $this->get_category_fields(),
				'discounts'           => array($term->term_id=>wholesalex()->get_category_discounts($term->term_id)),
				'i18n' => array(
					'unlock' => __("UNLOCK",'wholesalex'),
					'unlock_heading' => __("Unlock All Features",'wholesalex'),
					'unlock_desc' => __("We are sorry, but unfortunately, this feature is unavailable in the free version. Please upgrade to a pro plan to unlock all features.",'wholesalex'),
					'upgrade_to_pro' => __("Upgrade to Pro  ➤",'wholesalex'),
				)
			),
		);
		wp_nonce_field( 'wholesalex_cat_add_update', '_wpnonce_add_update_cat' ); ?>

		<div id="_wholesalex_edit_category"></div>
		<?php
	}

	/**
	 * WholesaleX Category Products Visibility Handler
	 * This will alter woocommerce product query by excluding hidden category ids.
	 *
	 * @param WP_Query $query WP Query.
	 * @since 1.0.0
	 */
	public function control_category_visibilty( $query ) {
		$tax_query = $query->get( 'tax_query' );

		if ( is_array( $tax_query ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => wholesalex()->hidden_ids( 'category' ),
				'operator' => 'NOT IN',
			);
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * WholesaleX Redirect From Hidden Products
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function redirect_from_hidden_products() {
		if ( is_product() && function_exists( 'wc_get_product_term_ids' ) ) {
			$cats = wc_get_product_term_ids( get_the_ID(), 'product_cat' );
			if ( array_intersect( $cats, wholesalex()->hidden_ids( 'category' ) ) ) {
				/* translators: %s: Product Name */
				wc_add_notice( sprintf( __( 'Sorry, you are not allowed to see %s product.', 'wholesalex' ), get_the_title( get_the_ID() ) ), 'notice' );
				$redirect_url = get_permalink( get_option( 'woocommerce_shop_page_id' ) );
				wp_safe_redirect( $redirect_url );
				exit();
			}
		}
	}

	/**
	 * Prevent Checkout If any cart has any wholesalex hidden category product
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Allow Hidden to checkout hook added
	 */
	public function prevent_hidden_category_product_at_checkout() {
		if ( is_admin() || null === WC()->cart ) {
			return;
		}

		$allow_hidden_product_to_checkout = apply_filters( 'wholesalex_allow_hidden_product_to_checkout', false );
		if ( $allow_hidden_product_to_checkout ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$cats       = wc_get_product_term_ids( $product_id, 'product_cat' );
			if ( array_intersect( $cats, wholesalex()->hidden_ids( 'category' ) ) ) {
				// Remove Hidden Product From Cart.
				WC()->cart->remove_cart_item( $key );
				/* translators: %s: Product Name */
				wc_add_notice( sprintf( __( 'Sorry, you are not allowed to checkout %s product.', 'wholesalex' ), get_the_title( $product_id ) ), 'error' );
			}
		}
	}


	/**
	 * Get WholesaleX Category Fields
	 *
	 * @return array wholesalex category fields.
	 * @since 1.0.0
	 */
	public function get_category_fields() {
		$__roles_options = wholesalex()->get_roles( 'b2b_roles_option' );
		$__users_options = wholesalex()->get_users()['user_options'];

		$__b2b_roles = array();
		foreach ( $__roles_options as $role ) {
			if ( ! ( isset( $role['value'] ) && isset( $role['name'] ) ) ) {
				continue;
			}
			$__b2b_roles[ $role['value'] ] = array(
				'label'    => $role['name'],
				'type'     => 'tiers',
				'is_pro'   => true,
				'pro_data' => array(
					'type'  => 'limit',
					'value' => 3,
				),
				'attr'     => array(
					$role['value'] . 'tier' => array(
						'type'   => 'tier',
						'_tiers' => array(
							'columns'     => array(
								__( 'Discount Type', 'wholesalex' ),
								/* translators: %s: WholesaleX Role Name */
								sprintf( __( ' %s Price', 'wholesalex' ), $role['name'] ),
								__( 'Min Quantity', 'wholesalex' ),
							),
							'data'        => array(
								'_discount_type'   => array(
									'type'    => 'select',
									'options' => array(
										''            => __( 'Choose Discount Type...', 'wholesalex' ),
										'amount'      => __( 'Discount Amount', 'wholesalex' ),
										'percentage'  => __( 'Discount Percentage', 'wholesalex' ),
										'fixed_price' => __( 'Fixed Price', 'wholesalex' ),
									),
									'default' => '',
									'label'   => __( 'Discount Type', 'wholesalex' ),
								),
								'_discount_amount' => array(
									'type'        => 'number',
									'placeholder' => '',
									'default'     => '',
									/* translators: %s: WholesaleX Role Name */
									'label'       => sprintf( __( ' %s Price', 'wholesalex' ), $role['name'] ),
								),
								'_min_quantity'    => array(
									'type'        => 'number',
									'placeholder' => '',
									'default'     => '',
									'label'       => __( 'Min Quantity', 'wholesalex' ),
								),
							),
							'add'         => array(
								'type'  => 'button',
								'label' => __( 'Add Price Tier', 'wholesalex' ),
							),
							'upgrade_pro' => array(
								'type'  => 'button',
								'label' => __( 'Go For Unlimited Price Tiers', 'wholesalex' ),
							),
						),
					),
				),
			);
		}

		return apply_filters(
			'wholesalex_category_fields',
			array(
				'_category_settings' => array(
					/* translators: %s: Plugin Name */
					'label' => sprintf( __( '%s Settings', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'attr'  => array(
						'_settings_category_visibility' => array(
							'label' => __( 'Visibility', 'wholesalex' ),
							'type'  => 'visibility_section',
							'attr'  => array(
								'_hide_for_b2c'      => array(
									'type'    => 'switch',
									'label'   => __( 'Hide product for B2C', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
								),
								'_hide_for_visitors' => array(
									'type'    => 'switch',
									'label'   => __( 'Hide product for Visitors', 'wholesalex' ),
									'help'    => '',
									'default' => 'no',
								),
								'_hide_for_b2b_role_n_user' => array(
									'type'    => 'select',
									'label'   => __( 'Hide B2B Role and Users', 'wholesalex' ),
									'options' => array(
										''              => __( 'Choose Options...', 'wholesalex' ),
										'b2b_all'       => __( 'All B2B Users', 'wholesalex' ),
										'b2b_specific'  => __( 'Specific B2B Roles', 'wholesalex' ),
										'user_specific' => __( 'Specific Register Users', 'wholesalex' ),
									),
									'help'    => '',
									'default' => '',
								),
								'_hide_for_roles'    => array(
									'type'        => 'multiselect',
									'label'       => '',
									'options'     => $__roles_options,
									'placeholder' => __( 'Choose Roles...', 'wholesalex' ),
									'default'     => array(),
								),
								'_hide_for_users'    => array(
									'type'        => 'multiselect',
									'label'       => '',
									'options'     => $__users_options,
									'placeholder' => __( 'Choose Users...', 'wholesalex' ),
									'default'     => array(),
								),
							),
						),
					),
				),
				'_b2b_tiers'         => array(
					/* translators: %s: Plugin Name */
					'label' => sprintf( __( '%s Tier Pricing', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'attr'  => apply_filters( 'wholesalex_category_b2b_roles_tier_fields', $__b2b_roles ),
				),
			),
		);
	}

	/**
	 * Get WholesaleX Settings Fields
	 *
	 * @return array wholesalex category settings fields.
	 * @since 1.0.0
	 */
	public function get_category_settings() {
		$roles                      = wholesalex()->get_roles( 'b2b_roles_option' );
		$role_based_discount_option = array();
		foreach ( $roles as $role ) {
			if ( ! ( isset( $role['value'] ) && isset( $role['name'] ) ) ) {
				continue;
			}
			$role_based_discount_option[ $role['value'] ] = array(
				'label'         => $role['name'],
				'discount_type' => array(
					'type'    => 'select',
					'options' => array(
						''            => __( 'Choose Discount Type...', 'wholesalex' ),
						'amount'      => __( 'Discount Amount', 'wholesalex' ),
						'percentage'  => __( 'Discount Percentage', 'wholesalex' ),
						'fixed_price' => __( 'Fixed Price', 'wholesalex' ),
					),
					'default' => '',
				),
				'amount'        => array(
					'type'        => 'number',
					'placeholder' => '',
					'default'     => '',
				),
			);
		}

		return apply_filters(
			'wholesalex_category_fields',
			array(
				'general'        => array(
					'attr' => array(
						'wholesalex_category_visibility' => array(
							'type'    => 'radio',
							'label'   => __( 'Visibility', 'wholesalex' ),
							'options' => array(
								/* translators: %s: Plugin Name */
								'wholesalex_user_public' => sprintf( __( '%s Users and Public', 'wholesalex' ), wholesalex()->get_plugin_name() ),
								'public_only'            => __( 'Public Only', 'wholesalex' ),
								/* translators: %s: Plugin Name */
								'wholesalex_only'        => sprintf( __( '%s User Only', 'wholesalex' ), wholesalex()->get_plugin_name() ),
							),
							'default' => '',
						),
					),
				),
				'role_based'     => array(
					'attr' => $role_based_discount_option,
				),
				'quantity_based' => array(
					'attr' => array(
						'wholesalex_qb_pricing_switch'   => array(
							'type'    => 'switch',
							'label'   => __( 'Quantity Based Pricing', 'wholesalex' ),
							'desc'    => __( 'Enable Quantity Based Pricing', 'wholesalex' ),
							'default' => 'no',
						),
						'wholesalex_cat_quantity_switch' => array(
							'type'    => 'switch',
							'label'   => __( 'Apply Quantity Based Pricing in each product', 'wholesalex' ),
							'desc'    => __( 'Enable Quantity Based Pricing on each product', 'wholesalex' ),
							'default' => 'no',
						),
						'_quantity_based_discount'       => array(
							'wholesalex_start_quantity' => array(
								'type'        => 'number',
								'label'       => __( 'Start Qty', 'wholesalex' ),
								'placeholder' => __( '2', 'wholesalex' ),
								'help'        => '',
								'default'     => '',
							),
							'wholesalex_end_quantity'   => array(
								'type'        => 'number',
								'label'       => __( 'End Qty', 'wholesalex' ),
								'placeholder' => __( '10', 'wholesalex' ),
								'help'        => '',
								'default'     => '',
							),
							'wholesalex_quantity_based_discount_type' => array(
								'type'    => 'select',
								'label'   => __( 'Discount Type', 'wholesalex' ),
								'options' => array(
									''            => __( 'Choose Discount Type...', 'wholesalex' ),
									'amount'      => __( 'Discount Amount', 'wholesalex' ),
									'percentage'  => __( 'Discount Percentage', 'wholesalex' ),
									'fixed_price' => __( 'Fixed Price', 'wholesalex' ),
								),
								'default' => '',
							),
							'wholesalex_quantity_based_discount_value' => array(
								'type'        => 'number',
								'label'       => '',
								'placeholder' => __( '10', 'wholesalex' ),
								'help'        => '',
								'default'     => '',
							),
						),
					),
				),
			)
		);
	}
}
