( function () {
	'use strict';

	const { createElement: el, Fragment, useState, useEffect, useCallback, useRef, useMemo } = wp.element;
	const apiFetch = wp.apiFetch;
	const cfg = window.CFB_BUILDER || {};
	const S = cfg.strings || {};

	const WORKSPACE_TABS = [
		{ id: 'build', label: S.tabBuild || 'Build', desc: S.tabBuildDesc || 'Add & arrange fields', icon: 'dashicons-layout' },
		{ id: 'preview', label: S.tabPreview || 'Preview', desc: S.tabPreviewDesc || 'Visitor view', icon: 'dashicons-visibility' },
		{ id: 'logic', label: S.tabLogic || 'Rules', desc: S.tabLogicDesc || 'Conditions', icon: 'dashicons-randomize' },
		{ id: 'settings', label: S.tabSettings || 'Settings', desc: S.tabSettingsDesc || 'GDPR & more', icon: 'dashicons-admin-generic' },
	];

	function renderVisualPreview( props ) {
		if ( ! window.CFB_PREVIEW ) {
			return el( 'p', { className: 'cfb-preview-fallback' }, 'Preview loading…' );
		}
		return el( window.CFB_PREVIEW, props );
	}

	function StepsBar( props ) {
		const {
			schema,
			activeStep,
			setActiveStep,
			onTitleChange,
			onTitleCommit,
			onRemoveStep,
			onAddStep,
			compact,
			S,
		} = props;
		const multistep = schema.settings?.multistep && schema.steps.length > 1;
		if ( ! multistep ) {
			return null;
		}

		return el(
			'div',
			{ className: 'cfb-steps-panel' + ( compact ? ' cfb-steps-panel--compact' : '' ) },
			compact
				? null
				: el(
					'div',
					{ className: 'cfb-steps-panel-head' },
					el( 'div', null,
						el( 'strong', null, S.stepsTitle || 'Form steps' ),
						el( 'p', null, S.stepsHint || 'Name each step — titles appear on the live form progress bar.' )
					),
					el(
						'span',
						{ className: 'cfb-steps-count' },
						schema.steps.length + ' ' + ( S.stepsLabel || 'steps' )
					)
				),
			el(
				'div',
				{ className: 'cfb-steps-list', role: 'tablist', 'aria-label': S.stepsTitle || 'Form steps' },
				schema.steps.map( ( step, i ) =>
					el(
						'div',
						{
							key: step.id,
							className: 'cfb-step-item' + ( activeStep === i ? ' is-active' : '' ),
							role: 'tab',
							'aria-selected': activeStep === i ? 'true' : 'false',
						},
						el(
							'button',
							{
								type: 'button',
								className: 'cfb-step-select',
								onClick: () => setActiveStep( i ),
								title: S.editStep || 'Select step',
							},
							el( 'span', { className: 'cfb-step-num' }, String( i + 1 ) )
						),
						el( 'input', {
							type: 'text',
							className: 'cfb-step-title-input',
							value: step.title || '',
							placeholder: ( S.stepPlaceholder || 'Step %d' ).replace( '%d', String( i + 1 ) ),
							onFocus: () => setActiveStep( i ),
							onChange: ( e ) => onTitleChange( i, e.target.value ),
							onBlur: ( e ) => onTitleCommit( i, e.target.value ),
							onClick: ( e ) => e.stopPropagation(),
						} ),
						schema.steps.length > 2
							? el( 'button', {
								type: 'button',
								className: 'cfb-step-remove',
								title: S.removeStep || 'Remove step',
								onClick: ( e ) => {
									e.stopPropagation();
									onRemoveStep( i );
								},
							}, el( 'span', { className: 'dashicons dashicons-trash' } ) )
							: null
					)
				),
				el(
					'button',
					{
						type: 'button',
						className: 'cfb-btn cfb-btn--dashed cfb-add-step-btn',
						onClick: onAddStep,
					},
					el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
					S.addStep || 'Add step'
				)
			)
		);
	}

	function DeviceToggle( { device, setDevice, compact } ) {
		return el(
			'div',
			{ className: 'cfb-device-toggle' + ( compact ? ' cfb-device-toggle--compact' : '' ), role: 'group', 'aria-label': 'Preview device size' },
			[ 'desktop', 'tablet', 'mobile' ].map( ( d ) =>
				el(
					'button',
					{
						key: d,
						type: 'button',
						className: 'cfb-btn' + ( device === d ? ' cfb-btn--active' : '' ),
						'aria-pressed': device === d ? 'true' : 'false',
						onClick: ( e ) => {
							e.preventDefault();
							e.stopPropagation();
							setDevice( d );
						},
					},
					S[ d ] || d.charAt( 0 ).toUpperCase() + d.slice( 1 )
				)
			)
		);
	}

	const FIELD_DEFAULTS = {
		text: { name: 'text-field', label: 'Text Field' },
		textarea: { name: 'message', label: 'Message' },
		email: { name: 'email', label: 'Email' },
		phone: { name: 'phone', label: 'Phone' },
		number: { name: 'number', label: 'Number' },
		select: { name: 'select', label: 'Select', options: [ { label: 'Option 1', value: 'Option 1' }, { label: 'Option 2', value: 'Option 2' } ] },
		checkbox: { name: 'choices', label: 'Choices', options: [ { label: 'Option A', value: 'Option A' } ] },
		radio: { name: 'choice', label: 'Choice', options: [ { label: 'Yes', value: 'Yes' }, { label: 'No', value: 'No' } ] },
		file: { name: 'file', label: 'Upload' },
		date: { name: 'date', label: 'Date' },
		hidden: { name: 'hidden', label: 'Hidden' },
		rating: { name: 'rating', label: 'Rating' },
		signature: { name: 'signature', label: 'Signature' },
		repeater: { name: 'repeater', label: 'Repeater' },
		submit: { name: 'submit', label: 'Send', required: false },
	};

	function uid( prefix ) {
		return ( prefix || 'id' ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function clone( obj ) {
		return JSON.parse( JSON.stringify( obj ) );
	}

	function loadInitialSchema() {
		const input = document.getElementById( 'cfb-schema-input' );
		if ( input && input.value ) {
			try {
				return JSON.parse( input.value );
			} catch ( e ) { /* noop */ }
		}
		const boot = document.getElementById( 'cfb-default-schema' );
		if ( boot && boot.textContent ) {
			try {
				return JSON.parse( boot.textContent );
			} catch ( e ) { /* noop */ }
		}
		return { version: '1.0', settings: { multistep: false }, steps: [ { id: 'step-1', title: 'Step 1', sections: [ { id: 'section-1', title: '', fields: [] } ] } ], rules: [] };
	}

	function compileField( field ) {
		const req = field.required ? '*' : '';
		const name = field.name || 'field';
		const opts = [];
		if ( field.placeholder ) {
			opts.push( 'placeholder "' + String( field.placeholder ).replace( /"/g, '' ) + '"' );
		}
		const optionValues = ( field.options || [] ).map( ( o ) => '"' + String( o.value || o.label || o ).replace( /"/g, '' ) + '"' );

		switch ( field.type ) {
			case 'email': return `[email${req} ${name} ${opts.join( ' ' )}]`;
			case 'phone': return `[tel${req} ${name} ${opts.join( ' ' )}]`;
			case 'textarea': return `[textarea${req} ${name} ${opts.join( ' ' )}]`;
			case 'number': return `[number${req} ${name} ${opts.join( ' ' )}]`;
			case 'select': return `[select${req} ${name} ${opts.concat( optionValues ).join( ' ' )}]`;
			case 'checkbox': return `[checkbox${req} ${name} use_label_element ${optionValues.join( ' ' )}]`;
			case 'radio': return `[radio${req} ${name} use_label_element ${optionValues.join( ' ' )}]`;
			case 'file': return `[file${req} ${name}]`;
			case 'date': return `[date${req} ${name}]`;
			case 'hidden': return `[hidden ${name}]`;
			case 'submit': return `[submit "${String( field.label || 'Send' ).replace( /"/g, '' )}"]`;
			default: return `[text${req} ${name} ${opts.join( ' ' )}]`;
		}
	}

	function compileStepBody( step ) {
		const stepLines = [];
		( step.sections || [] ).forEach( ( section ) => {
			if ( section.title ) {
				stepLines.push( '<h3 class="cfb-section-title">' + section.title + '</h3>' );
			}
			( section.fields || [] ).forEach( ( field ) => {
				if ( field.label && field.type !== 'hidden' && field.type !== 'submit' ) {
					stepLines.push( '<label> ' + field.label + ( field.required ? ' <span class="cfb-req">*</span>' : '' ) );
					stepLines.push( compileField( field ) );
					stepLines.push( '</label>' );
				} else {
					stepLines.push( compileField( field ) );
				}
				if ( field.help ) {
					stepLines.push( '<span class="cfb-help">' + field.help + '</span>' );
				}
			} );
		} );
		return stepLines.filter( Boolean ).join( '\n\n' );
	}

	function compileSchema( schema ) {
		const settings = schema.settings || {};
		const multistep = settings.multistep && ( schema.steps || [] ).length > 1;
		const lines = [];
		( schema.steps || [] ).forEach( ( step, stepIndex ) => {
			const stepBody = compileStepBody( step );

			if ( multistep ) {
				const stepTitle = String( step.title || '' ).trim();
				const titleAttr = stepTitle
					? ' data-cfb-step-title="' + stepTitle.replace( /"/g, '&quot;' ) + '"'
					: '';
				lines.push(
					'<div class="cfb-step-group" data-cfb-step="' + step.id + '" data-cfb-step-index="' + stepIndex + '"' + titleAttr + '>' +
					stepBody +
					'</div>'
				);
			} else if ( stepBody ) {
				lines.push( stepBody );
			}
		} );

		if ( settings.security && settings.security.honeypot ) {
			const hp = settings.security.honeypotField || 'cfb_hp';
			lines.push(
				'<span class="cfb-hp" aria-hidden="true" style="position:absolute;left:-9999px;"><label>Leave empty [text ' + hp + ']</label></span>'
			);
		}

		if ( ! multistep && settings.gdpr && settings.gdpr.enabled ) {
			const gn = settings.gdpr.fieldName || 'gdpr-consent';
			const gl = settings.gdpr.label || 'I agree to the privacy policy.';
			lines.push( '[acceptance ' + gn + ']\n' + gl );
		}

		return lines.filter( Boolean ).join( '\n\n' );
	}

	function createField( type ) {
		const base = FIELD_DEFAULTS[ type ] || FIELD_DEFAULTS.text;
		return {
			id: uid( 'field' ),
			type,
			name: base.name + '-' + Math.floor( Math.random() * 9999 ),
			label: base.label,
			required: type !== 'submit' && type !== 'hidden',
			placeholder: '',
			help: '',
			options: base.options ? clone( base.options ) : [],
			validation: {},
			attrs: {},
		};
	}

	function findFieldPath( schema, fieldId ) {
		for ( let si = 0; si < schema.steps.length; si++ ) {
			const step = schema.steps[ si ];
			for ( let sei = 0; sei < step.sections.length; sei++ ) {
				const section = step.sections[ sei ];
				const fi = section.fields.findIndex( ( f ) => f.id === fieldId );
				if ( fi >= 0 ) {
					return { stepIndex: si, sectionIndex: sei, fieldIndex: fi };
				}
			}
		}
		return null;
	}

	function resolveInsertIndex( path, toStep, toSection, atIndex ) {
		let insertIndex = atIndex;
		if ( path.stepIndex === toStep && path.sectionIndex === toSection && path.fieldIndex < insertIndex ) {
			insertIndex--;
		}
		return insertIndex;
	}

	function MockControl( { field } ) {
		const common = { disabled: true, readOnly: true };
		if ( field.type === 'textarea' ) {
			return el( 'textarea', { ...common, rows: 3, placeholder: field.placeholder || '' } );
		}
		if ( field.type === 'select' ) {
			return el(
				'select',
				{ ...common },
				( field.options || [] ).map( ( o, i ) =>
					el( 'option', { key: i }, o.label || o.value || o )
				)
			);
		}
		if ( field.type === 'submit' ) {
			return el( 'input', { type: 'submit', value: field.label || 'Send', ...common } );
		}
		const typeMap = { email: 'email', phone: 'tel', number: 'number', date: 'date', file: 'file', hidden: 'hidden' };
		return el( 'input', {
			type: typeMap[ field.type ] || 'text',
			placeholder: field.placeholder || field.label,
			...common,
		} );
	}

	function BuilderApp() {
		const [ schema, setSchema ] = useState( loadInitialSchema );
		const [ activeStep, setActiveStep ] = useState( 0 );
		const [ selectedId, setSelectedId ] = useState( null );
		const [ device, setDevice ] = useState( schema.settings?.previewDevice || 'desktop' );
		const [ workspace, setWorkspace ] = useState( 'build' );
		const [ inlineEditId, setInlineEditId ] = useState( null );
		const [ dragType, setDragType ] = useState( null );
		const [ dragFieldId, setDragFieldId ] = useState( null );
		const [ dropTarget, setDropTarget ] = useState( null );
		const history = useRef( [] );
		const future = useRef( [] );

		const compiled = useMemo( () => compileSchema( schema ), [ schema ] );

		const pushHistory = useCallback( ( next ) => {
			history.current.push( clone( schema ) );
			if ( history.current.length > 40 ) {
				history.current.shift();
			}
			future.current = [];
			setSchema( next );
		}, [ schema ] );

		const patchSchema = useCallback( ( next ) => {
			setSchema( next );
		}, [] );

		function normalizeStepTitle( title ) {
			return String( title || '' ).trim();
		}

		function applyStepTitlesFromDom( baseSchema ) {
			const next = clone( baseSchema );
			document.querySelectorAll( '.cfb-step-title-input' ).forEach( ( input, index ) => {
				if ( ! next.steps[ index ] ) {
					return;
				}
				next.steps[ index ].title = normalizeStepTitle( input.value );
			} );
			return next;
		}

		const undo = () => {
			if ( ! history.current.length ) return;
			future.current.push( clone( schema ) );
			const prev = history.current.pop();
			setSchema( prev );
		};

		const redo = () => {
			if ( ! future.current.length ) return;
			history.current.push( clone( schema ) );
			setSchema( future.current.pop() );
		};

		const syncDom = useCallback( ( nextSchema, markup ) => {
			const json = JSON.stringify( nextSchema );
			const schemaInput = document.getElementById( 'cfb-schema-input' );
			const cf7Form = document.getElementById( 'wpcf7-form' );
			if ( schemaInput ) {
				schemaInput.value = json;
			}
			if ( cf7Form ) {
				cf7Form.value = markup;
			}
		}, [] );

		useEffect( () => {
			syncDom( schema, compiled );
		}, [ schema, compiled, syncDom ] );

		useEffect( () => {
			const form = document.getElementById( 'wpcf7-admin-form-element' );
			if ( ! form ) {
				return;
			}
			const onSubmit = () => {
				const merged = applyStepTitlesFromDom( schema );
				syncDom( merged, compileSchema( merged ) );
			};
			form.addEventListener( 'submit', onSubmit, true );
			return () => form.removeEventListener( 'submit', onSubmit, true );
		}, [ schema, compiled, syncDom ] );

		const currentStep = schema.steps[ activeStep ] || schema.steps[ 0 ];
		const selectedField = useMemo( () => {
			if ( ! selectedId ) return null;
			const path = findFieldPath( schema, selectedId );
			if ( ! path ) return null;
			return schema.steps[ path.stepIndex ].sections[ path.sectionIndex ].fields[ path.fieldIndex ];
		}, [ schema, selectedId ] );

		const updateField = ( fieldId, patch ) => {
			const next = clone( schema );
			const path = findFieldPath( next, fieldId );
			if ( ! path ) return;
			const field = next.steps[ path.stepIndex ].sections[ path.sectionIndex ].fields[ path.fieldIndex ];
			Object.assign( field, patch );
			pushHistory( next );
		};

		const addFieldToSection = ( stepIndex, sectionIndex, type, atIndex ) => {
			const next = clone( schema );
			const field = createField( type );
			const fields = next.steps[ stepIndex ].sections[ sectionIndex ].fields;
			const index = typeof atIndex === 'number' ? atIndex : fields.length;
			fields.splice( index, 0, field );
			pushHistory( next );
			setSelectedId( field.id );
			setActiveStep( stepIndex );
		};

		const removeField = ( fieldId ) => {
			const next = clone( schema );
			const path = findFieldPath( next, fieldId );
			if ( ! path ) return;
			next.steps[ path.stepIndex ].sections[ path.sectionIndex ].fields.splice( path.fieldIndex, 1 );
			pushHistory( next );
			if ( selectedId === fieldId ) setSelectedId( null );
		};

		const duplicateField = ( fieldId ) => {
			const path = findFieldPath( schema, fieldId );
			if ( ! path ) return;
			const next = clone( schema );
			const orig = next.steps[ path.stepIndex ].sections[ path.sectionIndex ].fields[ path.fieldIndex ];
			const copy = clone( orig );
			copy.id = uid( 'field' );
			copy.name = orig.name + '-copy';
			next.steps[ path.stepIndex ].sections[ path.sectionIndex ].fields.splice( path.fieldIndex + 1, 0, copy );
			pushHistory( next );
			setSelectedId( copy.id );
		};

		const moveField = ( fieldId, toStep, toSection, toIndex ) => {
			const next = clone( schema );
			const from = findFieldPath( next, fieldId );
			if ( ! from ) return;
			const [ moved ] = next.steps[ from.stepIndex ].sections[ from.sectionIndex ].fields.splice( from.fieldIndex, 1 );
			next.steps[ toStep ].sections[ toSection ].fields.splice( toIndex, 0, moved );
			pushHistory( next );
		};

		const toggleMultistep = () => {
			const next = clone( schema );
			if ( ! next.settings ) next.settings = {};
			if ( next.settings.multistep && next.steps.length > 1 ) {
				const allFields = [];
				next.steps.forEach( ( st ) => st.sections.forEach( ( sec ) => allFields.push( ...sec.fields ) ) );
				next.steps = [ { id: 'step-1', title: 'Step 1', sections: [ { id: 'section-1', title: '', fields: allFields } ] } ];
				next.settings.multistep = false;
				setActiveStep( 0 );
			} else {
				next.settings.multistep = true;
				if ( next.steps.length < 2 ) {
					next.steps.push( { id: uid( 'step' ), title: 'Step 2', sections: [ { id: uid( 'section' ), title: '', fields: [] } ] } );
				}
			}
			pushHistory( next );
		};

		const addStep = () => {
			const next = clone( schema );
			next.settings.multistep = true;
			const n = next.steps.length + 1;
			next.steps.push( {
				id: uid( 'step' ),
				title: ( S.defaultStepTitle || 'Step %d' ).replace( '%d', String( n ) ),
				sections: [ { id: uid( 'section' ), title: '', fields: [] } ],
			} );
			pushHistory( next );
			setActiveStep( next.steps.length - 1 );
		};

		const updateStepTitleLive = ( stepIndex, title ) => {
			const newTitle = normalizeStepTitle( title );
			if ( schema.steps[ stepIndex ].title === newTitle ) {
				return;
			}
			const next = clone( schema );
			next.steps[ stepIndex ].title = newTitle;
			patchSchema( next );
		};

		const commitStepTitle = ( stepIndex, title ) => {
			const newTitle = normalizeStepTitle( title );
			if ( schema.steps[ stepIndex ].title === newTitle ) {
				return;
			}
			const next = clone( schema );
			next.steps[ stepIndex ].title = newTitle;
			pushHistory( next );
		};

		const removeStep = ( stepIndex ) => {
			if ( schema.steps.length <= 2 ) {
				return;
			}
			const next = clone( schema );
			next.steps.splice( stepIndex, 1 );
			pushHistory( next );
			const nextActive = Math.min( activeStep, next.steps.length - 1 );
			setActiveStep( Math.max( 0, nextActive ) );
		};

		const stepsBarProps = {
			schema,
			activeStep,
			setActiveStep,
			onTitleChange: updateStepTitleLive,
			onTitleCommit: commitStepTitle,
			onRemoveStep: removeStep,
			onAddStep: addStep,
			S,
		};

		const addSection = () => {
			const next = clone( schema );
			currentStep.sections = currentStep.sections || [];
			next.steps[ activeStep ].sections.push( { id: uid( 'section' ), title: '', fields: [] } );
			pushHistory( next );
		};

		const addRule = () => {
			const next = clone( schema );
			if ( ! next.rules ) next.rules = [];
			next.rules.push( {
				id: uid( 'rule' ),
				enabled: true,
				operator: 'and',
				conditions: [ { field: '', compare: 'equals', value: '' } ],
				actions: [ { type: 'show', target: '' } ],
			} );
			pushHistory( next );
		};

		const onPaletteDragStart = ( type ) => ( e ) => {
			setDragType( type );
			e.dataTransfer.setData( 'text/cfb-type', type );
			e.dataTransfer.effectAllowed = 'copy';
		};

		const onFieldDragStart = ( fieldId ) => ( e ) => {
			setDragFieldId( fieldId );
			e.dataTransfer.setData( 'text/cfb-field', fieldId );
			e.dataTransfer.effectAllowed = 'move';
		};

		const clearDragState = () => {
			setDragType( null );
			setDragFieldId( null );
			setDropTarget( null );
		};

		const onDropAtIndex = ( stepIndex, sectionIndex, atIndex ) => ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			const type = e.dataTransfer.getData( 'text/cfb-type' ) || dragType;
			const fieldId = e.dataTransfer.getData( 'text/cfb-field' ) || dragFieldId;

			if ( type ) {
				addFieldToSection( stepIndex, sectionIndex, type, atIndex );
			} else if ( fieldId ) {
				const path = findFieldPath( schema, fieldId );
				if ( path ) {
					const insertIndex = resolveInsertIndex( path, stepIndex, sectionIndex, atIndex );
					const unchanged =
						path.stepIndex === stepIndex &&
						path.sectionIndex === sectionIndex &&
						path.fieldIndex === insertIndex;
					if ( ! unchanged ) {
						moveField( fieldId, stepIndex, sectionIndex, insertIndex );
					}
				}
			}
			clearDragState();
		};

		const onDragOverSlot = ( slotKey ) => ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			e.dataTransfer.dropEffect = dragFieldId ? 'move' : 'copy';
			setDropTarget( slotKey );
		};

		const logicProps = { schema, setSchema, pushHistory, clone, S };
		const settingsProps = logicProps;
		const previewProps = {
			schema,
			device,
			activeStepIndex: activeStep,
			activeStepOnly: !! schema.settings?.multistep && schema.steps.length > 1,
			compiled,
			showCode: workspace === 'preview',
		};

		return el(
			'div',
			{ className: 'cfb-app' },
			el(
				'nav',
				{ className: 'cfb-main-tabs', role: 'tablist', 'aria-label': 'Form builder sections' },
				WORKSPACE_TABS.map( ( tab ) =>
					el(
						'button',
						{
							key: tab.id,
							type: 'button',
							role: 'tab',
							'aria-selected': workspace === tab.id ? 'true' : 'false',
							className: 'cfb-main-tab' + ( workspace === tab.id ? ' is-active' : '' ),
							onClick: () => setWorkspace( tab.id ),
						},
						el( 'span', { className: 'cfb-main-tab-icon dashicons ' + tab.icon } ),
						el(
							'span',
							{ className: 'cfb-main-tab-text' },
							el( 'strong', null, tab.label ),
							el( 'small', null, tab.desc )
						)
					)
				)
			),
			workspace === 'build'
				? el(
					'div',
					{ className: 'cfb-subtoolbar' },
					el( 'button', { type: 'button', className: 'cfb-btn', onClick: undo, disabled: ! history.current.length, title: S.undo }, '↶ ' + ( S.undo || 'Undo' ) ),
					el( 'button', { type: 'button', className: 'cfb-btn', onClick: redo, disabled: ! future.current.length, title: S.redo }, '↷ ' + ( S.redo || 'Redo' ) ),
					el( 'span', { className: 'cfb-subtoolbar-sep' } ),
					el( 'button', {
						type: 'button',
						className: 'cfb-btn' + ( schema.settings?.multistep && schema.steps.length > 1 ? ' cfb-btn--active' : '' ),
						onClick: toggleMultistep,
					}, S.multistep || 'Multistep' ),
					el( 'button', { type: 'button', className: 'cfb-btn', onClick: addStep }, S.addStep || 'Add step' ),
					el( 'button', { type: 'button', className: 'cfb-btn', onClick: addSection }, S.addSection || 'Add section' ),
					el( 'span', { className: 'cfb-subtoolbar-sep' } ),
					el( 'button', { type: 'button', className: 'cfb-btn cfb-btn--primary', onClick: () => setWorkspace( 'preview' ) }, '👁 ' + ( S.preview || 'Preview' ) )
				)
				: null,
			workspace === 'preview'
				? el(
					'div',
					{ className: 'cfb-preview-workspace' },
					el(
						'div',
						{ className: 'cfb-preview-workspace-head' },
						el( 'p', null, S.previewHint || 'This is how your form will look on the website.' ),
						el( DeviceToggle, { device, setDevice } )
					),
					el( StepsBar, Object.assign( {}, stepsBarProps, { compact: true } ) ),
					renderVisualPreview( Object.assign( {}, previewProps, { fullWidth: true, showCode: true } ) )
				)
				: null,
			workspace === 'logic' && window.CFB_LOGIC
				? el( 'div', { className: 'cfb-workspace-panel' }, el( window.CFB_LOGIC, logicProps ) )
				: null,
			workspace === 'settings' && window.CFB_SETTINGS
				? el( 'div', { className: 'cfb-workspace-panel' }, el( window.CFB_SETTINGS, settingsProps ) )
				: null,
			workspace === 'build'
				? el(
				'div',
				{ className: 'cfb-layout' },
				el(
					'aside',
					{ className: 'cfb-palette' },
					el( 'div', { className: 'cfb-col-head' }, S.palette || 'Fields' ),
					el(
						'div',
						{ className: 'cfb-palette-list' },
						( cfg.fieldTypes || [] ).map( ( item ) =>
							el(
								'div',
								{
									key: item.type,
									className: 'cfb-palette-item',
									draggable: true,
									onDragStart: onPaletteDragStart( item.type ),
									onDragEnd: clearDragState,
									onClick: () => addFieldToSection( activeStep, 0, item.type ),
								},
								el( 'span', { className: 'dashicons ' + item.icon } ),
								item.label
							)
						)
					)
				),
				el(
					'main',
					{ className: 'cfb-canvas-wrap' },
					el(
						'div',
						{ className: 'cfb-canvas-toolbar' },
						el( 'strong', null, S.canvas || 'Canvas' ),
						el( DeviceToggle, { device, setDevice } )
					),
					el( StepsBar, stepsBarProps ),
					el(
						'div',
						{ className: 'cfb-canvas-stage' },
						el(
							'div',
							{ className: 'cfb-canvas-device cfb-canvas-device--' + device },
							el(
								'div',
								{ className: 'cfb-canvas-card' },
								( currentStep.sections || [] ).map( ( section, sectionIndex ) =>
									el(
										'div',
										{ key: section.id, className: 'cfb-section' },
										section.title ? el( 'h3', { className: 'cfb-section-title' }, section.title ) : null,
										el(
											'div',
											{ className: 'cfb-fields-list' },
											! section.fields.length
												? el(
													'div',
													{
														className:
															'cfb-drop-slot cfb-drop-slot--empty' +
															( dropTarget === section.id + ':0' ? ' is-drag-over' : '' ),
														onDragOver: onDragOverSlot( section.id + ':0' ),
														onDragLeave: () => setDropTarget( null ),
														onDrop: onDropAtIndex( activeStep, sectionIndex, 0 ),
													},
													el( 'span', { className: 'cfb-drop-slot-label' }, S.dropHint || 'Drop fields here' )
												)
												: null,
											section.fields.map( ( field, fieldIndex ) =>
												el(
													Fragment,
													{ key: field.id },
													el( 'div', {
														className:
															'cfb-drop-slot' +
															( dropTarget === section.id + ':' + fieldIndex ? ' is-drag-over' : '' ),
														onDragOver: onDragOverSlot( section.id + ':' + fieldIndex ),
														onDragLeave: () => setDropTarget( null ),
														onDrop: onDropAtIndex( activeStep, sectionIndex, fieldIndex ),
													} ),
													el(
													'div',
													{
														className:
															'cfb-field-card' +
															( selectedId === field.id ? ' is-selected' : '' ) +
															( dragFieldId === field.id ? ' is-dragging' : '' ),
														draggable: true,
														onDragStart: onFieldDragStart( field.id ),
														onDragEnd: clearDragState,
														onClick: () => setSelectedId( field.id ),
													},
													el( 'span', { className: 'cfb-field-grip dashicons dashicons-move' } ),
													el(
														'div',
														{ className: 'cfb-field-body' },
														inlineEditId === field.id
															? el( 'input', {
																type: 'text',
																className: 'cfb-inline-edit',
																autoFocus: true,
																value: field.label || '',
																onBlur: ( e ) => {
																	updateField( field.id, { label: e.target.value } );
																	setInlineEditId( null );
																},
																onKeyDown: ( e ) => {
																	if ( e.key === 'Enter' ) {
																		updateField( field.id, { label: e.target.value } );
																		setInlineEditId( null );
																	}
																},
															} )
															: el(
																'div',
																{
																	className: 'cfb-field-label',
																	onDoubleClick: () => setInlineEditId( field.id ),
																	title: 'Double-click to edit label',
																},
																field.label || field.name,
																field.required ? el( 'span', { className: 'cfb-req' }, ' *' ) : null
															),
														el(
															'div',
															{ className: 'cfb-field-mock' },
															el( MockControl, { field } )
														)
													)
												)
												)
											),
											section.fields.length
												? el( 'div', {
													className:
														'cfb-drop-slot cfb-drop-slot--end' +
														( dropTarget === section.id + ':end' ? ' is-drag-over' : '' ),
													onDragOver: onDragOverSlot( section.id + ':end' ),
													onDragLeave: () => setDropTarget( null ),
													onDrop: onDropAtIndex( activeStep, sectionIndex, section.fields.length ),
												} )
												: null
										)
									)
								)
							)
						)
					),
				),
				el(
					'aside',
					{ className: 'cfb-inspector' },
					el( 'div', { className: 'cfb-col-head' }, S.inspector || 'Inspector' ),
					! selectedField
						? el( 'p', { className: 'cfb-inspector-empty' }, S.clickFieldHint || S.noField || 'Select a field' )
						: el(
							'div',
							{ className: 'cfb-inspector-form' },
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, S.label || 'Label' ),
								el( 'input', {
									type: 'text',
									value: selectedField.label || '',
									onChange: ( e ) => updateField( selectedField.id, { label: e.target.value } ),
								} )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, S.name || 'Name' ),
								el( 'input', {
									type: 'text',
									value: selectedField.name || '',
									onChange: ( e ) => updateField( selectedField.id, { name: e.target.value.replace( /[^a-z0-9\-_]/gi, '' ) } ),
								} )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, S.placeholder || 'Placeholder' ),
								el( 'input', {
									type: 'text',
									value: selectedField.placeholder || '',
									onChange: ( e ) => updateField( selectedField.id, { placeholder: e.target.value } ),
								} )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, S.help || 'Help' ),
								el( 'textarea', {
									rows: 2,
									value: selectedField.help || '',
									onChange: ( e ) => updateField( selectedField.id, { help: e.target.value } ),
								} )
							),
							el(
								'div',
								{ className: 'cfb-check-row' },
								el( 'input', {
									type: 'checkbox',
									id: 'cfb-req',
									checked: !! selectedField.required,
									onChange: ( e ) => updateField( selectedField.id, { required: e.target.checked } ),
								} ),
								el( 'label', { htmlFor: 'cfb-req' }, S.required || 'Required' )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, 'Min length' ),
								el( 'input', {
									type: 'number',
									value: selectedField.validation?.minLength || '',
									onChange: ( e ) => {
										const validation = clone( selectedField.validation || {} );
										validation.minLength = parseInt( e.target.value, 10 ) || undefined;
										updateField( selectedField.id, { validation } );
									},
								} )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, 'Max length' ),
								el( 'input', {
									type: 'number',
									value: selectedField.validation?.maxLength || '',
									onChange: ( e ) => {
										const validation = clone( selectedField.validation || {} );
										validation.maxLength = parseInt( e.target.value, 10 ) || undefined;
										updateField( selectedField.id, { validation } );
									},
								} )
							),
							el(
								'div',
								{ className: 'cfb-field-group' },
								el( 'label', null, 'Regex pattern' ),
								el( 'input', {
									type: 'text',
									value: selectedField.validation?.pattern || '',
									onChange: ( e ) => {
										const validation = clone( selectedField.validation || {} );
										validation.pattern = e.target.value;
										updateField( selectedField.id, { validation } );
									},
								} )
							),
							[ 'select', 'checkbox', 'radio' ].includes( selectedField.type )
								? el(
									'div',
									{ className: 'cfb-field-group' },
									el( 'label', null, S.options || 'Options' ),
									el(
										'div',
										{ className: 'cfb-options-list' },
										( selectedField.options || [] ).map( ( opt, oi ) =>
											el(
												'div',
												{ key: oi, className: 'cfb-option-row' },
												el( 'input', {
													type: 'text',
													value: opt.label || opt.value || '',
													onChange: ( e ) => {
														const options = clone( selectedField.options || [] );
														options[ oi ] = { label: e.target.value, value: e.target.value };
														updateField( selectedField.id, { options } );
													},
												} ),
												el( 'button', {
													type: 'button',
													className: 'cfb-btn cfb-btn--ghost',
													onClick: () => {
														const options = clone( selectedField.options || [] );
														options.splice( oi, 1 );
														updateField( selectedField.id, { options } );
													},
												}, '×' )
											)
										),
										el( 'button', {
											type: 'button',
											className: 'cfb-btn',
											onClick: () => {
												const options = clone( selectedField.options || [] );
												options.push( { label: 'New option', value: 'New option' } );
												updateField( selectedField.id, { options } );
											},
										}, S.addOption || 'Add option' )
									)
								)
								: null,
							el(
								'div',
								{ className: 'cfb-toolbar-group', style: { marginTop: 8 } },
								el( 'button', { type: 'button', className: 'cfb-btn', onClick: () => duplicateField( selectedField.id ) }, S.duplicate || 'Duplicate' ),
								el( 'button', { type: 'button', className: 'cfb-btn', onClick: () => removeField( selectedField.id ) }, S.delete || 'Delete' )
							)
						)
				)
			)
				: null
		);
	}

	function mount() {
		const root = document.getElementById( 'cfb-builder-root' );
		if ( ! root || ! wp.element ) return;

		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( el( BuilderApp ) );
		} else {
			wp.element.render( el( BuilderApp ), root );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}
} )();
