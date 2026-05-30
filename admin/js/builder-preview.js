( function () {
	'use strict';

	const { createElement: el } = wp.element;

	function appearanceClasses( schema ) {
		const app = ( schema.settings && schema.settings.appearance ) || {};
		return [
			'cf7vb-enhanced',
			'cf7vb-theme-' + ( app.theme || 'modern' ),
			'cf7vb-width-' + ( app.formWidth || 'medium' ),
			'cf7vb-spacing-' + ( app.fieldSpacing || 'comfortable' ),
			'cf7vb-radius-' + ( app.borderRadius || 'medium' ),
			'cf7vb-progress-style-' + ( app.progressStyle || 'pills' ),
		].join( ' ' );
	}

	function appearanceStyle( schema ) {
		const app = ( schema.settings && schema.settings.appearance ) || {};
		const radius = { none: '0px', small: '6px', medium: '10px', large: '16px' };
		const width = { narrow: '480px', medium: '640px', wide: '760px', full: '100%' };
		const gap = { compact: '14px', comfortable: '22px' };
		return {
			'--cf7vb-primary': app.primaryColor || '#3858e9',
			'--cf7vb-radius': radius[ app.borderRadius ] || '10px',
			'--cf7vb-form-width': width[ app.formWidth ] || '640px',
			'--cf7vb-field-gap': gap[ app.fieldSpacing ] || '22px',
		};
	}

	function fieldId( field ) {
		return 'cf7vb-preview-' + ( field.name || field.id );
	}

	function PreviewField( { field } ) {
		const req = field.required ? el( 'abbr', { title: 'required', 'aria-hidden': 'true' }, '*' ) : null;
		const help = field.help
			? el( 'p', { className: 'cf7vb-preview-help' }, field.help )
			: null;

		const labelEl = field.type !== 'hidden' && field.type !== 'submit'
			? el( 'label', { className: 'cf7vb-preview-label', htmlFor: fieldId( field ) },
				field.label || field.name,
				req
			)
			: null;

		let control = null;
		const common = {
			id: fieldId( field ),
			name: field.name,
			disabled: true,
			'aria-disabled': 'true',
		};

		switch ( field.type ) {
			case 'textarea':
				control = el( 'textarea', {
					...common,
					rows: 4,
					placeholder: field.placeholder || '',
					className: 'cf7vb-preview-control',
				} );
				break;
			case 'email':
				control = el( 'input', { ...common, type: 'email', placeholder: field.placeholder || 'name@example.com', className: 'cf7vb-preview-control' } );
				break;
			case 'phone':
				control = el( 'input', { ...common, type: 'tel', placeholder: field.placeholder || '+1 (555) 000-0000', className: 'cf7vb-preview-control' } );
				break;
			case 'number':
				control = el( 'input', { ...common, type: 'number', placeholder: field.placeholder || '0', className: 'cf7vb-preview-control' } );
				break;
			case 'date':
				control = el( 'input', { ...common, type: 'date', className: 'cf7vb-preview-control' } );
				break;
			case 'file':
				control = el( 'div', { className: 'cf7vb-preview-file' },
					el( 'span', { className: 'dashicons dashicons-upload' } ),
					' ',
					field.label || 'Choose file'
				);
				break;
			case 'select':
				control = el(
					'select',
					{ ...common, className: 'cf7vb-preview-control' },
					el( 'option', { value: '' }, field.placeholder || 'Select…' ),
					( field.options || [] ).map( ( o, i ) =>
						el( 'option', { key: i, value: o.value || o.label || o }, o.label || o.value || o )
					)
				);
				break;
			case 'checkbox':
				control = el(
					'div',
					{ className: 'cf7vb-preview-choice-group' },
					( field.options || [ { label: 'Option', value: 'option' } ] ).map( ( o, i ) =>
						el(
							'label',
							{ key: i, className: 'cf7vb-preview-choice' },
							el( 'input', { type: 'checkbox', disabled: true, name: field.name } ),
							' ',
							o.label || o.value || o
						)
					)
				);
				break;
			case 'radio':
				control = el(
					'div',
					{ className: 'cf7vb-preview-choice-group' },
					( field.options || [ { label: 'Yes', value: 'yes' }, { label: 'No', value: 'no' } ] ).map( ( o, i ) =>
						el(
							'label',
							{ key: i, className: 'cf7vb-preview-choice' },
							el( 'input', { type: 'radio', disabled: true, name: field.name } ),
							' ',
							o.label || o.value || o
						)
					)
				);
				break;
			case 'hidden':
				return el(
					'div',
					{ className: 'cf7vb-preview-field cf7vb-preview-field--hidden' },
					el( 'span', { className: 'cf7vb-preview-badge' }, 'Hidden: ' + ( field.name || '' ) )
				);
			case 'rating':
				control = el(
					'div',
					{ className: 'cf7vb-preview-rating', 'aria-label': 'Rating' },
					[ 1, 2, 3, 4, 5 ].map( ( n ) =>
						el( 'span', { key: n, className: 'cf7vb-preview-star' }, '★' )
					)
				);
				break;
			case 'signature':
				control = el(
					'div',
					{ className: 'cf7vb-preview-signature' },
					el( 'span', null, 'Sign here' )
				);
				break;
			case 'repeater':
				control = el(
					'div',
					{ className: 'cf7vb-preview-repeater' },
					el( 'p', null, 'Repeater — respondents can add multiple rows' ),
					el( 'button', { type: 'button', className: 'cf7vb-preview-btn', disabled: true }, '+ Add row' )
				);
				break;
			case 'submit':
				return el(
					'div',
					{ className: 'cf7vb-preview-field cf7vb-preview-field--submit' },
					el( 'button', { type: 'button', className: 'cf7vb-preview-submit', disabled: true }, field.label || 'Send' )
				);
			default:
				control = el( 'input', {
					...common,
					type: 'text',
					placeholder: field.placeholder || field.label || '',
					className: 'cf7vb-preview-control',
				} );
		}

		return el(
			'p',
			{ className: 'cf7vb-preview-field cf7vb-preview-field--' + field.type },
			labelEl,
			el( 'span', { className: 'wpcf7-form-control-wrap' }, control ),
			help
		);
	}

	function PreviewForm( { schema, activeStepOnly, activeStepIndex } ) {
		const steps = schema.steps || [];
		const multistep = schema.settings?.multistep && steps.length > 1;
		const gdpr = schema.settings?.gdpr;

		const stepsToRender = activeStepOnly && steps.length
			? [ steps[ activeStepIndex ] || steps[ 0 ] ]
			: steps;

		return el(
			'div',
			{ className: 'cf7vb-preview-form-inner' },
			multistep && ! activeStepOnly
				? el(
					'div',
					{ className: 'cf7vb-preview-steps-bar' },
					steps.map( ( step, i ) =>
						el(
							'span',
							{
								key: step.id,
								className: 'cf7vb-preview-step-pill' + ( i === activeStepIndex ? ' is-active' : '' ),
							},
							step.title || 'Step ' + ( i + 1 )
						)
					)
				)
				: null,
			stepsToRender.map( ( step, si ) =>
				el(
					'div',
					{ key: step.id, className: 'cf7vb-preview-step' },
					multistep && activeStepOnly
						? el( 'h4', { className: 'cf7vb-preview-step-title' }, step.title || 'Step ' + ( activeStepIndex + 1 ) )
						: null,
					! activeStepOnly && multistep
						? el( 'h4', { className: 'cf7vb-preview-step-title' }, step.title || 'Step ' + ( si + 1 ) )
						: null,
					( step.sections || [] ).map( ( section ) =>
						el(
							'div',
							{ key: section.id, className: 'cf7vb-preview-section' },
							section.title ? el( 'h5', { className: 'cf7vb-preview-section-title' }, section.title ) : null,
							( section.fields || [] ).map( ( field ) =>
								el( PreviewField, { key: field.id, field } )
							)
						)
					)
				)
			),
			gdpr && gdpr.enabled
				? el(
					'div',
					{ className: 'cf7vb-preview-field cf7vb-preview-gdpr' },
					el(
						'label',
						{ className: 'cf7vb-preview-choice' },
						el( 'input', { type: 'checkbox', disabled: true } ),
						' ',
						gdpr.label || 'I agree to the privacy policy.'
					)
				)
				: null
		);
	}

	/**
	 * @param {object} props
	 * @param {object} props.schema
	 * @param {string} props.device desktop|tablet|mobile
	 * @param {boolean} props.activeStepOnly
	 * @param {number} props.activeStepIndex
	 * @param {boolean} props.showCode
	 * @param {string} props.compiled
	 * @param {boolean} props.fullWidth
	 */
	window.CF7VB_PREVIEW = function VisualPreview( props ) {
		const schema = props.schema || { steps: [] };
		const device = props.device || 'desktop';
		const activeStepOnly = props.activeStepOnly !== false && schema.settings?.multistep;
		const activeStepIndex = typeof props.activeStepIndex === 'number' ? props.activeStepIndex : 0;

		const deviceLabel = device.charAt( 0 ).toUpperCase() + device.slice( 1 );

		return el(
			'div',
			{
				className:
					'cf7vb-visual-preview cf7vb-visual-preview--' +
					device +
					( props.fullWidth ? ' is-full' : ' is-compact' ),
				'data-device': device,
			},
			el(
				'div',
				{ className: 'cf7vb-device-frame', 'data-device': device },
				props.fullWidth
					? el(
						'div',
						{ className: 'cf7vb-preview-chrome' },
						el( 'span', { className: 'cf7vb-preview-chrome-dot' } ),
						el( 'span', { className: 'cf7vb-preview-chrome-dot' } ),
						el( 'span', { className: 'cf7vb-preview-chrome-dot' } ),
						el( 'span', { className: 'cf7vb-preview-chrome-title' }, deviceLabel + ' — ' + ( 'Form preview' ) )
					)
					: el( 'div', { className: 'cf7vb-preview-device-badge' }, deviceLabel ),
				el(
					'div',
					{
						className: 'cf7vb-preview-card ' + appearanceClasses( schema ),
						style: appearanceStyle( schema ),
					},
					el(
						'form',
						{
							className: 'wpcf7-form cf7vb-preview-form',
							onSubmit: function ( e ) {
								e.preventDefault();
							},
						},
						el( PreviewForm, { schema, activeStepOnly, activeStepIndex } )
					)
				)
			),
			props.showCode && props.compiled
				? el(
					'details',
					{ className: 'cf7vb-preview-code-details' },
					el( 'summary', null, 'Show CF7 code (advanced)' ),
					el( 'pre', { className: 'cf7vb-preview-code' }, props.compiled )
				)
				: null
		);
	};
} )();
