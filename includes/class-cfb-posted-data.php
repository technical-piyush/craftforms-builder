<?php
/**
 * Normalize posted values (phone country code, multi-select formatting).
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Posted_Data {

	public static function init() {
		add_filter( 'wpcf7_posted_data', array( __CLASS__, 'normalize' ), 99 );
	}

	/**
	 * @param array $posted Posted data.
	 * @return array
	 */
	public static function normalize( $posted ) {
		$contact_form = WPCF7_ContactForm::get_current();
		if ( ! $contact_form instanceof WPCF7_ContactForm ) {
			return $posted;
		}

		if ( ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return $posted;
		}

		foreach ( $posted as $key => $value ) {
			if ( is_array( $value ) ) {
				$posted[ $key ] = implode( ', ', array_map( 'strval', array_filter( $value ) ) );
			}
		}

		$tags = $contact_form->scan_form_tags(
			array(
				'basetype' => array( 'tel' ),
			)
		);

		foreach ( $tags as $tag ) {
			if ( empty( $tag->name ) ) {
				continue;
			}

			$classes = $tag->get_class_option( 'wpcf7-form-control wpcf7-tel' );
			if ( ! $classes || false === strpos( $classes, 'cfb-phone' ) ) {
				continue;
			}

			$name     = $tag->name;
			$dial_key = $name . '_cfb_dial';
			$raw      = isset( $posted[ $name ] ) ? trim( (string) $posted[ $name ] ) : '';
			$dial     = isset( $posted[ $dial_key ] ) ? preg_replace( '/\D+/', '', (string) $posted[ $dial_key ] ) : '';

			$posted[ $name ] = self::normalize_phone_value( $raw, $dial );

			unset( $posted[ $dial_key ] );
		}

		return $posted;
	}

	/**
	 * Build E.164 phone value: country code first, then national digits (no duplicate prefix).
	 *
	 * @param string $raw  Submitted phone value.
	 * @param string $dial Dial code digits from the phone widget flag selector.
	 * @return string
	 */
	private static function normalize_phone_value( $raw, $dial ) {
		if ( '' === $raw ) {
			return '';
		}

		if ( 0 === strpos( $raw, '+' ) ) {
			return self::format_e164_readable( $raw );
		}

		$national = preg_replace( '/\D+/', '', $raw );
		if ( ! $dial || ! $national ) {
			return $raw;
		}

		if ( 0 === strpos( $national, $dial ) && strlen( $national ) > strlen( $dial ) ) {
			$national = substr( $national, strlen( $dial ) );
		}

		return self::format_e164_readable( '+' . $dial . $national );
	}

	/**
	 * Format E.164 for mail readability: +91-7839098600
	 *
	 * @param string $e164 E.164 number.
	 * @return string
	 */
	private static function format_e164_readable( $e164 ) {
		$digits = preg_replace( '/\D+/', '', (string) $e164 );
		if ( '' === $digits ) {
			return (string) $e164;
		}

		$countries = CFB_Countries::get_phone_countries();
		usort(
			$countries,
			static function ( $a, $b ) {
				return strlen( (string) $b['dial'] ) - strlen( (string) $a['dial'] );
			}
		);

		foreach ( $countries as $country ) {
			$dial = (string) $country['dial'];
			if ( '' === $dial ) {
				continue;
			}
			if ( 0 === strpos( $digits, $dial ) && strlen( $digits ) > strlen( $dial ) ) {
				$national = substr( $digits, strlen( $dial ) );
				return '+' . $dial . '-' . $national;
			}
		}

		return '+' . $digits;
	}
}
