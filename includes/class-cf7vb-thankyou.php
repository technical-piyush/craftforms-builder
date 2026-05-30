<?php
/**
 * Thank you page / redirect after successful submit.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Thankyou {

	public static function init() {
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'on_sent' ), 5 );
		add_filter( 'wpcf7_feedback_response', array( __CLASS__, 'feedback_response' ), 10, 2 );
	}

	/**
	 * @param WPCF7_ContactForm $contact_form Form.
	 */
	public static function on_sent( $contact_form ) {
		$schema = get_post_meta( $contact_form->id(), CF7VB_SCHEMA_META, true );
		if ( ! is_array( $schema ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		$ty     = $schema['settings']['thankYou'] ?? array();
		$rules  = CF7VB_Conditional::resolve_actions( $schema, $posted );

		$redirect = ! empty( $rules['redirect'] ) ? $rules['redirect'] : ( $ty['redirectUrl'] ?? '' );

		if ( $redirect ) {
			$submission->set_response( $contact_form->filter_message( $ty['message'] ?? __( 'Thank you. Redirecting…', 'form-studio-for-contact-form-7' ) ) );
			add_filter(
				'wpcf7_feedback_response',
				static function ( $response ) use ( $redirect, $ty ) {
					$response['cf7vb_redirect'] = esc_url_raw( $redirect );
					if ( ! empty( $ty['message'] ) ) {
						$response['message'] = wp_kses_post( $ty['message'] );
					}
					return $response;
				},
				20
			);
		}
	}

	/**
	 * @param array             $response Response.
	 * @param WPCF7_ContactForm $form   Form.
	 * @return array
	 */
	public static function feedback_response( $response, $form ) {
		$schema = get_post_meta( $form->id(), CF7VB_SCHEMA_META, true );
		if ( ! is_array( $schema ) ) {
			return $response;
		}

		$ty = $schema['settings']['thankYou'] ?? array();
		if ( ! empty( $ty['message'] ) && 'mail_sent_ok' === ( $response['status'] ?? '' ) ) {
			$response['message'] = wp_kses_post( $ty['message'] );
		}

		if ( ! empty( $ty['type'] ) && 'page' === $ty['type'] && ! empty( $ty['pageId'] ) ) {
			$url = get_permalink( (int) $ty['pageId'] );
			if ( $url ) {
				$response['cf7vb_redirect'] = esc_url_raw( $url );
			}
		}

		return $response;
	}
}
