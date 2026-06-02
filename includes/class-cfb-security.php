<?php
/**
 * Honeypot, rate limiting, spam checks.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honeypot, rate limiting, and spam checks.
 */
final class CFB_Security {

	/**
	 * Register submission validation hooks.
	 */
	public static function init() {
		add_filter( 'wpcf7_validate', array( __CLASS__, 'validate_spam' ), 5, 1 );
	}

	/**
	 * Reject submissions that fail honeypot or rate-limit checks.
	 *
	 * @param WPCF7_Validation $result Result.
	 * @return WPCF7_Validation
	 */
	public static function validate_spam( $result ) {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return $result;
		}

		$form = $submission->get_contact_form();
		if ( ! $form ) {
			return $result;
		}

		if ( ! CFB_Plugin::form_uses_builder( $form->id() ) ) {
			return $result;
		}

		$schema = get_post_meta( $form->id(), CFB_SCHEMA_META, true );

		$security = $schema['settings']['security'] ?? array();
		$posted   = $submission->get_posted_data();

		if ( ! empty( $security['honeypot'] ) ) {
			$hp = $security['honeypotField'] ?? 'cfb_hp';
			if ( ! empty( $posted[ $hp ] ) ) {
				$result->invalidate( null, __( 'Spam detected.', 'craftforms-builder' ) );
				return $result;
			}
		}

		if ( ! empty( $security['rateLimit'] ) ) {
			$limit  = max( 1, (int) ( $security['rateLimitCount'] ?? 5 ) );
			$window = max( 60, (int) ( $security['rateLimitWindow'] ?? 3600 ) );
			$key    = 'cfb_rl_' . $form->id() . '_' . CFB_Submissions::hash_ip();
			$count  = (int) get_transient( $key );
			if ( $count >= $limit ) {
				$result->invalidate( null, __( 'Too many submissions. Please try again later.', 'craftforms-builder' ) );
				return $result;
			}
			set_transient( $key, $count + 1, $window );
			self::remember_rate_limit_key( $key );
		}

		return $result;
	}

	/**
	 * Track rate-limit transient keys so uninstall can remove them without a direct DB query.
	 *
	 * @param string $cfb_key Transient name.
	 */
	private static function remember_rate_limit_key( $cfb_key ) {
		$cfb_key = sanitize_key( $cfb_key );
		if ( '' === $cfb_key ) {
			return;
		}

		$cfb_keys = get_option( 'cfb_rate_limit_keys', array() );
		if ( ! is_array( $cfb_keys ) ) {
			$cfb_keys = array();
		}

		if ( ! in_array( $cfb_key, $cfb_keys, true ) ) {
			$cfb_keys[] = $cfb_key;
			update_option( 'cfb_rate_limit_keys', $cfb_keys, false );
		}
	}
}
