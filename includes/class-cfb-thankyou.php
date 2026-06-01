<?php
/**
 * Thank you page / redirect after successful submit.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Thankyou {

	public static function init() {
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'on_sent' ), 5 );
		add_filter( 'wpcf7_feedback_response', array( __CLASS__, 'feedback_response' ), 10, 2 );
	}

	/**
	 * @param WPCF7_ContactForm $contact_form Form.
	 */
	public static function on_sent( $contact_form ) {
		if ( ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return;
		}

		$schema = get_post_meta( $contact_form->id(), CFB_SCHEMA_META, true );

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		$ty     = $schema['settings']['thankYou'] ?? array();
		$rules  = CFB_Conditional::resolve_actions( $schema, $posted );

		$redirect = ! empty( $rules['redirect'] ) ? $rules['redirect'] : ( $ty['redirectUrl'] ?? '' );

		if ( $redirect ) {
			$submission->set_response( $contact_form->filter_message( $ty['message'] ?? __( 'Thank you. Redirecting…', 'craftforms-builder' ) ) );
			add_filter(
				'wpcf7_feedback_response',
				static function ( $response ) use ( $redirect, $ty ) {
					$response['cfb_redirect'] = esc_url_raw( $redirect );
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
	 * @param array $response REST/AJAX feedback payload.
	 * @param array $result   Submission result from WPCF7_ContactForm::submit() (not a contact form object).
	 * @return array
	 */
	public static function feedback_response( $response, $result ) {
		$form_id = 0;
		if ( is_array( $result ) && ! empty( $result['contact_form_id'] ) ) {
			$form_id = (int) $result['contact_form_id'];
		}

		if ( ! $form_id ) {
			return $response;
		}

		if ( ! CFB_Plugin::form_uses_builder( $form_id ) ) {
			return $response;
		}

		$schema = get_post_meta( $form_id, CFB_SCHEMA_META, true );

		$ty = $schema['settings']['thankYou'] ?? array();
		if ( ! empty( $ty['message'] ) && 'mail_sent_ok' === ( $response['status'] ?? '' ) ) {
			$response['message'] = wp_kses_post( $ty['message'] );
		}

		if ( ! empty( $ty['type'] ) && 'page' === $ty['type'] && ! empty( $ty['pageId'] ) ) {
			$url = get_permalink( (int) $ty['pageId'] );
			if ( $url ) {
				$response['cfb_redirect'] = esc_url_raw( $url );
			}
		}

		return $response;
	}
}
