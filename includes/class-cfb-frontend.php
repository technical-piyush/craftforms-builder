<?php
/**
 * Frontend: multistep, conditional logic, validation, analytics.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Frontend {

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
		$allowed['div']['data-cfb-step']       = true;
		$allowed['div']['data-cfb-step-index'] = true;
		$allowed['div']['data-cfb-step-title'] = true;

		return $allowed;
	}

	public static function enqueue_assets() {
		$vendor = CFB_PLUGIN_URL . 'assets/vendor/';

		wp_enqueue_style(
			'cfb-select2',
			$vendor . 'select2/css/select2.min.css',
			array(),
			'4.1.0-rc.0'
		);
		wp_enqueue_style(
			'cfb-intl-tel-input',
			$vendor . 'intl-tel-input/css/intlTelInput.min.css',
			array(),
			'19.5.6'
		);
		wp_enqueue_style( 'cfb-frontend', CFB_PLUGIN_URL . 'assets/css/frontend.css', array( 'cfb-select2', 'cfb-intl-tel-input' ), CFB_VERSION );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script(
			'cfb-select2',
			$vendor . 'select2/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0-rc.0',
			true
		);
		wp_enqueue_script(
			'cfb-intl-tel-input',
			$vendor . 'intl-tel-input/js/intlTelInput.min.js',
			array(),
			'19.5.6',
			true
		);
		wp_enqueue_script(
			'cfb-intl-tel-input-utils',
			$vendor . 'intl-tel-input/js/utils.js',
			array( 'cfb-intl-tel-input' ),
			'19.5.6',
			true
		);

		wp_enqueue_script(
			'cfb-frontend',
			CFB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'contact-form-7', 'jquery', 'cfb-select2', 'cfb-intl-tel-input', 'cfb-intl-tel-input-utils' ),
			CFB_VERSION,
			true
		);

		wp_localize_script(
			'cfb-frontend',
			'CFB_FRONT',
			array(
				'restUrl' => esc_url_raw( rest_url( CFB_REST_API::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'phoneCountries'    => CFB_Countries::get_phone_countries(),
				'defaultPhoneIso'   => CFB_Countries::get_default_phone_iso(),
				'itiUtilsUrl'       => esc_url_raw( $vendor . 'intl-tel-input/js/utils.js' ),
				'strings'           => array(
					'next'            => __( 'Next', 'craftforms-builder' ),
					'prev'            => __( 'Previous', 'craftforms-builder' ),
					'submit'          => __( 'Submit', 'craftforms-builder' ),
					'step'            => __( 'Step', 'craftforms-builder' ),
					'of'              => __( 'of', 'craftforms-builder' ),
					'saveContinue'    => __( 'Save & continue later', 'craftforms-builder' ),
					'draftSaved'      => __( 'Progress saved.', 'craftforms-builder' ),
					'signClear'       => __( 'Clear signature', 'craftforms-builder' ),
					'stepError'       => __( 'Please fill in the required fields before continuing.', 'craftforms-builder' ),
					'selectCountry'   => __( 'Select a country', 'craftforms-builder' ),
					'countrySearch'   => __( 'Search countries…', 'craftforms-builder' ),
					'countryEmpty'    => __( 'No countries match your search.', 'craftforms-builder' ),
					'multiselectHint' => __( 'Choose one or more options', 'craftforms-builder' ),
					/* translators: %d: number of options selected in a multiselect field. */
					'selectedCount'        => __( '%d selected', 'craftforms-builder' ),
					'phoneLocalPlaceholder' => __( '555 000 0000', 'craftforms-builder' ),
					'phoneCodeSearch'      => __( 'Search country or code…', 'craftforms-builder' ),
					'phoneCodeEmpty'       => __( 'No matching country code.', 'craftforms-builder' ),
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

		if ( ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return $fields;
		}

		$session = filter_input( INPUT_COOKIE, 'cfb_session', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$session = $session ? sanitize_text_field( $session ) : wp_generate_password( 16, false );

		$fields['_cfb']         = '1';
		$fields['_cfb_session'] = $session;
		$fields['_cfb_form_id'] = (string) $contact_form->id();

		$draft = filter_input( INPUT_GET, 'cfb_draft', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_string( $draft ) && '' !== $draft ) {
			$fields['_cfb_draft'] = sanitize_key( $draft );
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

		if ( ! CFB_Plugin::form_uses_builder( $contact_form->id() ) ) {
			return $elements;
		}

		$schema = CFB_Parser::normalize_schema( CFB_Plugin::get_schema( $contact_form->id() ) );
		$app    = $schema['settings']['appearance'] ?? array();

		$classes = array( 'cfb-enhanced' );
		if ( ! empty( $schema['settings']['multistep'] ) && count( $schema['steps'] ) > 1 ) {
			$classes[] = 'cfb-multistep';
		}
		if ( ! empty( $schema['settings']['conversational'] ) ) {
			$classes[] = 'cfb-conversational';
		}
		if ( ! empty( $schema['settings']['accessibility']['highContrast'] ) ) {
			$classes[] = 'cfb-a11y-high-contrast';
		}
		if ( ! empty( $schema['settings']['accessibility']['focusVisible'] ) ) {
			$classes[] = 'cfb-a11y-focus';
		}

		$theme = sanitize_html_class( $app['theme'] ?? 'modern' );
		if ( $theme ) {
			$classes[] = 'cfb-theme-' . $theme;
		}
		$classes[] = 'cfb-width-' . sanitize_html_class( $app['formWidth'] ?? 'medium' );
		$classes[] = 'cfb-spacing-' . sanitize_html_class( $app['fieldSpacing'] ?? 'comfortable' );
		$classes[] = 'cfb-radius-' . sanitize_html_class( $app['borderRadius'] ?? 'medium' );
		$classes[] = 'cfb-progress-style-' . sanitize_html_class( $app['progressStyle'] ?? 'pills' );

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
			'--cfb-primary:%1$s;--cfb-radius:%2$s;--cfb-form-width:%3$s;--cfb-field-gap:%4$s;',
			esc_attr( $primary ),
			esc_attr( $radius_map[ $app['borderRadius'] ?? 'medium' ] ?? '10px' ),
			esc_attr( $width_map[ $app['formWidth'] ?? 'medium' ] ?? '640px' ),
			esc_attr( $gap_map[ $app['fieldSpacing'] ?? 'comfortable' ] ?? '22px' )
		);

		$schema_json = wp_json_encode( $schema );
		$attrs       = array(
			'class'           => implode( ' ', $classes ),
			'data-cfb-form' => (string) $contact_form->id(),
			'style'           => $style,
		);

		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		// Compact schema on the element; full copy in a script tag avoids attribute size/encoding issues.
		return sprintf(
			'<div%s data-cfb-schema="%s"><script type="application/json" class="cfb-schema-json">%s</script>%s</div>',
			$attr_html,
			esc_attr( $schema_json ),
			str_replace( '</', '<\/', $schema_json ),
			$elements
		);
	}
}
