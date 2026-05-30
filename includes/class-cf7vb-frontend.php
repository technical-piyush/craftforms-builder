<?php
/**
 * Frontend: multistep, conditional logic, validation, analytics.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Frontend {

	public static function init() {
		add_action( 'wpcf7_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wpcf7_form_hidden_fields', array( __CLASS__, 'hidden_fields' ), 10, 1 );
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'inject_wrapper' ), 20, 1 );
		add_filter( 'wpcf7_kses_allowed_html', array( __CLASS__, 'allow_step_markup' ), 10, 2 );
	}

	/**
	 * Allow multistep wrapper attributes in CF7 form template.
	 *
	 * @param array  $allowed Allowed HTML.
	 * @param string $context KSES context.
	 * @return array
	 */
	public static function allow_step_markup( $allowed, $context ) {
		if ( 'form' !== $context ) {
			return $allowed;
		}

		if ( ! isset( $allowed['div'] ) ) {
			$allowed['div'] = array();
		}

		$allowed['div']['class']                 = true;
		$allowed['div']['data-cf7vb-step']       = true;
		$allowed['div']['data-cf7vb-step-index'] = true;
		$allowed['div']['data-cf7vb-step-title'] = true;

		return $allowed;
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'cf7vb-frontend', CF7VB_PLUGIN_URL . 'assets/css/frontend.css', array(), CF7VB_VERSION );
		wp_enqueue_script(
			'cf7vb-frontend',
			CF7VB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'contact-form-7' ),
			CF7VB_VERSION,
			true
		);

		wp_localize_script(
			'cf7vb-frontend',
			'CF7VB_FRONT',
			array(
				'restUrl' => esc_url_raw( rest_url( CF7VB_REST_API::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'next'         => __( 'Next', 'form-studio-for-contact-form-7' ),
					'prev'         => __( 'Previous', 'form-studio-for-contact-form-7' ),
					'submit'       => __( 'Submit', 'form-studio-for-contact-form-7' ),
					'step'         => __( 'Step', 'form-studio-for-contact-form-7' ),
					'of'           => __( 'of', 'form-studio-for-contact-form-7' ),
					'saveContinue' => __( 'Save & continue later', 'form-studio-for-contact-form-7' ),
					'draftSaved'   => __( 'Progress saved.', 'form-studio-for-contact-form-7' ),
					'signClear'    => __( 'Clear signature', 'form-studio-for-contact-form-7' ),
					'stepError'    => __( 'Please fill in the required fields before continuing.', 'form-studio-for-contact-form-7' ),
				),
			)
		);
	}

	/**
	 * @param array $fields Hidden fields.
	 * @return array
	 */
	public static function hidden_fields( $fields ) {
		$contact_form = WPCF7_ContactForm::get_current();
		if ( ! $contact_form ) {
			return $fields;
		}

		$schema = get_post_meta( $contact_form->id(), CF7VB_SCHEMA_META, true );
		if ( ! is_array( $schema ) ) {
			return $fields;
		}

		$session = filter_input( INPUT_COOKIE, 'cf7vb_session', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$session = $session ? sanitize_text_field( $session ) : wp_generate_password( 16, false );

		$fields['_cf7vb']         = '1';
		$fields['_cf7vb_session'] = $session;
		$fields['_cf7vb_form_id'] = (string) $contact_form->id();

		$draft = filter_input( INPUT_GET, 'cf7vb_draft' );
		if ( null !== $draft && '' !== $draft ) {
			$fields['_cf7vb_draft'] = sanitize_key( $draft );
		}

		return $fields;
	}

	/**
	 * @param string $elements Form HTML.
	 * @return string
	 */
	public static function inject_wrapper( $elements ) {
		$contact_form = WPCF7_ContactForm::get_current();
		if ( ! $contact_form ) {
			return $elements;
		}

		$schema = get_post_meta( $contact_form->id(), CF7VB_SCHEMA_META, true );
		if ( ! is_array( $schema ) ) {
			return $elements;
		}

		$schema = CF7VB_Parser::normalize_schema( $schema );
		$app    = $schema['settings']['appearance'] ?? array();

		$classes = array( 'cf7vb-enhanced' );
		if ( ! empty( $schema['settings']['multistep'] ) && count( $schema['steps'] ) > 1 ) {
			$classes[] = 'cf7vb-multistep';
		}
		if ( ! empty( $schema['settings']['conversational'] ) ) {
			$classes[] = 'cf7vb-conversational';
		}
		if ( ! empty( $schema['settings']['accessibility']['highContrast'] ) ) {
			$classes[] = 'cf7vb-a11y-high-contrast';
		}
		if ( ! empty( $schema['settings']['accessibility']['focusVisible'] ) ) {
			$classes[] = 'cf7vb-a11y-focus';
		}

		$theme = sanitize_html_class( $app['theme'] ?? 'modern' );
		if ( $theme ) {
			$classes[] = 'cf7vb-theme-' . $theme;
		}
		$classes[] = 'cf7vb-width-' . sanitize_html_class( $app['formWidth'] ?? 'medium' );
		$classes[] = 'cf7vb-spacing-' . sanitize_html_class( $app['fieldSpacing'] ?? 'comfortable' );
		$classes[] = 'cf7vb-radius-' . sanitize_html_class( $app['borderRadius'] ?? 'medium' );
		$classes[] = 'cf7vb-progress-style-' . sanitize_html_class( $app['progressStyle'] ?? 'pills' );

		$radius_map = array(
			'none'   => '0px',
			'small'  => '6px',
			'medium' => '10px',
			'large'  => '16px',
		);
		$width_map  = array(
			'narrow' => '480px',
			'medium' => '640px',
			'wide'   => '760px',
			'full'   => '100%',
		);
		$gap_map    = array(
			'compact'     => '14px',
			'comfortable' => '22px',
		);

		$primary = $app['primaryColor'] ?? '#3858e9';
		$style   = sprintf(
			'--cf7vb-primary:%1$s;--cf7vb-radius:%2$s;--cf7vb-form-width:%3$s;--cf7vb-field-gap:%4$s;',
			esc_attr( $primary ),
			esc_attr( $radius_map[ $app['borderRadius'] ?? 'medium' ] ?? '10px' ),
			esc_attr( $width_map[ $app['formWidth'] ?? 'medium' ] ?? '640px' ),
			esc_attr( $gap_map[ $app['fieldSpacing'] ?? 'comfortable' ] ?? '22px' )
		);

		$schema_json = wp_json_encode( $schema );
		$attrs       = array(
			'class'           => implode( ' ', $classes ),
			'data-cf7vb-form' => (string) $contact_form->id(),
			'style'           => $style,
		);

		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		// Compact schema on the element; full copy in a script tag avoids attribute size/encoding issues.
		return sprintf(
			'<div%s data-cf7vb-schema="%s"><script type="application/json" class="cf7vb-schema-json">%s</script>%s</div>',
			$attr_html,
			esc_attr( $schema_json ),
			str_replace( '</', '<\/', $schema_json ),
			$elements
		);
	}
}
