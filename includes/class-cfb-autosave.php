<?php
/**
 * Autosave and save & continue later (drafts).
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autosave and save-and-continue-later draft storage.
 */
final class CFB_Autosave {

	const CACHE_GROUP = 'cfb_drafts';

	/**
	 * Placeholder init (REST routes live in CFB_REST_API).
	 */
	public static function init() {
		// REST routes registered in CFB_REST_API.
	}

	/**
	 * Cache invalidation token. Changing it busts every cached draft read.
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
	 * Insert or update a draft row and return its key.
	 *
	 * @param int    $form_id  Form ID.
	 * @param string $draft_key Draft key.
	 * @param array  $data     Field data.
	 * @param string $email    Optional email for resume link.
	 * @return string Draft key.
	 */
	public static function save_draft( $form_id, $draft_key, $data, $email = '' ) {
		global $wpdb;
		$db = $wpdb;

		$table   = $db->prefix . 'cfb_drafts';
		$key     = $draft_key ? sanitize_key( $draft_key ) : wp_generate_password( 32, false );
		$expires = gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS );

		$existing_cache = 'draft_id_' . $key . '_' . self::cache_token();
		$existing       = wp_cache_get( $existing_cache, self::CACHE_GROUP );
		if ( false === $existing ) {
			$existing = $db->get_var( $db->prepare( "SELECT id FROM {$db->prefix}cfb_drafts WHERE draft_key = %s", $key ) );
			wp_cache_set( $existing_cache, $existing, self::CACHE_GROUP );
		}

		$row = array(
			'form_id'    => absint( $form_id ),
			'draft_key'  => $key,
			'data'       => wp_json_encode( $data ),
			'email'      => sanitize_email( $email ),
			'expires_at' => $expires,
			'updated_at' => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$db->update( $table, $row, array( 'id' => $existing ), array( '%d', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$db->insert( $table, $row, array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
		}

		wp_cache_delete( 'last_changed', self::CACHE_GROUP );

		return $key;
	}

	/**
	 * Load a non-expired draft by key.
	 *
	 * @param string $draft_key Draft key.
	 * @return array|null
	 */
	public static function get_draft( $draft_key ) {
		global $wpdb;
		$db = $wpdb;

		$key       = sanitize_key( $draft_key );
		$cache_key = 'draft_' . $key . '_' . self::cache_token();
		$row       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $row ) {
			$row = $db->get_row( $db->prepare( "SELECT * FROM {$db->prefix}cfb_drafts WHERE draft_key = %s AND (expires_at IS NULL OR expires_at > %s)", $key, current_time( 'mysql' ) ) );
			wp_cache_set( $cache_key, $row, self::CACHE_GROUP );
		}

		if ( ! $row ) {
			return null;
		}

		return array(
			'form_id'   => (int) $row->form_id,
			'draft_key' => $row->draft_key,
			'data'      => json_decode( $row->data, true ),
			'email'     => $row->email,
		);
	}
}
