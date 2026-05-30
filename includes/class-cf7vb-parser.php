<?php
/**
 * Parse / normalize visual builder schema.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Parser {

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
						'title'    => __( 'Step 1', 'cf7-visual-form-builder' ),
						'sections' => array(
							array(
								'id'     => 'section-1',
								'title'  => '',
								'fields' => array(
									self::default_field( 'text', 'your-name', __( 'Your Name', 'cf7-visual-form-builder' ) ),
									self::default_field( 'email', 'your-email', __( 'Your Email', 'cf7-visual-form-builder' ) ),
									self::default_field( 'textarea', 'your-message', __( 'Your Message', 'cf7-visual-form-builder' ) ),
									self::default_field( 'submit', 'submit', __( 'Send', 'cf7-visual-form-builder' ), false ),
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
		$schema = wp_parse_args(
			$schema,
			array(
				'version'  => '1.0',
				'settings' => array(),
				'steps'    => array(),
				'rules'    => array(),
			)
		);

		if ( empty( $schema['steps'] ) || ! is_array( $schema['steps'] ) ) {
			$schema['steps'] = array(
				array(
					'id'       => 'step-1',
					'title'    => __( 'Step 1', 'cf7-visual-form-builder' ),
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
			if ( empty( $step['id'] ) ) {
				$step['id'] = 'step-' . ( $si + 1 );
			}
			if ( ! array_key_exists( 'title', $step ) ) {
				/* translators: %d: step number. */
				$step['title'] = sprintf( __( 'Step %d', 'cf7-visual-form-builder' ), $si + 1 );
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
				$schema['steps'][ $si ]['sections'][ $sei ] = wp_parse_args(
					$section,
					array(
						'id'     => 'section-' . ( $sei + 1 ),
						'title'  => '',
						'fields' => array(),
					)
				);

				if ( ! is_array( $schema['steps'][ $si ]['sections'][ $sei ]['fields'] ) ) {
					$schema['steps'][ $si ]['sections'][ $sei ]['fields'] = array();
				}

				foreach ( $schema['steps'][ $si ]['sections'][ $sei ]['fields'] as $fi => $field ) {
					$schema['steps'][ $si ]['sections'][ $sei ]['fields'][ $fi ] = self::normalize_field( $field );
				}
			}
		}

		if ( ! is_array( $schema['rules'] ) ) {
			$schema['rules'] = array();
		}

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
				'label'        => __( 'I agree to the privacy policy.', 'cf7-visual-form-builder' ),
				'errorMessage' => __( 'You must accept the privacy policy.', 'cf7-visual-form-builder' ),
			),
			'accessibility'       => array(
				'ariaLabels'   => true,
				'highContrast' => false,
				'focusVisible' => true,
			),
			'security'            => array(
				'honeypot'        => true,
				'honeypotField'   => 'cf7vb_hp',
				'rateLimit'       => true,
				'rateLimitCount'  => 10,
				'rateLimitWindow' => 3600,
			),
			'thankYou'            => array(
				'type'        => 'message',
				'message'     => __( 'Thank you for your message. It has been sent.', 'cf7-visual-form-builder' ),
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

		foreach ( array( 'gdpr', 'accessibility', 'security', 'thankYou', 'emails', 'appearance' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) && isset( $defaults[ $key ] ) ) {
				$settings[ $key ] = wp_parse_args( $settings[ $key ], $defaults[ $key ] );
			}
		}

		$allowed_themes = array( 'modern', 'minimal', 'bordered' );
		if ( ! in_array( $settings['appearance']['theme'], $allowed_themes, true ) ) {
			$settings['appearance']['theme'] = 'modern';
		}

		$primary_color                          = sanitize_hex_color( $settings['appearance']['primaryColor'] );
		$settings['appearance']['primaryColor'] = $primary_color ? $primary_color : '#3858e9';

		return $settings;
	}

	/**
	 * @param array $field Field data.
	 * @return array
	 */
	public static function normalize_field( $field ) {
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
			)
		);

		$field['name'] = sanitize_key( $field['name'] );
		if ( '' === $field['name'] ) {
			$field['name'] = 'field-' . wp_rand( 1000, 9999 );
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
