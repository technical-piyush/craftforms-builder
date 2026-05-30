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
				'title'    => __( 'Visual Builder', 'cf7-visual-form-builder' ),
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
		<h2 class="cf7vb-panel-title"><?php esc_html_e( 'Visual Form Builder', 'cf7-visual-form-builder' ); ?></h2>
		<p class="description cf7vb-panel-desc">
			<?php esc_html_e( 'Use the tabs below: Build your form, check Preview, add Rules, then adjust Settings. Click Save on the contact form to publish.', 'cf7-visual-form-builder' ); ?>
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
					'palette'               => __( 'Fields', 'cf7-visual-form-builder' ),
					'canvas'                => __( 'Form canvas', 'cf7-visual-form-builder' ),
					'inspector'             => __( 'Field settings', 'cf7-visual-form-builder' ),
					'preview'               => __( 'Live preview', 'cf7-visual-form-builder' ),
					'undo'                  => __( 'Undo', 'cf7-visual-form-builder' ),
					'redo'                  => __( 'Redo', 'cf7-visual-form-builder' ),
					'duplicate'             => __( 'Duplicate', 'cf7-visual-form-builder' ),
					'delete'                => __( 'Delete', 'cf7-visual-form-builder' ),
					'addSection'            => __( 'Add section', 'cf7-visual-form-builder' ),
					'addStep'               => __( 'Add step', 'cf7-visual-form-builder' ),
					'multistep'             => __( 'Multistep form', 'cf7-visual-form-builder' ),
					'conditional'           => __( 'Conditional logic', 'cf7-visual-form-builder' ),
					'noField'               => __( 'Select a field to edit its settings.', 'cf7-visual-form-builder' ),
					'dropHint'              => __( 'Drag a field here or click to add', 'cf7-visual-form-builder' ),
					'required'              => __( 'Required', 'cf7-visual-form-builder' ),
					'label'                 => __( 'Label', 'cf7-visual-form-builder' ),
					'name'                  => __( 'Field name', 'cf7-visual-form-builder' ),
					'placeholder'           => __( 'Placeholder', 'cf7-visual-form-builder' ),
					'help'                  => __( 'Help text', 'cf7-visual-form-builder' ),
					'options'               => __( 'Options', 'cf7-visual-form-builder' ),
					'addOption'             => __( 'Add option', 'cf7-visual-form-builder' ),
					'desktop'               => __( 'Desktop', 'cf7-visual-form-builder' ),
					'tablet'                => __( 'Tablet', 'cf7-visual-form-builder' ),
					'mobile'                => __( 'Mobile', 'cf7-visual-form-builder' ),
					'rulesEmpty'            => __( 'Add rules to show, hide, or require fields based on user input.', 'cf7-visual-form-builder' ),
					'addRule'               => __( 'Add rule', 'cf7-visual-form-builder' ),
					'tabBuild'              => __( 'Build', 'cf7-visual-form-builder' ),
					'tabBuildDesc'          => __( 'Add & arrange fields', 'cf7-visual-form-builder' ),
					'tabPreview'            => __( 'Preview', 'cf7-visual-form-builder' ),
					'tabPreviewDesc'        => __( 'See how visitors will see it', 'cf7-visual-form-builder' ),
					'tabLogic'              => __( 'Rules', 'cf7-visual-form-builder' ),
					'tabLogicDesc'          => __( 'Show, hide & conditions', 'cf7-visual-form-builder' ),
					'tabSettings'           => __( 'Settings', 'cf7-visual-form-builder' ),
					'tabSettingsDesc'       => __( 'Steps, GDPR, emails', 'cf7-visual-form-builder' ),
					'previewHint'           => __( 'This is how your form will look on the website.', 'cf7-visual-form-builder' ),
					'clickFieldHint'        => __( 'Click a field on the canvas to edit it here.', 'cf7-visual-form-builder' ),
					'stepsTitle'            => __( 'Form steps', 'cf7-visual-form-builder' ),
					'stepsHint'             => __( 'Name each step — titles appear on the live form progress bar.', 'cf7-visual-form-builder' ),
					'stepsLabel'            => __( 'steps', 'cf7-visual-form-builder' ),
					/* translators: %d: step number. */
					'stepPlaceholder'       => __( 'Step %d', 'cf7-visual-form-builder' ),
					/* translators: %d: step number. */
					'defaultStepTitle'      => __( 'Step %d', 'cf7-visual-form-builder' ),
					'removeStep'            => __( 'Remove step', 'cf7-visual-form-builder' ),
					'editStep'              => __( 'Select step', 'cf7-visual-form-builder' ),
					'settingsTitle'         => __( 'Form settings', 'cf7-visual-form-builder' ),
					'settingsIntro'         => __( 'Appearance, multistep behavior, thank-you page, privacy, security, and email.', 'cf7-visual-form-builder' ),
					'secAppearance'         => __( 'Appearance & layout', 'cf7-visual-form-builder' ),
					'secAppearanceDesc'     => __( 'Control how the form looks on your website.', 'cf7-visual-form-builder' ),
					'setTheme'              => __( 'Form style', 'cf7-visual-form-builder' ),
					'setThemeModern'        => __( 'Modern card', 'cf7-visual-form-builder' ),
					'setThemeMinimal'       => __( 'Minimal', 'cf7-visual-form-builder' ),
					'setThemeBordered'      => __( 'Bold border', 'cf7-visual-form-builder' ),
					'setPrimaryColor'       => __( 'Primary color', 'cf7-visual-form-builder' ),
					'setFormWidth'          => __( 'Form width', 'cf7-visual-form-builder' ),
					'setWidthNarrow'        => __( 'Narrow (480px)', 'cf7-visual-form-builder' ),
					'setWidthMedium'        => __( 'Medium (640px)', 'cf7-visual-form-builder' ),
					'setWidthWide'          => __( 'Wide (760px)', 'cf7-visual-form-builder' ),
					'setWidthFull'          => __( 'Full width', 'cf7-visual-form-builder' ),
					'setBorderRadius'       => __( 'Corner radius', 'cf7-visual-form-builder' ),
					'setRadiusNone'         => __( 'Square', 'cf7-visual-form-builder' ),
					'setRadiusSmall'        => __( 'Subtle', 'cf7-visual-form-builder' ),
					'setRadiusMedium'       => __( 'Rounded', 'cf7-visual-form-builder' ),
					'setRadiusLarge'        => __( 'Soft', 'cf7-visual-form-builder' ),
					'setFieldSpacing'       => __( 'Field spacing', 'cf7-visual-form-builder' ),
					'setSpacingComfortable' => __( 'Comfortable', 'cf7-visual-form-builder' ),
					'setSpacingCompact'     => __( 'Compact', 'cf7-visual-form-builder' ),
					'setProgressStyle'      => __( 'Step indicator', 'cf7-visual-form-builder' ),
					'setProgressPills'      => __( 'Steps with labels', 'cf7-visual-form-builder' ),
					'setProgressBar'        => __( 'Progress bar only', 'cf7-visual-form-builder' ),
					'setProgressMinimal'    => __( 'Minimal text', 'cf7-visual-form-builder' ),
					'setShowCounter'        => __( 'Show step counter', 'cf7-visual-form-builder' ),
					'setShowDots'           => __( 'Show step circles', 'cf7-visual-form-builder' ),
					'setShowDotsHint'       => __( 'Numbered circles with step titles.', 'cf7-visual-form-builder' ),
					'setShowHeading'        => __( 'Large step title above fields', 'cf7-visual-form-builder' ),
					'setShowHeadingHint'    => __( 'Off by default to avoid repeating the step name.', 'cf7-visual-form-builder' ),
					'logicTitle'            => __( 'Conditional logic', 'cf7-visual-form-builder' ),
					'logicIntro'            => __( 'Create rules to show, hide, or require fields based on answers.', 'cf7-visual-form-builder' ),
					'logicNoFields'         => __( 'Add fields on the Build tab before creating rules.', 'cf7-visual-form-builder' ),
					'addCondition'          => __( 'Add condition', 'cf7-visual-form-builder' ),
					'addAction'             => __( 'Add action', 'cf7-visual-form-builder' ),
					'addNested'             => __( 'Add nested group', 'cf7-visual-form-builder' ),
					'ifLabel'               => __( 'IF', 'cf7-visual-form-builder' ),
					'thenLabel'             => __( 'THEN', 'cf7-visual-form-builder' ),
					'matchAll'              => __( 'ALL conditions', 'cf7-visual-form-builder' ),
					'matchAny'              => __( 'ANY condition', 'cf7-visual-form-builder' ),
					'enabled'               => __( 'Active', 'cf7-visual-form-builder' ),
					'deleteRule'            => __( 'Delete rule', 'cf7-visual-form-builder' ),
					/* translators: %d: rule number. */
					'ruleLabel'             => __( 'Rule %d', 'cf7-visual-form-builder' ),
					'selectField'           => __( 'Select field', 'cf7-visual-form-builder' ),
					'selectStep'            => __( 'Select step', 'cf7-visual-form-builder' ),
					'secMultistep'          => __( 'Multistep & experience', 'cf7-visual-form-builder' ),
					'secMultistepDesc'      => __( 'How visitors move through and interact with your form.', 'cf7-visual-form-builder' ),
					'setAutosave'           => __( 'Autosave progress', 'cf7-visual-form-builder' ),
					'setSaveContinue'       => __( 'Save & continue later', 'cf7-visual-form-builder' ),
					'setAnimated'           => __( 'Animated step transitions', 'cf7-visual-form-builder' ),
					'setConversational'     => __( 'Conversational mode', 'cf7-visual-form-builder' ),
					'secThankyou'           => __( 'Thank you', 'cf7-visual-form-builder' ),
					'secGdpr'               => __( 'Privacy (GDPR)', 'cf7-visual-form-builder' ),
					'secA11y'               => __( 'Accessibility', 'cf7-visual-form-builder' ),
					'secSecurity'           => __( 'Security & spam', 'cf7-visual-form-builder' ),
					'secEmail'              => __( 'Email appearance', 'cf7-visual-form-builder' ),
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
				'label' => __( 'Text', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-editor-textcolor',
			),
			array(
				'type'  => 'textarea',
				'label' => __( 'Textarea', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-editor-alignleft',
			),
			array(
				'type'  => 'email',
				'label' => __( 'Email', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-email',
			),
			array(
				'type'  => 'phone',
				'label' => __( 'Phone', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-phone',
			),
			array(
				'type'  => 'number',
				'label' => __( 'Number', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-calculator',
			),
			array(
				'type'  => 'select',
				'label' => __( 'Select', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-arrow-down-alt2',
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Checkbox', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-yes',
			),
			array(
				'type'  => 'radio',
				'label' => __( 'Radio', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-marker',
			),
			array(
				'type'  => 'file',
				'label' => __( 'File upload', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-upload',
			),
			array(
				'type'  => 'date',
				'label' => __( 'Date', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			array(
				'type'  => 'hidden',
				'label' => __( 'Hidden', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-hidden',
			),
			array(
				'type'  => 'rating',
				'label' => __( 'Rating', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-star-filled',
			),
			array(
				'type'  => 'signature',
				'label' => __( 'Signature', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-edit',
			),
			array(
				'type'  => 'repeater',
				'label' => __( 'Repeater', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-plus-alt2',
			),
			array(
				'type'  => 'submit',
				'label' => __( 'Submit', 'cf7-visual-form-builder' ),
				'icon'  => 'dashicons-arrow-right-alt',
			),
		);
	}

	public static function add_entries_menu() {
		add_submenu_page(
			'wpcf7',
			__( 'Form Entries', 'cf7-visual-form-builder' ),
			__( 'Entries', 'cf7-visual-form-builder' ),
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
					'entryTitle'   => __( 'Entry #%d', 'cf7-visual-form-builder' ),
					'loading'      => __( 'Loading entry…', 'cf7-visual-form-builder' ),
					'loadError'    => __( 'Could not load entry.', 'cf7-visual-form-builder' ),
					'noFields'     => __( 'No field data recorded.', 'cf7-visual-form-builder' ),
					'referrer'     => __( 'Referrer', 'cf7-visual-form-builder' ),
					'statusSaved'  => __( 'Status updated.', 'cf7-visual-form-builder' ),
					'statusError'  => __( 'Could not update status.', 'cf7-visual-form-builder' ),
					/* translators: %d: step number. */
					'stepView'     => __( 'Step %d viewed', 'cf7-visual-form-builder' ),
					/* translators: %s: field name. */
					'fieldFocus'   => __( 'Field focus: %s', 'cf7-visual-form-builder' ),
					'eventView'    => __( 'Form viewed', 'cf7-visual-form-builder' ),
					'eventStart'   => __( 'Form started', 'cf7-visual-form-builder' ),
					'eventSubmit'  => __( 'Form submitted', 'cf7-visual-form-builder' ),
					'eventAbandon' => __( 'Form abandoned', 'cf7-visual-form-builder' ),
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
			wp_die( esc_html__( 'You are not allowed to view entries.', 'cf7-visual-form-builder' ) );
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
					<h1><?php esc_html_e( 'Form Entries', 'cf7-visual-form-builder' ); ?></h1>
					<p><?php esc_html_e( 'Review submissions, update status, and export data.', 'cf7-visual-form-builder' ); ?></p>
				</div>
				<div class="cf7vb-dash-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_args ) ) ); ?>"><?php esc_html_e( 'Export CSV', 'cf7-visual-form-builder' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_pdf ) ) ); ?>"><?php esc_html_e( 'Export PDF', 'cf7-visual-form-builder' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cf7vb-analytics' ) ); ?>"><?php esc_html_e( 'Analytics', 'cf7-visual-form-builder' ); ?></a>
				</div>
			</header>

			<div class="cf7vb-kpi-grid">
				<div class="cf7vb-kpi-card is-primary">
					<strong><?php esc_html_e( 'Total entries', 'cf7-visual-form-builder' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $entries['total'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Unread', 'cf7-visual-form-builder' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['unread'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Starred', 'cf7-visual-form-builder' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['starred'] ); ?></p>
				</div>
				<div class="cf7vb-kpi-card">
					<strong><?php esc_html_e( 'Spam', 'cf7-visual-form-builder' ); ?></strong>
					<p class="cf7vb-kpi-value"><?php echo esc_html( (string) $counts['spam'] ); ?></p>
				</div>
			</div>

			<div class="cf7vb-panel">
				<div class="cf7vb-panel-head">
					<h2><?php esc_html_e( 'Filter entries', 'cf7-visual-form-builder' ); ?></h2>
				</div>
				<form method="get" class="cf7vb-filter-bar">
					<input type="hidden" name="page" value="cf7vb-entries" />
					<?php wp_nonce_field( 'cf7vb_entries_filter', 'cf7vb_entries_nonce', false ); ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search field values…', 'cf7-visual-form-builder' ); ?>" />
					<select name="form_id">
						<option value="0"><?php esc_html_e( 'All forms', 'cf7-visual-form-builder' ); ?></option>
						<?php foreach ( $forms as $form ) : ?>
							<option value="<?php echo esc_attr( (string) $form->id() ); ?>" <?php selected( $form_id, $form->id() ); ?>><?php echo esc_html( $form->title() ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'cf7-visual-form-builder' ); ?></option>
						<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Apply filters', 'cf7-visual-form-builder' ), 'secondary', '', false ); ?>
				</form>
			</div>

			<div class="cf7vb-panel">
				<div class="cf7vb-panel-head">
					<h2><?php esc_html_e( 'Submissions', 'cf7-visual-form-builder' ); ?></h2>
				</div>
				<div class="cf7vb-table-wrap">
					<?php if ( empty( $entries['items'] ) ) : ?>
						<div class="cf7vb-empty">
							<span class="dashicons dashicons-email-alt"></span>
							<p><?php esc_html_e( 'No entries match your filters.', 'cf7-visual-form-builder' ); ?></p>
						</div>
					<?php else : ?>
						<table class="cf7vb-entries-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Entry', 'cf7-visual-form-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Form', 'cf7-visual-form-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Preview', 'cf7-visual-form-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'cf7-visual-form-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Device', 'cf7-visual-form-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Submitted', 'cf7-visual-form-builder' ); ?></th>
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
												<?php esc_html_e( 'View', 'cf7-visual-form-builder' ); ?>
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
							__( 'Showing page %1$d — %2$d total entries', 'cf7-visual-form-builder' ),
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
						<h2 id="cf7vb-modal-heading" class="cf7vb-modal-title"><?php esc_html_e( 'Entry details', 'cf7-visual-form-builder' ); ?></h2>
						<div class="cf7vb-modal-meta"></div>
					</div>
					<button type="button" class="cf7vb-modal-close" aria-label="<?php esc_attr_e( 'Close', 'cf7-visual-form-builder' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</header>
				<div class="cf7vb-modal-body"></div>
				<footer class="cf7vb-modal-footer">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Status', 'cf7-visual-form-builder' ); ?></span>
						<select class="cf7vb-status-select">
							<?php foreach ( array( 'unread', 'read', 'starred', 'spam', 'trash' ) as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( ucfirst( $st ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="button" class="button button-primary cf7vb-save-status"><?php esc_html_e( 'Update status', 'cf7-visual-form-builder' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}
}
