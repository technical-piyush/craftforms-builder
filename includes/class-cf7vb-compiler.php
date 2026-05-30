<?php
/**
 * Compile visual schema to Contact Form 7 form template markup.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Compiler {

	/**
	 * @param array $schema Form schema.
	 * @return string
	 */
	public static function compile( $schema ) {
		$schema    = CF7VB_Parser::normalize_schema( $schema );
		$lines     = array();
		$settings  = $schema['settings'] ?? array();
		$multistep = ! empty( $settings['multistep'] ) && count( $schema['steps'] ) > 1;
		$last_step = count( $schema['steps'] ) - 1;

		foreach ( $schema['steps'] as $step_index => $step ) {
			$step_lines = array();

			foreach ( $step['sections'] as $section ) {
				if ( ! empty( $section['title'] ) ) {
					$step_lines[] = sprintf( '<h3 class="cf7vb-section-title">%s</h3>', esc_html( $section['title'] ) );
				}

				foreach ( $section['fields'] as $field ) {
					$block = self::compile_field( $field );
					if ( $block ) {
						$step_lines[] = $block;
					}
				}
			}

			if ( $multistep && $step_index === $last_step && ! empty( $settings['gdpr']['enabled'] ) ) {
				$gn           = $settings['gdpr']['fieldName'] ?? 'gdpr-consent';
				$gl           = $settings['gdpr']['label'] ?? __( 'I agree to the privacy policy.', 'cf7-visual-form-builder' );
				$step_lines[] = '[acceptance ' . esc_attr( $gn ) . ']' . "\n" . esc_html( $gl );
			}

			$step_body = implode( "\n\n", array_filter( $step_lines ) );

			if ( $multistep ) {
				$step_title = isset( $step['title'] ) ? trim( (string) $step['title'] ) : '';
				$lines[]    = sprintf(
					'<div class="cf7vb-step-group" data-cf7vb-step="%1$s" data-cf7vb-step-index="%2$d"%4$s>%3$s</div>',
					esc_attr( $step['id'] ),
					(int) $step_index,
					$step_body,
					$step_title ? sprintf( ' data-cf7vb-step-title="%s"', esc_attr( $step_title ) ) : ''
				);
			} elseif ( $step_body ) {
				$lines[] = $step_body;
			}
		}

		if ( ! empty( $settings['security']['honeypot'] ) ) {
			$hp      = $settings['security']['honeypotField'] ?? 'cf7vb_hp';
			$lines[] = sprintf(
				'<span class="cf7vb-hp" aria-hidden="true" style="position:absolute;left:-9999px;"><label>%1$s [text %2$s]</label></span>',
				esc_html__( 'Leave empty', 'cf7-visual-form-builder' ),
				esc_attr( $hp )
			);
		}

		if ( ! $multistep && ! empty( $settings['gdpr']['enabled'] ) ) {
			$gn      = $settings['gdpr']['fieldName'] ?? 'gdpr-consent';
			$gl      = $settings['gdpr']['label'] ?? __( 'I agree to the privacy policy.', 'cf7-visual-form-builder' );
			$lines[] = '[acceptance ' . esc_attr( $gn ) . ']' . "\n" . esc_html( $gl );
		}

		return implode( "\n\n", array_filter( $lines ) );
	}

	/**
	 * @param array $field Field definition.
	 * @return string
	 */
	public static function compile_field( $field ) {
		$field = CF7VB_Parser::normalize_field( $field );
		$type  = $field['type'];
		$req   = ! empty( $field['required'] ) ? '*' : '';
		$name  = $field['name'];
		$label = $field['label'];

		$lines = array();

		if ( $label && 'hidden' !== $type && 'submit' !== $type ) {
			$lines[] = sprintf( '<label> %s', esc_html( $label ) );
			if ( ! empty( $field['required'] ) ) {
				$lines[] = ' <span class="cf7vb-req">*</span>';
			}
			$lines[] = '';
			$lines[] = self::compile_tag( $field, $req );
			$lines[] = '</label>';
		} else {
			$lines[] = self::compile_tag( $field, $req );
		}

		if ( ! empty( $field['help'] ) ) {
			$lines[] = sprintf( '<span class="cf7vb-help">%s</span>', esc_html( $field['help'] ) );
		}

		return implode( "\n", array_filter( $lines ) );
	}

	/**
	 * @param array  $field Field.
	 * @param string $req   Asterisk for required.
	 * @return string
	 */
	private static function compile_tag( $field, $req ) {
		$type = $field['type'];
		$name = $field['name'];
		$opts = array();

		if ( ! empty( $field['placeholder'] ) ) {
			$opts[] = 'placeholder "' . self::escape_cf7_value( $field['placeholder'] ) . '"';
		}

		if ( ! empty( $field['attrs']['class'] ) ) {
			$opts[] = 'class:' . sanitize_html_class( $field['attrs']['class'] );
		}

		if ( ! empty( $field['attrs']['id'] ) ) {
			$opts[] = 'id:' . sanitize_html_class( $field['attrs']['id'] );
		}

		switch ( $type ) {
			case 'text':
				return self::tag( 'text' . $req, $name, $opts );

			case 'email':
				return self::tag( 'email' . $req, $name, $opts );

			case 'phone':
				return self::tag( 'tel' . $req, $name, $opts );

			case 'url':
				return self::tag( 'url' . $req, $name, $opts );

			case 'number':
				if ( ! empty( $field['validation']['min'] ) ) {
					$opts[] = 'min:' . floatval( $field['validation']['min'] );
				}
				if ( ! empty( $field['validation']['max'] ) ) {
					$opts[] = 'max:' . floatval( $field['validation']['max'] );
				}
				return self::tag( 'number' . $req, $name, $opts );

			case 'textarea':
				return self::tag( 'textarea' . $req, $name, $opts );

			case 'select':
				$values = self::option_values( $field );
				return self::tag( 'select' . $req, $name, array_merge( $opts, $values ) );

			case 'checkbox':
				$values = self::option_values( $field );
				$opts[] = 'use_label_element';
				return self::tag( 'checkbox' . $req, $name, array_merge( $opts, $values ) );

			case 'radio':
				$values = self::option_values( $field );
				$opts[] = 'use_label_element';
				return self::tag( 'radio' . $req, $name, array_merge( $opts, $values ) );

			case 'file':
				if ( ! empty( $field['validation']['filetypes'] ) ) {
					$opts[] = 'filetypes:' . preg_replace( '/[^a-z0-9|]/i', '', $field['validation']['filetypes'] );
				}
				if ( ! empty( $field['validation']['limit'] ) ) {
					$opts[] = 'limit:' . sanitize_text_field( $field['validation']['limit'] );
				}
				return self::tag( 'file' . $req, $name, $opts );

			case 'date':
				return self::tag( 'date' . $req, $name, $opts );

			case 'hidden':
				if ( ! empty( $field['attrs']['default'] ) ) {
					$opts[] = 'default:' . sanitize_text_field( $field['attrs']['default'] );
				}
				return self::tag( 'hidden', $name, $opts, $field['attrs']['value'] ?? '' );

			case 'rating':
				$opts[] = 'cf7vb-rating';
				return self::tag( 'number' . $req, $name, $opts );

			case 'signature':
				$opts[] = 'cf7vb-signature';
				return self::tag( 'text' . $req, $name, $opts );

			case 'repeater':
				$opts[] = 'cf7vb-repeater';
				return '<!-- cf7vb:repeater ' . esc_attr( $name ) . ' -->';

			case 'submit':
				$label = $field['label'] ? $field['label'] : __( 'Send', 'cf7-visual-form-builder' );
				return '[submit "' . self::escape_cf7_value( $label ) . '"]';

			default:
				return self::tag( 'text' . $req, $name, $opts );
		}
	}

	/**
	 * @param array $field Field.
	 * @return array
	 */
	private static function option_values( $field ) {
		$out = array();
		if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
			$out[] = '"Option 1"';
			$out[] = '"Option 2"';
			return $out;
		}
		foreach ( $field['options'] as $opt ) {
			if ( is_array( $opt ) ) {
				$val = $opt['value'] ?? $opt['label'] ?? '';
			} else {
				$val = $opt;
			}
			$val = trim( (string) $val );
			if ( '' !== $val ) {
				$out[] = '"' . self::escape_cf7_value( $val ) . '"';
			}
		}
		return $out;
	}

	/**
	 * @param string $basetype CF7 tag type.
	 * @param string $name     Field name.
	 * @param array  $options  Options.
	 * @param string $default_value Default value.
	 * @return string
	 */
	private static function tag( $basetype, $name, $options = array(), $default_value = '' ) {
		$parts = array_filter( array_merge( array( $basetype, $name ), $options ) );
		$inner = implode( ' ', $parts );
		if ( '' !== $default_value ) {
			$inner .= ' "' . self::escape_cf7_value( $default_value ) . '"';
		}
		return '[' . $inner . ']';
	}

	/**
	 * @param string $value Value.
	 * @return string
	 */
	private static function escape_cf7_value( $value ) {
		return str_replace( '"', '', (string) $value );
	}
}
