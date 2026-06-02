<?php
/**
 * Custom validation from visual schema.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom field validation from the visual builder schema.
 */
final class CFB_Validation {

	/**
	 * Register CF7 validation filter.
	 */
	public static function init() {
		add_filter( 'wpcf7_validate', array( __CLASS__, 'validate' ), 20, 2 );
	}

	/**
	 * Apply schema validation rules to the current submission.
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param array            $tags   Form tags.
	 * @return WPCF7_Validation
	 */
	public static function validate( $result, $tags ) {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return $result;
		}

		$contact_form = $submission->get_contact_form();
		if ( ! $contact_form ) {
			return $result;
		}

		if ( ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return $result;
		}

		$schema = get_post_meta( $contact_form->id(), CFB_SCHEMA_META, true );

		$posted = $submission->get_posted_data();

		foreach ( self::iterate_fields( $schema ) as $field ) {
			$name = $field['name'] ?? '';
			if ( '' === $name ) {
				continue;
			}

			$value = isset( $posted[ $name ] ) ? $posted[ $name ] : '';
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$rules = $field['validation'] ?? array();

			if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
				$result->invalidate( $tags[0] ?? null, self::message( $field, 'required' ) );
				continue;
			}

			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			if ( ! empty( $rules['minLength'] ) && strlen( (string) $value ) < (int) $rules['minLength'] ) {
				$result->invalidate( null, self::message( $field, 'minlength' ) );
			}

			if ( ! empty( $rules['maxLength'] ) && strlen( (string) $value ) > (int) $rules['maxLength'] ) {
				$result->invalidate( null, self::message( $field, 'maxlength' ) );
			}

			if ( ! empty( $rules['pattern'] ) ) {
				$pattern = '/' . str_replace( '/', '\/', $rules['pattern'] ) . '/u';
				$valid   = preg_match( $pattern, (string) $value );
				if ( false === $valid ) {
					continue;
				}
				if ( ! $valid ) {
					$result->invalidate( null, self::message( $field, 'pattern' ) );
				}
			}

			if ( 'email' === $field['type'] && ! is_email( $value ) ) {
				$result->invalidate( null, self::message( $field, 'email' ) );
			}

			if ( 'url' === $field['type'] && ! wp_http_validate_url( $value ) ) {
				$result->invalidate( null, self::message( $field, 'invalid' ) );
			}

			if ( 'number' === $field['type'] && is_numeric( $value ) ) {
				$numeric = (float) $value;
				if ( isset( $rules['min'] ) && '' !== $rules['min'] && $numeric < (float) $rules['min'] ) {
					$result->invalidate( null, self::message( $field, 'min' ) );
				}
				if ( isset( $rules['max'] ) && '' !== $rules['max'] && $numeric > (float) $rules['max'] ) {
					$result->invalidate( null, self::message( $field, 'max' ) );
				}
			}
		}

		$gdpr = $schema['settings']['gdpr'] ?? array();
		if ( ! empty( $gdpr['enabled'] ) && ! empty( $gdpr['fieldName'] ) ) {
			$consent = isset( $posted[ $gdpr['fieldName'] ] ) ? $posted[ $gdpr['fieldName'] ] : '';
			if ( empty( $consent ) ) {
				$result->invalidate(
					null,
					$gdpr['errorMessage'] ?? __( 'You must accept the privacy policy.', 'craftforms-builder' )
				);
			}
		}

		return $result;
	}

	/**
	 * Resolve a validation error message for a field.
	 *
	 * @param array  $field Field.
	 * @param string $type  Message type.
	 * @return string
	 */
	private static function message( $field, $type ) {
		$custom = $field['validation']['messages'][ $type ] ?? '';
		if ( $custom ) {
			return $custom;
		}
		$label = $field['label'] ?? $field['name'];
		switch ( $type ) {
			case 'required':
				/* translators: %s: field label. */
				return sprintf( __( '%s is required.', 'craftforms-builder' ), $label );
			case 'email':
				/* translators: %s: field label. */
				return sprintf( __( '%s must be a valid email.', 'craftforms-builder' ), $label );
			case 'min':
				/* translators: %s: field label. */
				return sprintf( __( '%s is too low.', 'craftforms-builder' ), $label );
			case 'max':
				/* translators: %s: field label. */
				return sprintf( __( '%s is too high.', 'craftforms-builder' ), $label );
			default:
				/* translators: %s: field label. */
				return sprintf( __( '%s is invalid.', 'craftforms-builder' ), $label );
		}
	}

	/**
	 * Flatten all fields from a schema (including repeater children).
	 *
	 * @param array $schema Schema.
	 * @return array
	 */
	public static function iterate_fields( $schema ) {
		$fields = array();
		foreach ( (array) ( $schema['steps'] ?? array() ) as $step ) {
			foreach ( (array) ( $step['sections'] ?? array() ) as $section ) {
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					$fields[] = $field;
					if ( 'repeater' === ( $field['type'] ?? '' ) && ! empty( $field['children'] ) ) {
						foreach ( $field['children'] as $child ) {
							$fields[] = $child;
						}
					}
				}
			}
		}
		return $fields;
	}
}
