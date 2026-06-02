<?php
/**
 * REST API for builder schema, entries, analytics, drafts.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for builder schema, entries, analytics, and drafts.
 */
final class CFB_REST_API {

	const NS = 'cfb/v1';

	/**
	 * Register REST routes on rest_api_init.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all CraftForms REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/forms/(?P<id>\d+)/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_schema' ),
					'permission_callback' => array( __CLASS__, 'can_edit_form' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_schema' ),
					'permission_callback' => array( __CLASS__, 'can_edit_form' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/(?P<id>\d+)/compile',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'compile_preview' ),
				'permission_callback' => array( __CLASS__, 'can_edit_form' ),
			)
		);

		register_rest_route(
			self::NS,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_entries' ),
				'permission_callback' => static function () {
					return current_user_can( 'wpcf7_read_contact_forms' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/entries/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_entry' ),
					'permission_callback' => static function () {
						return current_user_can( 'wpcf7_read_contact_forms' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_entry' ),
					'permission_callback' => static function () {
						return current_user_can( 'wpcf7_read_contact_forms' );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/analytics/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_analytics_stats' ),
				'permission_callback' => static function () {
					return current_user_can( 'wpcf7_read_contact_forms' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/analytics/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'track_event' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/drafts',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_draft' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_draft' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Permission check for schema read/write routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function can_edit_form( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
			return true;
		}
		return new WP_Error( 'cfb_forbidden', __( 'Forbidden.', 'craftforms-builder' ), array( 'status' => 403 ) );
	}

	/**
	 * GET /forms/{id}/schema — return schema and compiled markup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_schema( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$schema = CFB_Plugin::get_schema( $id );
		return rest_ensure_response(
			array(
				'schema'   => $schema,
				'compiled' => CFB_Compiler::compile( $schema ),
			)
		);
	}

	/**
	 * PUT /forms/{id}/schema — save schema and update CF7 form body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_schema( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$schema = $request->get_param( 'schema' );

		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'cfb_invalid_schema', __( 'Invalid schema.', 'craftforms-builder' ), array( 'status' => 400 ) );
		}

		$schema   = CFB_Parser::normalize_schema( $schema );
		$compiled = CFB_Compiler::compile( $schema );

		update_post_meta( $id, CFB_SCHEMA_META, $schema );

		$contact_form = wpcf7_contact_form( $id );
		if ( $contact_form ) {
			$props         = $contact_form->get_properties();
			$props['form'] = wpcf7_sanitize_form( $compiled );
			$contact_form->set_properties( $props );
			$contact_form->save();
		}

		return rest_ensure_response(
			array(
				'schema'   => $schema,
				'compiled' => $compiled,
			)
		);
	}

	/**
	 * POST /forms/{id}/compile — return compiled markup for a schema payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function compile_preview( WP_REST_Request $request ) {
		$schema = $request->get_param( 'schema' );
		if ( ! is_array( $schema ) ) {
			$schema = CFB_Parser::default_schema();
		}
		return rest_ensure_response( array( 'compiled' => CFB_Compiler::compile( $schema ) ) );
	}

	/**
	 * GET /entries — list entries with filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_entries( WP_REST_Request $request ) {
		return rest_ensure_response(
			CFB_Submissions::query(
				array(
					'form_id'  => (int) $request->get_param( 'form_id' ),
					'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
					'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
					'page'     => max( 1, (int) $request->get_param( 'page' ) ),
					'per_page' => 20,
				)
			)
		);
	}

	/**
	 * GET /entries/{id} — return one formatted entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_entry( WP_REST_Request $request ) {
		$row = CFB_Submissions::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'cfb_not_found', __( 'Entry not found.', 'craftforms-builder' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( CFB_Submissions::format_entry( $row ) );
	}

	/**
	 * GET /analytics/stats — aggregate analytics for a form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_analytics_stats( WP_REST_Request $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		return rest_ensure_response( CFB_Analytics::get_stats( $form_id ) );
	}

	/**
	 * PATCH /entries/{id} — update entry status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_entry( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! CFB_Submissions::update_status( $id, $status ) ) {
			return new WP_Error( 'cfb_update_failed', __( 'Update failed.', 'craftforms-builder' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /analytics/track — record a frontend analytics event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function track_event( WP_REST_Request $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		$type    = sanitize_key( (string) $request->get_param( 'event_type' ) );
		$data    = $request->get_param( 'event_data' );
		$session = sanitize_text_field( (string) $request->get_param( 'session_key' ) );

		if ( ! $form_id || ! $type ) {
			return new WP_Error( 'cfb_invalid', __( 'Invalid event.', 'craftforms-builder' ), array( 'status' => 400 ) );
		}

		$event_data = is_array( $data ) ? CFB_Parser::sanitize_posted_data( $data ) : array();
		CFB_Analytics::track( $form_id, $type, $event_data, $session );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * POST /drafts — create or update a save-and-continue draft.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_draft( WP_REST_Request $request ) {
		$form_id   = (int) $request->get_param( 'form_id' );
		$draft_key = sanitize_key( (string) $request->get_param( 'draft_key' ) );
		$data      = $request->get_param( 'data' );
		$email     = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! $form_id || ! is_array( $data ) ) {
			return new WP_Error( 'cfb_invalid', __( 'Invalid draft.', 'craftforms-builder' ), array( 'status' => 400 ) );
		}

		$data = CFB_Parser::sanitize_posted_data( $data );
		$key  = CFB_Autosave::save_draft( $form_id, $draft_key, $data, $email );
		$link = add_query_arg(
			array(
				'cfb_draft' => $key,
			),
			get_permalink()
		);

		return rest_ensure_response(
			array(
				'draft_key'  => $key,
				'resume_url' => $link,
			)
		);
	}

	/**
	 * GET /drafts — load draft field data by key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_draft( WP_REST_Request $request ) {
		$key   = sanitize_key( (string) $request->get_param( 'draft_key' ) );
		$draft = CFB_Autosave::get_draft( $key );
		if ( ! $draft ) {
			return new WP_Error( 'cfb_not_found', __( 'Draft not found.', 'craftforms-builder' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $draft );
	}
}
