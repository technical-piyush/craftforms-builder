<?php
/**
 * CF7 editor integration + entries admin.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CFB_Admin {

	public static function init() {
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'add_visual_panel' ), 5 );
		add_action( 'wpcf7_admin_footer', array( __CLASS__, 'render_schema_bootstrap' ), 5 );
		add_action( 'wpcf7_admin_load', array( __CLASS__, 'maybe_set_default_tab' ), 5, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_builder_assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_entries_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dashboard_assets' ) );
	}

	/**
	 * Visual Builder tab inside CF7 contact form editor.
	 *
	 * @param array $panels Editor panels.
	 * @return array
	 */
	public static function add_visual_panel( $panels ) {
		$visual = array(
			'cfb-visual-panel' => array(
				'title'    => __( 'Visual Builder', 'craftforms-builder' ),
				'callback' => array( __CLASS__, 'render_visual_panel' ),
			),
		);

		return array_merge( $visual, $panels );
	}

	/**
	 * @param WPCF7_ContactForm $post Contact form.
	 */
	public static function render_visual_panel( $post ) {
		$form_id = $post->id();
		if ( $post->initial() ) {
			$schema = CFB_Parser::from_cf7_markup( $post->prop( 'form' ) );
			if ( empty( $schema['steps'][0]['sections'][0]['fields'] ) ) {
				$schema = CFB_Parser::default_schema();
			} else {
				$schema = CFB_Parser::normalize_schema( $schema );
			}
		} else {
			$schema = CFB_Plugin::get_schema( $form_id );
		}
		?>
		<h2 class="cfb-panel-title"><?php esc_html_e( 'Visual Form Builder', 'craftforms-builder' ); ?></h2>
		<p class="description cfb-panel-desc">
			<?php esc_html_e( 'Use the tabs below: Build your form, check Preview, add Rules, then adjust Settings. Click Save on the contact form to publish.', 'craftforms-builder' ); ?>
		</p>
		<?php
		// Must live inside #wpcf7-admin-form-element (footer hook runs after </form>).
		printf(
			'<textarea id="cfb-schema-input" name="cfb_schema" class="cfb-schema-store" hidden aria-hidden="true">%s</textarea>',
			esc_textarea( wp_json_encode( $schema ) )
		);
		?>
		<div id="cfb-builder-root" class="cfb-builder-root" data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"></div>
		<?php
	}

	/**
	 * Hidden bootstrap for new forms before post ID exists.
	 *
	 * @param WPCF7_ContactForm $post Contact form.
	 */
	public static function render_schema_bootstrap( $post ) {
		if ( ! $post->initial() ) {
			return;
		}
		$schema = CFB_Parser::from_cf7_markup( $post->prop( 'form' ) );
		if ( empty( $schema['steps'][0]['sections'][0]['fields'] ) ) {
			$schema = CFB_Parser::default_schema();
		} else {
			$schema = CFB_Parser::normalize_schema( $schema );
		}
		printf(
			'<script type="application/json" id="cfb-default-schema">%s</script>',
			wp_json_encode( $schema )
		);
	}

	/**
	 * @param string $page_slug Admin page slug.
	 */
	public static function maybe_set_default_tab( $page_slug ) {
		if ( 'wpcf7-new' !== $page_slug ) {
			return;
		}
		$active_tab = filter_input( INPUT_GET, 'active-tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $active_tab ) ) {
			$_GET['active-tab'] = 'cfb-visual-panel';
		}
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 */
	public static function enqueue_builder_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'wpcf7' ) ) {
			return;
		}

		wp_enqueue_style(
			'cfb-builder',
			CFB_PLUGIN_URL . 'admin/css/builder.css',
			array( 'contact-form-7-admin' ),
			CFB_VERSION
		);

		wp_enqueue_style(
			'cfb-frontend',
			CFB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			CFB_VERSION
		);

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-api-fetch' );

		wp_enqueue_script(
			'cfb-builder-preview',
			CFB_PLUGIN_URL . 'admin/js/builder-preview.js',
			array( 'wp-element' ),
			CFB_VERSION,
			true
		);

		wp_enqueue_script(
			'cfb-builder-logic',
			CFB_PLUGIN_URL . 'admin/js/builder-logic.js',
			array( 'wp-element' ),
			CFB_VERSION,
			true
		);

		wp_enqueue_script(
			'cfb-builder-settings',
			CFB_PLUGIN_URL . 'admin/js/builder-settings.js',
			array( 'wp-element' ),
			CFB_VERSION,
			true
		);

		wp_enqueue_script(
			'cfb-builder',
			CFB_PLUGIN_URL . 'admin/js/builder.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wpcf7-admin', 'cfb-builder-preview', 'cfb-builder-logic', 'cfb-builder-settings' ),
			CFB_VERSION,
			true
		);

		$form_id = (int) filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		wp_localize_script(
			'cfb-builder',
			'CFB_BUILDER',
			array(
				'root'           => esc_url_raw( rest_url( CFB_REST_API::NS ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'formId'         => $form_id,
				'fieldTypes'     => self::field_palette(),
				'countries'      => CFB_Countries::get_options(),
				'phoneCountries' => CFB_Countries::get_phone_countries(),
				'defaultPhoneIso' => CFB_Countries::get_default_phone_iso(),
				'strings'        => array(
					'countries'             => CFB_Countries::get_options(),
					'repeaterChildTypes'    => array(
						array( 'type' => 'text', 'label' => __( 'Text', 'craftforms-builder' ) ),
						array( 'type' => 'email', 'label' => __( 'Email', 'craftforms-builder' ) ),
						array( 'type' => 'phone', 'label' => __( 'Phone', 'craftforms-builder' ) ),
						array( 'type' => 'number', 'label' => __( 'Number', 'craftforms-builder' ) ),
						array( 'type' => 'select', 'label' => __( 'Dropdown', 'craftforms-builder' ) ),
						array( 'type' => 'textarea', 'label' => __( 'Textarea', 'craftforms-builder' ) ),
					),
					'palette'               => __( 'Fields', 'craftforms-builder' ),
					'selectCountry'         => __( 'Select a country', 'craftforms-builder' ),
					'countryHint'           => __( 'Searchable country dropdown on the live form.', 'craftforms-builder' ),
					'phoneHint'             => __( 'Country code is chosen separately; visitors enter the local number only (no + prefix).', 'craftforms-builder' ),
					'phoneLocalPlaceholder' => __( '555 000 0000', 'craftforms-builder' ),
					'multiselectHint'       => __( 'Searchable multi-select dropdown (Select2) on the live form.', 'craftforms-builder' ),
					'repeaterFields'        => __( 'Repeater sub-fields', 'craftforms-builder' ),
					'repeaterFieldsHint'    => __( 'Add the fields that repeat in each row (name, email, etc.).', 'craftforms-builder' ),
					'addRepeaterField'      => __( 'Add sub-field', 'craftforms-builder' ),
					'minRows'               => __( 'Minimum rows', 'craftforms-builder' ),
					'maxRows'               => __( 'Maximum rows', 'craftforms-builder' ),
					'minValue'              => __( 'Minimum value', 'craftforms-builder' ),
					'maxValue'              => __( 'Maximum value', 'craftforms-builder' ),
					'stepValue'             => __( 'Step', 'craftforms-builder' ),
					'canvas'                => __( 'Form canvas', 'craftforms-builder' ),
					'inspector'             => __( 'Field settings', 'craftforms-builder' ),
					'preview'               => __( 'Live preview', 'craftforms-builder' ),
					'undo'                  => __( 'Undo', 'craftforms-builder' ),
					'redo'                  => __( 'Redo', 'craftforms-builder' ),
					'duplicate'             => __( 'Duplicate', 'craftforms-builder' ),
					'delete'                => __( 'Delete', 'craftforms-builder' ),
					'addSection'            => __( 'Add section', 'craftforms-builder' ),
					'addStep'               => __( 'Add step', 'craftforms-builder' ),
					'multistep'             => __( 'Multistep form', 'craftforms-builder' ),
					'conditional'           => __( 'Conditional logic', 'craftforms-builder' ),
					'noField'               => __( 'Select a field to edit its settings.', 'craftforms-builder' ),
					'dropHint'              => __( 'Drag a field here or click to add', 'craftforms-builder' ),
					'required'              => __( 'Required', 'craftforms-builder' ),
					'label'                 => __( 'Label', 'craftforms-builder' ),
					'name'                  => __( 'Field name', 'craftforms-builder' ),
					'placeholder'           => __( 'Placeholder', 'craftforms-builder' ),
					'help'                  => __( 'Help text', 'craftforms-builder' ),
					'options'               => __( 'Options', 'craftforms-builder' ),
					'addOption'             => __( 'Add option', 'craftforms-builder' ),
					'desktop'               => __( 'Desktop', 'craftforms-builder' ),
					'tablet'                => __( 'Tablet', 'craftforms-builder' ),
					'mobile'                => __( 'Mobile', 'craftforms-builder' ),
					'rulesEmpty'            => __( 'Add rules to show, hide, or require fields based on user input.', 'craftforms-builder' ),
					'addRule'               => __( 'Add rule', 'craftforms-builder' ),
					'tabBuild'              => __( 'Build', 'craftforms-builder' ),
					'tabBuildDesc'          => __( 'Add & arrange fields', 'craftforms-builder' ),
					'tabPreview'            => __( 'Preview', 'craftforms-builder' ),
					'tabPreviewDesc'        => __( 'See how visitors will see it', 'craftforms-builder' ),
					'tabLogic'              => __( 'Rules', 'craftforms-builder' ),
					'tabLogicDesc'          => __( 'Show, hide & conditions', 'craftforms-builder' ),
					'tabSettings'           => __( 'Settings', 'craftforms-builder' ),
					'tabSettingsDesc'       => __( 'Steps, GDPR, emails', 'craftforms-builder' ),
					'previewHint'           => __( 'This is how your form will look on the website.', 'craftforms-builder' ),
					'clickFieldHint'        => __( 'Click a field on the canvas to edit it here.', 'craftforms-builder' ),
					'stepsTitle'            => __( 'Form steps', 'craftforms-builder' ),
					'stepsHint'             => __( 'Name each step — titles appear on the live form progress bar.', 'craftforms-builder' ),
					'stepsLabel'            => __( 'steps', 'craftforms-builder' ),
					/* translators: %d: step number. */
					'stepPlaceholder'       => __( 'Step %d', 'craftforms-builder' ),
					/* translators: %d: step number. */
					'defaultStepTitle'      => __( 'Step %d', 'craftforms-builder' ),
					'removeStep'            => __( 'Remove step', 'craftforms-builder' ),
					'editStep'              => __( 'Select step', 'craftforms-builder' ),
					'settingsTitle'         => __( 'Form settings', 'craftforms-builder' ),
					'settingsIntro'         => __( 'Appearance, multistep behavior, thank-you page, privacy, security, and email.', 'craftforms-builder' ),
					'secAppearance'         => __( 'Appearance & layout', 'craftforms-builder' ),
					'secAppearanceDesc'     => __( 'Control how the form looks on your website.', 'craftforms-builder' ),
					'setTheme'              => __( 'Form style', 'craftforms-builder' ),
					'setThemeModern'        => __( 'Modern card', 'craftforms-builder' ),
					'setThemeMinimal'       => __( 'Minimal', 'craftforms-builder' ),
					'setThemeBordered'      => __( 'Bold border', 'craftforms-builder' ),
					'setPrimaryColor'       => __( 'Primary color', 'craftforms-builder' ),
					'setFormWidth'          => __( 'Form width', 'craftforms-builder' ),
					'setWidthNarrow'        => __( 'Narrow (480px)', 'craftforms-builder' ),
					'setWidthMedium'        => __( 'Medium (640px)', 'craftforms-builder' ),
					'setWidthWide'          => __( 'Wide (760px)', 'craftforms-builder' ),
					'setWidthFull'          => __( 'Full width', 'craftforms-builder' ),
					'setBorderRadius'       => __( 'Corner radius', 'craftforms-builder' ),
					'setRadiusNone'         => __( 'Square', 'craftforms-builder' ),
					'setRadiusSmall'        => __( 'Subtle', 'craftforms-builder' ),
					'setRadiusMedium'       => __( 'Rounded', 'craftforms-builder' ),
					'setRadiusLarge'        => __( 'Soft', 'craftforms-builder' ),
					'setFieldSpacing'       => __( 'Field spacing', 'craftforms-builder' ),
					'setSpacingComfortable' => __( 'Comfortable', 'craftforms-builder' ),
					'setSpacingCompact'     => __( 'Compact', 'craftforms-builder' ),
					'setProgressStyle'      => __( 'Step indicator', 'craftforms-builder' ),
					'setProgressPills'      => __( 'Steps with labels', 'craftforms-builder' ),
					'setProgressBar'        => __( 'Progress bar only', 'craftforms-builder' ),
					'setProgressMinimal'    => __( 'Minimal text', 'craftforms-builder' ),
					'setShowCounter'        => __( 'Show step counter', 'craftforms-builder' ),
					'setShowDots'           => __( 'Show step circles', 'craftforms-builder' ),
					'setShowDotsHint'       => __( 'Numbered circles with step titles.', 'craftforms-builder' ),
					'setShowHeading'        => __( 'Large step title above fields', 'craftforms-builder' ),
					'setShowHeadingHint'    => __( 'Off by default to avoid repeating the step name.', 'craftforms-builder' ),
					'logicTitle'            => __( 'Conditional logic', 'craftforms-builder' ),
					'logicIntro'            => __( 'Create rules to show, hide, or require fields based on answers.', 'craftforms-builder' ),
					'logicNoFields'         => __( 'Add fields on the Build tab before creating rules.', 'craftforms-builder' ),
					'addCondition'          => __( 'Add condition', 'craftforms-builder' ),
					'addAction'             => __( 'Add action', 'craftforms-builder' ),
					'addNested'             => __( 'Add nested group', 'craftforms-builder' ),
					'ifLabel'               => __( 'IF', 'craftforms-builder' ),
					'thenLabel'             => __( 'THEN', 'craftforms-builder' ),
					'matchAll'              => __( 'ALL conditions', 'craftforms-builder' ),
					'matchAny'              => __( 'ANY condition', 'craftforms-builder' ),
					'enabled'               => __( 'Active', 'craftforms-builder' ),
					'deleteRule'            => __( 'Delete rule', 'craftforms-builder' ),
					/* translators: %d: rule number. */
					'ruleLabel'             => __( 'Rule %d', 'craftforms-builder' ),
					'selectField'           => __( 'Select field', 'craftforms-builder' ),
					'selectStep'            => __( 'Select step', 'craftforms-builder' ),
					'secMultistep'          => __( 'Multistep & experience', 'craftforms-builder' ),
					'secMultistepDesc'      => __( 'How visitors move through and interact with your form.', 'craftforms-builder' ),
					'setAutosave'           => __( 'Autosave progress', 'craftforms-builder' ),
					'setSaveContinue'       => __( 'Save & continue later', 'craftforms-builder' ),
					'setAnimated'           => __( 'Animated step transitions', 'craftforms-builder' ),
					'setConversational'     => __( 'Conversational mode', 'craftforms-builder' ),
					'secThankyou'           => __( 'Thank you', 'craftforms-builder' ),
					'secGdpr'               => __( 'Privacy (GDPR)', 'craftforms-builder' ),
					'secA11y'               => __( 'Accessibility', 'craftforms-builder' ),
					'secSecurity'           => __( 'Security & spam', 'craftforms-builder' ),
					'secEmail'              => __( 'Email appearance', 'craftforms-builder' ),
				),
			)
		);
	}

	/**
	 * @return array
	 */
	public static function field_palette() {
		return array(
			array(
				'type'  => 'text',
				'label' => __( 'Text', 'craftforms-builder' ),
				'icon'  => 'dashicons-editor-textcolor',
			),
			array(
				'type'  => 'textarea',
				'label' => __( 'Textarea', 'craftforms-builder' ),
				'icon'  => 'dashicons-editor-alignleft',
			),
			array(
				'type'  => 'email',
				'label' => __( 'Email', 'craftforms-builder' ),
				'icon'  => 'dashicons-email',
			),
			array(
				'type'  => 'phone',
				'label' => __( 'Phone', 'craftforms-builder' ),
				'icon'  => 'dashicons-phone',
			),
			array(
				'type'  => 'number',
				'label' => __( 'Number', 'craftforms-builder' ),
				'icon'  => 'dashicons-calculator',
			),
			array(
				'type'  => 'url',
				'label' => __( 'Website URL', 'craftforms-builder' ),
				'icon'  => 'dashicons-admin-links',
			),
			array(
				'type'  => 'select',
				'label' => __( 'Dropdown', 'craftforms-builder' ),
				'icon'  => 'dashicons-arrow-down-alt2',
			),
			array(
				'type'  => 'multiselect',
				'label' => __( 'Multi-select', 'craftforms-builder' ),
				'icon'  => 'dashicons-list-view',
			),
			array(
				'type'  => 'country',
				'label' => __( 'Country', 'craftforms-builder' ),
				'icon'  => 'dashicons-admin-site-alt3',
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Checkbox', 'craftforms-builder' ),
				'icon'  => 'dashicons-yes',
			),
			array(
				'type'  => 'radio',
				'label' => __( 'Radio', 'craftforms-builder' ),
				'icon'  => 'dashicons-marker',
			),
			array(
				'type'  => 'file',
				'label' => __( 'File upload', 'craftforms-builder' ),
				'icon'  => 'dashicons-upload',
			),
			array(
				'type'  => 'date',
				'label' => __( 'Date', 'craftforms-builder' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			array(
				'type'  => 'hidden',
				'label' => __( 'Hidden', 'craftforms-builder' ),
				'icon'  => 'dashicons-hidden',
			),
			array(
				'type'  => 'rating',
				'label' => __( 'Rating', 'craftforms-builder' ),
				'icon'  => 'dashicons-star-filled',
			),
			array(
				'type'  => 'signature',
				'label' => __( 'Signature', 'craftforms-builder' ),
				'icon'  => 'dashicons-edit',
			),
			array(
				'type'  => 'repeater',
				'label' => __( 'Repeater', 'craftforms-builder' ),
				'icon'  => 'dashicons-plus-alt2',
				'desc'  => __( 'Let visitors add multiple rows of the same fields.', 'craftforms-builder' ),
			),
			array(
				'type'  => 'submit',
				'label' => __( 'Submit', 'craftforms-builder' ),
				'icon'  => 'dashicons-arrow-right-alt',
			),
		);
	}

	public static function add_entries_menu() {
		add_submenu_page(
			'wpcf7',
			__( 'Form Entries', 'craftforms-builder' ),
			__( 'Entries', 'craftforms-builder' ),
			'wpcf7_read_contact_forms',
			'cfb-entries',
			array( __CLASS__, 'render_entries_page' )
		);
	}

	/**
	 * @param string $hook_suffix Admin hook.
	 */
	public static function enqueue_dashboard_assets( $hook_suffix ) {
		if (
			false === strpos( $hook_suffix, 'cfb-entries' ) &&
			false === strpos( $hook_suffix, 'cfb-analytics' )
		) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'cfb-admin-dashboard',
			CFB_PLUGIN_URL . 'admin/css/admin-dashboard.css',
			array(),
			CFB_VERSION
		);

		wp_enqueue_script(
			'cfb-admin-dashboard',
			CFB_PLUGIN_URL . 'admin/js/admin-dashboard.js',
			array(),
			CFB_VERSION,
			true
		);

		wp_localize_script(
			'cfb-admin-dashboard',
			'CFB_DASH',
			array(
				'restUrl' => esc_url_raw( rest_url( CFB_REST_API::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					/* translators: %d: entry ID. */
					'entryTitle'   => __( 'Entry #%d', 'craftforms-builder' ),
					'loading'      => __( 'Loading entry…', 'craftforms-builder' ),
					'loadError'    => __( 'Could not load entry.', 'craftforms-builder' ),
					'noFields'     => __( 'No field data recorded.', 'craftforms-builder' ),
					'referrer'     => __( 'Referrer', 'craftforms-builder' ),
					'statusSaved'  => __( 'Status updated.', 'craftforms-builder' ),
					'statusError'  => __( 'Could not update status.', 'craftforms-builder' ),
					/* translators: %d: step number. */
					'stepView'     => __( 'Step %d viewed', 'craftforms-builder' ),
					/* translators: %s: field name. */
					'fieldFocus'   => __( 'Field focus: %s', 'craftforms-builder' ),
					'eventView'    => __( 'Form viewed', 'craftforms-builder' ),
					'eventStart'   => __( 'Form started', 'craftforms-builder' ),
					'eventSubmit'  => __( 'Form submitted', 'craftforms-builder' ),
					'eventAbandon' => __( 'Form abandoned', 'craftforms-builder' ),
				),
			)
		);
	}

	/**
	 * @param string $data_json Entry data JSON.
	 * @return string
	 */
	private static function entry_preview_text( $data_json ) {
		$data = json_decode( $data_json, true );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return '—';
		}
		foreach ( $data as $key => $val ) {
			if ( 0 === strpos( (string) $key, '_' ) ) {
				continue;
			}
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$text = wp_strip_all_tags( (string) $val );
			if ( '' !== $text ) {
				return ucwords( str_replace( array( '-', '_' ), ' ', (string) $key ) ) . ': ' . $text;
			}
		}
		return '—';
	}

	public static function render_entries_page() {
		if ( ! current_user_can( 'wpcf7_read_contact_forms' ) ) {
			wp_die( esc_html__( 'You are not allowed to view entries.', 'craftforms-builder' ) );
		}

		// Filters arrive via the GET filter form below, which carries a nonce. Without a valid
		// nonce we fall back to the default unfiltered view.
		$nonce    = isset( $_GET['cfb_entries_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['cfb_entries_nonce'] ) ) : '';
		$verified = (bool) wp_verify_nonce( $nonce, 'cfb_entries_filter' );

		$form_id = ( $verified && isset( $_GET['form_id'] ) ) ? absint( $_GET['form_id'] ) : 0;
		$search  = ( $verified && isset( $_GET['s'] ) ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status  = ( $verified && isset( $_GET['status'] ) ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page    = ( $verified && isset( $_GET['paged'] ) ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$entries = CFB_Submissions::query(
			array(
				'form_id'  => $form_id,
				'search'   => $search,
				'status'   => $status,
				'per_page' => 30,
				'page'     => $page,
			)
		);
		$counts  = CFB_Submissions::get_status_counts( array( 'form_id' => $form_id ) );
		$forms   = WPCF7_ContactForm::find();

		$export_args          = array(
			'action'   => 'cfb_export_csv',
			'_wpnonce' => wp_create_nonce( 'cfb_export' ),
			'form_id'  => $form_id,
			's'        => $search,
			'status'   => $status,
		);
		$export_pdf           = $export_args;
		$export_pdf['action'] = 'cfb_export_pdf';
		?>
		<div class="wrap cfb-dash cfb-entries-wrap">
			<header class="cfb-dash-header">
				<div>
					<h1><?php esc_html_e( 'Form Entries', 'craftforms-builder' ); ?></h1>
					<p><?php esc_html_e( 'Review submissions, update status, and export data.', 'craftforms-builder' ); ?></p>
				</div>
				<div class="cfb-dash-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_args ) ) ); ?>"><?php esc_html_e( 'Export CSV', 'craftforms-builder' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_pdf ) ) ); ?>"><?php esc_html_e( 'Export PDF', 'craftforms-builder' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-analytics' ) ); ?>"><?php esc_html_e( 'Analytics', 'craftforms-builder' ); ?></a>
				</div>
			</header>

			<div class="cfb-kpi-grid">
				<div class="cfb-kpi-card is-primary">
					<strong><?php esc_html_e( 'Total entries', 'craftforms-builder' ); ?></strong>
					<p class="cfb-kpi-value"><?php echo esc_html( (string) $entries['total'] ); ?></p>
				</div>
				<div class="cfb-kpi-card">
					<strong><?php esc_html_e( 'Unread', 'craftforms-builder' ); ?></strong>
					<p class="cfb-kpi-value"><?php echo esc_html( (string) $counts['unread'] ); ?></p>
				</div>
				<div class="cfb-kpi-card">
					<strong><?php esc_html_e( 'Starred', 'craftforms-builder' ); ?></strong>
					<p class="cfb-kpi-value"><?php echo esc_html( (string) $counts['starred'] ); ?></p>
				</div>
				<div class="cfb-kpi-card">
					<strong><?php esc_html_e( 'Spam', 'craftforms-builder' ); ?></strong>
					<p class="cfb-kpi-value"><?php echo esc_html( (string) $counts['spam'] ); ?></p>
				</div>
			</div>

			<div class="cfb-panel">
				<div class="cfb-panel-head">
					<h2><?php esc_html_e( 'Filter entries', 'craftforms-builder' ); ?></h2>
				</div>
				<form method="get" class="cfb-filter-bar">
					<input type="hidden" name="page" value="cfb-entries" />
					<?php wp_nonce_field( 'cfb_entries_filter', 'cfb_entries_nonce', false ); ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search field values…', 'craftforms-builder' ); ?>" />
					<select name="form_id">
						<option value="0"><?php esc_html_e( 'All forms', 'craftforms-builder' ); ?></option>
						<?php foreach ( $forms as $form ) : ?>
							<option value="<?php echo esc_attr( (string) $form->id() ); ?>" <?php selected( $form_id, $form->id() ); ?>><?php echo esc_html( $form->title() ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'craftforms-builder' ); ?></option>
						<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Apply filters', 'craftforms-builder' ), 'secondary', '', false ); ?>
				</form>
			</div>

			<div class="cfb-panel">
				<div class="cfb-panel-head">
					<h2><?php esc_html_e( 'Submissions', 'craftforms-builder' ); ?></h2>
				</div>
				<div class="cfb-table-wrap">
					<?php if ( empty( $entries['items'] ) ) : ?>
						<div class="cfb-empty">
							<span class="dashicons dashicons-email-alt"></span>
							<p><?php esc_html_e( 'No entries match your filters.', 'craftforms-builder' ); ?></p>
						</div>
					<?php else : ?>
						<table class="cfb-entries-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Entry', 'craftforms-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Form', 'craftforms-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Preview', 'craftforms-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'craftforms-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Device', 'craftforms-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Submitted', 'craftforms-builder' ); ?></th>
									<th scope="col"></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $entries['items'] as $row ) :
									$form_title = '';
									$contact    = wpcf7_contact_form( (int) $row->form_id );
									if ( $contact ) {
										$form_title = $contact->title();
									}
									$row_status = sanitize_key( $row->status );
									$device     = $row->device ? $row->device : 'desktop';
									$icon       = 'desktop';
									if ( 'mobile' === $device ) {
										$icon = 'phone';
									} elseif ( 'tablet' === $device ) {
										$icon = 'tablet';
									}
									?>
									<tr data-entry-id="<?php echo esc_attr( (string) $row->id ); ?>" class="<?php echo 'unread' === $row_status ? 'is-unread' : ''; ?>">
										<td><strong>#<?php echo esc_html( (string) $row->id ); ?></strong></td>
										<td><?php echo esc_html( $form_title ); ?></td>
										<td><span class="cfb-entry-preview"><?php echo esc_html( self::entry_preview_text( $row->data ) ); ?></span></td>
										<td><span class="cfb-badge cfb-badge--<?php echo esc_attr( $row_status ); ?>"><?php echo esc_html( ucfirst( $row_status ) ); ?></span></td>
										<td>
											<span class="cfb-device-pill">
												<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
												<?php echo esc_html( ucfirst( $device ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) ); ?></td>
										<td>
											<button type="button" class="cfb-btn-view" data-entry-id="<?php echo esc_attr( (string) $row->id ); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e( 'View', 'craftforms-builder' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<div class="cfb-table-foot">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: total entries */
							__( 'Showing page %1$d — %2$d total entries', 'craftforms-builder' ),
							$page,
							$entries['total']
						)
					);
					?>
				</div>
			</div>
		</div>

		<div id="cfb-entry-modal" class="cfb-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cfb-modal-heading">
			<div class="cfb-modal" role="document">
				<header class="cfb-modal-header">
					<div>
						<h2 id="cfb-modal-heading" class="cfb-modal-title"><?php esc_html_e( 'Entry details', 'craftforms-builder' ); ?></h2>
						<div class="cfb-modal-meta"></div>
					</div>
					<button type="button" class="cfb-modal-close" aria-label="<?php esc_attr_e( 'Close', 'craftforms-builder' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</header>
				<div class="cfb-modal-body"></div>
				<footer class="cfb-modal-footer">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Status', 'craftforms-builder' ); ?></span>
						<select class="cfb-status-select">
							<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( ucfirst( $st ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="button" class="button button-primary cfb-save-status"><?php esc_html_e( 'Update status', 'craftforms-builder' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}
}
