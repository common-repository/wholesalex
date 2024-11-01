<?php
/**
 * Product
 *
 * @package WHOLESALEX
 * @since 1.0.0
 */

namespace WHOLESALEX;

/**
 * WholesaleX Product Class
 */
class WHOLESALEX_Product {

	/**
	 * Rule on Lists
	 *
	 * @var array
	 */
	public $rule_on_lists = array();

	/**
	 * Product Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_options_pricing', array( $this, 'wholesalex_single_product_settings' ) );
		add_filter( 'woocommerce_product_after_variable_attributes', array( $this, 'wholesalex_single_product_settings' ), 10, 3 );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'wholesalex_product_meta_save' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'wholesalex_product_meta_save' ) );
		add_action( 'rest_api_init', array( $this, 'get_product_callback' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_custom_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'wholesalex_tab_data' ) );
		add_action( 'save_post', array( $this, 'product_settings_data_save' ) );
		/**
		 * Use of Pre Get Posts Hook instead of woocommerce_product_query.
		 *
		 * @since 1.0.2
		 */
		add_filter( 'pre_get_posts', array( $this, 'control_single_product_visibility' ) );
		add_filter( 'woocommerce_product_query', array( $this, 'control_single_product_visibility' ) );
		add_action( 'template_redirect', array( $this, 'redirect_from_hidden_products' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'prevent_checkout_hidden_products' ) );
		/**
		 * Remove Hidden Product From Related Products.
		 *
		 * @since 1.0.2
		 */
		add_filter( 'woocommerce_related_products', array( $this, 'filter_hidden_products' ) );

		/**
		 * Add WholesaleX Rule on Column on All Products Page.
		 *
		 * @since 1.0.4
		 */
		add_filter( 'manage_edit-product_columns', array( $this, 'add_wholesalex_rule_on_column_on_product_page' ) );

		add_action( 'manage_product_posts_custom_column', array( $this, 'populate_data_on_wholesalex_rule_on_column' ), 10, 2 );

		/**
		 * Add More Tier Layout
		 *
		 * @since 1.0.6 Tier layouts added on v1.0.1 But Code was refactored on v1.0.6.
		 */
		if ( wholesalex()->is_pro_active() && version_compare( WHOLESALEX_PRO_VER, '1.0.6', '>=' ) ) {
			add_filter( 'wholesalex_single_product_tier_layout', array( $this, 'add_more_tier_layouts' ), 20 );
			add_filter( 'wholesalex_settings_product_tier_layout', array( $this, 'add_more_tier_layouts' ), 20 );
		} else {
			add_filter( 'wholesalex_single_product_tier_layout', array( $this, 'add_more_tier_layouts' ), 1 );
			add_filter( 'wholesalex_settings_product_tier_layout', array( $this, 'add_more_tier_layouts' ), 1 );
		}

		add_action( 'woocommerce_process_product_meta', array( $this, 'after_product_update' ), 1 );

		/**
		 * WooCommerce Importer and Exporter Integration On Single Product WholesaleX Rolewise Price.
		 *
		 * @since 1.1.5
		 */
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_wholesale_rolewise_column_exporter' ), 99999 );
		$wholesalex_roles = wholesalex()->get_roles( 'b2b_roles_option' );
		foreach ( $wholesalex_roles as $role ) {
			$base_price_meta_key = $role['value'] . '_base_price';
			$sale_price_meta_key = $role['value'] . '_sale_price';
			add_filter( "woocommerce_product_export_product_column_{$base_price_meta_key}", array( $this, 'export_column_value' ), 99999, 3 );
			add_filter( "woocommerce_product_export_product_column_{$sale_price_meta_key}", array( $this, 'export_column_value' ), 99999, 3 );
		}

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'import_column_mapping' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'import_column_mapping' ) );
		add_filter( 'woocommerce_product_import_inserted_product_object', array( $this, 'process_import' ), 10, 2 );

		add_action('woocommerce_variable_product_bulk_edit_actions',array($this,'variable_product_bulk_edit_actions'));

		add_action('wp_ajax_wholesalex_bulk_edit_variations',array($this,'handle_wholesalex_bulk_edit_variations'));

		if('yes' === wholesalex()->get_setting('b2b_stock_management_status','no')) {
			$this->b2b_stock_management();
		}

	}

	public function b2b_stock_management() {
		add_action('woocommerce_product_options_stock_status', array($this,'b2b_stock_manage_fields_on_simple_product'));
		add_action('woocommerce_variation_options_inventory',array($this,'b2b_stock_manage_fields_on_product_variation'),10,3);

		add_action('save_post',array($this,'save_b2b_stock_manage_fields_for_simple_product'));
		add_action('woocommerce_save_product_variation',array($this,'save_b2b_stock_manage_fields_for_product_variation'),10,2);

		add_filter('woocommerce_admin_stock_html',array($this,'admin_stock_html'),10,2);

		if(!(is_admin() && !wp_doing_ajax())) {

			add_filter('woocommerce_product_get_stock_status',array($this,'b2b_get_stock_status'),10,2);
			add_filter('woocommerce_product_get_stock_quantity', array($this,'b2b_get_stock_quantity'),10,2);
			add_action('woocommerce_product_get_backorders',array($this, 'b2b_get_backorders'),10,2);

			//Product Variation
			add_filter('woocommerce_product_variation_get_stock_status', array($this,'b2b_variation_get_stock_status'),10,2);
			add_filter('woocommerce_product_variation_get_stock_quantity', array($this,'b2b_variation_get_stock_quantity'),10,2);
			add_filter('woocommerce_product_variation_get_backorders', array($this,'b2b_variation_get_backorders'),10,2);
		}

		//Remove All woocommerce stock related action and will rewrite this action
		remove_action( 'woocommerce_payment_complete', 'wc_maybe_reduce_stock_levels' );
		remove_action( 'woocommerce_order_status_completed', 'wc_maybe_reduce_stock_levels' );
		remove_action( 'woocommerce_order_status_processing', 'wc_maybe_reduce_stock_levels' );
		remove_action( 'woocommerce_order_status_on-hold', 'wc_maybe_reduce_stock_levels' );
		remove_action( 'woocommerce_order_status_cancelled', 'wc_maybe_increase_stock_levels' );
		remove_action( 'woocommerce_order_status_pending', 'wc_maybe_increase_stock_levels' );

		//rewrite all woocommerce store related action
		add_action('woocommerce_payment_complete', array($this,'maybe_reduce_stock_levels'));
		add_action('woocommerce_order_status_completed', array($this,'maybe_reduce_stock_levels'));
		add_action('woocommerce_order_status_processing', array($this,'maybe_reduce_stock_levels'));
		add_action('woocommerce_order_status_on-hold', array($this,'maybe_reduce_stock_levels'));

		add_action('woocommerce_order_status_cancelled', array($this,'maybe_increase_stock_levels'));
		add_action('woocommerce_order_status_pending', array($this,'maybe_increase_stock_levels'));
	}

	/**
	 * All Products Stock Columns : WholesaleX B2B Stock Added
	 *
	 * @param string $stock_html stock html.
	 * @param object $product Product.
	 * @return void
	 */
	public function admin_stock_html($stock_html, $product) {
		$product_id = $product->get_id();


		$separate_b2b_stock_status = get_post_meta($product_id,'wholesalex_b2b_separate_stock_status',true);
		$b2b_stock = get_post_meta($product_id,'wholesalex_b2b_stock',true);
		$b2b_backorders = get_post_meta($product_id,'wholesalex_b2b_backorders',true);
		$stock_status = get_post_meta($product_id,'wholesalex_b2b_stock_status',true);


		if ( $product->is_on_backorder() ) {
			$stock_html = '<mark class="onbackorder">' . __( 'On backorder', 'woocommerce' ) . '</mark>';
		} elseif ( $product->is_in_stock() ) {
			$stock_html = '<mark class="instock">' . __( 'In stock', 'woocommerce' ) . '</mark>';
		} else {
			$stock_html = '<mark class="outofstock">' . __( 'Out of stock', 'woocommerce' ) . '</mark>';
		}

		if ( $product->managing_stock() ) {
			$stock_html .= ' (' . wc_stock_amount( $product->get_stock_quantity() ) . ')';
		}
		
		$b2b_stock_html = '';

		$backorder_status = $product->managing_stock() && ('yes' === $b2b_backorders || 'notify' === $b2b_backorders) && intval($b2b_stock)<=0;


		if('onbackorder' == $stock_status || $backorder_status ) {
			$b2b_stock_html = '<mark class="onbackorder">' . __( 'On backorder', 'woocommerce' ) . '</mark>';
		} elseif('outofstock'!==$stock_status) {
			$b2b_stock_html = '<mark class="instock">' . __( 'In stock', 'woocommerce' ) . '</mark>';
		} else {
			$b2b_stock_html = '<mark class="outofstock">' . __( 'Out of stock', 'woocommerce' ) . '</mark>';
		}
		

		if ( $product->managing_stock() && 'outofstock'!==$stock_status ) {
			if('yes' == $separate_b2b_stock_status) {
				$b2b_stock_html .= ' (' . wc_stock_amount( $b2b_stock ) . ')';
			} else {
				$b2b_stock_html .= ' (' . wc_stock_amount( $product->get_stock_quantity() ) . ')';
			}
		}

		$output = sprintf('<span> <strong>B2C:</strong> %s <br/> <strong>B2B:</strong> %s </span>',$stock_html,$b2b_stock_html);

		return $output;
	}

	/**
	 * B2B Stock Manage Fields
	 *
	 * @return void
	 */
	public function b2b_stock_manage_fields_on_simple_product() {
		global $post;
		$product_id = $post->ID;

		if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
			$separate_b2b_stock_status = get_post_meta($product_id,'wholesalex_b2b_separate_stock_status',true);
			$b2b_stock = get_post_meta($product_id,'wholesalex_b2b_stock',true);
			$b2b_backorders = get_post_meta($product_id,'wholesalex_b2b_backorders',true);
			

            ?>
            <div class="stock_fields show_if_simple show_if_variable wholesalex_stock_management_fields">
            <?php

			woocommerce_wp_select(
				array(
					'id'          => 'wholesalex_b2b_separate_stock_status',
					'value'       => $separate_b2b_stock_status,
					'label'       => __('Separate Stock For WholesaleX B2B User?','wholesalex'),
					'options'     => array('yes' => __('Yes','wholesalex'),'no'=>__('No','wholesalex')),
					'wrapper_class' => '',
					'desc_tip'    => true,
					'description' => esc_html__( 'By selecting Yes, Separate Stock Will be managed for B2B Users. If Select No, then same stock will be used for all.', 'wholesalex' ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => 'wholesalex_b2b_stock',
					'value'             => wc_stock_amount( $b2b_stock ?? 1 ),
					'label'             => __( 'WholesaleX B2B Stock Quantity', 'wholesalex' ),
					'desc_tip'          => true,
					'description'       => __( 'Stock quantity. If this is a variable product this value will be used to control stock for all variations, unless you define stock at variation level.', 'wholesalex' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
					),
					'data_type'         => 'stock',
				)
			);

            ?>
			    <input type="hidden" name="_original_wholesalex_b2b_stock" value="<?php echo esc_attr( wc_stock_amount( $b2b_stock??1 ) ); ?>" />
            <?php

			$backorder_args = array(
				'id'          => 'wholesalex_b2b_backorders',
				'value'       => $b2b_backorders,
				'label'       => __( 'Allow WholesaleX B2B Users backorders?', 'wholesalex' ),
				'options'     => wc_get_product_backorder_options(),
				'desc_tip'    => true,
				'description' => __('If managing stock, this controls whether or not backorders are allowed. If enabled, stock quantity can go below 0.','wholesalex')
			);

			/**
			 * Allow 3rd parties to control whether "Allow backorder?" option will use radio buttons or a select.
			 *
			 * @since 7.6.0
			 *
			 * @param bool If false, "Allow backorders?" will be shown as a select. Default: it will use radio buttons.
			 */
			if ( apply_filters( 'woocommerce_product_allow_backorder_use_radio', true ) ) {
				woocommerce_wp_radio( $backorder_args );
			} else {
				woocommerce_wp_select( $backorder_args );
			}


            ?>
			    </div>
            <?php
		} 

		$stock_status_options = wc_get_product_stock_status_options();
		$b2b_stock_status = get_post_meta($product_id,'wholesalex_b2b_stock_status',true);
		$stock_status_count   = count( $stock_status_options );
		$stock_status_args    = array(
			'id'            => 'wholesalex_b2b_stock_status',
			'value'         => $b2b_stock_status,
			'wrapper_class' => 'stock_status_field hide_if_variable hide_if_external hide_if_grouped wholesalex_stock_management_fields',
			'label'         => __( 'WholesaleX B2B Stock status', 'wholesalex' ),
			'options'       => $stock_status_options,
			'desc_tip'      => true,
			'description'   => __( 'Controls whether or not the product is listed as "in stock" or "out of stock" on the frontend for wholesalex b2b users', 'wholesalex' ),
		);

		if ( apply_filters( 'woocommerce_product_stock_status_use_radio', $stock_status_count <= 3 && $stock_status_count >= 1 ) ) {
			woocommerce_wp_radio( $stock_status_args );
		} else {
			woocommerce_wp_select( $stock_status_args );
		}
	}

	/**
	 * Save B2B Stock Manage Fields for Simple Product
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save_b2b_stock_manage_fields_for_simple_product($product_id) {
        
        if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$nonce = isset( $_POST['meta-box-order-nonce'] )?sanitize_key( $_POST['meta-box-order-nonce'] ):'';

		if(!wp_verify_nonce($nonce,'meta-box-order')) {
			return;
		}

		$product= wc_get_product($product_id);
		if (is_a($product,'WC_Product') || is_a($product,'WC_Product_Variation')){
			$product_id = $product->get_id();
		} else{
			return;
		}
		

		if(isset($_POST['wholesalex_b2b_stock_status'])) {
			$stock_status = sanitize_text_field($_POST['wholesalex_b2b_stock_status']);
			update_post_meta($product_id,'wholesalex_b2b_stock_status',$stock_status);
		}
		if(isset($_POST['wholesalex_b2b_stock'])) {
			$stock_count = sanitize_text_field($_POST['wholesalex_b2b_stock']);
			$original_value = sanitize_text_field($_POST['_original_wholesalex_b2b_stock'] );
			$current_stock = get_post_meta($product_id,'wholesalex_b2b_stock',true);

			if(empty($original_value)) {
				$original_value = $current_stock;
			}

			if(intval($current_stock) == intval($original_value)) {
				//Seems good
				update_post_meta($product_id,'wholesalex_b2b_stock',$stock_count);
			} else {
				/* translators: %1s Product ID, %2s Stock Count. */
				\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'The stock has not been updated because the value has changed since editing. Product %1$d has %2$d units in stock.', 'wholesalex' ), $product_id, $current_stock ) );
			}
		}

		if(isset($_POST['wholesalex_b2b_backorders'])) {
			$backorder_status = sanitize_text_field($_POST['wholesalex_b2b_backorders']);
			update_post_meta($product_id,'wholesalex_b2b_backorders',$backorder_status);
		}
		if(isset($_POST['wholesalex_b2b_separate_stock_status'])) {
			$stock_status = sanitize_text_field($_POST['wholesalex_b2b_separate_stock_status']);
			update_post_meta($product_id,'wholesalex_b2b_separate_stock_status',$stock_status);
		}

	}

	/**
	 * WholesaleX B2B Stock Management options inventory action.
	 *
	 * @since v.todo
	 *
	 * @param int     $loop           Position in the loop.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Post data.
	 */
	public function b2b_stock_manage_fields_on_product_variation($loop, $variation_data, $variation ) {
		$product_id = $variation->ID;
		$separate_b2b_stock_status = get_post_meta($product_id,'wholesalex_b2b_variable_separate_stock_status',true);
		$b2b_stock = get_post_meta($product_id,'wholesalex_b2b_variable_stock',true);
		$b2b_backorders = get_post_meta($product_id,'wholesalex_b2b_variable_backorders',true);

		woocommerce_wp_select(
			array(
				'id'          => "wholesalex_b2b_separate_stock_status_{$product_id}",
				'value'       => $separate_b2b_stock_status,
				'label'       => __('Separate Stock For WholesaleX B2B User?','wholesalex'),
				'options'     => array('yes' => __('Yes','wholesalex'),'no'=>__('No','wholesalex')),
				'wrapper_class'     => 'form-row form-row-first',
				'desc_tip'    => true,
				'description' => esc_html__( 'By selecting Yes, Separate Stock Will be managed for B2B Users. If Select No, then same stock will be used for all.', 'wholesalex' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => "wholesalex_b2b_variable_stock_{$product_id}",
				'name'              => "wholesalex_b2b_variable_stock_{$product_id}",
				'value'             => wc_stock_amount( $b2b_stock),
				'label'             => __( 'WholesaleX B2B Users Stock quantity', 'wholesalex' ),
				'desc_tip'          => true,
				'description'       => __( "Enter a number to set stock quantity at the variation level. Use a variation's 'Manage stock?' check box above to enable/disable stock management at the variation level.", 'wholesalex' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
				),
				'data_type'         => 'stock',
				'wrapper_class'     => 'form-row form-row-first',
			)
		);

        ?>
		    <input type="hidden" name="<?php echo esc_attr('variable_original_wholesalex_b2b_stock_' . $product_id); ?>" value="<?php echo esc_attr( wc_stock_amount( $b2b_stock ) ); ?>" />
        <?php

		woocommerce_wp_select(
			array(
				'id'            => "wholesalex_b2b_variable_backorders_{$product_id}",
				'name'          => "wholesalex_b2b_variable_backorders_{$product_id}",
				'value'         => $b2b_backorders,
				'label'         => __( 'Allow WholesaleX B2B Users backorders?', 'wholesalex' ),
				'options'       => wc_get_product_backorder_options(),
				'desc_tip'      => true,
				'description'   => __( 'If managing stock, this controls whether or not backorders are allowed. If enabled, stock quantity can go below 0.', 'wholesalex' ),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
	}

	/**
	 * Save Variation Stock Manage Fields
	 *
	 * @param int $variation_id Variation Id.
	 * @return void
	 */
	public function save_b2b_stock_manage_fields_for_product_variation( $variation_id) {

        if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		$nonce = isset( $_POST['security'] )?sanitize_key( $_POST['security'] ):'';
		if(empty($nonce)) {
			$nonce = isset( $_POST['meta-box-order-nonce'] )?sanitize_key( $_POST['meta-box-order-nonce'] ):'';
		}
		$is_nonce_verify = false;
		if(wp_verify_nonce($nonce,'save-variations') || wp_verify_nonce($nonce,'meta-box-order')) {
			$is_nonce_verify = true;
		}

		if(!$is_nonce_verify) {
			return;
		}
		if(isset($_POST["wholesalex_b2b_variable_stock_".$variation_id])) {
			$stock_count = sanitize_text_field($_POST["wholesalex_b2b_variable_stock_".$variation_id] );
			$original_value = sanitize_text_field($_POST["variable_original_wholesalex_b2b_stock_".$variation_id] );
			$current_stock = get_post_meta($variation_id,'wholesalex_b2b_variable_stock',true);

			if(empty($original_value)) {
				$original_value = $current_stock;
			}

			if(intval($current_stock) == intval($original_value)) {
				//Seems good
				update_post_meta($variation_id,'wholesalex_b2b_variable_stock',$stock_count);
			} else {
				/* translators: %1s Product ID, %2s Stock Count. */
				\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'The stock has not been updated because the value has changed since editing. Product %1$d has %2$d units in stock.', 'wholesalex' ), $variation_id, $current_stock ) );
			}
		}

		if(isset($_POST['wholesalex_b2b_variable_backorders_'.$variation_id])) {
			$backorder_status = sanitize_text_field($_POST['wholesalex_b2b_variable_backorders_'.$variation_id]);
			update_post_meta($variation_id,'wholesalex_b2b_variable_backorders',$backorder_status);
		}

		if(isset($_POST['wholesalex_b2b_separate_stock_status_'.$variation_id])) {
			$backorder_status = sanitize_text_field($_POST['wholesalex_b2b_separate_stock_status_'.$variation_id]);
			update_post_meta($variation_id,'wholesalex_b2b_variable_separate_stock_status',$backorder_status);
		}

	}

	/**
	 * Set B2B Stock Status
	 *
	 * @param string $stock_status Default Stock Status.
	 * @param Object $product Product.
	 * @return string stock status
	 */
	public function b2b_get_stock_status($stock_status, $product) {

		if(wholesalex()->is_active_b2b_user() ) {

			if($product->get_manage_stock() ) {
				//Manage Stock Enabled

				if(!intval($product->get_stock_quantity())) {
					$stock_status = 'outofstock';
				}

				if('no' !==$product->get_backorders()) {
					$stock_status = 'instock'; // For allowing backorder
				}
			} else {
				//Manage Stock Disabled
				$stock_status = get_post_meta($product->get_id(),'wholesalex_b2b_stock_status',true);
			}

			if(empty($stock_status)) {
				$stock_status = 'instock';
			}
		}
		
		return $stock_status;
	}

	/**
	 * Set B2B Stock Quantity
	 *
	 * @param int|string $quantity Stock Quantity
	 * @param Object $product Product.
	 * @return int stock quantity
	 */
	public function b2b_get_stock_quantity($quantity, $product) {

		if(wholesalex()->is_active_b2b_user()) {
			$product_id = $product->get_id();
			$separate_b2b_stock_status = get_post_meta($product_id,'wholesalex_b2b_separate_stock_status',true);
			if('yes' ==$separate_b2b_stock_status  ) {
				$quantity = get_post_meta($product_id,'wholesalex_b2b_stock',true);
			}

		}

		return $quantity;
	}

	/**
	 * Set B2B Backorders
	 *
	 * @param string $status Backorder Status
	 * @param Object $product Product.
	 * @return string backorder status
	 */
	public function b2b_get_backorders($status, $product) {

		if(wholesalex()->is_active_b2b_user()) {
			$product_id = $product->get_id();
			$status = get_post_meta($product_id,'wholesalex_b2b_backorders',true);
		}
		return $status;
	}

	/**
	 * Get Variation Product B2B Stock Status
	 *
	 * @param string $stock_status Stock Status.
	 * @param Object $product Product.
	 * @return string stock status.
	 */
	public function b2b_variation_get_stock_status($stock_status, $product) {

		if(wholesalex()->is_active_b2b_user() ) {

			if($product->get_manage_stock() ) {
				//Manage Stock Enabled

				if(!intval($product->get_stock_quantity())) {
					$stock_status = 'outofstock';
				}

				if('no' !==$product->get_backorders()) {
					$stock_status = 'instock'; // For allowing backorder
				}
			} else {
				//Manage Stock Disabled
				$stock_status = get_post_meta($product->get_id(),'wholesalex_b2b_stock_status',true);
			}

			if(empty($stock_status)) {
				$stock_status = 'instock';
			}
		}
		return $stock_status;
	}

	/**
	 * Get B2B Variation Stock quantity
	 *
	 * @param int|string $quantity stock quantity.
	 * @param Object $product Product.
	 * @return int stock quantity
	 */
	public function b2b_variation_get_stock_quantity($quantity, $product) {
		if ( wholesalex()->is_active_b2b_user() ) {
			$product_id   = $product->get_id();
			$manage_stock = $product->get_manage_stock();
	
			if ( $manage_stock ) { // The variation manages its own stock
				$separate_b2b_stock_status = get_post_meta( $product_id, 'wholesalex_b2b_variable_separate_stock_status', true );
				if ( 'yes' === $separate_b2b_stock_status ) {
					$quantity = get_post_meta( $product_id, 'wholesalex_b2b_variable_stock', true );
				}
			} elseif ( $manage_stock === 'parent' ) { // The variation inherits stock from the parent product
				$parent_id = $product->get_parent_id();
				$separate_b2b_stock_status = get_post_meta( $parent_id, 'wholesalex_b2b_separate_stock_status', true );
				if ('yes' === $separate_b2b_stock_status ) {
					$quantity = get_post_meta( $parent_id, 'wholesalex_b2b_stock', true );
				}
			}
		}
		return $quantity;
	}

	/**
	 * Set Variation B2B Backorders
	 * Inspired From WooCommerce Core
	 *
	 * @param string $status Backorder Status
	 * @param Object $product Product.
	 * @return string backorder status
	 */
	public function b2b_variation_get_backorders($status, $product) {

		if(wholesalex()->is_active_b2b_user()) {
			$product_id = $product->get_id();
			$status = get_post_meta($product_id,'wholesalex_b2b_variable_backorders',true);
		}
		return $status;
	}

	/**
	 * Reduce stock levels for items within an order, if stock has not already been reduced for the items.
	 * Inspired From WooCommerce Core
	 *
	 * @param int|WC_Order $order_id Order ID or order instance.
	 */
	public function reduce_b2b_stock($order_id) {
		if ( is_a( $order_id, 'WC_Order' ) ) {
			$order    = $order_id;
			$order_id = $order->get_id();
		} else {
			$order = wc_get_order( $order_id );
		}
		// We need an order, and a store with stock management to continue.
		if ( ! $order || 'yes' !== get_option( 'woocommerce_manage_stock' ) || ! apply_filters( 'woocommerce_can_reduce_order_stock', true, $order ) ) {
			return;
		}
	
		$changes = array();
		$customer_id = $order->get_customer_id();
	
		// Loop over all items.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->is_type( 'line_item' ) ) {
				continue;
			}
	
			// Only reduce stock once for each item.
			$product            = $item->get_product();
			$item_stock_reduced = $item->get_meta( '_reduced_stock', true );
	
			if ( $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
				continue;
			}
	
			/**
			 * Filter order item quantity.
			 *
			 * @param int|float             $quantity Quantity.
			 * @param WC_Order              $order    Order data.
			 * @param WC_Order_Item_Product $item Order item data.
			 */
			$qty       = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
			$item_name = $product->get_formatted_name();
			$new_stock = $this->b2b_update_product_stock( $product, $qty, 'decrease',false,$customer_id );
	
			if ( is_wp_error( $new_stock ) ) {
				/* translators: %s item name. */
				$order->add_order_note( sprintf( __( 'Unable to reduce stock for item %s.', 'woocommerce' ), $item_name ) );
				continue;
			}
	
			$item->add_meta_data( '_reduced_stock', $qty, true );
			$item->save();
	
			$change    = array(
				'product' => $product,
				'from'    => $new_stock + $qty,
				'to'      => $new_stock,
			);
			$changes[] = $change;
	
			/**
			 * Fires when stock reduced to a specific line item
			 *
			 * @param WC_Order_Item_Product $item Order item data.
			 * @param array $change  Change Details.
			 * @param WC_Order $order  Order data.
			 * @since 7.6.0
			 */
			do_action( 'woocommerce_reduce_order_item_stock', $item, $change, $order );
		}
	
		$this->trigger_stock_change_notifications( $order, $changes,$customer_id );
	
		do_action( 'woocommerce_reduce_order_stock', $order );
	}

	/**
	 * After stock change events, triggers emails and adds order notes.
	 * Inspired From WooCommerce Core
	 *
	 * @param WC_Order $order order object.
	 * @param array    $changes Array of changes.
	 */
	public function trigger_stock_change_notifications( $order, $changes ) {
		if ( empty( $changes ) ) {
			return;
		}

		$order_notes     = array();
		$no_stock_amount = absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) );

		foreach ( $changes as $change ) {
			$order_notes[]    = $change['product']->get_formatted_name() . ' ' . $change['from'] . '&rarr;' . $change['to'];
			$low_stock_amount = absint( wc_get_low_stock_amount( wc_get_product( $change['product']->get_id() ) ) );
			if ( $change['to'] <= $no_stock_amount ) {
				do_action( 'woocommerce_no_stock', wc_get_product( $change['product']->get_id() ) );
			} elseif ( $change['to'] <= $low_stock_amount ) {
				do_action( 'woocommerce_low_stock', wc_get_product( $change['product']->get_id() ) );
			}

			if ( $change['to'] < 0 ) {
				do_action(
					'woocommerce_product_on_backorder',
					array(
						'product'  => wc_get_product( $change['product']->get_id() ),
						'order_id' => $order->get_id(),
						'quantity' => abs( $change['from'] - $change['to'] ),
					)
				);
			}
		}

		$order->add_order_note( __( 'Stock levels reduced:', 'woocommerce' ) . ' ' . implode( ', ', $order_notes ).' '.__('Wholesale Customer','wholesalex') );
	}

	/**
	 * Update a product's stock amount directly.
	 *
	 * Uses queries rather than update_post_meta so we can do this in one query (to avoid stock issues).
	 * Ignores manage stock setting on the product and sets quantities directly in the db: post meta and lookup tables.
	 * Uses locking to update the quantity. If the lock is not acquired, change is lost.
	 * Inspired From WooCommerce Core
	 *
	 * @param  int            $product_id_with_stock Product ID.
	 * @param  int|float|null $stock_quantity Stock quantity.
	 * @param  string         $operation Set, increase and decrease.
	 * @return int|float New stock level.
	 */
	public function update_product_stock( $product_id_with_stock, $stock_quantity = null, $operation = 'set' ) {
		global $wpdb;
	
		$stock_meta_key = '_stock';
		$product = wc_get_product($product_id_with_stock);
	
		if($product->is_type('simple') || $product->is_type('variable') ) {
			//simple product
			$separate_b2b_stock_status = get_post_meta($product_id_with_stock,'wholesalex_b2b_separate_stock_status',true);
			if('yes' == $separate_b2b_stock_status) {
				$stock_meta_key = 'wholesalex_b2b_stock';
			}
		} else if($product->is_type('variation')) {
			//variable product
			$separate_b2b_stock_status = get_post_meta($product_id_with_stock,'wholesalex_b2b_variable_separate_stock_status',true);
			if('yes' === $separate_b2b_stock_status) {
				$stock_meta_key='wholesalex_b2b_variable_stock';
			}
		}
	
		// Ensures a row exists to update.
		add_post_meta( $product_id_with_stock, $stock_meta_key, 0, true );
	
		if ( 'set' === $operation ) {
			$new_stock = wc_stock_amount( $stock_quantity );
	
			// Generate SQL.
			$sql = $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %f WHERE post_id = %d AND meta_key = %s;",
				$new_stock,
				$product_id_with_stock,
				$stock_meta_key
			);
		} else {
			$current_stock = wc_stock_amount(
				$wpdb->get_var(//phpcs:ignore
					$wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s;",
						$product_id_with_stock,
						$stock_meta_key
					)
				)
			);
	
			// Calculate new value for filter below. Set multiplier to subtract or add the meta_value.
			switch ( $operation ) {
				case 'increase':
					$new_stock  = $current_stock + wc_stock_amount( $stock_quantity );
					$multiplier = 1;
					break;
				default:
					$new_stock  = $current_stock - wc_stock_amount( $stock_quantity );
					$multiplier = -1;
					break;
			}
	
			// Generate SQL.
			$sql = $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + (%f * %d) WHERE post_id = %d AND meta_key = %s;",
				wc_stock_amount( $stock_quantity ),
				$multiplier,
				$product_id_with_stock,
				$stock_meta_key
			);
		}
	
		$sql = apply_filters( 'woocommerce_update_product_stock_query', $sql, $product_id_with_stock, $new_stock, $operation );
	
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	
		// Cache delete is required (not only) to set correct data for lookup table (which reads from cache).
		// Sometimes I wonder if it shouldn't be part of update_lookup_table.
		wp_cache_delete( $product_id_with_stock, 'post_meta' );
	
		$datastore = \WC_Data_Store::load( 'product' );
		$datastore->update_lookup_table( $product_id_with_stock, 'wc_product_meta_lookup' );
	
		/**
		 * Fire an action for this direct update so it can be detected by other code.
		 *
		 * @param int $product_id_with_stock Product ID that was updated directly.
		 */
		do_action( 'woocommerce_updated_product_stock', $product_id_with_stock );
	
		return $new_stock;
	}	
	
	/**
	 * Update a product's stock amount.
	 *
	 * Uses queries rather than update_post_meta so we can do this in one query (to avoid stock issues).
	 * Inspired From WooCommerce Core
	 *
	 *
	 * @param  int|WC_Product $product        Product ID or product instance.
	 * @param  int|null       $stock_quantity Stock quantity.
	 * @param  string         $operation      Type of operation, allows 'set', 'increase' and 'decrease'.
	 * @param  bool           $updating       If true, the product object won't be saved here as it will be updated later.
	 * @return bool|int|null
	 */
	public function b2b_update_product_stock( $product, $stock_quantity = null, $operation = 'set', $updating = false) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( $product );
		}
	
		if ( ! $product ) {
			return false;
		}
	
		if ( ! is_null( $stock_quantity ) && $product->managing_stock() ) {
			// Some products (variations) can have their stock managed by their parent. Get the correct object to be updated here.
			$product_id_with_stock = $product->get_stock_managed_by_id();
			$product_with_stock    = $product_id_with_stock !== $product->get_id() ? wc_get_product( $product_id_with_stock ) : $product;
			$data_store            = \WC_Data_Store::load( 'product' );
	
			// Fire actions to let 3rd parties know the stock is about to be changed.
			if ( $product_with_stock->is_type( 'variation' ) ) {
				do_action( 'woocommerce_variation_before_set_stock', $product_with_stock );
			} else {
				do_action( 'woocommerce_product_before_set_stock', $product_with_stock );
			}
	
			// Update the database.
			$new_stock = $this->update_product_stock( $product_id_with_stock, $stock_quantity, $operation );
	
			// Update the product object.
			$data_store->read_stock_quantity( $product_with_stock, $new_stock );
	
			// If this is not being called during an update routine, save the product so stock status etc is in sync, and caches are cleared.
			if ( ! $updating ) {
				$product_with_stock->save();
			}
	
			// Fire actions to let 3rd parties know the stock changed.
			if ( $product_with_stock->is_type( 'variation' ) ) {
				do_action( 'woocommerce_variation_set_stock', $product_with_stock );
			} else {
				do_action( 'woocommerce_product_set_stock', $product_with_stock );
			}
	
			return $product_with_stock->get_stock_quantity();
		}
		return $product->get_stock_quantity();
	}

	/**
	 * When a payment is complete, we can reduce stock levels for items within an order. 
	 * If customer is B2B Then we reduce b2b stock level, otherwise woocommerce will handle it.
	 * Inspired From WooCommerce Core
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_reduce_stock_levels($order_id) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if(!wholesalex()->is_active_b2b_user($customer_id)) {
			wc_maybe_reduce_stock_levels($order_id);
			return;
		}

		$stock_reduced  = $order->get_data_store()->get_stock_reduced( $order_id );
		$trigger_reduce = apply_filters( 'woocommerce_payment_complete_reduce_order_stock', ! $stock_reduced, $order_id );
	
		// Only continue if we're reducing stock.
		if ( ! $trigger_reduce ) {
			return;
		}

		$this->reduce_b2b_stock($order);

		// Ensure stock is marked as "reduced" in case payment complete or other stock actions are called.
		$order->get_data_store()->set_stock_reduced( $order_id, true );
	}

	/**
	 * When a payment is cancelled, restore stock.
	 * Inspired From WooCommerce Core
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_increase_stock_levels($order_id) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if(!wholesalex()->is_active_b2b_user($customer_id)) {
			wc_maybe_increase_stock_levels($order_id);
			return;
		}


		$stock_reduced    = $order->get_data_store()->get_stock_reduced( $order_id );
		$trigger_increase = (bool) $stock_reduced;

		// Only continue if we're increasing stock.
		if ( ! $trigger_increase ) {
			return;
		}

		$this->increase_b2b_stock( $order );

		// Ensure stock is not marked as "reduced" anymore.
		$order->get_data_store()->set_stock_reduced( $order_id, false );


	}

	/**
	 * Increase stock levels for items within an order.
	 * Inspired From WooCommerce Core
	 *
	 * @param int|WC_Order $order_id Order ID or order instance.
	 */
	public function increase_b2b_stock($order_id) {
		if ( is_a( $order_id, 'WC_Order' ) ) {
			$order    = $order_id;
			$order_id = $order->get_id();
		} else {
			$order = wc_get_order( $order_id );
		}
	
		// We need an order, and a store with stock management to continue.
		if ( ! $order || 'yes' !== get_option( 'woocommerce_manage_stock' ) || ! apply_filters( 'woocommerce_can_restore_order_stock', true, $order ) ) {
			return;
		}
	
		$changes = array();
	
		// Loop over all items.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->is_type( 'line_item' ) ) {
				continue;
			}
	
			// Only increase stock once for each item.
			$product            = $item->get_product();
			$item_stock_reduced = $item->get_meta( '_reduced_stock', true );
	
			if ( ! $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
				continue;
			}
	
			$item_name = $product->get_formatted_name();
			$new_stock = $this->b2b_update_product_stock( $product, $item_stock_reduced, 'increase' );
	
			if ( is_wp_error( $new_stock ) ) {
				/* translators: %s item name. */
				$order->add_order_note( sprintf( __( 'Unable to restore stock for item %s.', 'woocommerce' ), $item_name ) );
				continue;
			}
	
			$item->delete_meta_data( '_reduced_stock' );
			$item->save();
	
			$changes[] = $item_name . ' ' . ( $new_stock - $item_stock_reduced ) . '&rarr;' . $new_stock;
		}
	
		if ( $changes ) {
			$order->add_order_note( __( 'Stock levels increased:', 'woocommerce' ) . ' ' . implode( ', ', $changes ). ' '.__('Wholesale Customer','wholesalex') );
		}
	
		do_action( 'woocommerce_restore_order_stock', $order );
	}
	


	/**
	 * Process Import
	 *
	 * @param object $product Product Object.
	 * @param array  $data Data.
	 * @return void
	 * @since 1.1.5
	 */
	public function process_import( $product, $data ) {

		$product_id = $product->get_id();
		$roles      = wholesalex()->get_roles( 'b2b_roles_option' );

		foreach ( $roles as $role ) {
			$base_price_column_id = $role['value'] . '_base_price';
			$sale_price_column_id = $role['value'] . '_sale_price';
			if ( isset( $data[ $base_price_column_id ] ) && ! empty( $data[ $base_price_column_id ] ) ) {
				update_post_meta( $product_id, $base_price_column_id, $data[ $base_price_column_id ] );
			}
			if ( isset( $data[ $sale_price_column_id ] ) && ! empty( $data[ $sale_price_column_id ] ) ) {
				update_post_meta( $product_id, $sale_price_column_id, $data[ $sale_price_column_id ] );
			}
		}

	}

	/**
	 * Save Wholesalex Category
	 *
	 * @since 1.0.0
	 */
	public function get_product_callback() {
		register_rest_route(
			'wholesalex/v1',
			'/product_action/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'product_action_callback' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * WholesaleX Tab in Single Product Edit Page
	 *
	 * @param array $tabs Single Product Page Tabs.
	 * @return array Updated Tabs.
	 */
	public function product_custom_tab( $tabs ) {
		$tabs['wholesalex'] = array(
			'label'    => wholesalex()->get_plugin_name(),
			'priority' => 15,
			'target'   => 'wholesalex_tab_data',
			'class'    => array( 'hide_if_grouped' ),
		);

		return $tabs;
	}

	/**
	 * WholesaleX Custom Tab Data.
	 *
	 * @return void
	 */
	public function wholesalex_tab_data() {
		global $post;
		/**
		 * Enqueue Script
		 *
		 * @since 1.1.0 Enqueue Script (Reconfigure Build File)
		 */
		wp_enqueue_script( 'wholesalex_product' );
		wp_localize_script( 'wholesalex_product', 'wholesalex_product', array(
			'roles'=>wholesalex()->get_roles('b2b_roles_option'),
			'i18n' => array(
				'unlock' => __("UNLOCK",'wholesalex'),
				'unlock_heading' => __("Unlock All Features",'wholesalex'),
				'unlock_desc' => __("We are sorry, but unfortunately, this feature is unavailable in the free version. Please upgrade to a pro plan to unlock all features.",'wholesalex'),
				'upgrade_to_pro' => __("Upgrade to Pro  ➤",'wholesalex'),
			)
			)
		 );
		$settings = wholesalex()->get_single_product_setting();

		wp_localize_script(
			'wholesalex_components',
			'wholesalex_product_tab',
			array(
				'fields'   => self::get_product_settings(),
				'settings' => isset( $settings[ $post->ID ] ) ? $settings[ $post->ID ] : array(),
			),
		);
		?>
		<div class="panel woocommerce_options_panel" id="wholesalex_tab_data"></div>
		<?php
	}

	/**
	 * Save Product Setting Data
	 *
	 * @param mixed $post_id Product ID.
	 */
	public function product_settings_data_save( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['meta-box-order-nonce'] )?sanitize_key( $_POST['meta-box-order-nonce'] ):'';
		
		if(!wp_verify_nonce($nonce,'meta-box-order')) {
			return;
		}

		if ( isset( $_POST['wholesalex_product_settings'] ) ) {
			$product_settings = wholesalex()->sanitize( json_decode( wp_unslash( $_POST['wholesalex_product_settings'] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wholesalex()->save_single_product_settings( $post_id, $product_settings );
		}

	}



	/**
	 * Get Category actions
	 *
	 * @param object $server Server.
	 * @return void
	 */
	public function product_action_callback( $server ) {
		$post = $server->get_params();
		if ( ! ( isset( $post['nonce'] ) && wp_verify_nonce( sanitize_key( $post['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}
		$type    = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : '';
		$post_id = isset( $post['postId'] ) ? sanitize_text_field( $post['postId'] ) : '';
		$is_tab  = isset( $post['isTab'] ) ? sanitize_text_field( $post['isTab'] ) : '';
		if ( 'get' === $type ) {

			if ( $is_tab ) {
				wp_send_json_success(
					array(
						'default' => self::get_product_settings(),
						'value'   => wholesalex()->get_single_product_setting( $post_id ),
					),
				);
			}

			wp_send_json_success(
				array(
					'default' => self::get_product_fields(),
					'value'   => wholesalex()->get_single_product_discount( $post_id ),
				)
			);
		}
	}

	/**
	 * WholesaleX Single Product Settings
	 */
	public function wholesalex_single_product_settings() {

		$post_id   = get_the_ID();
		$discounts = array();
		if ( $post_id ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$is_variable = 'variable' === $product->get_type();
				if ( $is_variable ) {
					if ( $product->has_child() ) {
						$childrens = $product->get_children();
						foreach ( $childrens as $key => $child_id ) {
							$discounts[ $child_id ] = wholesalex()->get_single_product_discount( $child_id );
						}
					}
				} else {
					$discounts[ $post_id ] = wholesalex()->get_single_product_discount( $post_id );
				}
			}
		}
		wp_localize_script(
			'wholesalex_components',
			'wholesalex_single_product',
			array(
				'fields'    => self::get_product_fields(),
				'discounts' => $discounts,
			),
		);
		?>
		<div class="_wholesalex_single_product_settings" class="options-group hide_if_external"></div>
		<?php
	}

	/**
	 * Save WholesaleX Product Meta
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 * @since 1.0.0
	 * @access public
	 */
	public function wholesalex_product_meta_save( $post_id ) {

		$nonce = isset( $_POST['security'] )?sanitize_key( $_POST['security'] ):'';
		if(empty($nonce)) {
			$nonce = isset( $_POST['meta-box-order-nonce'] )?sanitize_key( $_POST['meta-box-order-nonce'] ):'';
		}
		$is_nonce_verify = false;
		if(wp_verify_nonce($nonce,'save-variations') || wp_verify_nonce($nonce,'meta-box-order')) {
			$is_nonce_verify = true;
		}

		if ( $is_nonce_verify && isset( $_POST[ 'wholesalex_single_product_tiers_' . $post_id ] ) ) {
			$product_discounts = wholesalex()->sanitize( json_decode( wp_unslash( $_POST[ 'wholesalex_single_product_tiers_' . $post_id ] ), true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wholesalex()->save_single_product_discount( $post_id, $product_discounts );
		}

	}

	/**
	 * Control Single Product Visibility
	 *
	 * @param WP_Query $q Query Object.
	 * @since 1.0.0
	 * @since 1.0.3 Added post type checking.
	 * @since 1.2.4 Add Filter wholesalex_is_admin_dashboard
	 */
	public function control_single_product_visibility( $q ) {
		$is_admin_dashboard = apply_filters( 'wholesalex_is_admin_dashboard', is_admin() );
		if ( $is_admin_dashboard ) {
			return $q;
		}
		$post_type = $q->get( 'post_type' );

		$product_cat = $q->get( 'product_cat' );

		if ( 'product' === $post_type || '' != $product_cat && ! $is_admin_dashboard ) {
			$__role = wholesalex()->get_current_user_role();
			if ( 'wholesalex_guest' === $__role ) {
				$__hide_for_guest_global = apply_filters( 'wholesalex_hide_all_products_for_guest', wholesalex()->get_setting( '_settings_hide_all_products_from_guest' ) );
				if ( 'yes' === $__hide_for_guest_global ) {
					$q->set( 'post__in', (array) array( '9999999' ) );
				}
			}
			if ( 'wholesalex_b2c_users' === $__role ) {
				$__hide_for_b2c_global = apply_filters( 'wholesalex_hide_all_products_for_b2c', wholesalex()->get_setting( '_settings_hide_products_from_b2c' ) );
				if ( 'yes' === $__hide_for_b2c_global ) {
					$q->set( 'post__in', (array) array( '9999999' ) );
				}
			}
			$existing_ids = isset($q->query['post__not_in']) ? (array) $q->query['post__not_in'] : array();
			$hidden_ids = (array) wholesalex()->hidden_product_ids();
			$q->set('post__not_in', array_merge($existing_ids, $hidden_ids)); // Exclude "$q->query['post__not_in']" WowStore Product with WholesaleX

		}
		return $q;
	}

	/**
	 * WholesaleX Redirect From Hidden Products
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function redirect_from_hidden_products() {
		if ( is_product() ) {
			$__role      = wholesalex()->get_current_user_role();
			$__is_hidden = false;
			if ( 'wholesalex_guest' === $__role ) {
				$__hide_for_guest_global = apply_filters( 'wholesalex_hide_all_products_for_guest', wholesalex()->get_setting( '_settings_hide_all_products_from_guest' ) );
				if ( 'yes' === $__hide_for_guest_global ) {
					$__is_hidden = true;
				}
			}
			if ( 'wholesalex_b2c_users' === $__role ) {
				$__hide_for_b2c_global = apply_filters( 'wholesalex_hide_all_products_for_b2c', wholesalex()->get_setting( '_settings_hide_products_from_b2c' ) );
				if ( 'yes' === $__hide_for_b2c_global ) {
					$__is_hidden = true;
				}
			}
			$__id = get_the_ID();
			if ( in_array( $__id, wholesalex()->hidden_product_ids(), true ) || $__is_hidden ) {
				/* translators: %s: Product Name */
				wc_add_notice( sprintf( __( 'Sorry, you are not allowed to see %s product.', 'wholesalex' ), get_the_title( get_the_ID() ) ), 'notice' );
				$previous_url = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
				$redirect_url = !empty($previous_url) ? $previous_url : home_url();
				wp_safe_redirect( $redirect_url );
				exit();
			}
		}
	}

	/**
	 * Prevent Checkout If any cart has any wholesalex hidden product
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Allow Hidden to checkout hook added
	 */
	public function prevent_checkout_hidden_products() {
		if ( ! ( isset( WC()->cart ) && ! empty( WC()->cart ) ) ) {
			return;
		}
		$allow_hidden_product_to_checkout = apply_filters( 'wholesalex_allow_hidden_product_to_checkout', false );
		if ( $allow_hidden_product_to_checkout ) {
			return;
		}
		$__role      = wholesalex()->get_current_user_role();
		$__is_hidden = false;
		if ( 'wholesalex_guest' === $__role ) {
			$__hide_for_guest_global = apply_filters( 'wholesalex_hide_all_products_for_guest', wholesalex()->get_setting( '_settings_hide_all_products_from_guest' ) );
			if ( 'yes' === $__hide_for_guest_global ) {
				$__is_hidden = true;
			}
		}
		if ( 'wholesalex_b2c_users' === $__role ) {
			$__hide_for_b2c_global = apply_filters( 'wholesalex_hide_all_products_for_b2c', wholesalex()->get_setting( '_settings_hide_products_from_b2c' ) );
			if ( 'yes' === $__hide_for_b2c_global ) {
				$__is_hidden = true;
			}
		}

		$__hide_regular_price = wholesalex()->get_setting( '_settings_hide_retail_price' ) ?? '';

		$__hide_wholesale_price = wholesalex()->get_setting( '_settings_hide_wholesalex_price' ) ?? '';

		if ( ! is_admin() ) {
			if ( 'yes' === (string) $__hide_wholesale_price && 'yes' === (string) $__hide_regular_price ) {
				$__is_hidden = true;
			}
		}
		foreach ( WC()->cart->get_cart() as $key => $cart_item ) {

			$__product_id = '';

			if ( $cart_item['variation_id'] ) {
				$__product    = wc_get_product( $cart_item['variation_id'] );
				$__product_id = $__product->get_parent_id();
			} elseif ( $cart_item['product_id'] ) {
				$__product_id = $cart_item['product_id'];
			}
			if ( in_array( $__product_id, wholesalex()->hidden_product_ids(), true ) || $__is_hidden ) {
				// Remove Hidden Product From Cart.
				WC()->cart->remove_cart_item( $key );
				/* translators: %s: Product Name */
				wc_add_notice( sprintf( __( 'Sorry, you are not allowed to checkout %s product.', 'wholesalex' ), get_the_title( $__product_id ) ), 'error' );
			}
		}
	}

	/**
	 * Get Product Settings Field
	 *
	 * @return array Product Settings Fields.
	 */
	public static function get_product_settings() {
		$__roles_options = wholesalex()->get_roles( 'b2b_roles_option' );
		$__users_options = wholesalex()->get_users()['user_options'];

		// Key changed _settings_tier_layout to _settings_tier_layout_single_product
		// Reset Default Data
		return apply_filters(
			'wholesalex_single_product_settings_field',
			array(
				'_product_settings_tab' => array(
					'type' => 'custom_tab',
					'attr' => array(
						'_settings_tier_layout_single_product'        => array(
							'type'    => 'choosebox',
							'label'   => __( 'Tier Table Layout in Product Single Page', 'wholesalex' ),
							'options' => apply_filters(
								'wholesalex_single_product_tier_layout',
								array(
									'layout_one'   => WHOLESALEX_URL . '/assets/img/layout_one.png',
									'layout_two'   => WHOLESALEX_URL . '/assets/img/layout_two.png',
									'layout_three' => WHOLESALEX_URL . '/assets/img/layout_three.png',
								)
							),
							'default' => wholesalex()->get_setting('_settings_tier_layout'),
						),
						'_settings_show_tierd_pricing_table' => array(
							'type'    => 'switch',
							'label'   => __( 'Show Tierd Pricing Table', 'wholesalex' ),
							'help'    => '',
							'default' => 'yes',
						),
						// '_settings_override_tax_extemption' => array(
						// 'type'    => 'select',
						// 'label'   => __( 'Override Tax Extemption', 'wholesalex' ),
						// 'options' => array(
						// 'enable'  => __( 'Enable', 'wholesalex' ),
						// 'disable' => __( 'Disable', 'wholesalex' ),
						// ),
						// 'help'    => '',
						// 'default' => 'disable',
						// ),
						// '_settings_override_shipping_role' => array(
						// 'type'    => 'select',
						// 'label'   => __( 'Override Shipping Role', 'wholesalex' ),
						// 'options' => array(
						// 'enable'  => __( 'Enable', 'wholesalex' ),
						// 'disable' => __( 'Disable', 'wholesalex' ),
						// ),
						// 'help'    => '',
						// 'default' => 'disable',
						// ),
						'_settings_product_visibility' => array(
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
			),
		);
	}


	/**
	 * Single Product Field Return.
	 */
	public static function get_product_fields() {
		$b2b_roles   = wholesalex()->get_roles( 'b2b_roles_option' );
		$b2c_roles   = wholesalex()->get_roles( 'b2c_roles_option' );
		$__b2b_roles = array();
		foreach ( $b2b_roles as $role ) {
			if ( ! ( isset( $role['value'] ) && isset( $role['value'] ) ) ) {
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
					'_prices'               => array(
						'type' => 'prices',
						'attr' => array(
							'wholesalex_base_price' => array(
								'type'    => 'number',
								'label'   => __( 'Base Price', 'wholesalex' ),
								'default' => '',
							),
							'wholesalex_sale_price' => array(
								'type'    => 'number',
								'label'   => __( 'Sale Price', 'wholesalex' ),
								'default' => '',
							),
						),
					),
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
									'label'       => /* translators: %s: WholesaleX Role Name */
									sprintf( __( ' %s Price', 'wholesalex' ), $role['name'] ),
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

		$__b2c_roles = array();
		foreach ( $b2c_roles as $role ) {
			if ( ! ( isset( $role['value'] ) && isset( $role['value'] ) ) ) {
				continue;
			}
			$__b2c_roles[ $role['value'] ] = array(
				'label'    => $role['name'],
				'type'     => 'tiers',
				'is_pro'   => true,
				'pro_data' => array(
					'type'  => 'limit',
					'value' => 2,
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
									'label'   => __( 'Discount Type', 'wholesalex' ),
									'default' => '',
								),
								'_discount_amount' => array(
									'type'        => 'number',
									'placeholder' => '',
									'label'       => /* translators: %s: WholesaleX Role Name */
									sprintf( __( ' %s Price', 'wholesalex' ), $role['name'] ),
									'default'     => '',
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
			'wholesalex_single_product_fields',
			array(
				'_b2c_section' => array(
					'label' => '',
					'attr'  => apply_filters( 'wholesalex_single_product_b2c_roles_tier_fields', $__b2c_roles ),
				),
				'_b2b_section' => array(
					/* translators: %s - Plugin Name */
					'label' => sprintf( __( '%s B2B Special', 'wholesalex' ), wholesalex()->get_plugin_name() ),
					'attr'  => apply_filters( 'wholesalex_single_product_b2b_roles_tier_fields', $__b2b_roles ),
				),
			),
		);
	}

	/**
	 * Filter Hidden Products
	 *
	 * @param array $args Related Products.
	 * @return array related products excluding hidden products.
	 * @since 1.0.2
	 */
	public function filter_hidden_products( $args ) {
		return array_diff( $args, wholesalex()->hidden_product_ids() );
	}

	/**
	 * Add WholesaleX Rule On Column On Product Page.
	 *
	 * @param array $columns Order Columns.
	 * @return array
	 * @since 1.0.4
	 */
	public function add_wholesalex_rule_on_column_on_product_page( $columns ) {
		/* translators: %s - Plugin Name */
		$columns['wholesalex_rule_on'] = sprintf( __( 'Enabled %s Rule', 'wholesalex' ), wholesalex()->get_plugin_name() );
		return $columns;
	}

	/**
	 * Rule on All Variation List Modals
	 *
	 * @param string|int $product_id Product Id.
	 * @return void
	 * @since 1.4.7
	 */
	public function list_parent_modal( $product_id ) {
		?>
		<div class="wholesalex_rule_modal <?php echo  esc_attr( 'product_'. $product_id ); ?>">
			<div class="modal_content">
				<div class="modal_header">
					<div class="modal-close-btn">
						<span class="close-modal-icon dashicons dashicons-no-alt" ></span>
					</div>
				</div>
				<div class="wholesalex_rule_on_lists">
					<?php
					$product = wc_get_product( $product_id );
					if ( $product->has_child() ) {
						$childrens = $product->get_children();
						foreach ( $childrens as $key => $child_id ){
							if ( isset( $this->rule_on_lists[ $child_id ] ) && is_array( $this->rule_on_lists[ $child_id ] ) ) {
								foreach ( $this->rule_on_lists[ $child_id ] as $rule_on ) {
									echo wp_kses_post( $rule_on );
								}
							}
						}
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}
	/**
	 * Rule on List Modals
	 *
	 * @param string|int $product_id Product Id.
	 * @return void
	 * @since 1.0.4
	 */
	public function list_modal( $product_id ) {
		?>
		<div class="wholesalex_rule_modal <?php echo  esc_attr( 'product_'.$product_id ); ?>">
			<div class="modal_content">
				<div class="modal_header">
					<div class="modal-close-btn">
						<span class="close-modal-icon dashicons dashicons-no-alt" ></span>
					</div>
				</div>
				<div class="wholesalex_rule_on_lists">
					<?php
					if ( isset( $this->rule_on_lists[ $product_id ] ) && is_array( $this->rule_on_lists[ $product_id ] ) ) {
						foreach ( $this->rule_on_lists[ $product_id ] as $rule_on ) {
							echo wp_kses_post( $rule_on );
						}
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}
	/**
	 * Populate Data on WholesaleX Rule On Column on Products page
	 *
	 * @param string $column Products Page Column.
	 * @param int    $product_id Product ID.
	 * @since 1.0.4
	 */
	public function populate_data_on_wholesalex_rule_on_column( $column, $product_id ) {

		if ( 'wholesalex_rule_on' === $column ) {

			$product = wc_get_product( $product_id );
			// Single Products.
			if ( $product->has_child() ) {
				$childrens = $product->get_children();
				foreach ( $childrens as $key => $child_id ) {
					$__discounts = wholesalex()->get_single_product_discount( $child_id );
					$status      = $this->wholesalex_rule_on( $__discounts, $child_id, 'Single Product' );
				}
			} else {

				$__discounts = wholesalex()->get_single_product_discount( $product_id );

				$status = $this->wholesalex_rule_on( $__discounts, $product_id, 'Single Product' );
			}

			// Profile.
			$users = get_users(
				array(
					'fields'   => 'ids',
					'meta_key' => '__wholesalex_profile_discounts',
				)
			);

			$__parent_id = $product->get_parent_id();

			$__cat_ids = wc_get_product_term_ids( 0 === $__parent_id ? $product_id : $__parent_id, 'product_cat' );

			foreach ( $users as $user_id ) {
				$discounts = get_user_meta( $user_id, '__wholesalex_profile_discounts', true );
				if ( isset( $discounts['_profile_discounts']['tiers'] ) ) {
					$discounts = wholesalex()->filter_empty_tier( $discounts['_profile_discounts']['tiers'] );
				} else {
					$discounts = array();
				}

				if ( ! empty( $discounts ) ) {
					foreach ( $discounts as $discount ) {
						if ( ! isset( $discount['_product_filter'] ) ) {
							continue;
						}
						$__has_discount = true;
						switch ( $discount['_product_filter'] ) {
							case 'all_products':
								$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
								break;
							case 'products_in_list':
								if ( ! isset( $discount['products_in_list'] ) ) {
									break;
								}
								foreach ( $discount['products_in_list'] as $list ) {
									if ( (int) $product_id === (int) $list['value'] ) {
										$__has_discount = true;
										break;
									}
								}
								if ( $__has_discount ) {
									$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
								}
								break;
							case 'products_not_in_list':
								if ( ! isset( $discount['products_not_in_list'] ) ) {
									break;
								}
								$__flag = true;
								foreach ( $discount['products_not_in_list'] as $list ) {
									if ( isset( $list['value'] ) && (int) $product_id === (int) $list['value'] ) {
										$__flag = false;
									}
								}
								if ( $__flag ) {
									$__has_discount                       = true;
									$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
								}
								break;
							case 'cat_in_list':
								if ( ! isset( $discount['cat_in_list'] ) ) {
									break;
								}
								foreach ( $discount['cat_in_list'] as $list ) {
									if (in_array($list['value'], $__cat_ids)) { //phpcs:ignore
											$__has_discount = true;
											break;
									}
								}
								if ( $__has_discount ) {
									$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
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
									if ( $__has_discount ) {
										$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
									}
								}
								break;
							case 'attribute_in_list':
								if ( ! isset( $discount['attribute_in_list'] ) ) {
									break;
								}
								if ( 'product_variation' === $product->post_type ) {
									foreach ( $discount['attribute_in_list'] as $list ) {
										if ( isset( $list['value'] ) && (int) $product_id === (int) $list['value'] ) {
											$__has_discount = true;
											break;
										}
									}
								}
								if ( $__has_discount ) {
									$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
								}
								break;
							case 'attribute_not_in_list':
								if ( ! isset( $discount['attribute_not_in_list'] ) ) {
									break;
								}
								if ( 'product_variation' === $product->post_type ) {
									$__flag = true;
									foreach ( $discount['attribute_not_in_list'] as $list ) {
										if ( isset( $list['value'] ) && (int) $product_id === (int) $list['value'] ) {
											$__flag = false;
										}
									}
									if ( $__flag ) {
										$__has_discount = true;
										if ( $__has_discount ) {
											$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( sprintf( 'User ID: %s ', $user_id ), 'Profile' );
										}
									}
								}
								break;
						}
					}
				}
			}

			// Category.

			foreach ( $__cat_ids as $cat_id ) {
				$__discounts = wholesalex()->get_category_discounts( $cat_id );
				$status      = $this->wholesalex_rule_on( $__discounts, $product_id, 'Category' );
				if ( $status ) {
					break;
				}
			}

			// Dynamic Rules.
			$__discounts = wholesalex()->get_dynamic_rules();
			foreach ( $__discounts as $discount ) {
				$__has_discount  = false;
				$__product_id    = $product->get_id();
				$__parent_id     = $product->get_parent_id();
				$__cat_ids       = wc_get_product_term_ids( 0 === $__parent_id ? $__product_id : $__parent_id, 'product_cat' );
				$__regular_price = $product->get_regular_price();
				$__for           = '';
				$__src_id        = '';

				if ( isset( $discount['_rule_status'] ) && ! empty( $discount['_rule_status'] ) && isset( $discount['_product_filter'] ) ) {
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
								if ( (int) $__product_id === (int) $list['value'] ) {
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
								if ( isset( $list['value'] ) && (int) $__product_id === (int) $list['value'] ) {
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
								$__src_id       = isset( $__cat_ids[0] ) ? $__cat_ids[0] : '';
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

				if ( ! isset( $discount['_rule_type'] ) || ! isset( $discount[ $discount['_rule_type'] ] ) ) {
					continue;
				}

				$__rule_type = '';

				switch ( $discount['_rule_type'] ) {
					case 'product_discount':
						$__rule_type = 'Product Discount';

						break;
					case 'quantity_based':
						if ( ! isset( $discount['quantity_based']['tiers'] ) ) {
							break;
						}
						$__rule_type = 'Quantity Based';

						break;

					case 'payment_discount':
						$__rule_type = 'Payment Discount';
						break;
					case 'payment_order_qty':
						$__rule_type = 'Payment Order Quantity';
						break;
					case 'tax_rule':
						$__rule_type = 'Tax Rule';
						break;
					case 'extra_charge':
						$__rule_type = 'Extra Charge';
						break;
					case 'cart_discount':
						$__rule_type = 'Cart Discount';
						break;
					case 'shipping_rule':
						$__rule_type = 'Shipping Rule';
						break;
					case 'buy_x_get_y':
						$__rule_type = 'Buy X Get Y';
						break;
					case 'buy_x_get_one':
						$__rule_type = __( 'BOGO Discounts (Buy X Get One Free)', 'wholesalex' );
						break;
				}

				if ( ! isset( $discount['_rule_for'] ) ) {
					continue;
				}

				$__role_for = $discount['_rule_for'];
				switch ( $__role_for ) {
					case 'specific_roles':
						foreach ( $discount['specific_roles'] as $role ) {
							if ( '' != $__rule_type ) {
								$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( $role['name'], sprintf( 'Dynamic Rules( %s )', $__rule_type ) );
							}
						}
						break;
					case 'specific_users':
						foreach ( $discount['specific_users'] as $user ) {
							$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( $user['value'], sprintf( 'Dynamic Rules( %s )', $__rule_type ) );
						}
						break;
					case 'all_roles':
						$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( 'All Roles', sprintf( 'Dynamic Rules( %s )', $__rule_type ) );
						break;
					case 'all_users':
						$this->rule_on_lists[ $product_id ][] = $this->rule_on_message( 'All Users', sprintf( 'Dynamic Rules( %s )', $__rule_type ) );
						break;
				}
			}

			$product = wc_get_product( $product_id );
			if ( $product && $product->get_type() == 'variable' ) {
				$is_any_variation_apply = false;
				$product = wc_get_product( $product_id );
					if ( $product->has_child() ) {
						$childrens = $product->get_children();
						foreach ( $childrens as $key => $child_id ){
							if ( isset( $this->rule_on_lists[ $child_id ] ) ){
								$is_any_variation_apply = true;
								break;
							}
						}
					}
				if ( $is_any_variation_apply ) {
					?> <span class="wholesalex_rule_on_more" id="<?php echo esc_attr( 'product_' . $product_id ); ?>"> <?php echo esc_html( __( 'Show Variations','wholesalex' ) ) ?> </span> <?php
					$this->list_parent_modal( $product_id );
				}
			}
			if ( isset( $this->rule_on_lists[ $product_id ] ) ) {
				$this->rule_on_lists[ $product_id ] = array_unique( $this->rule_on_lists[ $product_id ] );
				if ( count( $this->rule_on_lists[ $product_id ] ) > 3 ) {
					$__count = 0;
					if ( isset( $this->rule_on_lists[ $product_id ] ) && is_array(  $this->rule_on_lists[ $product_id ] ) ) {
						foreach ( $this->rule_on_lists[ $product_id ] as $key => $value ) {
							echo wp_kses_post( $value );
							$__count++;
							if ( 3 == $__count ) {
								break;
							}
						}
					}
                    ?>
					    <span class="wholesalex_rule_on_more" id="<?php echo esc_attr( 'product_'. $product_id ); ?>">More+</span>
                    <?php
					$this->list_modal( $product_id );
				} else {
					if ( isset( $this->rule_on_lists[ $product_id ] ) && is_array( $this->rule_on_lists[ $product_id ] ) ) {
						foreach ( $this->rule_on_lists[ $product_id ] as $key => $value ) {
							echo wp_kses_post( $value );
						}
					}
				}
			}
		}

	}


	/**
	 * Check Product Has Any WholesaleX Rule
	 *
	 * @param array      $__discounts Discounts.
	 * @param int|string $product_id Product ID.
	 * @param string     $rule_src Rule Src.
	 * @return boolean
	 * @since 1.0.4
	 */
	public function wholesalex_rule_on( $__discounts, $product_id, $rule_src ) {
		$has_rule = false;

		foreach ( $__discounts as $role_id => $discount ) {

			$_temp          = $discount;
			$_temp['tiers'] = wholesalex()->filter_empty_tier( $_temp['tiers'] );

			if ( ! empty( $_temp['wholesalex_base_price'] ) || ! empty( $_temp['wholesalex_sale_price'] ) || ! empty( $_temp['tiers'] ) ) {
				$product   = wc_get_product( $product_id );
				$parent_id = $product->get_parent_id();
				$suffix    = '';
				if ( $parent_id ) {
					$suffix = $product->get_name();
				}
				$_role_name                           = wholesalex()->get_role_name_by_role_id( $role_id );
				if ( $product && $product->is_type( 'variation' ) ) {
					$this->rule_on_lists[ $product_id ][] = '<span class="wholesalex_rule_on_list">' . $_role_name . ' -> ' . $rule_src . ' ' . ' <span class="wsx-rule-variation-name"> '. $suffix . '</span> </span>';
				}else {
					$this->rule_on_lists[ $product_id ][] = '<span class="wholesalex_rule_on_list">' . $_role_name . ' -> ' . $rule_src . $suffix . '</span>';
				}
				$has_rule                             = true;
			}
		}
		return $has_rule;
	}

	/**
	 * Rule on Message
	 *
	 * @param string $name Role/Src Name.
	 * @param string $rule_src Rule Src.
	 * @param string $suffix If Have Any Suffix.
	 * @return string
	 * @since 1.0.4
	 */
	public function rule_on_message( $name, $rule_src, $suffix = '' ) {
		return '<span class="wholesalex_rule_on_list">' . $name . ' -> ' . $rule_src . $suffix . '</span>';
	}

	/**
	 * Add More Tier Layouts
	 *
	 * @param array $existing_layouts Existing Layout.
	 * @return array
	 * @since 1.0.6 Tier Layouts added on v1.0.1 but Refactored on v1.0.6
	 */
	public function add_more_tier_layouts( $existing_layouts ) {
		$new_layouts = array(
			'pro_layout_four'  => WHOLESALEX_URL . '/assets/img/layout_four.png',
			'pro_layout_five'  => WHOLESALEX_URL . '/assets/img/layout_five.png',
			'pro_layout_six'   => WHOLESALEX_URL . '/assets/img/layout_six.png',
			'pro_layout_seven' => WHOLESALEX_URL . '/assets/img/layout_seven.png',
			'pro_layout_eight' => WHOLESALEX_URL . '/assets/img/layout_eight.png',
		);
		return array_merge( $existing_layouts, $new_layouts );
	}


	/**
	 * After Product Update : ProductX Filter Integration.
	 *
	 * @param string|int $post_id Post ID.
	 * @return void
	 * @since 1.1.5
	 */
	public function after_product_update( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( $product->is_type( 'variable' ) ) {
			$role_ids = wholesalex()->get_roles( 'ids' );
			foreach ( $role_ids as $role_id ) {
				$base_price_meta_key = $role_id . '_base_price';
				$sale_price_meta_key = $role_id . '_sale_price';
				$price_meta_key      = $role_id . '_price';
				delete_post_meta( $post_id, $price_meta_key );
				foreach ( $product->get_available_variations() as $variation ) {
					$base_price = get_post_meta( $variation['variation_id'], $base_price_meta_key, true );
					$sale_price = get_post_meta( $variation['variation_id'], $sale_price_meta_key, true );
					if ( $sale_price ) {
						add_post_meta( $post_id, $price_meta_key, $sale_price );
					} elseif ( $base_price ) {
						add_post_meta( $post_id, $price_meta_key, $base_price );
					}
				}
			}
		}
	}



	/**
	 * Import Column Mapping: WC Importer and Exporter Plugin Integration
	 *
	 * @param array $columns Columns.
	 * @return void
	 * @since 1.1.5
	 */
	public function import_column_mapping( $columns ) {
		$roles = wholesalex()->get_roles( 'b2b_roles_option' );

		foreach ( $roles as $role ) {
			$columns[ $role['value'] . '_base_price' ] = $role['name'] . ' Base Price';
			$columns[ $role['value'] . '_sale_price' ] = $role['name'] . ' Sale Price';
		}
		return $columns;
	}

	/**
	 * Export Column Value
	 *
	 * @since 1.1.5
	 */
	public function export_column_value( $value, $product, $column_name ) {
		$id    = $product->get_id();
		$value = get_post_meta( $id, $column_name, true );

		return $value;
	}


	/**
	 * Add WholesaleX Rolewise Column to WC Exporter
	 *
	 * @param array $columns Columns.
	 * @return array
	 * @since 1.1.5
	 */
	public function add_wholesale_rolewise_column_exporter( $columns ) {
		$roles = wholesalex()->get_roles( 'b2b_roles_option' );

		foreach ( $roles as $role ) {
			$columns[ $role['value'] . '_base_price' ] = $role['name'] . ' Base Price';
			$columns[ $role['value'] . '_sale_price' ] = $role['name'] . ' Sale Price';
		}
		return $columns;
	}


	/**
	 * Variable Product WholesaleX Rolewise base and Sale Price Bulk Action Options
	 *
	 * @return void
	 */
	public function variable_product_bulk_edit_actions() {
		$wholesalex_roles = wholesalex()->get_roles('b2b_roles_option');
		
		$plugin_name = wholesalex()->get_plugin_name();
		$optiongroup_label = $plugin_name.' '.__('Rolewise Pricing','wholesalex');
		?>
		<optgroup label="<?php echo esc_html($optiongroup_label); ?>">
		<?php
		foreach ($wholesalex_roles as $role) {
			$option_name_base = $role['name'].' '.__('Base Price');
			$option_name_sale = $role['name'].' '.__('Sale Price');

			$option_value_base = 'wholesalex_product_price_'.$role['value'].'_base';
			$option_value_sale = 'wholesalex_product_price_'.$role['value'].'_sale';
			?>
				<option value="<?php echo esc_attr($option_value_base); ?>"><?php echo esc_html($option_name_base); ?></option>
				<option value="<?php echo esc_attr($option_value_sale); ?>"><?php echo esc_html($option_name_sale); ?></option>
			<?php
		}
		?>
		</optgroup>
		<?php
	}

	public function handle_wholesalex_bulk_edit_variations() {
		check_ajax_referer( 'bulk-edit-variations', 'security' );
		// Check permissions again and make sure we have what we need.
		if ( ! current_user_can( 'edit_products' ) || !isset( $_POST['product_id'] ) || !isset( $_POST['bulk_action'] ) ) {
			wp_die( -1 );
		}

		$product_id  = absint( sanitize_text_field($_POST['product_id']) );
		$bulk_action = wc_clean( wp_unslash( $_POST['bulk_action'] ) );
		$data        = ! empty( $_POST['data'] ) ? wc_clean( wp_unslash( $_POST['data'] ) ) : array();
		$variations  = array();

		
		$variations = get_posts(
			array(
				'post_parent'    => $product_id,
				'posts_per_page' => -1,
				'post_type'      => 'product_variation',
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'private' ),
			)
		);
		

		$wholesalex_roles = wholesalex()->get_roles('b2b_roles_option');

		foreach ($wholesalex_roles as $role) {
			if('wholesalex_product_price_'.$role['value'].'_base' === $bulk_action) {
				foreach ($variations as $variation_id) {
					wholesalex()->save_single_product_discount( $variation_id, array($role['value']=>array('wholesalex_base_price'=>$data['value'])) );
				}
			} elseif('wholesalex_product_price_'.$role['value'].'_sale'===$bulk_action) {
				foreach ($variations as $variation_id) {
					wholesalex()->save_single_product_discount( $variation_id, array($role['value']=>array('wholesalex_sale_price'=>$data['value'])) );
				}
			}
		}
		wp_send_json_success('Success');
	}


}
