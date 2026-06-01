<?php
/**
 * Parse / normalize visual builder schema.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Parser {

	/** @var string[] */
	private const FIELD_TYPES = array(
		'text',
		'textarea',
		'email',
		'phone',
		'number',
		'select',
		'checkbox',
		'radio',
		'file',
		'date',
		'hidden',
		'rating',
		'signature',
		'repeater',
		'submit',
	);

	/** @var string[] */
	private const RULE_COMPARES = array(
		'equals',
		'not_equals',
		'contains',
		'not_contains',
		'empty',
		'not_empty',
		'greater',
		'less',
	);

	/** @var string[] */
	private const RULE_ACTIONS = array(
		'show',
		'hide',
		'require',
		'skip_step',
		'redirect',
		'conditional_email',
	);

	/**
	 * Sanitize flat posted field values (drafts, analytics payloads).
	 *
	 * @param array $data Raw posted data.
	 * @return array<string, string|array<int, string>>
	 */
	public static function sanitize_posted_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$clean = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map(
					static function ( $item ) {
						return sanitize_text_field( (string) $item );
					},
					$value
				);
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}

	/**
	 * Decode JSON schema from a request and sanitize every nested value.
	 *
	 * @param string $raw Raw JSON string (unslashed).
	 * @return array|null Sanitized schema or null when invalid.
	 */
	public static function decode_schema_json( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		if ( strlen( $raw ) > 1048576 ) {
			return null;
		}

		$schema = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $schema ) ) {
			return null;
		}

		return self::normalize_schema( $schema );
	}

	/**
	 * Read builder schema from POST (sanitized after JSON decode).
	 *
	 * @return array|null
	 */
	public static function get_schema_from_post() {
		$raw = filter_input( INPUT_POST, 'cfb_schema' );
		if ( null === $raw || false === $raw || ! is_string( $raw ) ) {
			return null;
		}

		return self::decode_schema_json( wp_unslash( $raw ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function sanitize_bool( $value ) {
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @param string $value   Raw id.
	 * @param string $fallback Fallback id.
	 * @return string
	 */
	private static function sanitize_slug_id( $value, $fallback ) {
		$id = preg_replace( '/[^a-zA-Z0-9\-_]/', '', (string) $value );
		$id = substr( $id, 0, 64 );

		return '' !== $id ? $id : $fallback;
	}

	/**
	 * @param string $value   Raw value.
	 * @param array  $allowed Allowed values.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function sanitize_enum( $value, array $allowed, $default ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * @param mixed $options Options list.
	 * @return array<int, array{label: string, value: string}>
	 */
	private static function sanitize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$clean = array();

		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$clean[] = array(
					'label' => sanitize_text_field( (string) ( $option['label'] ?? '' ) ),
					'value' => sanitize_text_field( (string) ( $option['value'] ?? $option['label'] ?? '' ) ),
				);
			} else {
				$text    = sanitize_text_field( (string) $option );
				$clean[] = array(
					'label' => $text,
					'value' => $text,
				);
			}
		}

		return $clean;
	}

	/**
	 * @param mixed $attrs HTML attributes map.
	 * @return array<string, string>
	 */
	private static function sanitize_attrs( $attrs ) {
		if ( ! is_array( $attrs ) ) {
			return array();
		}

		$clean = array();

		foreach ( $attrs as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}

	/**
	 * @param mixed $validation Validation rules.
	 * @return array<string, mixed>
	 */
	private static function sanitize_validation( $validation ) {
		if ( ! is_array( $validation ) ) {
			return array();
		}

		$clean = array();

		if ( isset( $validation['minLength'] ) ) {
			$clean['minLength'] = absint( $validation['minLength'] );
		}
		if ( isset( $validation['maxLength'] ) ) {
			$clean['maxLength'] = absint( $validation['maxLength'] );
		}
		if ( isset( $validation['pattern'] ) ) {
			$pattern = (string) $validation['pattern'];
			$pattern = preg_replace( '/[^\w\s\[\]()^$.*+?|\\\\\-]/', '', $pattern );
			$clean['pattern'] = substr( $pattern, 0, 200 );
		}
		if ( isset( $validation['filetypes'] ) ) {
			$clean['filetypes'] = preg_replace( '/[^a-z0-9|]/i', '', (string) $validation['filetypes'] );
		}
		if ( ! empty( $validation['messages'] ) && is_array( $validation['messages'] ) ) {
			$messages = array();
			foreach ( $validation['messages'] as $type => $message ) {
				$key = sanitize_key( (string) $type );
				if ( '' !== $key ) {
					$messages[ $key ] = sanitize_text_field( (string) $message );
				}
			}
			$clean['messages'] = $messages;
		}

		return $clean;
	}

	/**
	 * @param mixed $condition Single IF condition.
	 * @return array<string, string>
	 */
	private static function sanitize_condition( $condition ) {
		if ( ! is_array( $condition ) ) {
			return array(
				'field'   => '',
				'compare' => 'equals',
				'value'   => '',
			);
		}

		return array(
			'field'   => sanitize_key( (string) ( $condition['field'] ?? '' ) ),
			'compare' => self::sanitize_enum( $condition['compare'] ?? 'equals', self::RULE_COMPARES, 'equals' ),
			'value'   => sanitize_text_field( (string) ( $condition['value'] ?? '' ) ),
		);
	}

	/**
	 * @param mixed $action THEN action.
	 * @return array<string, string>
	 */
	private static function sanitize_action( $action ) {
		if ( ! is_array( $action ) ) {
			return array(
				'type'   => 'show',
				'target' => '',
			);
		}

		$type = self::sanitize_enum( $action['type'] ?? 'show', self::RULE_ACTIONS, 'show' );

		$clean = array(
			'type'   => $type,
			'target' => sanitize_key( (string) ( $action['target'] ?? '' ) ),
		);

		if ( 'redirect' === $type ) {
			$clean['url'] = esc_url_raw( (string) ( $action['url'] ?? '' ) );
		}
		if ( 'conditional_email' === $type ) {
			$clean['recipient'] = sanitize_email( (string) ( $action['recipient'] ?? '' ) );
		}

		return $clean;
	}

	/**
	 * @param mixed $group Rule or nested condition group.
	 * @return array<string, mixed>
	 */
	private static function sanitize_rule_group( $group ) {
		if ( ! is_array( $group ) ) {
			return array(
				'operator'   => 'and',
				'conditions' => array(),
				'groups'     => array(),
			);
		}

		$operator = isset( $group['operator'] ) ? strtolower( (string) $group['operator'] ) : 'and';
		if ( 'or' !== $operator ) {
			$operator = 'and';
		}

		$conditions = array();
		if ( ! empty( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
			foreach ( $group['conditions'] as $condition ) {
				$conditions[] = self::sanitize_condition( $condition );
			}
		}

		$groups = array();
		if ( ! empty( $group['groups'] ) && is_array( $group['groups'] ) ) {
			foreach ( $group['groups'] as $nested ) {
				$groups[] = self::sanitize_rule_group( $nested );
			}
		}

		return array(
			'operator'   => $operator,
			'conditions' => $conditions,
			'groups'     => $groups,
		);
	}

	/**
	 * @param mixed $rules Conditional logic rules.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$clean = array();

		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$rule_id = self::sanitize_slug_id( (string) ( $rule['id'] ?? '' ), 'rule-' . ( $index + 1 ) );
			$group   = self::sanitize_rule_group( $rule );

			$actions = array();
			if ( ! empty( $rule['actions'] ) && is_array( $rule['actions'] ) ) {
				foreach ( $rule['actions'] as $action ) {
					$actions[] = self::sanitize_action( $action );
				}
			}

			$clean[] = array_merge(
				array(
					'id'      => $rule_id,
					'enabled' => ! isset( $rule['enabled'] ) || self::sanitize_bool( $rule['enabled'] ),
				),
				$group,
				array( 'actions' => $actions )
			);
		}

		return $clean;
	}

	/**
	 * Default empty schema.
	 *
	 * @return array
	 */
	public static function default_schema() {
		return self::normalize_schema(
			array(
				'version'  => '1.0',
				'settings' => self::default_settings(),
				'steps'    => array(
					array(
						'id'       => 'step-1',
						'title'    => __( 'Step 1', 'craftforms-builder' ),
						'sections' => array(
							array(
								'id'     => 'section-1',
								'title'  => '',
								'fields' => array(
									self::default_field( 'text', 'your-name', __( 'Your Name', 'craftforms-builder' ) ),
									self::default_field( 'email', 'your-email', __( 'Your Email', 'craftforms-builder' ) ),
									self::default_field( 'textarea', 'your-message', __( 'Your Message', 'craftforms-builder' ) ),
									self::default_field( 'submit', 'submit', __( 'Send', 'craftforms-builder' ), false ),
								),
							),
						),
					),
				),
				'rules'    => array(),
			)
		);
	}

	/**
	 * @param string $type Field type.
	 * @param string $name Field name.
	 * @param string $label Label.
	 * @param bool   $required Required.
	 * @return array
	 */
	public static function default_field( $type, $name, $label, $required = true ) {
		return array(
			'id'          => 'field-' . self::uuid(),
			'type'        => $type,
			'name'        => $name,
			'label'       => $label,
			'required'    => $required,
			'placeholder' => '',
			'help'        => '',
			'options'     => array(),
			'validation'  => array(),
			'attrs'       => array(),
		);
	}

	/**
	 * @param array $schema Raw schema.
	 * @return array
	 */
	public static function normalize_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			$schema = array();
		}

		$schema = wp_parse_args(
			$schema,
			array(
				'version'  => '1.0',
				'settings' => array(),
				'steps'    => array(),
				'rules'    => array(),
			)
		);

		$schema['version'] = '1.0';

		if ( empty( $schema['steps'] ) || ! is_array( $schema['steps'] ) ) {
			$schema['steps'] = array(
				array(
					'id'       => 'step-1',
					'title'    => __( 'Step 1', 'craftforms-builder' ),
					'sections' => array(
						array(
							'id'     => 'section-1',
							'title'  => '',
							'fields' => array(),
						),
					),
				),
			);
		}

		foreach ( $schema['steps'] as $si => $step ) {
			$step = is_array( $step ) ? $step : array();
			$step['id'] = self::sanitize_slug_id( (string) ( $step['id'] ?? '' ), 'step-' . ( $si + 1 ) );
			if ( ! array_key_exists( 'title', $step ) ) {
				/* translators: %d: step number. */
				$step['title'] = sprintf( __( 'Step %d', 'craftforms-builder' ), $si + 1 );
			} else {
				$step['title'] = sanitize_text_field( (string) $step['title'] );
			}
			if ( empty( $step['sections'] ) || ! is_array( $step['sections'] ) ) {
				$step['sections'] = array();
			}

			$schema['steps'][ $si ] = $step;

			if ( empty( $schema['steps'][ $si ]['sections'] ) ) {
				$schema['steps'][ $si ]['sections'] = array(
					array(
						'id'     => 'section-1',
						'title'  => '',
						'fields' => array(),
					),
				);
			}

			foreach ( $schema['steps'][ $si ]['sections'] as $sei => $section ) {
				$section = is_array( $section ) ? $section : array();
				$section = wp_parse_args(
					$section,
					array(
						'id'     => 'section-' . ( $sei + 1 ),
						'title'  => '',
						'fields' => array(),
					)
				);

				$section['id']    = self::sanitize_slug_id( (string) $section['id'], 'section-' . ( $sei + 1 ) );
				$section['title'] = sanitize_text_field( (string) $section['title'] );

				$schema['steps'][ $si ]['sections'][ $sei ] = $section;

				if ( ! is_array( $schema['steps'][ $si ]['sections'][ $sei ]['fields'] ) ) {
					$schema['steps'][ $si ]['sections'][ $sei ]['fields'] = array();
				}

				foreach ( $schema['steps'][ $si ]['sections'][ $sei ]['fields'] as $fi => $field ) {
					$schema['steps'][ $si ]['sections'][ $sei ]['fields'][ $fi ] = self::normalize_field( $field );
				}
			}
		}

		$schema['rules'] = self::sanitize_rules( $schema['rules'] ?? array() );

		$schema['settings']              = self::normalize_settings( $schema['settings'] );
		$schema['settings']['multistep'] = count( $schema['steps'] ) > 1 || ! empty( $schema['settings']['multistep'] );

		return $schema;
	}

	/**
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'multistep'           => false,
			'autosave'            => true,
			'saveContinue'        => true,
			'animatedTransitions' => true,
			'conversational'      => false,
			'previewDevice'       => 'desktop',
			'gdpr'                => array(
				'enabled'      => false,
				'fieldName'    => 'gdpr-consent',
				'label'        => __( 'I agree to the privacy policy.', 'craftforms-builder' ),
				'errorMessage' => __( 'You must accept the privacy policy.', 'craftforms-builder' ),
			),
			'accessibility'       => array(
				'ariaLabels'   => true,
				'highContrast' => false,
				'focusVisible' => true,
			),
			'security'            => array(
				'honeypot'        => true,
				'honeypotField'   => 'cfb_hp',
				'rateLimit'       => true,
				'rateLimitCount'  => 10,
				'rateLimitWindow' => 3600,
			),
			'thankYou'            => array(
				'type'        => 'message',
				'message'     => __( 'Thank you for your message. It has been sent.', 'craftforms-builder' ),
				'redirectUrl' => '',
				'pageId'      => 0,
			),
			'emails'              => array(
				'htmlTemplate' => true,
				'htmlWrapper'  => '',
				'brandColor'   => '#3858e9',
				'logoUrl'      => '',
				'attachPdf'    => false,
			),
			'appearance'          => array(
				'theme'           => 'modern',
				'primaryColor'    => '#3858e9',
				'borderRadius'    => 'medium',
				'formWidth'       => 'medium',
				'fieldSpacing'    => 'comfortable',
				'progressStyle'   => 'pills',
				'showStepCounter' => true,
				'showStepDots'    => true,
				'showStepHeading' => false,
			),
		);
	}

	/**
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function normalize_settings( $settings ) {
		$defaults = self::default_settings();
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

		$settings['multistep']           = self::sanitize_bool( $settings['multistep'] ?? false );
		$settings['autosave']            = self::sanitize_bool( $settings['autosave'] ?? true );
		$settings['saveContinue']        = self::sanitize_bool( $settings['saveContinue'] ?? true );
		$settings['animatedTransitions'] = self::sanitize_bool( $settings['animatedTransitions'] ?? true );
		$settings['conversational']      = self::sanitize_bool( $settings['conversational'] ?? false );
		$settings['previewDevice']       = self::sanitize_enum(
			$settings['previewDevice'] ?? 'desktop',
			array( 'desktop', 'tablet', 'mobile' ),
			'desktop'
		);

		foreach ( array( 'gdpr', 'accessibility', 'security', 'thankYou', 'emails', 'appearance' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) && isset( $defaults[ $key ] ) ) {
				$settings[ $key ] = wp_parse_args( $settings[ $key ], $defaults[ $key ] );
			}
		}

		$settings['gdpr']['enabled']      = self::sanitize_bool( $settings['gdpr']['enabled'] ?? false );
		$settings['gdpr']['fieldName']    = sanitize_key( (string) ( $settings['gdpr']['fieldName'] ?? 'gdpr-consent' ) );
		$settings['gdpr']['label']        = sanitize_text_field( (string) ( $settings['gdpr']['label'] ?? '' ) );
		$settings['gdpr']['errorMessage'] = sanitize_text_field( (string) ( $settings['gdpr']['errorMessage'] ?? '' ) );

		$settings['accessibility']['ariaLabels']   = self::sanitize_bool( $settings['accessibility']['ariaLabels'] ?? true );
		$settings['accessibility']['highContrast'] = self::sanitize_bool( $settings['accessibility']['highContrast'] ?? false );
		$settings['accessibility']['focusVisible'] = self::sanitize_bool( $settings['accessibility']['focusVisible'] ?? true );

		$settings['security']['honeypot']        = self::sanitize_bool( $settings['security']['honeypot'] ?? true );
		$settings['security']['honeypotField']   = sanitize_key( (string) ( $settings['security']['honeypotField'] ?? 'cfb_hp' ) );
		$settings['security']['rateLimit']       = self::sanitize_bool( $settings['security']['rateLimit'] ?? true );
		$settings['security']['rateLimitCount']  = max( 1, absint( $settings['security']['rateLimitCount'] ?? 10 ) );
		$settings['security']['rateLimitWindow'] = max( 60, absint( $settings['security']['rateLimitWindow'] ?? 3600 ) );

		$settings['thankYou']['type']        = self::sanitize_enum(
			$settings['thankYou']['type'] ?? 'message',
			array( 'message', 'redirect', 'page' ),
			'message'
		);
		$settings['thankYou']['message']     = sanitize_textarea_field( (string) ( $settings['thankYou']['message'] ?? '' ) );
		$settings['thankYou']['redirectUrl'] = esc_url_raw( (string) ( $settings['thankYou']['redirectUrl'] ?? '' ) );
		$settings['thankYou']['pageId']      = absint( $settings['thankYou']['pageId'] ?? 0 );

		$settings['emails']['htmlTemplate'] = self::sanitize_bool( $settings['emails']['htmlTemplate'] ?? true );
		$settings['emails']['htmlWrapper']  = wp_kses_post( (string) ( $settings['emails']['htmlWrapper'] ?? '' ) );
		$brand                              = sanitize_hex_color( (string) ( $settings['emails']['brandColor'] ?? '#3858e9' ) );
		$settings['emails']['brandColor']   = $brand ? $brand : '#3858e9';
		$settings['emails']['logoUrl']      = esc_url_raw( (string) ( $settings['emails']['logoUrl'] ?? '' ) );
		$settings['emails']['attachPdf']    = self::sanitize_bool( $settings['emails']['attachPdf'] ?? false );

		$settings['appearance']['theme'] = self::sanitize_enum(
			$settings['appearance']['theme'] ?? 'modern',
			array( 'modern', 'minimal', 'bordered' ),
			'modern'
		);
		$primary_color                     = sanitize_hex_color( (string) ( $settings['appearance']['primaryColor'] ?? '#3858e9' ) );
		$settings['appearance']['primaryColor'] = $primary_color ? $primary_color : '#3858e9';
		$settings['appearance']['borderRadius'] = self::sanitize_enum(
			$settings['appearance']['borderRadius'] ?? 'medium',
			array( 'none', 'small', 'medium', 'large' ),
			'medium'
		);
		$settings['appearance']['formWidth'] = self::sanitize_enum(
			$settings['appearance']['formWidth'] ?? 'medium',
			array( 'narrow', 'medium', 'wide', 'full' ),
			'medium'
		);
		$settings['appearance']['fieldSpacing'] = self::sanitize_enum(
			$settings['appearance']['fieldSpacing'] ?? 'comfortable',
			array( 'comfortable', 'compact' ),
			'comfortable'
		);
		$settings['appearance']['progressStyle'] = self::sanitize_enum(
			$settings['appearance']['progressStyle'] ?? 'pills',
			array( 'pills', 'bar', 'minimal' ),
			'pills'
		);
		$settings['appearance']['showStepCounter'] = self::sanitize_bool( $settings['appearance']['showStepCounter'] ?? true );
		$settings['appearance']['showStepDots']    = self::sanitize_bool( $settings['appearance']['showStepDots'] ?? true );
		$settings['appearance']['showStepHeading'] = self::sanitize_bool( $settings['appearance']['showStepHeading'] ?? false );

		return $settings;
	}

	/**
	 * @param array $field Field data.
	 * @return array
	 */
	public static function normalize_field( $field ) {
		if ( ! is_array( $field ) ) {
			$field = array();
		}

		$field = wp_parse_args(
			$field,
			array(
				'id'          => 'field-' . self::uuid(),
				'type'        => 'text',
				'name'        => 'field-' . wp_rand( 1000, 9999 ),
				'label'       => '',
				'required'    => false,
				'placeholder' => '',
				'help'        => '',
				'options'     => array(),
				'validation'  => array(),
				'attrs'       => array(),
				'children'    => array(),
			)
		);

		$field['id']          = self::sanitize_slug_id( (string) $field['id'], 'field-' . self::uuid() );
		$field['type']        = self::sanitize_enum( $field['type'], self::FIELD_TYPES, 'text' );
		$field['name']        = sanitize_key( (string) $field['name'] );
		$field['label']       = sanitize_text_field( (string) $field['label'] );
		$field['placeholder'] = sanitize_text_field( (string) $field['placeholder'] );
		$field['help']        = sanitize_text_field( (string) $field['help'] );
		$field['required']    = self::sanitize_bool( $field['required'] );
		$field['options']     = self::sanitize_options( $field['options'] );
		$field['validation']  = self::sanitize_validation( $field['validation'] );
		$field['attrs']       = self::sanitize_attrs( $field['attrs'] );

		if ( '' === $field['name'] ) {
			$field['name'] = 'field-' . wp_rand( 1000, 9999 );
		}

		if ( ! empty( $field['children'] ) && is_array( $field['children'] ) ) {
			$children = array();
			foreach ( $field['children'] as $child ) {
				$children[] = self::normalize_field( $child );
			}
			$field['children'] = $children;
		} else {
			$field['children'] = array();
		}

		return $field;
	}

	/**
	 * @return string
	 */
	private static function uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0x0fff ) | 0x4000, wp_rand( 0, 0x3fff ) | 0x8000, wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ) );
	}

	/**
	 * Best-effort import from existing CF7 form template.
	 *
	 * @param string $markup CF7 form body.
	 * @return array
	 */
	public static function from_cf7_markup( $markup ) {
		$schema  = self::default_schema();
		$fields  = array();
		$pattern = '/\[(?:\/)?([a-z][a-z0-9\*\-]*)(?:\s+[^\]]*)?\]/i';

		if ( preg_match_all( $pattern, (string) $markup, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$tag = strtolower( $match[1] );
				if ( in_array( $tag, array( '/label', 'label' ), true ) ) {
					continue;
				}

				$type = str_replace( '*', '', $tag );
				$map  = array(
					'text'       => 'text',
					'email'      => 'email',
					'tel'        => 'phone',
					'url'        => 'text',
					'textarea'   => 'textarea',
					'select'     => 'select',
					'checkbox'   => 'checkbox',
					'radio'      => 'radio',
					'number'     => 'number',
					'date'       => 'date',
					'file'       => 'file',
					'hidden'     => 'hidden',
					'submit'     => 'submit',
					'quiz'       => 'text',
					'acceptance' => 'checkbox',
				);

				if ( ! isset( $map[ $type ] ) ) {
					continue;
				}

				$full = $match[0];
				preg_match( '/\s([a-z][a-z0-9\-_]*)/i', $full, $name_match );
				$name = isset( $name_match[1] ) ? sanitize_key( $name_match[1] ) : 'field-' . wp_rand( 1000, 9999 );

				$fields[] = array(
					'id'          => 'field-' . self::uuid(),
					'type'        => $map[ $type ],
					'name'        => $name,
					'label'       => ucwords( str_replace( array( '-', '_' ), ' ', $name ) ),
					'required'    => false !== strpos( $tag, '*' ),
					'placeholder' => '',
					'help'        => '',
					'options'     => array(),
					'validation'  => array(),
					'attrs'       => array(),
				);
			}
		}

		if ( $fields ) {
			$schema['steps'][0]['sections'][0]['fields'] = array_map( array( __CLASS__, 'normalize_field' ), $fields );
		}

		return $schema;
	}
}
