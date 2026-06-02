<?php
/**
 * Conditional emails and HTML template overrides.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional emails and HTML template overrides.
 */
final class CFB_Email {

	/**
	 * Register mail component filters and hooks.
	 */
	public static function init() {
		add_filter( 'wpcf7_mail_components', array( __CLASS__, 'filter_mail' ), 20, 2 );
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'maybe_send_conditional' ), 15 );
	}

	/**
	 * Apply HTML template wrapper to outgoing mail.
	 *
	 * @param array             $components Mail components.
	 * @param WPCF7_ContactForm $form       Form.
	 * @return array
	 */
	public static function filter_mail( $components, $form ) {
		if ( ! $form instanceof WPCF7_ContactForm || ! CFB_Plugin::form_uses_builder( $form->id() ) ) {
			return $components;
		}

		$schema = get_post_meta( $form->id(), CFB_SCHEMA_META, true );

		$emails = $schema['settings']['emails'] ?? array();
		if ( empty( $emails['htmlTemplate'] ) ) {
			return $components;
		}

		$body = $components['body'] ?? '';
		if ( ! empty( $emails['htmlWrapper'] ) ) {
			$body = str_replace( '{{content}}', $body, $emails['htmlWrapper'] );
		}

		$components['body']     = self::wrap_html( $body, $emails );
		$components['use_html'] = true;

		return $components;
	}

	/**
	 * Send extra conditional autoresponders.
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 */
	public static function maybe_send_conditional( $contact_form ) {
		if ( ! $contact_form instanceof WPCF7_ContactForm || ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return;
		}

		$schema = get_post_meta( $contact_form->id(), CFB_SCHEMA_META, true );

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted  = $submission->get_posted_data();
		$actions = CFB_Conditional::resolve_actions( $schema, $posted );

		foreach ( $actions['mail_overrides'] as $mail_action ) {
			if ( empty( $mail_action['recipient'] ) || empty( $mail_action['subject'] ) ) {
				continue;
			}
			$body    = $mail_action['body'] ?? '';
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail(
				$mail_action['recipient'],
				$mail_action['subject'],
				self::wrap_html( $body, $schema['settings']['emails'] ?? array() ),
				$headers
			);
		}
	}

	/**
	 * Wrap mail body in branded HTML layout.
	 *
	 * @param string $body   Body HTML.
	 * @param array  $emails Email settings.
	 * @return string
	 */
	private static function wrap_html( $body, $emails ) {
		$brand = $emails['brandColor'] ?? '#3858e9';
		$logo  = $emails['logoUrl'] ?? '';

		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;background:#f6f7f7;padding:24px;">';
		$html .= '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;border-top:4px solid ' . esc_attr( $brand ) . ';">';
		if ( $logo ) {
			$html .= '<p><img src="' . esc_url( $logo ) . '" alt="" style="max-height:48px;"></p>';
		}
		$html .= $body;
		$html .= '<p style="font-size:12px;color:#646970;margin-top:24px;">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$html .= '</div></body></html>';

		return $html;
	}
}
