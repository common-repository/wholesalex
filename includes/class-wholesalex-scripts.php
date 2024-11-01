<?php
/**
 * WholesaleX Scripts
 *
 * @package wholesalex
 * @since 1.0.0
 */

namespace WHOLESALEX;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WholesaleX Scripts Class
 */
class Scripts {

	/**
	 * Contains all wholesalex pages slug
	 *
	 * @var array
	 */
	public $wholesalex_pages = array(
		'wholesalex-settings'      => 'wholesalex_settings',
		'wholesalex-users'         => 'wholesalex_header',
		'wholesalex-addons'        => 'wholesalex_header',
		'wholesalex_role'          => 'wholesalex_roles',
		'wholesalex-email'         => 'wholesalex_header',
		'wholesalex_dynamic_rules' => 'wholesalex_dynamic_rules',
		'wholesalex-registration'  => 'wholesalex_form_builder',
		'wsx_conversation'         => 'wholesalex_header',
		'wholesalex-overview'      => 'wholesalex_overview',
		'wholesalex-setup-wizard'  => 'wholesalex_wizard',
	);
	/**
	 * Register all scripts
	 */
	public static function register_backend_scripts() {
		$register_scripts = apply_filters(
			'wholesalex_register_backend_scripts',
			array(
				'wholesalex_node_vendors'  => array(
					'src'       => WHOLESALEX_URL . 'assets/js/node_vendors.js',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_components'    => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_components.js',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_category'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_cat.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_dynamic_rules' => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_dr.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_overview'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_overview.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors', 'wc-components', 'wp-components', 'wp-element','lodash', 'moment','wc-date' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_product'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_product.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_profile'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_profile.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_form_builder'  => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_regi_builder.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors','wp-components' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_form'          => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_regi_form.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_roles'         => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_roles.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_settings'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_settings.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_wizard'        => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_wizard.js',
					'deps'      => array( 'wp-i18n', 'wp-api-fetch', 'wholesalex','react', 'react-dom' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_header'        => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_header.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wholesalex_node_vendors', 'wholesalex_components' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex-builder'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_wallet.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex-admin.js',
					'deps'      => array( 'jquery', 'wp-i18n' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_user_import_export'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_user_import_export.js',
					'deps'      => array('react', 'react-dom','wp-polyfill', 'wp-api-fetch'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_features'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_features.js',
					'deps'      => array('react', 'react-dom'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_help_center'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_help_center.js',
					'deps'      => array('react', 'react-dom'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_support'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_support.js',
					'deps'      => array('react', 'react-dom','wp-polyfill', 'wp-api-fetch'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_addons'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_addons.js',
					'deps'      => array('react', 'react-dom','wp-polyfill', 'wp-api-fetch'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_email_templates'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_email_templates.js',
					'deps'      => array('react', 'react-dom','wp-polyfill', 'wp-api-fetch'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'whx_users'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_users.js',
					'deps'      => array('react', 'react-dom','wp-polyfill', 'wp-api-fetch'),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				)
			)
		);
		foreach ( $register_scripts as $handle => $args ) {
			wp_register_script( $handle, $args['src'], $args['deps'], $args['ver'], $args['in_footer'] );
			wp_set_script_translations( $handle, 'wholesalex', WHOLESALEX_PATH . 'languages' );
		}

	}
	/**
	 * Register all scripts
	 */
	public static function register_frontend_scripts() {
		$register_scripts = apply_filters(
			'wholesalex_register_frontend_scripts',
			array(
				'wholesalex_node_vendors'  => array(
					'src'       => WHOLESALEX_URL . 'assets/js/node_vendors.js',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_components'    => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_components.js',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_category'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_cat.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_dynamic_rules' => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_dr.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_overview'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_overview.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_product'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_product.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_profile'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_profile.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_form_builder'  => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_regi_builder.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_form'          => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_regi_form.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_roles'         => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_roles.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_settings'      => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_settings.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch', 'wholesalex_components', 'wholesalex_node_vendors' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_wizard'        => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_wizard.js',
					'deps'      => array( 'wp-i18n', 'wp-api-fetch', 'wholesalex','react', 'react-dom' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex_header'        => array(
					'src'       => WHOLESALEX_URL . 'assets/js/whx_header.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wholesalex_node_vendors', 'wholesalex_components' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex-builder'       => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex_wallet.js',
					'deps'      => array( 'react', 'react-dom', 'wp-i18n', 'wp-polyfill', 'wp-api-fetch' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
				'wholesalex'               => array(
					'src'       => WHOLESALEX_URL . 'assets/js/wholesalex-public.js',
					'deps'      => array( 'jquery', 'wp-i18n' ),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => true,
				),
			)
		);
		foreach ( $register_scripts as $handle => $args ) {
			wp_register_script( $handle, $args['src'], $args['deps'], $args['ver'], $args['in_footer'] );
			wp_set_script_translations( $handle, 'wholesalex', WHOLESALEX_PATH . 'languages/' );
		}

	}

	/**
	 * Register All Styles
	 *
	 * @return void
	 */
	public static function register_styles() {
		$register_scripts = apply_filters(
			'wholesalex_register_styles',
			array(
				'wholesalex' => array(
					'src'       => WHOLESALEX_URL . 'assets/css/wholesalex-admin.css',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => false,
				),
				'wholesalex' => array(
					'src'       => WHOLESALEX_URL . 'assets/css/wholesalex-public.css',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => false,
				),

			)
		);
		foreach ( $register_scripts as $handle => $args ) {
			wp_register_style( $handle, $args['src'], $args['deps'], $args['ver'], $args['in_footer'] );
		}

	}
	/**
	 * Register Frontend Styles
	 *
	 * @return void
	 */
	public static function register_fronend_style() {
		$register_scripts = apply_filters(
			'wholesalex_register_frontend_styles',
			array(
				'wholesalex' => array(
					'src'       => WHOLESALEX_URL . 'assets/css/wholesalex-public.css',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => false,
				),
			)
		);
		foreach ( $register_scripts as $handle => $args ) {
			wp_register_style( $handle, $args['src'], $args['deps'], $args['ver'], $args['in_footer'] );
		}

	}

	/**
	 * Register Backend Style
	 *
	 * @return void
	 */
	public static function register_backend_style() {
		$register_scripts = apply_filters(
			'wholesalex_register_backend_styles',
			array(
				'wholesalex' => array(
					'src'       => WHOLESALEX_URL . 'assets/css/wholesalex-admin.css',
					'deps'      => array(),
					'ver'       => WHOLESALEX_VER,
					'in_footer' => false,
				),
			)
		);
		foreach ( $register_scripts as $handle => $args ) {
			wp_register_style( $handle, $args['src'], $args['deps'], $args['ver'], $args['in_footer'] );
		}

	}


}
