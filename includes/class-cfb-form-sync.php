<?php
/**
 * Keep CF7 form markup in sync with the visual builder schema.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Form_Sync {

	public static function init() {
		add_filter( 'wpcf7_contact_form_properties', array( __CLASS__, 'apply_compiled_form' ), 20, 2 );
		add_filter( 'wpcf7_form_tag_data_option', array( __CLASS__, 'country_data_option' ), 10, 3 );
	}

	/**
	 * Always render the latest compiled template from stored schema (fixes stale/broken CF7 body).
	 *
	 * @param array             $properties     Form properties.
	 * @param WPCF7_ContactForm $contact_form   Contact form.
	 * @return array
	 */
	public static function apply_compiled_form( $properties, $contact_form ) {
		$form_id = (int) $contact_form->id();
		if ( ! $form_id || ! CFB_Plugin::form_uses_builder( $form_id ) ) {
			return $properties;
		}

		$schema = CFB_Plugin::get_schema( $form_id );
		$properties['form'] = wpcf7_sanitize_form( CFB_Compiler::compile( $schema ) );

		return $properties;
	}

	/**
	 * Inject country list for [select data:cfb_countries] tags.
	 *
	 * @param mixed  $data     Existing data.
	 * @param array  $options  Data option names.
	 * @param string $args     Args.
	 * @return mixed
	 */
	public static function country_data_option( $data, $options, $args ) {
		unset( $args );

		if ( in_array( 'cfb_countries', (array) $options, true ) ) {
			return array_merge( (array) $data, CFB_Countries::get_options() );
		}

		return $data;
	}
}
