<?php //phpcs:ignore
/**
 * Class WHOLESALEX_Role_CSV_Importer_Controller file.
 * Inspired By WooCommerce Core Product Import
 *
 * @package WholesaleX
 */

namespace WHOLESALEX;

use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Importer' ) ) {
	return;
}

/**
 * Product importer controller - handles file upload and forms in admin.
 *
 * @package     WholesaleX
 * @version     1.2.9
 */
class WHOLESALEX_Role_CSV_Importer_Controller {

	/**
	 * The path to the current file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * The current import step.
	 *
	 * @var string
	 */
	protected $step = '';

	/**
	 * Progress steps.
	 *
	 * @var array
	 */
	protected $steps = array();

	/**
	 * Errors.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * The current delimiter for the file being read.
	 *
	 * @var string
	 */
	protected $delimiter = ',';

	/**
	 * Whether to use previous mapping selections.
	 *
	 * @var bool
	 */
	protected $map_preferences = false;

	/**
	 * Whether to skip existing roles.
	 *
	 * @var bool
	 */
	protected $update_existing = false;

	/**
	 * The character encoding to use to interpret the input file, or empty string for autodetect.
	 *
	 * @var string
	 */
	protected $character_encoding = 'UTF-8';


	/**
	 * Get importer instance.
	 *
	 * @param  string $file File to import.
	 * @param  array  $args Importer arguments.
	 * @return WHOLESALEX_Role_CSV_Importer
	 */
	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'wholesalex_role_csv_importer_class', '\WHOLESALEX\WHOLESALEX_Role_CSV_Importer' );
		$args           = apply_filters( 'wholesalex_role_csv_importer_args', $args, $importer_class );

		return new $importer_class( $file, $args );
	}

	/**
	 * Check whether a file is a valid CSV file.
	 *
	 * @param string $file File path.
	 * @param bool   $check_path Whether to also check the file is located in a valid location (Default: true).
	 * @return bool
	 */
	public static function is_file_valid_csv( $file, $check_path = true ) {
		return wc_is_file_valid_csv( $file, $check_path );
	}

	/**
	 * Get all the valid filetypes for a CSV file.
	 *
	 * @return array
	 */
	protected static function get_valid_csv_filetypes() {
		return apply_filters(
			'wholesalex_csv_role_import_valid_filetypes',
			array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			)
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wholesalex_role_import_upload_file', array( $this, 'handle_file_upload' ) );
		add_action( 'wp_ajax_wholesalex_role_run_importer', array( $this, 'handle_import' ) );
		add_action( 'wp_ajax_wholesalex_do_ajax_role_import', array( $this, 'do_ajax_role_import' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$this->file               = isset( $_REQUEST['file'] ) ? wc_clean( wp_unslash( $_REQUEST['file'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->update_existing    = isset( $_REQUEST['update_existing'] ) ? 'yes' === wc_clean($_REQUEST['update_existing']) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->delimiter          = ! empty( $_REQUEST['delimiter'] ) ? wc_clean( wp_unslash( $_REQUEST['delimiter'] ) ) : ','; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->map_preferences    = isset( $_REQUEST['map_preferences'] ) ? (bool) wc_clean($_REQUEST['map_preferences']) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->character_encoding = isset( $_REQUEST['character_encoding'] ) ? wc_clean( wp_unslash( $_REQUEST['character_encoding'] ) ) : 'UTF-8'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $this->map_preferences ) {
			add_filter( 'wholesalex_csv_role_import_mapped_columns', array( $this, 'auto_map_user_preferences' ), 9999 );
		}
	}


	/**
	 * Dispatch current step and show correct view.
	 */
	public function dispatch() {
		// phpcs:ignore WordPress.Security.NonceVerification.MissingW

	}


	/**
	 * Handles the CSV upload and initial parsing of the file to prepare for
	 * displaying author import options.
	 *
	 * @return string|WP_Error
	 */
	public function handle_upload() {
        if ( ! ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified in WHOLESALEX_Role_CSV_Importer_Controller::upload_form_handler()
		$file_url = isset( $_POST['file_url'] ) ? wc_clean( wp_unslash( $_POST['file_url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $file_url ) ) {
			if ( ! isset( $_FILES['import'] ) ) {
				return new WP_Error( 'wholesalex_role_csv_importer_upload_file_empty', __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', 'wholesalex' ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! self::is_file_valid_csv( wc_clean( wp_unslash( $_FILES['import']['name'] ) ), false ) ) { // phpcs:ignore
				return new WP_Error( 'wholesalex_role_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'wholesalex' ) );
			}

			$overrides = array(
				'test_form' => false,
				'mimes'     => self::get_valid_csv_filetypes(),
			);
			$import    = $_FILES['import']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$upload    = wp_handle_upload( $import, $overrides );

			if ( isset( $upload['error'] ) ) {
				return new WP_Error( 'wholesalex_role_csv_importer_upload_error', $upload['error'] );
			}

			// Construct the object array.
			$object = array(
				'post_title'     => basename( $upload['file'] ),
				'post_content'   => $upload['url'],
				'post_mime_type' => $upload['type'],
				'guid'           => $upload['url'],
				'context'        => 'import',
				'post_status'    => 'private',
			);

			// Save the data.
			$id = wp_insert_attachment( $object, $upload['file'] );

			/*
			 * Schedule a cleanup for one day from now in case of failed
			 * import or missing wp_import_cleanup() call.
			 */
			wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );

			return $upload['file'];
		} elseif (
			( 0 === stripos( realpath( ABSPATH . $file_url ), ABSPATH ) ) &&
			file_exists( ABSPATH . $file_url )
		) {
			if ( ! self::is_file_valid_csv( ABSPATH . $file_url ) ) {
				return new WP_Error( 'wholesalex_role_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'wholesalex' ) );
			}

			return ABSPATH . $file_url;
		}
		// phpcs:enable

		return new WP_Error( 'wholesalex_role_csv_importer_upload_invalid_file', __( 'Please upload or provide the link to a valid CSV file.', 'wholesalex' ) );
	}


	/**
	 * Columns to normalize.
	 *
	 * @param  array $columns List of columns names and keys.
	 * @return array
	 */
	protected function normalize_columns_names( $columns ) {
		$normalized = array();

		foreach ( $columns as $key => $value ) {
			$normalized[ strtolower( $key ) ] = $value;
		}

		return $normalized;
	}

	/**
	 * Auto map column names.
	 *
	 * @param  array $raw_headers Raw header columns.
	 * @param  bool  $num_indexes If should use numbers or raw header columns as indexes.
	 * @return array
	 */
	protected function auto_map_columns( $raw_headers, $num_indexes = true ) {

		$default_columns = $this->normalize_columns_names(
			apply_filters(
				'wholesalex_csv_role_import_mapping_default_columns',
				array(
					__( 'ID', 'wholesalex' )               => 'id',
					__( 'Role Title', 'wholesalex' )       => '_role_title',
					__( 'Shipping Methods', 'wholesalex' ) => '_shipping_methods',
					__( 'Payment Methods', 'wholesalex' )  => '_payment_methods',
					__( 'Disable Coupon', 'wholesalex' )   => '_disable_coupon',
					__( 'Auto Role Migration', 'wholesalex' ) => '_auto_role_migration',
					__( 'Role Migration Threshold Value', 'wholesalex' ) => '_role_migration_threshold_value',
				),
				$raw_headers
			)
		);
		$headers         = array();
		foreach ( $raw_headers as $key => $field ) {
			$normalized_field  = strtolower( $field );
			$index             = $num_indexes ? $key : $field;
			$headers[ $index ] = $normalized_field;

			if ( isset( $default_columns[ $normalized_field ] ) ) {
				$headers[ $index ] = $default_columns[ $normalized_field ];
			}
		}

		return apply_filters( 'wholesalex_csv_role_import_mapped_columns', $headers, $raw_headers );
	}

	/**
	 * Map columns using the user's latest import mappings.
	 *
	 * @param  array $headers Header columns.
	 * @return array
	 */
	public function auto_map_user_preferences( $headers ) {
		$mapping_preferences = get_user_option( 'wholesalex_role_import_mapping' );

		if ( ! empty( $mapping_preferences ) && is_array( $mapping_preferences ) ) {
			return $mapping_preferences;
		}

		return $headers;
	}

	/**
	 * Sanitize special column name regex.
	 *
	 * @param  string $value Raw special column name.
	 * @return string
	 */
	protected function sanitize_special_column_name_regex( $value ) {
		return '/' . str_replace( array( '%d', '%s' ), '(.*)', trim( quotemeta( $value ) ) ) . '/i';
	}


	/**
	 * Get mapping options.
	 *
	 * @param  string $item Item name.
	 * @return array
	 */
	protected function get_mapping_options( $item = '' ) {
		$options = array(
			'id'                              => __( 'ID', 'wholesalex' ),
			'_role_title'                     => __( 'Role Title', 'wholesalex' ),
			'_shipping_methods'               => __( 'Shipping Methods', 'wholesalex' ),
			'_payment_methods'                => __( 'Payment Methods', 'wholesalex' ),
			'_disable_coupon'                 => __( 'Disable Coupon', 'wholesalex' ),
			'_auto_role_migration'            => __( 'Auto Role Migration', 'wholesalex' ),
			'_role_migration_threshold_value' => __( 'Role Migration Threshold Value', 'wholesalex' ),
		);

		return apply_filters( 'wholesalex_csv_role_import_mapping_options', $options, $item );
	}


	/**
	 * Handle File Upload
	 *
	 * @return void
	 */
	public function handle_file_upload() {
		if ( ! ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}

        if( !current_user_can( 'manage_options' )) {
            return;
        }

		$response_data = array(
			'status'  => false,
			'message' => '',
		);
		$file          = $this->handle_upload();

		if ( is_wp_error( $file ) ) {
			$response_data['message'] = $file->get_error_message();
		} else {
			$this->file    = $file;
			$response_data = array(
				'status'  => true,
				'message' => 'Success',
			);
			$args          = array(
				'lines'              => 1,
				'delimiter'          => $this->delimiter,
				'character_encoding' => $this->character_encoding,
				'update_existing'    => isset( $_POST['update_existing'] ) && 'yes' === wc_clean($_POST['update_existing']),
			);

			$importer                         = self::get_importer( $this->file, $args );
			$headers                          = $importer->get_raw_keys();
			$response_data['args']            = $args;
			$response_data['headers']         = $headers;
			$response_data['mapped_items']    = $this->auto_map_columns( $headers );
			$response_data['sample']          = current( $importer->get_raw_data() );
			$response_data['mapping_options'] = $this->get_mapping_options();
			$response_data['file']            = $this->file;
		}

		wp_send_json( $response_data );
	}


	/**
	 * Handle Import
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}
        if( !current_user_can( 'manage_options' )) {
            return;
        }
		$response_data = array(
			'status'  => false,
			'message' => '',
		);
		$this->file    = isset( $_POST['file'] ) ? sanitize_text_field( $_POST['file'] ) : '';

		if ( ! self::is_file_valid_csv( $this->file ) ) {
			$response_data['message'] = __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'wholesalex' );
		}

		if ( ! is_file( $this->file ) ) {
			$response_data['message'] = __( 'The file does not exist, please try again.', 'wholesalex' );
		}

		if ( ! empty( $_POST['map_from'] ) && ! empty( $_POST['map_to'] ) ) {
			$mapping_from = wc_clean( wp_unslash( $_POST['map_from'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$mapping_to   = wc_clean( wp_unslash( $_POST['map_to'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Save mapping preferences for future imports.
			update_user_option( get_current_user_id(), 'wholesalex_role_import_mapping', $mapping_to );
		} else {
			$response_data['message'] = __( 'Error Occured!', 'wholesalex' );
			wp_send_json( $response_data );
		}
		$response_data['status']             = true;
		$response_data['mapping']            = array(
			'from' => array_values( $mapping_from ),
			'to'   => array_values( $mapping_to ),
		);
		$response_data['file']               = $this->file;
		$response_data['delimiter']          = $this->delimiter;
		$response_data['character_encoding'] = $this->character_encoding;

		wp_send_json( $response_data );

	}


	/**
	 * Import Role
	 *
	 * @return void
	 */
	public function do_ajax_role_import() {
		if ( ! ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wholesalex-registration' ) ) ) {
			return;
		}
        if( !current_user_can( 'manage_options' )) {
            return;
        }

		include_once WHOLESALEX_PATH . 'includes/import/class-wholesalex-role-csv-importer.php';
		$file   = wc_clean( wp_unslash( $_POST['file'] ) ); // phpcs:ignore 
		$params = array(
			'delimiter'          => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'start_pos'          => isset( $_POST['position'] ) ? absint( wc_clean($_POST['position']) ) : 0, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'mapping'            => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'update_existing'    => isset( $_POST['update_existing'] ) ? 'yes' === wc_clean($_POST['update_existing']) : false, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'character_encoding' => isset( $_POST['character_encoding'] ) ? wc_clean( wp_unslash( $_POST['character_encoding'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			/**
			 * Batch size for the wholesalex role import process.
			 *
			 * @param int $size Batch size.
			 *
			 * @since
			 */
			'lines'              => apply_filters( 'wholesalex_role_import_batch_size', 30 ),
			'parse'              => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'wholesalex_role_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer = self::get_importer( $file, $params );

		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'wholesalex_role_import_error_log', $error_log );

		$response_data = array(
			'status'     => true,
			'position'   => $importer->get_file_position(),
			'percentage' => $percent_complete,
			'imported'   => count( $results['imported'] ),
			'failed'     => count( $results['failed'] ),
			'updated'    => count( $results['updated'] ),
			'skipped'    => count( $results['skipped'] ),
		);

		if ( 100 === $percent_complete ) {

			$__roles = array_values( wholesalex()->get_roles() );
			if ( empty( $__roles ) ) {
				$__roles = array(
					array(
						'id'    => 1,
						'label' => 'New Role',
					),
				);
			}

			$errors          = array_filter( (array) get_user_option( 'wholesalex_role_import_error_log' ) );
			$error_json_data = array();
			if ( count( $errors ) ) {
				foreach ( $errors as $error ) {
					if ( is_wp_error( $error ) ) {

						$error_data = $error->get_error_data();

						$error_json_data[] = array(
							'id'      => esc_html( $error_data['id'] ),
							'message' => wp_kses_post( $error->get_error_message() ),
						);

					}
				}
			}
			$response_data['position']   = 'done';
			$response_data['percentage'] = 100;
			$response_data['errors']     = $error_json_data;

			$response_data['updated_roles'] = $__roles;
			// Send success.
			wp_send_json( $response_data );
		} else {
			wp_send_json( $response_data );
		}
	}

}
