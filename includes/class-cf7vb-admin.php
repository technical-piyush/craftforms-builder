<?php
/**
 * CF7 editor integration + entries admin.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Admin {

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
			'cf7vb-visual-panel' => array(
				'title'    => __( 'Visual Builder', 'form-studio-for-contact-form-7' ),
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
		$schema  = $post->initial()
			? CF7VB_Parser::default_schema()
			: CF7VB_Plugin::get_schema( $form_id );
		?>
		<h2 class="cf7vb-panel-title"><?php esc_html_e( 'Visual Form Builder', 'form-studio-for-contact-form-7' ); ?></h2>
		<p class="description cf7vb-panel-desc">
			<?php esc_html_e( 'Use the tabs below: Build your form, check Preview, add Rules, then adjust Settings. Click Save on the contact form to publish.', 'form-studio-for-contact-form-7' ); ?>
		</p>
		<?php
		// Must live inside #wpcf7-admin-form-element (footer hook runs after </form>).
		printf(
			'<textarea id="cf7vb-schema-input" name="cf7vb_schema" class="cf7vb-schema-store" hidden aria-hidden="true">%s</textarea>',
			esc_textarea( wp_json_encode( $schema ) )
		);
		?>
		<div id="cf7vb-builder-root" class="cf7vb-builder-root" data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"></div>
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
		$schema = CF7VB_Parser::default_schema();
		printf(
			'<script type="application/json" id="cf7vb-default-schema">%s</script>',
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
			$_GET['active-tab'] = 'cf7vb-visual-panel';
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
			'cf7vb-builder',
			CF7VB_PLUGIN_URL . 'admin/css/builder.css',
			array( 'contact-form-7-admin' ),
			CF7VB_VERSION
		);

		wp_enqueue_style(
			'cf7vb-frontend',
			CF7VB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			CF7VB_VERSION
		);

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-api-fetch' );

		wp_enqueue_script(
			'cf7vb-builder-preview',
			CF7VB_PLUGIN_URL . 'admin/js/builder-preview.js',
			array( 'wp-element' ),
			CF7VB_VERSION,
			true
		);

		wp_enqueue_script(
			'cf7vb-builder-logic',
			CF7VB_PLUGIN_URL . 'admin/js/builder-logic.js',
			array( 'wp-element' ),
			CF7VB_VERSION,
			true
		);

		wp_enqueue_script(
			'cf7vb-builder-settings',
			CF7VB_PLUGIN_URL . 'admin/js/builder-settings.js',
			array( 'wp-element' ),
			CF7VB_VERSION,
			true
		);

		wp_enqueue_script(
			'cf7vb-builder',
			CF7VB_PLUGIN_URL . 'admin/js/builder.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wpcf7-admin', 'cf7vb-builder-preview', 'cf7vb-builder-logic', 'cf7vb-builder-settings' ),
			CF7VB_VERSION,
			true
		);

		$form_id = (int) filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		wp_localize_script(
			'cf7vb-builder',
			'CF7VB_BUILDER',
			array(
				'root'       => esc_url_raw( rest_url( CF7VB_REST_API::NS ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'formId'     => $form_id,
				'fieldTypes' => self::field_palette(),
				'strings'    => array(
					'palette'               => __( 'Fields', 'form-studio-for-contact-form-7' ),
					'canvas'                => __( 'Form canvas', 'form-studio-for-contact-form-7' ),
					'inspector'             => __( 'Field settings', 'form-studio-for-contact-form-7' ),
					'preview'               => __( 'Live preview', 'form-studio-for-contact-form-7' ),
					'undo'                  => __( 'Undo', 'form-studio-for-contact-form-7' ),
					'redo'                  => __( 'Redo', 'form-studio-for-contact-form-7' ),
					'duplicate'             => __( 'Duplicate', 'form-studio-for-contact-form-7' ),
					'delete'                => __( 'Delete', 'form-studio-for-contact-form-7' ),
					'addSection'            => __( 'Add section', 'form-studio-for-contact-form-7' ),
					'addStep'               => __( 'Add step', 'form-studio-for-contact-form-7' ),
					'multistep'             => __( 'Multistep form', 'form-studio-for-contact-form-7' ),
					'conditional'           => __( 'Conditional logic', 'form-studio-for-contact-form-7' ),
					'noField'               => __( 'Select a field to edit its settings.', 'form-studio-for-contact-form-7' ),
					'dropHint'              => __( 'Drag a field here or click to add', 'form-studio-for-contact-form-7' ),
					'required'              => __( 'Required', 'form-studio-for-contact-form-7' ),
					'label'                 => __( 'Label', 'form-studio-for-contact-form-7' ),
					'name'                  => __( 'Field name', 'form-studio-for-contact-form-7' ),
					'placeholder'           => __( 'Placeholder', 'form-studio-for-contact-form-7' ),
					'help'                  => __( 'Help text', 'form-studio-for-contact-form-7' ),
					'options'               => __( 'Options', 'form-studio-for-contact-form-7' ),
					'addOption'             => __( 'Add option', 'form-studio-for-contact-form-7' ),
					'desktop'               => __( 'Desktop', 'form-studio-for-contact-form-7' ),
					'tablet'                => __( 'Tablet', 'form-studio-for-contact-form-7' ),
					'mobile'                => __( 'Mobile', 'form-studio-for-contact-form-7' ),
					'rulesEmpty'            => __( 'Add rules to show, hide, or require fields based on user input.', 'form-studio-for-contact-form-7' ),
					'addRule'               => __( 'Add rule', 'form-studio-for-contact-form-7' ),
					'tabBuild'              => __( 'Build', 'form-studio-for-contact-form-7' ),
					'tabBuildDesc'          => __( 'Add & arrange fields', 'form-studio-for-contact-form-7' ),
					'tabPreview'            => __( 'Preview', 'form-studio-for-contact-form-7' ),
					'tabPreviewDesc'        => __( 'See how visitors will see it', 'form-studio-for-contact-form-7' ),
					'tabLogic'              => __( 'Rules', 'form-studio-for-contact-form-7' ),
					'tabLogicDesc'          => __( 'Show, hide & conditions', 'form-studio-for-contact-form-7' ),
					'tabSettings'           => __( 'Settings', 'form-studio-for-contact-form-7' ),
					'tabSettingsDesc'       => __( 'Steps, GDPR, emails', 'form-studio-for-contact-form-7' ),
					'previewHint'           => __( 'This is how your form will look on the website.', 'form-studio-for-contact-form-7' ),
					'clickFieldHint'        => __( 'Click a field on the canvas to edit it here.', 'form-studio-for-contact-form-7' ),
					'stepsTitle'            => __( 'Form steps', 'form-studio-for-contact-form-7' ),
					'stepsHint'             => __( 'Name each step — titles appear on the live form progress bar.', 'form-studio-for-contact-form-7' ),
					'stepsLabel'            => __( 'steps', 'form-studio-for-contact-form-7' ),
					/* translators: %d: step number. */
					'stepPlaceholder'       => __( 'Step %d', 'form-studio-for-contact-form-7' ),
					/* translators: %d: step number. */
					'defaultStepTitle'      => __( 'Step %d', 'form-studio-for-contact-form-7' ),
					'removeStep'            => __( 'Remove step', 'form-studio-for-contact-form-7' ),
					'editStep'              => __( 'Select step', 'form-studio-for-contact-form-7' ),
					'settingsTitle'         => __( 'Form settings', 'form-studio-for-contact-form-7' ),
					'settingsIntro'         => __( 'Appearance, multistep behavior, thank-you page, privacy, security, and email.', 'form-studio-for-contact-form-7' ),
					'secAppearance'         => __( 'Appearance & layout', 'form-studio-for-contact-form-7' ),
					'secAppearanceDesc'     => __( 'Control how the form looks on your website.', 'form-studio-for-contact-form-7' ),
					'setTheme'              => __( 'Form style', 'form-studio-for-contact-form-7' ),
					'setThemeModern'        => __( 'Modern card', 'form-studio-for-contact-form-7' ),
					'setThemeMinimal'       => __( 'Minimal', 'form-studio-for-contact-form-7' ),
					'setThemeBordered'      => __( 'Bold border', 'form-studio-for-contact-form-7' ),
					'setPrimaryColor'       => __( 'Primary color', 'form-studio-for-contact-form-7' ),
					'setFormWidth'          => __( 'Form width', 'form-studio-for-contact-form-7' ),
					'setWidthNarrow'        => __( 'Narrow (480px)', 'form-studio-for-contact-form-7' ),
					'setWidthMedium'        => __( 'Medium (640px)', 'form-studio-for-contact-form-7' ),
					'setWidthWide'          => __( 'Wide (760px)', 'form-studio-for-contact-form-7' ),
					'setWidthFull'          => __( 'Full width', 'form-studio-for-contact-form-7' ),
					'setBorderRadius'       => __( 'Corner radius', 'form-studio-for-contact-form-7' ),
					'setRadiusNone'         => __( 'Square', 'form-studio-for-contact-form-7' ),
					'setRadiusSmall'        => __( 'Subtle', 'form-studio-for-contact-form-7' ),
					'setRadiusMedium'       => __( 'Rounded', 'form-studio-for-contact-form-7' ),
					'setRadiusLarge'        => __( 'Soft', 'form-studio-for-contact-form-7' ),
					'setFieldSpacing'       => __( 'Field spacing', 'form-studio-for-contact-form-7' ),
					'setSpacingComfortable' => __( 'Comfortable', 'form-studio-for-contact-form-7' ),
					'setSpacingCompact'     => __( 'Compact', 'form-studio-for-contact-form-7' ),
					'setProgressStyle'      => __( 'Step indicator', 'form-studio-for-contact-form-7' ),
					'setProgressPills'      => __( 'Steps with labels', 'form-studio-for-contact-form-7' ),
					'setProgressBar'        => __( 'Progress bar only', 'form-studio-for-contact-form-7' ),
					'setProgressMinimal'    => __( 'Minimal text', 'form-studio-for-contact-form-7' ),
					'setShowCounter'        => __( 'Show step counter', 'form-studio-for-contact-form-7' ),
					'setShowDots'           => __( 'Show step circles', 'form-studio-for-contact-form-7' ),
					'setShowDotsHint'       => __( 'Numbered circles with step titles.', 'form-studio-for-contact-form-7' ),
					'setShowHeading'        => __( 'Large step title above fields', 'form-studio-for-contact-form-7' ),
					'setShowHeadingHint'    => __( 'Off by default to avoid repeating the step name.', 'form-studio-for-contact-form-7' ),
					'logicTitle'            => __( 'Conditional logic', 'form-studio-for-contact-form-7' ),
					'logicIntro'            => __( 'Create rules to show, hide, or require fields based on answers.', 'form-studio-for-contact-form-7' ),
					'logicNoFields'         => __( 'Add fields on the Build tab before creating rules.', 'form-studio-for-contact-form-7' ),
					'addCondition'          => __( 'Add condition', 'form-studio-for-contact-form-7' ),
					'addAction'             => __( 'Add action', 'form-studio-for-contact-form-7' ),
					'addNested'             => __( 'Add nested group', 'form-studio-for-contact-form-7' ),
					'ifLabel'               => __( 'IF', 'form-studio-for-contact-form-7' ),
					'thenLabel'             => __( 'THEN', 'form-studio-for-contact-form-7' ),
					'matchAll'              => __( 'ALL conditions', 'form-studio-for-contact-form-7' ),
					'matchAny'              => __( 'ANY condition', 'form-studio-for-contact-form-7' ),
					'enabled'               => __( 'Active', 'form-studio-for-contact-form-7' ),
					'deleteRule'            => __( 'Delete rule', 'form-studio-for-contact-form-7' ),
					/* translators: %d: rule number. */
					'ruleLabel'             => __( 'Rule %d', 'form-studio-for-contact-form-7' ),
					'selectField'           => __( 'Select field', 'form-studio-for-contact-form-7' ),
					'selectStep'            => __( 'Select step', 'form-studio-for-contact-form-7' ),
					'secMultistep'          => __( 'Multistep & experience', 'form-studio-for-contact-form-7' ),
					'secMultistepDesc'      => __( 'How visitors move through and interact with your form.', 'form-studio-for-contact-form-7' ),
					'setAutosave'           => __( 'Autosave progress', 'form-studio-for-contact-form-7' ),
					'setSaveContinue'       => __( 'Save & continue later', 'form-studio-for-contact-form-7' ),
					'setAnimated'           => __( 'Animated step transitions', 'form-studio-for-contact-form-7' ),
					'setConversational'     => __( 'Conversational mode', 'form-studio-for-contact-form-7' ),
					'secThankyou'           => __( 'Thank you', 'form-studio-for-contact-form-7' ),
					'secGdpr'               => __( 'Privacy (GDPR)', 'form-studio-for-contact-form-7' ),
					'secA11y'               => __( 'Accessibility', 'form-studio-for-contact-form-7' ),
					'secSecurity'           => __( 'Security & spam', 'form-studio-for-contact-form-7' ),
					'secEmail'              => __( 'Email appearance', 'form-studio-for-contact-form-7' ),
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
				'label' => __( 'Text', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-editor-textcolor',
			),
			array(
				'type'  => 'textarea',
				'label' => __( 'Textarea', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-editor-alignleft',
			),
			array(
				'type'  => 'email',
				'label' => __( 'Email', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-email',
			),
			array(
				'type'  => 'phone',
				'label' => __( 'Phone', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-phone',
			),
			array(
				'type'  => 'number',
				'label' => __( 'Number', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-calculator',
			),
			array(
				'type'  => 'select',
				'label' => __( 'Select', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-arrow-down-alt2',
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Checkbox', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-yes',
			),
			array(
				'type'  => 'radio',
				'label' => __( 'Radio', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-marker',
			),
			array(
				'type'  => 'file',
				'label' => __( 'File upload', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-upload',
			),
			array(
				'type'  => 'date',
				'label' => __( 'Date', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			array(
				'type'  => 'hidden',
				'label' => __( 'Hidden', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-hidden',
			),
			array(
				'type'  => 'rating',
				'label' => __( 'Rating', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-star-filled',
			),
			array(
				'type'  => 'signature',
				'label' => __( 'Signature', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-edit',
			),
			array(
				'type'  => 'repeater',
				'label' => __( 'Repeater', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-plus-alt2',
			),
			array(
				'type'  => 'submit',
				'label' => __( 'Submit', 'form-studio-for-contact-form-7' ),
				'icon'  => 'dashicons-arrow-right-alt',
			),
		);
	}

	public static function add_entries_menu() {
		add_submenu_page(
			'wpcf7',
			__( 'Form Entries', 'form-studio-for-contact-form-7' ),
			__( 'Entries', 'form-studio-for-contact-form-7' ),
			'wpcf7_read_contact_forms',
			'cf7vb-entries',
			array( __CLASS__, 'render_entries_page' )
		);
	}

	/**
	 * @param string $hook_suffix Admin hook.
	 */
	public static function enqueue_dashboard_assets( $hook_suffix ) {
		if (
			false === strpos( $hook_suffix, 'cf7vb-entries' ) &&
			false === strpos( $hook_suffix, 'cf7vb-analytics' )
		) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'cf7vb-admin-dashboard',
			CF7VB_PLUGIN_URL . 'admin/css/admin-dashboard.css',
			array(),
			CF7VB_VERSION
		);

		wp_enqueue_script(
			'cf7vb-admin-dashboard',
			CF7VB_PLUGIN_URL . 'admin/js/admin-dashboard.js',
			array(),
			CF7VB_VERSION,
			true
		);

		wp_localize_script(
			'cf7vb-admin-dashboard',
			'CF7VB_DASH',
			array(
				'restUrl' => esc_url_raw( rest_url( CF7VB_REST_API::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					/* translators: %d: entry ID. */
					'entryTitle'   => __( 'Entry #%d', 'form-studio-for-contact-form-7' ),
					'loading'      => __( 'Loading entry…', 'form-studio-for-contact-form-7' ),
					'loadError'    => __( 'Could not load entry.', 'form-studio-for-contact-form-7' ),
					'noFields'     => __( 'No field data recorded.', 'form-studio-for-contact-form-7' ),
					'referrer'     => __( 'Referrer', 'form-studio-for-contact-form-7' ),
					'statusSaved'  => __( 'Status updated.', 'form-studio-for-contact-form-7' ),
					'statusError'  => __( 'Could not update status.', 'form-studio-for-contact-form-7' ),
					/* translators: %d: step number. */
					'stepView'     => __( 'Step %d viewed', 'form-studio-for-contact-form-7' ),
					/* translators: %s: field name. */
					'fieldFocus'   => __( 'Field focus: %s', 'form-studio-for-contact-form-7' ),
					'eventView'    => __( 'Form viewed', 'form-studio-for-contact-form-7' ),
					'eventStart'   => __( 'Form started', 'form-studio-for-contact-form-7' ),
					'eventSubmit'  => __( 'Form submitted', 'form-studio-for-contact-form-7' ),
					'eventAbandon' => __( 'Form abandoned', 'form-studio-for-contact-form-7' ),
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
			wp_die( esc_html__( 'You are not allowed to view entries.', 'form-studio-for-contact-form-7' ) );
		}

		// Filters arrive via the GET filter form below, which carries a nonce. Without a valid
		// nonce we fall back to the default unfiltered view.
		$nonce    = isset( $_GET['cf7vb_entries_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['cf7vb_entries_nonce'] ) ) : '';
		$verified = (bool) wp_verify_nonce( $nonce, 'cf7vb_entries_filter' );

		$form_id = ( $verified && isset( $_GET['form_id'] ) ) ? absint( $_GET['form_id'] ) : 0;
		$search  = ( $verified && isset( $_GET['s'] ) ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status  = ( $verified && isset( $_GET['status'] ) ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page    = ( $verified && isset( $_GET['paged'] ) ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$entries = CF7VB_Submissions::query(
			array(
				'form_id'  => $form_id,
				'search'   => $search,
				'status'   => $status,
				'per_page' => 30,
				'page'     => $page,
			)
		);
		$counts  = CF7VB_Submissions::get_status_counts( array( 'form_id' => $form_id ) );
		$forms   = WPCF7_ContactForm::find();

		$export_args          = array(
			'action'   => 'cf7vb_export_csv',
			'_wpnonce' => wp_create_nonce( 'cf7vb_export' ),
			'form_id'  => $form_id,
			's'        => $search,
			'status'   => $status,
		);
		$export_pdf           = $export_args;
		$export_pdf['action'] = 'cf7vb_export_pdf';
		?>
		<div class="wrap cf7vb-dash cf7vb-entries-wrap">
			<header class="cf7vb-dash-header">
				<div>
					<h1><?php esc_html_e( 'Form Entries', 'form-studio-for-contact-form-7' ); ?></h1>
					<p><?php esc_html_e( 'Review submissions, update status, and export data.', 'form-studio-for-contact-form-7' ); ?></p>
				</div>
				<div class="cf7vb-dash-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_args ) ) ); ?>"><?php esc_html_e( 'Export CSV', 'form-studio-for-contact-form-7' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_pdf ) ) ); ?>"><?php esc_html_e( 'Export PDF', 'form-studio-for-contact-form-7' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cf7vb-analytics' ) ); ?>"><?php esc_html_e( 'Analytics', 'form-studio-for-contact-form-7' ); ?></a>
				</div>
			</header>

			<div class="cf7vb-kpi-grid">
				<div class="cf7vb-kpi-card is-primary">
					<strong><?php esc_html_e( 'Total entries', 'form-studio-for-contact-form-7' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $entries['total'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Unread', 'form-studio-for-contact-form-7' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['unread'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Starred', 'form-studio-for-contact-form-7' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['starred'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Spam', 'form-studio-for-contact-form-7' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['spam'] ); ?></p>
				</div>
			</div>

			<div class="cf7vb-panel">
				<div class="cf7vb-panel-head">
					<h2><?php esc_html_e( 'Filter entries', 'form-studio-for-contact-form-7' ); ?></h2>
				</div>
				<form method="get" class="cf7vb-filter-bar">
					<input type="hidden" name="page" value="cf7vb-entries" />
					<?php wp_nonce_field( 'cf7vb_entries_filter', 'cf7vb_entries_nonce', false ); ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search field values…', 'form-studio-for-contact-form-7' ); ?>" />
					<select name="form_id">
						<option value="0"><?php esc_html_e( 'All forms', 'form-studio-for-contact-form-7' ); ?></option>
						<?php foreach ( $forms as $form ) : ?>
							<option value="<?php echo esc_attr( (string) $form->id() ); ?>" <?php selected( $form_id, $form->id() ); ?>><?php echo esc_html( $form->title() ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'form-studio-for-contact-form-7' ); ?></option>
						<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Apply filters', 'form-studio-for-contact-form-7' ), 'secondary', '', false ); ?>
				</form>
			</div>

			<div class="cf7vb-panel">
				<div class="cf7vb-panel-head">
					<h2><?php esc_html_e( 'Submissions', 'form-studio-for-contact-form-7' ); ?></h2>
				</div>
				<div class="cf7vb-table-wrap">
					<?php if ( empty( $entries['items'] ) ) : ?>
						<div class="cf7vb-empty">
							<span class="dashicons dashicons-email-alt"></span>
							<p><?php esc_html_e( 'No entries match your filters.', 'form-studio-for-contact-form-7' ); ?></p>
						</div>
					<?php else : ?>
						<table class="cf7vb-entries-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Entry', 'form-studio-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Form', 'form-studio-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Preview', 'form-studio-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'form-studio-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Device', 'form-studio-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Submitted', 'form-studio-for-contact-form-7' ); ?></th>
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
										<td><span class="cf7vb-entry-preview"><?php echo esc_html( self::entry_preview_text( $row->data ) ); ?></span></td>
										<td><span class="cf7vb-badge cf7vb-badge--<?php echo esc_attr( $row_status ); ?>"><?php echo esc_html( ucfirst( $row_status ) ); ?></span></td>
										<td>
											<span class="cf7vb-device-pill">
												<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
												<?php echo esc_html( ucfirst( $device ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) ); ?></td>
										<td>
											<button type="button" class="cf7vb-btn-view" data-entry-id="<?php echo esc_attr( (string) $row->id ); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e( 'View', 'form-studio-for-contact-form-7' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<div class="cf7vb-table-foot">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: total entries */
							__( 'Showing page %1$d — %2$d total entries', 'form-studio-for-contact-form-7' ),
							$page,
							$entries['total']
						)
					);
					?>
				</div>
			</div>
		</div>

		<div id="cf7vb-entry-modal" class="cf7vb-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cf7vb-modal-heading">
			<div class="cf7vb-modal" role="document">
				<header class="cf7vb-modal-header">
					<div>
						<h2 id="cf7vb-modal-heading" class="cf7vb-modal-title"><?php esc_html_e( 'Entry details', 'form-studio-for-contact-form-7' ); ?></h2>
						<div class="cf7vb-modal-meta"></div>
					</div>
					<button type="button" class="cf7vb-modal-close" aria-label="<?php esc_attr_e( 'Close', 'form-studio-for-contact-form-7' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</header>
				<div class="cf7vb-modal-body"></div>
				<footer class="cf7vb-modal-footer">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Status', 'form-studio-for-contact-form-7' ); ?></span>
						<select class="cf7vb-status-select">
							<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( ucfirst( $st ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="button" class="button button-primary cf7vb-save-status"><?php esc_html_e( 'Update status', 'form-studio-for-contact-form-7' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}
}
