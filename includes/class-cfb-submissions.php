<?php
/**
 * Store CF7 submissions when visual builder is active on a form.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and queries CF7 submissions for builder-enabled forms.
 */
final class CFB_Submissions {

	const CACHE_GROUP = 'cfb_entries';

	/**
	 * Register submission storage and shortcode hooks.
	 */
	public static function init() {
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'store_submission' ), 5 );
		add_shortcode( 'cfb_entries', array( __CLASS__, 'shortcode_entries' ) );
	}

	/**
	 * Cache invalidation token. Changing it busts every cached read for the entries table.
	 *
	 * @return string
	 */
	private static function cache_token() {
		$token = wp_cache_get( 'last_changed', self::CACHE_GROUP );
		if ( false === $token ) {
			$token = microtime();
			wp_cache_set( 'last_changed', $token, self::CACHE_GROUP );
		}
		return $token;
	}


	/**
	 * Save the current submission to the entries table.
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 */
	public static function store_submission( $contact_form ) {
		if ( ! $contact_form instanceof WPCF7_ContactForm ) {
			return;
		}

		$form_id = $contact_form->id();
		if ( ! CFB_Plugin::form_uses_builder( $form_id ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$data = $submission->get_posted_data();
		unset( $data['_wpcf7'], $data['_wpcf7_version'], $data['_wpcf7_locale'], $data['_wpcf7_unit_tag'], $data['_wpcf7_container_post'], $data['_cfb'], $data['_cfb_session'] );

		$draft_key = filter_input( INPUT_POST, '_cfb_draft', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$draft_key = ( is_string( $draft_key ) && '' !== $draft_key ) ? sanitize_key( $draft_key ) : '';

		self::insert(
			array(
				'form_id'   => $form_id,
				'status'    => 'unread',
				'data'      => $data,
				'device'    => self::detect_device(),
				'draft_key' => $draft_key,
			)
		);

		$session = filter_input( INPUT_POST, '_cfb_session', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$session = is_string( $session ) ? sanitize_text_field( wp_unslash( $session ) ) : '';

		CFB_Analytics::track( $form_id, 'submit', array( 'fields' => count( $data ) ), $session );
	}

	/**
	 * Insert a new entry row.
	 *
	 * @param array $args Row args.
	 * @return int|false
	 */
	public static function insert( $args ) {
		global $wpdb;

		$defaults = array(
			'form_id'    => 0,
			'status'     => 'unread',
			'data'       => array(),
			'ip_hash'    => self::hash_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'device'     => '',
			'draft_key'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$db       = $wpdb;
		$inserted = $db->insert(
			$db->prefix . 'cfb_entries',
			array(
				'form_id'    => absint( $args['form_id'] ),
				'status'     => sanitize_key( $args['status'] ),
				'data'       => wp_json_encode( $args['data'] ),
				'ip_hash'    => $args['ip_hash'],
				'user_agent' => substr( $args['user_agent'], 0, 255 ),
				'referrer'   => $args['referrer'],
				'device'     => sanitize_key( $args['device'] ),
				'draft_key'  => sanitize_key( $args['draft_key'] ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		wp_cache_set( 'last_changed', microtime(), self::CACHE_GROUP );

		return $inserted ? (int) $db->insert_id : false;
	}

	/**
	 * Fetch one entry by ID.
	 *
	 * @param int $id Entry ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$db = $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$cache_key = 'entry_' . $id . '_' . self::cache_token();
		$row       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $row ) {
			$row = $db->get_row(
				$db->prepare(
					"SELECT * FROM {$db->prefix}cfb_entries WHERE id = %d",
					$id
				)
			);
			wp_cache_set( $cache_key, $row, self::CACHE_GROUP );
		}

		return $row;
	}

	/**
	 * Format a database row for REST and admin display.
	 *
	 * @param object $row DB row.
	 * @return array
	 */
	public static function format_entry( $row ) {
		if ( ! $row ) {
			return array();
		}

		$data = json_decode( $row->data, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$form_title = '';
		$contact    = wpcf7_contact_form( (int) $row->form_id );
		if ( $contact ) {
			$form_title = $contact->title();
		}

		$fields = array();
		foreach ( $data as $key => $value ) {
			if ( 0 === strpos( (string) $key, '_' ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			$fields[] = array(
				'name'  => (string) $key,
				'label' => ucwords( str_replace( array( '-', '_' ), ' ', (string) $key ) ),
				'value' => (string) $value,
			);
		}

		return array(
			'id'         => (int) $row->id,
			'form_id'    => (int) $row->form_id,
			'form_title' => $form_title,
			'status'     => (string) $row->status,
			'device'     => (string) $row->device,
			'created_at' => (string) $row->created_at,
			'updated_at' => (string) $row->updated_at,
			'referrer'   => (string) $row->referrer,
			'user_agent' => (string) $row->user_agent,
			'fields'     => $fields,
		);
	}

	/**
	 * Count entries grouped by status.
	 *
	 * @param array $args Filter args (form_id).
	 * @return array
	 */
	public static function get_status_counts( $args = array() ) {
		global $wpdb;
		$db = $wpdb;

		$form_id   = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		$cache_key = 'status_counts_' . $form_id . '_' . self::cache_token();
		$rows      = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $rows ) {
			if ( $form_id > 0 ) {
				$rows = $db->get_results(
					$db->prepare( "SELECT status, COUNT(*) AS cnt FROM {$db->prefix}cfb_entries WHERE form_id = %d GROUP BY status", $form_id ),
					ARRAY_A
				);
			} else {
				$rows = $db->get_results(
					"SELECT status, COUNT(*) AS cnt FROM {$db->prefix}cfb_entries GROUP BY status",
					ARRAY_A
				);
			}
			wp_cache_set( $cache_key, $rows, self::CACHE_GROUP );
		}

		$counts = array(
			'unread'  => 0,
			'read'    => 0,
			'starred' => 0,
			'spam'    => 0,
			'trash'   => 0,
		);

		foreach ( $rows ? $rows : array() as $row ) {
			$status = sanitize_key( $row['status'] );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['cnt'];
			}
		}

		$counts['total'] = array_sum( $counts );

		return $counts;
	}

	/**
	 * Update an entry status.
	 *
	 * @param int    $id     Entry ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$db      = $wpdb;
		$allowed = array( 'unread', 'read', 'starred', 'spam', 'trash' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		$updated = (bool) $db->update(
			$db->prefix . 'cfb_entries',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_cache_delete( 'last_changed', self::CACHE_GROUP );

		return $updated;
	}

	/**
	 * Query entries with filters and pagination.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$db = $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'  => 0,
				'status'   => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$form_id  = (int) $args['form_id'];
		$status   = $args['status'] ? sanitize_key( $args['status'] ) : '';
		$search   = (string) $args['search'];
		$like     = '%' . $db->esc_like( $search ) . '%';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$cache_key = 'query_' . md5( wp_json_encode( array( $form_id, $status, $search, $per_page, $offset ) ) ) . '_' . self::cache_token();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// The query string is static; every filter is an optional bound placeholder so empty filters match all rows.
		$total = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM {$db->prefix}cfb_entries
				WHERE ( %d = 0 OR form_id = %d )
				AND ( %s = '' OR status = %s )
				AND ( %s = '' OR data LIKE %s )",
				$form_id,
				$form_id,
				$status,
				$status,
				$search,
				$like
			)
		);

		$items = $db->get_results(
			$db->prepare(
				"SELECT * FROM {$db->prefix}cfb_entries
				WHERE ( %d = 0 OR form_id = %d )
				AND ( %s = '' OR status = %s )
				AND ( %s = '' OR data LIKE %s )
				ORDER BY id DESC LIMIT %d OFFSET %d",
				$form_id,
				$form_id,
				$status,
				$status,
				$search,
				$like,
				$per_page,
				$offset
			)
		);

		$result = array(
			'items' => $items ? $items : array(),
			'total' => $total,
			'page'  => (int) $args['page'],
		);

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP );

		return $result;
	}

	/**
	 * Frontend entry list for logged-in users (own submissions by email).
	 *
	 * @param array $atts Shortcode atts.
	 * @return string
	 */
	public static function shortcode_entries( $atts ) {
		$atts = shortcode_atts( array( 'form_id' => 0 ), $atts, 'cfb_entries' );

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your submissions.', 'craftforms-builder' ) . '</p>';
		}

		$user  = wp_get_current_user();
		$email = $user->user_email;
		global $wpdb;
		$db = $wpdb;

		$cache_key = 'frontend_' . md5( $email ) . '_' . self::cache_token();
		$rows      = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $rows ) {
			$rows = $db->get_results(
				$db->prepare(
					"SELECT * FROM {$db->prefix}cfb_entries WHERE data LIKE %s ORDER BY id DESC LIMIT 20",
					'%' . $db->esc_like( $email ) . '%'
				)
			);
			wp_cache_set( $cache_key, $rows, self::CACHE_GROUP );
		}

		if ( ! $rows ) {
			return '<p>' . esc_html__( 'No submissions found.', 'craftforms-builder' ) . '</p>';
		}

		$html = '<table class="cfb-frontend-entries"><thead><tr><th>' . esc_html__( 'Date', 'craftforms-builder' ) . '</th><th>' . esc_html__( 'Status', 'craftforms-builder' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr><td>' . esc_html( $row->created_at ) . '</td><td>' . esc_html( $row->status ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		return $html;
	}

	/**
	 * Hash the visitor IP for privacy-preserving storage.
	 *
	 * @return string
	 */
	public static function hash_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip ? hash( 'sha256', $ip . wp_salt() ) : '';
	}

	/**
	 * Detect device class from the user agent.
	 *
	 * @return string
	 */
	private static function detect_device() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		if ( strpos( $ua, 'mobile' ) !== false ) {
			return 'mobile';
		}
		if ( strpos( $ua, 'tablet' ) !== false ) {
			return 'tablet';
		}
		return 'desktop';
	}
}
