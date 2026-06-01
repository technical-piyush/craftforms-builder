<?php
/**
 * Plugin bootstrap.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Plugin {

	private static $instance = null;

	/**
	 * Schema waiting to be written after a new contact form gets an ID.
	 *
	 * @var array|null
	 */
	private $pending_schema = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run() {
		CFB_Activator::maybe_upgrade();

		CFB_Admin::init();
		CFB_REST_API::init();
		CFB_Submissions::init();
		CFB_Frontend::init();
		CFB_Validation::init();
		CFB_Security::init();
		CFB_Email::init();
		CFB_Thankyou::init();
		CFB_Export::init();
		CFB_Analytics::init();

		add_action( 'wpcf7_save_contact_form', array( $this, 'on_save_contact_form' ), 5, 3 );
		add_action( 'wpcf7_after_save', array( $this, 'on_after_save' ), 10, 1 );
		add_filter( 'wpcf7_additional_settings', array( $this, 'additional_settings' ), 10, 1 );
	}

	/**
	 * @param string $content Additional settings.
	 * @return string
	 */
	public function additional_settings( $content ) {
		if ( false !== strpos( $content, 'cfb:' ) ) {
			return $content;
		}

		return $content . "\n" . 'cfb: enabled';
	}

	/**
	 * @param WPCF7_ContactForm $contact_form Contact form.
	 * @param array             $data         Save payload.
	 * @param string            $context      Save context.
	 */
	public function on_save_contact_form( $contact_form, $data, $context ) {
		if ( 'save' !== $context ) {
			return;
		}

		$schema = CFB_Parser::get_schema_from_post();
		if ( null === $schema ) {
			return;
		}
		$form_id = (int) $contact_form->id();

		if ( $form_id > 0 ) {
			update_post_meta( $form_id, CFB_SCHEMA_META, $schema );
		} else {
			$this->pending_schema = $schema;
		}

		$compiled      = CFB_Compiler::compile( $schema );
		$props         = $contact_form->get_properties();
		$props['form'] = wpcf7_sanitize_form( $compiled );
		$contact_form->set_properties( $props );
	}

	/**
	 * Persist schema meta after a brand-new contact form receives its post ID.
	 *
	 * @param WPCF7_ContactForm $contact_form Contact form.
	 */
	public function on_after_save( $contact_form ) {
		if ( ! is_array( $this->pending_schema ) ) {
			return;
		}

		$form_id = (int) $contact_form->id();
		if ( $form_id > 0 ) {
			update_post_meta( $form_id, CFB_SCHEMA_META, $this->pending_schema );
		}

		$this->pending_schema = null;
	}

	/**
	 * Whether this contact form was saved with the CraftForms visual builder.
	 *
	 * @param int $form_id Contact form post ID.
	 * @return bool
	 */
	public static function form_uses_builder( $form_id ) {
		$form_id = absint( $form_id );
		if ( ! $form_id ) {
			return false;
		}

		$schema = get_post_meta( $form_id, CFB_SCHEMA_META, true );

		return is_array( $schema ) && ! empty( $schema['steps'] );
	}

	/**
	 * @param int $form_id Post ID.
	 * @return array
	 */
	public static function get_schema( $form_id ) {
		$form_id = absint( $form_id );
		if ( ! $form_id ) {
			return CFB_Parser::default_schema();
		}

		$stored = get_post_meta( $form_id, CFB_SCHEMA_META, true );
		if ( is_array( $stored ) && ! empty( $stored['steps'] ) ) {
			return CFB_Parser::normalize_schema( $stored );
		}

		$contact_form = wpcf7_contact_form( $form_id );
		if ( $contact_form ) {
			$parsed = CFB_Parser::from_cf7_markup( $contact_form->prop( 'form' ) );
			if ( ! empty( $parsed['steps'][0]['sections'][0]['fields'] ) ) {
				return $parsed;
			}
		}

		return CFB_Parser::default_schema();
	}
}
