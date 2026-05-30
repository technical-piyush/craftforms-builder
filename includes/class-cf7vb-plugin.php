<?php
/**
 * Plugin bootstrap.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Plugin {

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
		CF7VB_Activator::maybe_upgrade();

		CF7VB_Admin::init();
		CF7VB_REST_API::init();
		CF7VB_Submissions::init();
		CF7VB_Frontend::init();
		CF7VB_Validation::init();
		CF7VB_Security::init();
		CF7VB_Email::init();
		CF7VB_Thankyou::init();
		CF7VB_Export::init();
		CF7VB_Analytics::init();

		add_action( 'wpcf7_save_contact_form', array( $this, 'on_save_contact_form' ), 5, 3 );
		add_action( 'wpcf7_after_save', array( $this, 'on_after_save' ), 10, 1 );
		add_filter( 'wpcf7_additional_settings', array( $this, 'additional_settings' ), 10, 1 );
	}

	/**
	 * @param string $content Additional settings.
	 * @return string
	 */
	public function additional_settings( $content ) {
		if ( false !== strpos( $content, 'cf7vb:' ) ) {
			return $content;
		}

		return $content . "\n" . 'cf7vb: enabled';
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

		// Read the raw JSON via filter_input(): it returns the unslashed request value and
		// every decoded field is sanitized in CF7VB_Parser::normalize_schema() below.
		$raw = filter_input( INPUT_POST, 'cf7vb_schema' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$schema = json_decode( $raw, true );
		if ( ! is_array( $schema ) ) {
			return;
		}

		$schema  = CF7VB_Parser::normalize_schema( $schema );
		$form_id = (int) $contact_form->id();

		if ( $form_id > 0 ) {
			update_post_meta( $form_id, CF7VB_SCHEMA_META, $schema );
		} else {
			$this->pending_schema = $schema;
		}

		$compiled      = CF7VB_Compiler::compile( $schema );
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
			update_post_meta( $form_id, CF7VB_SCHEMA_META, $this->pending_schema );
		}

		$this->pending_schema = null;
	}

	/**
	 * @param int $form_id Post ID.
	 * @return array
	 */
	public static function get_schema( $form_id ) {
		$form_id = absint( $form_id );
		if ( ! $form_id ) {
			return CF7VB_Parser::default_schema();
		}

		$stored = get_post_meta( $form_id, CF7VB_SCHEMA_META, true );
		if ( is_array( $stored ) && ! empty( $stored['steps'] ) ) {
			return CF7VB_Parser::normalize_schema( $stored );
		}

		$contact_form = wpcf7_contact_form( $form_id );
		if ( $contact_form ) {
			$parsed = CF7VB_Parser::from_cf7_markup( $contact_form->prop( 'form' ) );
			if ( ! empty( $parsed['steps'][0]['sections'][0]['fields'] ) ) {
				return $parsed;
			}
		}

		return CF7VB_Parser::default_schema();
	}
}
