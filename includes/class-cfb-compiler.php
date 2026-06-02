<?php
/**
 * Compile visual schema to Contact Form 7 form template markup.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles visual builder schema to Contact Form 7 markup.
 */
final class CFB_Compiler {

	/**
	 * Compile a full schema to CF7 form template text.
	 *
	 * @param array $schema Form schema.
	 * @return string
	 */
	public static function compile( $schema ) {
		$schema    = CFB_Parser::normalize_schema( $schema );
		$lines     = array();
		$settings  = $schema['settings'] ?? array();
		$multistep = ! empty( $settings['multistep'] ) && count( $schema['steps'] ) > 1;
		$last_step = count( $schema['steps'] ) - 1;

		foreach ( $schema['steps'] as $step_index => $step ) {
			$step_lines = array();

			foreach ( $step['sections'] as $section ) {
				if ( ! empty( $section['title'] ) ) {
					$step_lines[] = sprintf( '<h3 class="cfb-section-title">%s</h3>', esc_html( $section['title'] ) );
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
				$gl           = $settings['gdpr']['label'] ?? __( 'I agree to the privacy policy.', 'craftforms-builder' );
				$step_lines[] = '[acceptance ' . esc_attr( $gn ) . ']' . "\n" . esc_html( $gl );
			}

			$step_body = implode( "\n\n", array_filter( $step_lines ) );

			if ( $multistep ) {
				$step_title = isset( $step['title'] ) ? trim( (string) $step['title'] ) : '';
				$lines[]    = sprintf(
					'<div class="cfb-step-group" data-cfb-step="%1$s" data-cfb-step-index="%2$d"%4$s>%3$s</div>',
					esc_attr( $step['id'] ),
					(int) $step_index,
					$step_body,
					$step_title ? sprintf( ' data-cfb-step-title="%s"', esc_attr( $step_title ) ) : ''
				);
			} elseif ( $step_body ) {
				$lines[] = $step_body;
			}
		}

		if ( ! empty( $settings['security']['honeypot'] ) ) {
			$hp      = $settings['security']['honeypotField'] ?? 'cfb_hp';
			$lines[] = sprintf(
				'<span class="cfb-hp" aria-hidden="true" style="position:absolute;left:-9999px;"><label>%1$s [text %2$s]</label></span>',
				esc_html__( 'Leave empty', 'craftforms-builder' ),
				esc_attr( $hp )
			);
		}

		if ( ! $multistep && ! empty( $settings['gdpr']['enabled'] ) ) {
			$gn      = $settings['gdpr']['fieldName'] ?? 'gdpr-consent';
			$gl      = $settings['gdpr']['label'] ?? __( 'I agree to the privacy policy.', 'craftforms-builder' );
			$lines[] = '[acceptance ' . esc_attr( $gn ) . ']' . "\n" . esc_html( $gl );
		}

		return implode( "\n\n", array_filter( $lines ) );
	}

	/**
	 * Compile one field to labeled CF7 markup.
	 *
	 * @param array $field Field definition.
	 * @return string
	 */
	public static function compile_field( $field ) {
		$field = CFB_Parser::normalize_field( $field );
		$type  = $field['type'];

		if ( 'repeater' === $type ) {
			return self::compile_repeater( $field );
		}

		$req      = ! empty( $field['required'] ) ? '*' : '';
		$label    = $field['label'];
		$tag      = self::compile_tag( $field, $req );
		$required = ! empty( $field['required'] );

		if ( $label && 'hidden' !== $type && 'submit' !== $type ) {
			$label_html = esc_html( $label );
			if ( $required ) {
				$label_html .= sprintf(
					' <abbr class="wpcf7-required" title="%s">*</abbr>',
					esc_attr__( 'required', 'craftforms-builder' )
				);
			}
			$block = sprintf(
				'<p><label>%1$s<br />%2$s</label></p>',
				$label_html,
				$tag
			);
		} else {
			$block = '<p>' . $tag . '</p>';
		}

		if ( ! empty( $field['help'] ) ) {
			$block .= sprintf( '<p class="cfb-help">%s</p>', esc_html( $field['help'] ) );
		}

		return $block;
	}

	/**
	 * Build the CF7 shortcode for a single field.
	 *
	 * @param array  $field Field.
	 * @param string $req   Asterisk for required.
	 * @return string
	 */
	private static function compile_tag( $field, $req ) {
		$type   = $field['type'];
		$name   = $field['name'];
		$opts   = array();
		$quoted = array();

		if ( ! empty( $field['attrs']['class'] ) ) {
			$opts[] = 'class:' . sanitize_html_class( $field['attrs']['class'] );
		}

		if ( ! empty( $field['attrs']['id'] ) ) {
			$opts[] = 'id:' . sanitize_html_class( $field['attrs']['id'] );
		}

		if ( ! empty( $field['placeholder'] ) && self::type_supports_placeholder_attr( $type ) ) {
			$opts[]   = 'placeholder';
			$quoted[] = $field['placeholder'];
		}

		switch ( $type ) {
			case 'text':
				return self::tag( 'text' . $req, $name, $opts, $quoted );

			case 'email':
				return self::tag( 'email' . $req, $name, $opts, $quoted );

			case 'phone':
				$opts[] = 'class:cfb-phone';
				if ( empty( $field['placeholder'] ) ) {
					$opts[]   = 'placeholder';
					$quoted[] = __( '555 000 0000', 'craftforms-builder' );
				}
				return self::tag( 'tel' . $req, $name, $opts, $quoted );

			case 'url':
				return self::tag( 'url' . $req, $name, $opts, $quoted );

			case 'number':
				if ( isset( $field['validation']['min'] ) && '' !== $field['validation']['min'] ) {
					$opts[] = 'min:' . floatval( $field['validation']['min'] );
				}
				if ( isset( $field['validation']['max'] ) && '' !== $field['validation']['max'] ) {
					$opts[] = 'max:' . floatval( $field['validation']['max'] );
				}
				if ( isset( $field['validation']['step'] ) && '' !== $field['validation']['step'] ) {
					$opts[] = 'step:' . floatval( $field['validation']['step'] );
				}
				return self::tag( 'number' . $req, $name, $opts, $quoted );

			case 'textarea':
				return self::tag( 'textarea' . $req, $name, $opts, $quoted );

			case 'select':
				list( $blank_opt, $values ) = self::select_blank_and_values( $field, self::option_values( $field ) );
				$opts[]                     = $blank_opt;
				return self::tag( 'select' . $req, $name, $opts, array_merge( $quoted, $values ) );

			case 'multiselect':
				$opts[] = 'multiple';
				$opts[] = 'class:cfb-multiselect';
				return self::tag( 'select' . $req, $name, $opts, array_merge( $quoted, self::option_values( $field ) ) );

			case 'country':
				$opts[]                     = 'class:cfb-country';
				$opts[]                     = 'data:cfb_countries';
				list( $blank_opt, $values ) = self::select_blank_and_values( $field, array() );
				$opts[]                     = $blank_opt;
				return self::tag( 'select' . $req, $name, $opts, array_merge( $quoted, $values ) );

			case 'checkbox':
				$opts[] = 'use_label_element';
				return self::tag( 'checkbox' . $req, $name, $opts, array_merge( $quoted, self::option_values( $field ) ) );

			case 'radio':
				$opts[] = 'use_label_element';
				return self::tag( 'radio' . $req, $name, $opts, array_merge( $quoted, self::option_values( $field ) ) );

			case 'file':
				if ( ! empty( $field['validation']['filetypes'] ) ) {
					$opts[] = 'filetypes:' . preg_replace( '/[^a-z0-9|]/i', '', $field['validation']['filetypes'] );
				}
				if ( ! empty( $field['validation']['limit'] ) ) {
					$opts[] = 'limit:' . sanitize_text_field( $field['validation']['limit'] );
				}
				return self::tag( 'file' . $req, $name, $opts, $quoted );

			case 'date':
				return self::tag( 'date' . $req, $name, $opts, $quoted );

			case 'hidden':
				if ( ! empty( $field['attrs']['default'] ) ) {
					$opts[] = 'default:' . sanitize_text_field( $field['attrs']['default'] );
				}
				$hidden_val = isset( $field['attrs']['value'] ) ? (string) $field['attrs']['value'] : '';
				return self::tag( 'hidden', $name, $opts, '' !== $hidden_val ? array( $hidden_val ) : $quoted );

			case 'rating':
				$opts[] = 'cfb-rating';
				return self::tag( 'number' . $req, $name, $opts, $quoted );

			case 'signature':
				$opts[] = 'cfb-signature';
				return self::tag( 'text' . $req, $name, $opts, $quoted );

			case 'submit':
				$label = $field['label'] ? $field['label'] : __( 'Send', 'craftforms-builder' );
				return '[submit "' . self::escape_cf7_value( $label ) . '"]';

			default:
				return self::tag( 'text' . $req, $name, $opts, $quoted );
		}
	}

	/**
	 * Compile repeater: pre-render CF7 rows (hidden above min) so tags stay registered.
	 *
	 * @param array $field Repeater field.
	 * @return string
	 */
	private static function compile_repeater( $field ) {
		$name     = $field['name'];
		$label    = $field['label'];
		$children = $field['children'] ?? array();
		$min_rows = max( 1, (int) ( $field['attrs']['minRows'] ?? 1 ) );
		$max_rows = max( $min_rows, min( 20, (int) ( $field['attrs']['maxRows'] ?? 5 ) ) );

		$lines   = array();
		$lines[] = sprintf(
			'<div class="cfb-repeater" data-cfb-repeater="%1$s" data-min="%2$d" data-max="%3$d">',
			esc_attr( $name ),
			$min_rows,
			$max_rows
		);

		if ( $label ) {
			$lines[] = sprintf( '<p class="cfb-repeater-label"><strong>%s</strong></p>', esc_html( $label ) );
		}

		$lines[] = '<div class="cfb-repeater-rows">';

		for ( $i = 0; $i < $max_rows; $i++ ) {
			$row_class = $i >= $min_rows ? 'cfb-repeater-row cfb-repeater-row--hidden' : 'cfb-repeater-row';
			$lines[]   = sprintf( '<div class="%s" data-row-index="%d">', esc_attr( $row_class ), $i );
			foreach ( $children as $child ) {
				$child_field          = $child;
				$child_field['name']  = $name . '-row' . $i . '-' . $child['name'];
				$child_field['label'] = $child['label'] ?? $child['name'];
				$lines[]              = self::compile_field( $child_field );
			}
			if ( $i >= $min_rows ) {
				$lines[] = sprintf(
					'<p class="cfb-repeater-row-actions"><button type="button" class="cfb-repeater-remove button">%s</button></p>',
					esc_html__( 'Remove row', 'craftforms-builder' )
				);
			}
			$lines[] = '</div>';
		}

		$lines[] = '</div>';
		$lines[] = sprintf(
			'<p class="cfb-repeater-actions"><button type="button" class="cfb-repeater-add button">%s</button></p>',
			esc_html__( 'Add row', 'craftforms-builder' )
		);
		$lines[] = '</div>';

		if ( ! empty( $field['help'] ) ) {
			$lines[] = sprintf( '<span class="cfb-help">%s</span>', esc_html( $field['help'] ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * CF7 placeholder is only valid on text-like tags; select uses first_as_label / include_blank.
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	private static function type_supports_placeholder_attr( $type ) {
		return in_array( $type, array( 'text', 'email', 'phone', 'url', 'number', 'textarea' ), true );
	}

	/**
	 * Blank option strategy for [select] tags.
	 *
	 * @param array $field  Field.
	 * @param array $values Quoted option values.
	 * @return array{0:string,1:array} Tuple of blank option name and full values list.
	 */
	private static function select_blank_and_values( $field, $values ) {
		if ( ! empty( $field['placeholder'] ) ) {
			return array(
				'first_as_label',
				array_merge(
					array( $field['placeholder'] ),
					$values
				),
			);
		}

		return array( 'include_blank', $values );
	}

	/**
	 * Resolve select option values for a country field.
	 *
	 * @param array $field Country field.
	 * @return array
	 */
	private static function country_option_values( $field ) {
		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			return self::option_values( $field );
		}

		$out = array();
		foreach ( CFB_Countries::get_options() as $country ) {
			$out[] = $country;
		}
		return $out;
	}

	/**
	 * Extract quoted option values from a field definition.
	 *
	 * @param array $field Field.
	 * @return array
	 */
	private static function option_values( $field ) {
		$out = array();
		if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
			$out[] = 'Option 1';
			$out[] = 'Option 2';
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
				$out[] = $val;
			}
		}
		return $out;
	}

	/**
	 * Assemble a CF7 form tag shortcode string.
	 *
	 * @param string $basetype CF7 tag type.
	 * @param string $name     Field name.
	 * @param array  $options  Options.
	 * @param array  $quoted_values Quoted values (must come after all options in CF7 tags).
	 * @return string
	 */
	private static function tag( $basetype, $name, $options = array(), $quoted_values = array() ) {
		$parts = array_filter( array_merge( array( $basetype, $name ), $options ) );

		foreach ( (array) $quoted_values as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$parts[] = '"' . self::escape_cf7_value( $value ) . '"';
			}
		}

		return '[' . implode( ' ', $parts ) . ']';
	}

	/**
	 * Escape a value for use inside CF7 quoted tag parts.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function escape_cf7_value( $value ) {
		return str_replace( '"', '', (string) $value );
	}
}
