( function () {
	'use strict';

	const { createElement: el } = wp.element;
	const cfg = window.CFB_BUILDER || {};
	const L = cfg.strings || {};

	function updateSettings( props, patch ) {
		const s = props.clone( props.schema );
		s.settings = Object.assign( {}, s.settings, patch );
		props.pushHistory( s );
	}

	function patchNested( props, key, patch ) {
		const s = props.clone( props.schema );
		s.settings[ key ] = Object.assign( {}, s.settings[ key ] || {}, patch );
		props.pushHistory( s );
	}

	function ToggleRow( props ) {
		return el(
			'label',
			{ className: 'cfb-toggle-row' },
			el( 'input', {
				type: 'checkbox',
				className: 'cfb-toggle-input',
				checked: !! props.checked,
				onChange: function ( e ) {
					props.onChange( e.target.checked );
				},
			} ),
			el( 'span', { className: 'cfb-toggle-ui', 'aria-hidden': 'true' } ),
			el(
				'span',
				{ className: 'cfb-toggle-text' },
				el( 'strong', null, props.label ),
				props.hint ? el( 'small', null, props.hint ) : null
			)
		);
	}

	function SettingsSection( props ) {
		return el(
			'section',
			{ className: 'cfb-settings-section' },
			el(
				'header',
				{ className: 'cfb-settings-section-head' },
				props.icon
					? el( 'span', { className: 'cfb-settings-section-icon dashicons ' + props.icon } )
					: null,
				el(
					'div',
					null,
					el( 'h3', null, props.title ),
					props.desc ? el( 'p', null, props.desc ) : null
				)
			),
			el( 'div', { className: 'cfb-settings-section-body' }, props.children )
		);
	}

	function Field( props ) {
		return el(
			'div',
			{ className: 'cfb-settings-field' },
			el( 'label', null, props.label ),
			props.children,
			props.hint ? el( 'span', { className: 'cfb-settings-hint' }, props.hint ) : null
		);
	}

	window.CFB_SETTINGS = function SettingsPanel( props ) {
		const st = props.schema.settings || {};
		const ty = st.thankYou || {};
		const gdpr = st.gdpr || {};
		const a11y = st.accessibility || {};
		const sec = st.security || {};
		const emails = st.emails || {};
		const app = st.appearance || {};

		return el(
			'div',
			{ className: 'cfb-settings-panel' },
			el(
				'header',
				{ className: 'cfb-panel-intro' },
				el( 'h2', null, L.settingsTitle || 'Form settings' ),
				el( 'p', null, L.settingsIntro || 'Appearance, multistep behavior, thank-you page, privacy, security, and email.' )
			),
			el(
				SettingsSection,
				{
					title: L.secAppearance || 'Appearance & layout',
					desc: L.secAppearanceDesc || 'Control how the form looks on your website.',
					icon: 'dashicons-art',
				},
				el(
					'div',
					{ className: 'cfb-settings-row-2' },
					el(
						Field,
						{ label: L.setTheme || 'Form style' },
						el(
							'select',
							{
								className: 'cfb-settings-input',
								value: app.theme || 'modern',
								onChange: function ( e ) { patchNested( props, 'appearance', { theme: e.target.value } ); },
							},
							el( 'option', { value: 'modern' }, L.setThemeModern || 'Modern card' ),
							el( 'option', { value: 'minimal' }, L.setThemeMinimal || 'Minimal' ),
							el( 'option', { value: 'bordered' }, L.setThemeBordered || 'Bold border' )
						)
					),
					el(
						Field,
						{ label: L.setPrimaryColor || 'Primary color' },
						el( 'input', {
							type: 'color',
							className: 'cfb-settings-color',
							value: app.primaryColor || '#3858e9',
							onChange: function ( e ) { patchNested( props, 'appearance', { primaryColor: e.target.value } ); },
						} )
					)
				),
				el(
					'div',
					{ className: 'cfb-settings-row-2' },
					el(
						Field,
						{ label: L.setFormWidth || 'Form width' },
						el(
							'select',
							{
								className: 'cfb-settings-input',
								value: app.formWidth || 'medium',
								onChange: function ( e ) { patchNested( props, 'appearance', { formWidth: e.target.value } ); },
							},
							el( 'option', { value: 'narrow' }, L.setWidthNarrow || 'Narrow (480px)' ),
							el( 'option', { value: 'medium' }, L.setWidthMedium || 'Medium (640px)' ),
							el( 'option', { value: 'wide' }, L.setWidthWide || 'Wide (760px)' ),
							el( 'option', { value: 'full' }, L.setWidthFull || 'Full width' )
						)
					),
					el(
						Field,
						{ label: L.setBorderRadius || 'Corner radius' },
						el(
							'select',
							{
								className: 'cfb-settings-input',
								value: app.borderRadius || 'medium',
								onChange: function ( e ) { patchNested( props, 'appearance', { borderRadius: e.target.value } ); },
							},
							el( 'option', { value: 'none' }, L.setRadiusNone || 'Square' ),
							el( 'option', { value: 'small' }, L.setRadiusSmall || 'Subtle' ),
							el( 'option', { value: 'medium' }, L.setRadiusMedium || 'Rounded' ),
							el( 'option', { value: 'large' }, L.setRadiusLarge || 'Soft' )
						)
					)
				),
				el(
					'div',
					{ className: 'cfb-settings-row-2' },
					el(
						Field,
						{ label: L.setFieldSpacing || 'Field spacing' },
						el(
							'select',
							{
								className: 'cfb-settings-input',
								value: app.fieldSpacing || 'comfortable',
								onChange: function ( e ) { patchNested( props, 'appearance', { fieldSpacing: e.target.value } ); },
							},
							el( 'option', { value: 'comfortable' }, L.setSpacingComfortable || 'Comfortable' ),
							el( 'option', { value: 'compact' }, L.setSpacingCompact || 'Compact' )
						)
					),
					el(
						Field,
						{ label: L.setProgressStyle || 'Step indicator' },
						el(
							'select',
							{
								className: 'cfb-settings-input',
								value: app.progressStyle || 'pills',
								onChange: function ( e ) { patchNested( props, 'appearance', { progressStyle: e.target.value } ); },
							},
							el( 'option', { value: 'pills' }, L.setProgressPills || 'Steps with labels' ),
							el( 'option', { value: 'bar' }, L.setProgressBar || 'Progress bar only' ),
							el( 'option', { value: 'minimal' }, L.setProgressMinimal || 'Minimal text' )
						)
					)
				),
				el(
					'div',
					{ className: 'cfb-settings-toggles' },
					el( ToggleRow, {
						label: L.setShowCounter || 'Show step counter',
						checked: app.showStepCounter !== false,
						onChange: function ( v ) { patchNested( props, 'appearance', { showStepCounter: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setShowDots || 'Show step circles',
						hint: L.setShowDotsHint || 'Numbered circles with step titles.',
						checked: app.showStepDots !== false,
						onChange: function ( v ) { patchNested( props, 'appearance', { showStepDots: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setShowHeading || 'Large step title above fields',
						hint: L.setShowHeadingHint || 'Off by default to avoid repeating the step name.',
						checked: !! app.showStepHeading,
						onChange: function ( v ) { patchNested( props, 'appearance', { showStepHeading: v } ); },
					} )
				)
			),
			el(
				SettingsSection,
				{
					title: L.secMultistep || 'Multistep & experience',
					desc: L.secMultistepDesc || 'How visitors move through and interact with your form.',
					icon: 'dashicons-networking',
				},
				el(
					'div',
					{ className: 'cfb-settings-toggles' },
					el( ToggleRow, {
						label: L.setAutosave || 'Autosave progress',
						hint: L.setAutosaveHint || 'Save field values locally as users type.',
						checked: !! st.autosave,
						onChange: function ( v ) { updateSettings( props, { autosave: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setSaveContinue || 'Save & continue later',
						hint: L.setSaveContinueHint || 'Show a button to resume an incomplete form.',
						checked: !! st.saveContinue,
						onChange: function ( v ) { updateSettings( props, { saveContinue: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setAnimated || 'Animated step transitions',
						hint: L.setAnimatedHint || 'Smooth slide when changing steps on the frontend.',
						checked: st.animatedTransitions !== false,
						onChange: function ( v ) { updateSettings( props, { animatedTransitions: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setConversational || 'Conversational mode',
						hint: L.setConversationalHint || 'One question at a time (experimental).',
						checked: !! st.conversational,
						onChange: function ( v ) { updateSettings( props, { conversational: v } ); },
					} )
				)
			),
			el(
				SettingsSection,
				{
					title: L.secThankyou || 'Thank you',
					desc: L.secThankyouDesc || 'What happens after a successful submission.',
					icon: 'dashicons-yes-alt',
				},
				el(
					Field,
					{ label: L.setThankType || 'After submit' },
					el(
						'select',
						{
							className: 'cfb-settings-input',
							value: ty.type || 'message',
							onChange: function ( e ) { patchNested( props, 'thankYou', { type: e.target.value } ); },
						},
						el( 'option', { value: 'message' }, L.setThankMessage || 'Show message' ),
						el( 'option', { value: 'redirect' }, L.setThankRedirect || 'Redirect to URL' ),
						el( 'option', { value: 'page' }, L.setThankPage || 'WordPress page' )
					)
				),
				el(
					Field,
					{ label: L.setThankMsg || 'Success message' },
					el( 'textarea', {
						className: 'cfb-settings-input',
						rows: 3,
						value: ty.message || '',
						onChange: function ( e ) { patchNested( props, 'thankYou', { message: e.target.value } ); },
					} )
				),
				( ty.type === 'redirect' || ty.type === 'page' )
					? el(
						Field,
						{ label: L.setRedirectUrl || 'Redirect URL' },
						el( 'input', {
							type: 'url',
							className: 'cfb-settings-input',
							value: ty.redirectUrl || '',
							placeholder: 'https://',
							onChange: function ( e ) { patchNested( props, 'thankYou', { redirectUrl: e.target.value } ); },
						} )
					)
					: null
			),
			el(
				SettingsSection,
				{
					title: L.secGdpr || 'Privacy (GDPR)',
					desc: L.secGdprDesc || 'Consent checkbox on the last step of multistep forms.',
					icon: 'dashicons-privacy',
				},
				el( ToggleRow, {
					label: L.setGdprEnable || 'Require consent checkbox',
					checked: !! gdpr.enabled,
					onChange: function ( v ) { patchNested( props, 'gdpr', { enabled: v } ); },
				} ),
				gdpr.enabled
					? el(
						Field,
						{ label: L.setGdprLabel || 'Consent label' },
						el( 'input', {
							type: 'text',
							className: 'cfb-settings-input',
							value: gdpr.label || '',
							onChange: function ( e ) { patchNested( props, 'gdpr', { label: e.target.value } ); },
						} )
					)
					: null
			),
			el(
				SettingsSection,
				{
					title: L.secA11y || 'Accessibility',
					desc: L.secA11yDesc || 'Improve usability for all visitors.',
					icon: 'dashicons-universal-access',
				},
				el(
					'div',
					{ className: 'cfb-settings-toggles' },
					el( ToggleRow, {
						label: L.setAria || 'ARIA labels',
						checked: a11y.ariaLabels !== false,
						onChange: function ( v ) { patchNested( props, 'accessibility', { ariaLabels: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setHighContrast || 'High contrast mode',
						checked: !! a11y.highContrast,
						onChange: function ( v ) { patchNested( props, 'accessibility', { highContrast: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setFocus || 'Enhanced focus outlines',
						checked: a11y.focusVisible !== false,
						onChange: function ( v ) { patchNested( props, 'accessibility', { focusVisible: v } ); },
					} )
				)
			),
			el(
				SettingsSection,
				{
					title: L.secSecurity || 'Security & spam',
					desc: L.secSecurityDesc || 'Reduce bots and abuse.',
					icon: 'dashicons-shield',
				},
				el(
					'div',
					{ className: 'cfb-settings-toggles' },
					el( ToggleRow, {
						label: L.setHoneypot || 'Honeypot field',
						hint: L.setHoneypotHint || 'Hidden field that bots often fill.',
						checked: sec.honeypot !== false,
						onChange: function ( v ) { patchNested( props, 'security', { honeypot: v } ); },
					} ),
					el( ToggleRow, {
						label: L.setRateLimit || 'Rate limiting',
						hint: L.setRateLimitHint || 'Limit submissions per IP in a time window.',
						checked: sec.rateLimit !== false,
						onChange: function ( v ) { patchNested( props, 'security', { rateLimit: v } ); },
					} )
				)
			),
			el(
				SettingsSection,
				{
					title: L.secEmail || 'Email appearance',
					desc: L.secEmailDesc || 'HTML wrapper for notifications. Mail content is still edited under CF7 Mail.',
					icon: 'dashicons-email-alt',
				},
				el( ToggleRow, {
					label: L.setHtmlEmail || 'HTML email template',
					checked: !! emails.htmlTemplate,
					onChange: function ( v ) { patchNested( props, 'emails', { htmlTemplate: v } ); },
				} ),
				el(
					'div',
					{ className: 'cfb-settings-row-2' },
					el(
						Field,
						{ label: L.setBrandColor || 'Brand color' },
						el( 'input', {
							type: 'color',
							className: 'cfb-settings-color',
							value: emails.brandColor || '#3858e9',
							onChange: function ( e ) { patchNested( props, 'emails', { brandColor: e.target.value } ); },
						} )
					),
					el(
						Field,
						{ label: L.setLogoUrl || 'Logo URL' },
						el( 'input', {
							type: 'url',
							className: 'cfb-settings-input',
							value: emails.logoUrl || '',
							placeholder: 'https://',
							onChange: function ( e ) { patchNested( props, 'emails', { logoUrl: e.target.value } ); },
						} )
					)
				)
			)
		);
	};
} )();
