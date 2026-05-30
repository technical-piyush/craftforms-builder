<?php
/**
 * REST API for builder schema, entries, analytics, drafts.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_REST_API {

	const NS = 'cf7vb/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

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
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function can_edit_form( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
			return true;
		}
		return new WP_Error( 'cf7vb_forbidden', __( 'Forbidden.', 'form-studio-for-contact-form-7' ), array( 'status' => 403 ) );
	}

	public static function get_schema( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$schema = CF7VB_Plugin::get_schema( $id );
		return rest_ensure_response(
			array(
				'schema'   => $schema,
				'compiled' => CF7VB_Compiler::compile( $schema ),
			)
		);
	}

	public static function update_schema( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$schema = $request->get_param( 'schema' );

		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'cf7vb_invalid_schema', __( 'Invalid schema.', 'form-studio-for-contact-form-7' ), array( 'status' => 400 ) );
		}

		$schema   = CF7VB_Parser::normalize_schema( $schema );
		$compiled = CF7VB_Compiler::compile( $schema );

		update_post_meta( $id, CF7VB_SCHEMA_META, $schema );

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

	public static function compile_preview( WP_REST_Request $request ) {
		$schema = $request->get_param( 'schema' );
		if ( ! is_array( $schema ) ) {
			$schema = CF7VB_Parser::default_schema();
		}
		return rest_ensure_response( array( 'compiled' => CF7VB_Compiler::compile( $schema ) ) );
	}

	public static function list_entries( WP_REST_Request $request ) {
		return rest_ensure_response(
			CF7VB_Submissions::query(
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

	public static function get_entry( WP_REST_Request $request ) {
		$row = CF7VB_Submissions::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'cf7vb_not_found', __( 'Entry not found.', 'form-studio-for-contact-form-7' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( CF7VB_Submissions::format_entry( $row ) );
	}

	public static function get_analytics_stats( WP_REST_Request $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		return rest_ensure_response( CF7VB_Analytics::get_stats( $form_id ) );
	}

	public static function update_entry( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! CF7VB_Submissions::update_status( $id, $status ) ) {
			return new WP_Error( 'cf7vb_update_failed', __( 'Update failed.', 'form-studio-for-contact-form-7' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function track_event( WP_REST_Request $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		$type    = sanitize_key( (string) $request->get_param( 'event_type' ) );
		$data    = $request->get_param( 'event_data' );
		$session = sanitize_text_field( (string) $request->get_param( 'session_key' ) );

		if ( ! $form_id || ! $type ) {
			return new WP_Error( 'cf7vb_invalid', __( 'Invalid event.', 'form-studio-for-contact-form-7' ), array( 'status' => 400 ) );
		}

		CF7VB_Analytics::track( $form_id, $type, is_array( $data ) ? $data : array(), $session );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function save_draft( WP_REST_Request $request ) {
		$form_id   = (int) $request->get_param( 'form_id' );
		$draft_key = sanitize_key( (string) $request->get_param( 'draft_key' ) );
		$data      = $request->get_param( 'data' );
		$email     = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! $form_id || ! is_array( $data ) ) {
			return new WP_Error( 'cf7vb_invalid', __( 'Invalid draft.', 'form-studio-for-contact-form-7' ), array( 'status' => 400 ) );
		}

		$key  = CF7VB_Autosave::save_draft( $form_id, $draft_key, $data, $email );
		$link = add_query_arg(
			array(
				'cf7vb_draft' => $key,
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

	public static function get_draft( WP_REST_Request $request ) {
		$key   = sanitize_key( (string) $request->get_param( 'draft_key' ) );
		$draft = CF7VB_Autosave::get_draft( $key );
		if ( ! $draft ) {
			return new WP_Error( 'cf7vb_not_found', __( 'Draft not found.', 'form-studio-for-contact-form-7' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $draft );
	}
}
