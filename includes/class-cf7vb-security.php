<?php
/**
 * Honeypot, rate limiting, spam checks.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Security {

	public static function init() {
		add_filter( 'wpcf7_validate', array( __CLASS__, 'validate_spam' ), 5, 1 );
	}

	/**
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

		$schema = get_post_meta( $form->id(), CF7VB_SCHEMA_META, true );
		if ( ! is_array( $schema ) ) {
			return $result;
		}

		$security = $schema['settings']['security'] ?? array();
		$posted   = $submission->get_posted_data();

		if ( ! empty( $security['honeypot'] ) ) {
			$hp = $security['honeypotField'] ?? 'cf7vb_hp';
			if ( ! empty( $posted[ $hp ] ) ) {
				$result->invalidate( null, __( 'Spam detected.', 'form-studio-for-contact-form-7' ) );
				return $result;
			}
		}

		if ( ! empty( $security['rateLimit'] ) ) {
			$limit  = max( 1, (int) ( $security['rateLimitCount'] ?? 5 ) );
			$window = max( 60, (int) ( $security['rateLimitWindow'] ?? 3600 ) );
			$key    = 'cf7vb_rl_' . $form->id() . '_' . CF7VB_Submissions::hash_ip();
			$count  = (int) get_transient( $key );
			if ( $count >= $limit ) {
				$result->invalidate( null, __( 'Too many submissions. Please try again later.', 'form-studio-for-contact-form-7' ) );
				return $result;
			}
			set_transient( $key, $count + 1, $window );
		}

		return $result;
	}
}
