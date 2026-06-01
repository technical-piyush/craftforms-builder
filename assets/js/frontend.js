( function () {
	'use strict';

	const globalCfg = window.CFB_FRONT || {};
	const strings = globalCfg.strings || {};
	const phoneCountries = globalCfg.phoneCountries || [];
	const defaultPhoneIso = globalCfg.defaultPhoneIso || 'US';

	function phoneIsoList() {
		if ( ! phoneCountries.length ) {
			return [ 'us' ];
		}
		return phoneCountries
			.map( function ( c ) {
				return ( c.iso || '' ).toLowerCase();
			} )
			.filter( Boolean );
	}

	function getItiInstance( input ) {
		if ( ! input ) {
			return null;
		}
		if ( input.cfbIti ) {
			return input.cfbIti;
		}
		if ( typeof jQuery !== 'undefined' ) {
			const stored = jQuery( input ).data( 'cfbIti' );
			if ( stored ) {
				return stored;
			}
		}
		return null;
	}

	function stripLeadingDial( national, dial ) {
		const n = String( national || '' ).replace( /\D/g, '' );
		const d = String( dial || '' ).replace( /\D/g, '' );
		if ( d && n.indexOf( d ) === 0 && n.length > d.length ) {
			return n.slice( d.length );
		}
		return n;
	}

	function getFullPhoneNumber( input, iti ) {
		if ( ! input ) {
			return '';
		}

		const instance = iti || getItiInstance( input );

		if ( instance && window.intlTelInputUtils && typeof instance.getNumber === 'function' ) {
			const e164 = instance.getNumber( window.intlTelInputUtils.numberFormat.E164 );
			if ( e164 ) {
				return e164;
			}
		}

		if ( instance && typeof instance.getNumber === 'function' ) {
			const num = instance.getNumber();
			if ( num && num.indexOf( '+' ) === 0 ) {
				return num;
			}
		}

		const wrap = input.closest( '.wpcf7-form-control-wrap' ) || input.parentElement;
		const dialInput = wrap ? wrap.querySelector( 'input.cfb-phone-dial-hidden' ) : null;
		let dial = dialInput ? String( dialInput.value ).replace( /\D/g, '' ) : '';

		if ( ! dial && instance && typeof instance.getSelectedCountryData === 'function' ) {
			const data = instance.getSelectedCountryData();
			dial = data && data.dialCode ? String( data.dialCode ) : '';
		}

		const raw = String( input.value || '' ).trim();
		if ( raw.indexOf( '+' ) === 0 ) {
			const digits = raw.replace( /\D/g, '' );
			return digits ? '+' + digits : raw;
		}

		let national = raw.replace( /\D/g, '' );
		national = stripLeadingDial( national, dial );

		if ( dial && national ) {
			return '+' + dial + national;
		}

		return raw;
	}

	function syncIntlPhonesForSubmit( form ) {
		const root = form || document;
		root.querySelectorAll( 'input.cfb-phone, input[type="tel"].cfb-phone' ).forEach( function ( input ) {
			const full = getFullPhoneNumber( input, getItiInstance( input ) );
			if ( full ) {
				input.value = full;
			}
		} );
	}

	function bindPhoneSubmitHandlers( form ) {
		if ( ! form || form.dataset.cfbPhoneSubmitBound === '1' ) {
			return;
		}
		form.dataset.cfbPhoneSubmitBound = '1';

		const run = function () {
			syncIntlPhonesForSubmit( form );
		};

		form.addEventListener( 'submit', run, true );
		form.addEventListener(
			'click',
			function ( ev ) {
				if ( ev.target.closest( 'input[type="submit"], button[type="submit"], .wpcf7-submit' ) ) {
					run();
				}
			},
			true
		);
	}

	function initSelect2Fields( form ) {
		if ( typeof jQuery === 'undefined' || ! jQuery.fn.select2 ) {
			return;
		}
		const $ = jQuery;
		const $parent = $( form ).closest( '.cfb-enhanced' );

		$( form )
			.find( 'select.cfb-country' )
			.each( function () {
				const $el = $( this );
				if ( $el.data( 'cfbSelect2' ) ) {
					return;
				}
				$el.data( 'cfbSelect2', true );
				$el.select2( {
					width: '100%',
					placeholder: strings.selectCountry || 'Select a country',
					allowClear: ! this.required,
					dropdownParent: $parent.length ? $parent : $( 'body' ),
				} );
				const row = $el.closest( 'p' );
				if ( row.length ) {
					row.addClass( 'cfb-field-row cfb-field-country' );
				}
			} );

		$( form )
			.find( 'select.cfb-multiselect' )
			.each( function () {
				const $el = $( this );
				if ( $el.data( 'cfbSelect2' ) ) {
					return;
				}
				$el.data( 'cfbSelect2', true );
				$el.select2( {
					width: '100%',
					placeholder: strings.multiselectHint || 'Choose one or more options',
					closeOnSelect: false,
					allowClear: true,
					dropdownParent: $parent.length ? $parent : $( 'body' ),
				} );
				const row = $el.closest( 'p' );
				if ( row.length ) {
					row.addClass( 'cfb-field-row cfb-field-multiselect' );
				}
			} );
	}

	function initIntlPhoneFields( form ) {
		if ( typeof window.intlTelInput !== 'function' ) {
			return;
		}

		const preferred = ( defaultPhoneIso || 'us' ).toLowerCase();
		const onlyCountries = phoneIsoList();

		form.querySelectorAll( 'input[type="tel"].cfb-phone, input.cfb-phone' ).forEach( function ( input ) {
			if ( input.dataset.cfbItiDone === '1' ) {
				return;
			}
			input.dataset.cfbItiDone = '1';

			const row = input.closest( 'p' );
			if ( row ) {
				row.classList.add( 'cfb-field-row', 'cfb-field-phone' );
			}

			const wrap = input.closest( '.wpcf7-form-control-wrap' );
			if ( wrap ) {
				wrap.classList.add( 'cfb-phone-wrap' );
			}

			const options = {
				initialCountry: preferred,
				separateDialCode: true,
				nationalMode: true,
				autoPlaceholder: 'aggressive',
				formatAsYouType: true,
			};

			if ( globalCfg.itiUtilsUrl ) {
				options.utilsScript = globalCfg.itiUtilsUrl;
			}

			if ( onlyCountries.length ) {
				options.onlyCountries = onlyCountries;
				options.countryOrder = [ preferred ];
			}

			const iti = window.intlTelInput( input, options );
			input.cfbIti = iti;

			if ( typeof jQuery !== 'undefined' ) {
				jQuery( input ).data( 'cfbIti', iti );
			}

			const dialHidden = document.createElement( 'input' );
			dialHidden.type = 'hidden';
			dialHidden.className = 'cfb-phone-dial-hidden';
			dialHidden.name = input.name + '_cfb_dial';
			dialHidden.tabIndex = -1;
			dialHidden.setAttribute( 'aria-hidden', 'true' );

			const syncDialHidden = function () {
				const data = iti.getSelectedCountryData();
				dialHidden.value = data && data.dialCode ? String( data.dialCode ) : '';
			};

			if ( wrap ) {
				wrap.appendChild( dialHidden );
			} else {
				input.insertAdjacentElement( 'afterend', dialHidden );
			}

			input.addEventListener( 'countrychange', syncDialHidden );
			input.addEventListener( 'input', syncDialHidden );
			syncDialHidden();

			if ( input.value && input.value.indexOf( '+' ) === 0 ) {
				iti.setNumber( input.value );
			}
		} );

		bindPhoneSubmitHandlers( form );
	}

	function getAppearance( schema ) {
		return ( schema && schema.settings && schema.settings.appearance ) || {};
	}

	function fixRequiredMarkers( form ) {
		form.querySelectorAll( 'p' ).forEach( function ( row ) {
			const label = row.querySelector( 'label' );
			if ( ! label ) {
				return;
			}
			const required = row.querySelector( 'abbr[title], .wpcf7-required' );
			if ( required && ! label.contains( required ) ) {
				label.appendChild( required );
			}
			row.classList.add( 'cfb-field-row' );
			if ( row.querySelector( 'select.cfb-multiselect, .wpcf7-checkbox.cfb-multiselect' ) ) {
				row.classList.add( 'cfb-field-multiselect' );
			}
			if ( row.querySelector( 'select.cfb-country' ) ) {
				row.classList.add( 'cfb-field-country' );
			}
			if ( row.querySelector( 'input.cfb-phone, input.cfb-phone-number' ) ) {
				row.classList.add( 'cfb-field-phone' );
			}
		} );
	}

	function stepDisplayTitle( step, index ) {
		const custom = step && step.title ? String( step.title ).trim() : '';
		if ( custom ) {
			return custom;
		}
		const panelTitle = step && step.panelTitle ? String( step.panelTitle ).trim() : '';
		if ( panelTitle ) {
			return panelTitle;
		}
		return ( strings.step || 'Step' ) + ' ' + ( index + 1 );
	}

	function parseSchema( wrapper ) {
		const raw = wrapper.getAttribute( 'data-cfb-schema' );
		if ( raw ) {
			try {
				return JSON.parse( raw );
			} catch ( e ) {
				// Fall through to inline JSON script.
			}
		}

		const inline = wrapper.querySelector( 'script.cfb-schema-json' );
		if ( inline && inline.textContent ) {
			try {
				return JSON.parse( inline.textContent );
			} catch ( e2 ) {
				return null;
			}
		}

		return null;
	}

	function getFormConfig( wrapper ) {
		const schema = parseSchema( wrapper );
		if ( ! schema ) {
			return null;
		}
		return {
			formId: wrapper.getAttribute( 'data-cfb-form' ) || '',
			schema: schema,
			restUrl: globalCfg.restUrl || '',
			nonce: globalCfg.nonce || '',
		};
	}

	function getSession() {
		const match = document.cookie.match( /cfb_session=([^;]+)/ );
		if ( match ) {
			return match[1];
		}
		const key = 'cfb_' + Math.random().toString( 36 ).slice( 2 );
		document.cookie = 'cfb_session=' + key + ';path=/;max-age=' + ( 30 * 86400 );
		return key;
	}

	let sessionKey = getSession();

	function track( cfg, type, data ) {
		if ( ! cfg.restUrl || ! cfg.formId ) {
			return;
		}
		fetch( cfg.restUrl + '/analytics/track', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
			body: JSON.stringify( {
				form_id: parseInt( cfg.formId, 10 ),
				event_type: type,
				event_data: data || {},
				session_key: sessionKey,
			} ),
		} ).catch( function () {} );
	}

	function getWrapName( wrap ) {
		return wrap.getAttribute( 'data-name' ) || '';
	}

	function getFieldRow( wrap ) {
		return (
			wrap.closest( 'p' ) ||
			wrap.closest( 'label' ) ||
			wrap.closest( '.cfb-field-row' ) ||
			wrap
		);
	}

	function getFieldValueFromRow( row ) {
		const phoneInp = row.querySelector( 'input.cfb-phone' );
		if ( phoneInp ) {
			const full = getFullPhoneNumber( phoneInp, getItiInstance( phoneInp ) );
			if ( full ) {
				return full;
			}
		}

		const inp = row.querySelector( 'input, textarea, select' );
		if ( ! inp ) {
			return '';
		}
		if ( inp.tagName === 'SELECT' && inp.multiple ) {
			return Array.from( inp.selectedOptions ).map( function ( o ) {
				return o.value;
			} );
		}
		if ( inp.type === 'checkbox' || inp.type === 'radio' ) {
			const name = inp.name;
			const checked = row.closest( 'form' ).querySelectorAll( '[name="' + name + '"]:checked' );
			return Array.from( checked ).map( function ( c ) { return c.value; } ).join( ', ' );
		}
		return inp.value || '';
	}

	function rowIsRequiredInvalid( row ) {
		const controls = row.querySelectorAll( 'input, textarea, select' );
		for ( let i = 0; i < controls.length; i++ ) {
			const el = controls[i];
			if ( el.type === 'hidden' || el.type === 'submit' ) {
				continue;
			}
			const required =
				el.required ||
				el.getAttribute( 'aria-required' ) === 'true' ||
				el.classList.contains( 'wpcf7-validates-as-required' );
			if ( ! required ) {
				continue;
			}
			if ( el.type === 'checkbox' || el.type === 'radio' ) {
				const any = row.closest( 'form' ).querySelector( '[name="' + el.name + '"]:checked' );
				if ( ! any ) {
					return true;
				}
			} else if ( ! String( el.value || '' ).trim() ) {
				return true;
			}
		}
		return false;
	}

	function validateStepPanel( panel ) {
		const rows = panel.querySelectorAll( 'p, .wpcf7-form-control-wrap' );
		let invalid = false;
		rows.forEach( function ( row ) {
			if ( rowIsRequiredInvalid( row ) ) {
				invalid = true;
				row.classList.add( 'cfb-step-invalid' );
			} else {
				row.classList.remove( 'cfb-step-invalid' );
			}
		} );
		return ! invalid;
	}

	function buildGroupsFromSchema( form, schema ) {
		const panels = schema.steps.map( function ( step, index ) {
			const panel = document.createElement( 'div' );
			panel.className = 'cfb-step-panel cfb-step-group';
			panel.setAttribute( 'data-cfb-step', step.id );
			panel.setAttribute( 'data-cfb-step-index', String( index ) );
			return panel;
		} );

		const nameToStep = {};
		schema.steps.forEach( function ( step, stepIndex ) {
			step.sections.forEach( function ( sec ) {
				sec.fields.forEach( function ( f ) {
					if ( f.name ) {
						nameToStep[ f.name ] = stepIndex;
					}
				} );
			} );
		} );

		form.querySelectorAll( '.wpcf7-form-control-wrap' ).forEach( function ( wrap ) {
			const name = getWrapName( wrap );
			if ( ! name || typeof nameToStep[ name ] === 'undefined' ) {
				return;
			}
			const row = getFieldRow( wrap );
			const panel = panels[ nameToStep[ name ] ];
			if ( panel && row && ! panel.contains( row ) ) {
				panel.appendChild( row );
			}
		} );

		const submit = form.querySelector( 'input[type="submit"]' );
		if ( submit && panels.length ) {
			const submitRow = submit.closest( 'p' ) || submit;
			panels[ panels.length - 1 ].appendChild( submitRow );
		}

		return panels.filter( function ( p ) {
			return p.children.length > 0;
		} );
	}

	function initMultistep( form, wrapper, cfg ) {
		const schema = cfg.schema;
		const isMultistep =
			schema.settings &&
			schema.settings.multistep &&
			schema.steps &&
			schema.steps.length > 1;

		if ( ! isMultistep ) {
			return;
		}

		if ( wrapper.dataset.cfbMultistepReady === '1' ) {
			return;
		}
		wrapper.dataset.cfbMultistepReady = '1';

		let panels = Array.from( form.querySelectorAll( '.cfb-step-group' ) );

		panels.sort( function ( a, b ) {
			const ai = parseInt( a.getAttribute( 'data-cfb-step-index' ), 10 );
			const bi = parseInt( b.getAttribute( 'data-cfb-step-index' ), 10 );
			if ( ! isNaN( ai ) && ! isNaN( bi ) ) {
				return ai - bi;
			}
			return 0;
		} );

		if ( ! panels.length ) {
			panels = buildGroupsFromSchema( form, schema );
			panels.forEach( function ( panel ) {
				form.insertBefore( panel, form.firstChild );
			} );
		}

		if ( ! panels.length ) {
			return;
		}

		const submit = form.querySelector( 'input[type="submit"]' );
		if ( submit ) {
			const submitRow = submit.closest( 'p' ) || submit;
			const lastPanel = panels[ panels.length - 1 ];
			if ( lastPanel && ! lastPanel.contains( submitRow ) ) {
				lastPanel.appendChild( submitRow );
			}
		}

		panels.forEach( function ( panel ) {
			panel.classList.add( 'cfb-step-panel' );
		} );

		let current = 0;
		const animated = schema.settings.animatedTransitions !== false;
		const app = getAppearance( schema );

		const progress = document.createElement( 'div' );
		progress.className = 'cfb-step-progress cfb-progress-style-' + ( app.progressStyle || 'pills' );
		progress.innerHTML =
			'<span class="cfb-step-label"></span>' +
			'<div class="cfb-step-progress-bar"><div class="cfb-step-progress-fill"></div></div>' +
			'<div class="cfb-step-dots"></div>' +
			'<div class="cfb-step-heading" aria-live="polite"></div>';
		const fill = progress.querySelector( '.cfb-step-progress-fill' );
		const label = progress.querySelector( '.cfb-step-label' );
		const dots = progress.querySelector( '.cfb-step-dots' );
		const heading = progress.querySelector( '.cfb-step-heading' );

		if ( app.showStepCounter === false ) {
			label.hidden = true;
		}
		if ( app.showStepDots === false ) {
			dots.hidden = true;
		}
		if ( app.showStepHeading === false ) {
			heading.hidden = true;
		}

		schema.steps.forEach( function ( step, i ) {
			if ( i >= panels.length ) {
				return;
			}
			const panel = panels[ i ];
			const panelTitle = panel.getAttribute( 'data-cfb-step-title' );
			if ( panelTitle ) {
				step.panelTitle = panelTitle;
			}
			const titleText = stepDisplayTitle( step, i );

			const dotWrap = document.createElement( 'div' );
			dotWrap.className = 'cfb-step-dot-wrap';

			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'cfb-step-dot';
			dot.textContent = String( i + 1 );
			dot.title = titleText;
			dot.setAttribute( 'aria-label', titleText );
			dot.addEventListener( 'click', function () {
				if ( i < current || validateStepPanel( panels[ current ] ) ) {
					goTo( i );
				}
			} );

			const dotLabel = document.createElement( 'span' );
			dotLabel.className = 'cfb-step-dot-label';
			dotLabel.textContent = titleText;

			dotWrap.appendChild( dot );
			dotWrap.appendChild( dotLabel );
			dots.appendChild( dotWrap );
		} );

		const nav = document.createElement( 'div' );
		nav.className = 'cfb-step-nav';
		const prevBtn = document.createElement( 'button' );
		prevBtn.type = 'button';
		prevBtn.className = 'cfb-prev';
		prevBtn.textContent = strings.prev || 'Previous';
		const nextBtn = document.createElement( 'button' );
		nextBtn.type = 'button';
		nextBtn.className = 'cfb-next';
		nextBtn.textContent = strings.next || 'Next';
		nav.appendChild( prevBtn );
		nav.appendChild( nextBtn );

		if ( schema.settings.saveContinue ) {
			const saveBtn = document.createElement( 'button' );
			saveBtn.type = 'button';
			saveBtn.className = 'cfb-save-draft';
			saveBtn.textContent = strings.saveContinue || 'Save & continue';
			saveBtn.addEventListener( 'click', function () {
				autosave( form, cfg );
				alert( strings.draftSaved || 'Progress saved.' );
			} );
			nav.insertBefore( saveBtn, nextBtn );
		}

		const existingProgress = wrapper.querySelector( '.cfb-step-progress' );
		const existingNav = wrapper.querySelector( '.cfb-step-nav' );
		if ( existingProgress ) {
			existingProgress.remove();
		}
		if ( existingNav ) {
			existingNav.remove();
		}

		wrapper.insertBefore( progress, wrapper.firstChild );
		wrapper.appendChild( nav );

		function goTo( index ) {
			index = Math.max( 0, Math.min( index, panels.length - 1 ) );
			current = index;

			panels.forEach( function ( panel, i ) {
				const active = i === current;
				panel.hidden = ! active;
				panel.classList.toggle( 'cfb-step-active', active );
				if ( animated && active ) {
					panel.classList.remove( 'cfb-animate-in' );
					void panel.offsetWidth;
					panel.classList.add( 'cfb-animate-in' );
				}
			} );

			dots.querySelectorAll( '.cfb-step-dot-wrap' ).forEach( function ( wrap, i ) {
				const d = wrap.querySelector( '.cfb-step-dot' );
				if ( d ) {
					d.classList.toggle( 'is-active', i === current );
					d.classList.toggle( 'is-done', i < current );
				}
				wrap.classList.toggle( 'is-active', i === current );
			} );

			const submit = form.querySelector( 'input[type="submit"]' );
			if ( submit ) {
				const submitRow = submit.closest( 'p' );
				if ( current === panels.length - 1 ) {
					submit.style.display = '';
					if ( submitRow ) {
						submitRow.style.display = '';
					}
				} else {
					submit.style.display = 'none';
					if ( submitRow ) {
						submitRow.style.display = 'none';
					}
				}
			}

			prevBtn.style.visibility = current === 0 ? 'hidden' : 'visible';
			prevBtn.disabled = current === 0;

			if ( current === panels.length - 1 ) {
				nextBtn.textContent = strings.submit || 'Submit';
				nextBtn.classList.add( 'cfb-next--submit' );
			} else {
				nextBtn.textContent = strings.next || 'Next';
				nextBtn.classList.remove( 'cfb-next--submit' );
			}

			fill.style.width = ( ( current + 1 ) / panels.length * 100 ) + '%';
			const step = schema.steps[ current ] || {};
			const titleText = stepDisplayTitle( step, current );
			const counter =
				( strings.step || 'Step' ) + ' ' + ( current + 1 ) +
				' ' + ( strings.of || 'of' ) + ' ' + panels.length;
			if ( ! label.hidden ) {
				if ( app.progressStyle === 'minimal' ) {
					label.innerHTML = '<strong>' + titleText + '</strong> · ' + counter;
				} else {
					label.innerHTML = counter + ( titleText ? ' · <strong>' + titleText + '</strong>' : '' );
				}
			}
			if ( heading && ! heading.hidden ) {
				heading.textContent = titleText;
			}

			track( cfg, 'step_view', { step: current } );
		}

		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) {
				goTo( current - 1 );
			}
		} );

		nextBtn.addEventListener( 'click', function () {
			if ( ! validateStepPanel( panels[ current ] ) ) {
				alert( strings.stepError || 'Please fill in the required fields before continuing.' );
				return;
			}
			if ( current < panels.length - 1 ) {
				goTo( current + 1 );
			} else {
				const submitBtn = form.querySelector( 'input[type="submit"]' );
				if ( submitBtn ) {
					submitBtn.click();
				}
			}
		} );

		goTo( 0 );
	}

	function findSchemaField( schema, name ) {
		let found = null;
		( schema.steps || [] ).forEach( function ( step ) {
			( step.sections || [] ).forEach( function ( sec ) {
				( sec.fields || [] ).forEach( function ( f ) {
					if ( f.name === name ) {
						found = f;
					}
				} );
			} );
		} );
		return found;
	}

	function autosave( form, cfg ) {
		if ( ! cfg.schema.settings || ! cfg.schema.settings.autosave ) {
			return;
		}
		const data = {};
		form.querySelectorAll( 'input, textarea, select' ).forEach( function ( el ) {
			if ( ! el.name || el.type === 'submit' ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				if ( ! data[ el.name ] ) {
					data[ el.name ] = [];
				}
				if ( el.checked ) {
					data[ el.name ].push( el.value );
				}
			} else if ( el.type === 'radio' ) {
				if ( el.checked ) {
					data[ el.name ] = el.value;
				}
			} else {
				data[ el.name ] = el.value;
			}
		} );
		localStorage.setItem( 'cfb_autosave_' + cfg.formId, JSON.stringify( data ) );
	}

	/**
	 * Legacy checkbox fallback for old forms (pre–select + Select2).
	 */
	function initMultiselectFields( form ) {
		form.querySelectorAll( 'select[multiple]:not(.cfb-multiselect)' ).forEach( function ( select ) {
			if ( select.dataset.cfbMultiselectDone === '1' ) {
				return;
			}
			select.dataset.cfbMultiselectDone = '1';

			const wrap = select.closest( '.wpcf7-form-control-wrap' ) || select.parentElement;
			if ( ! wrap ) {
				return;
			}

			const baseName = select.name.replace( /\[\]$/, '' );
			const list = document.createElement( 'div' );
			list.className = 'cfb-multiselect-fallback';
			list.setAttribute( 'role', 'group' );

			Array.from( select.options ).forEach( function ( opt, index ) {
				if ( ! opt.value && ! opt.text ) {
					return;
				}
				const label = document.createElement( 'label' );
				label.className = 'cfb-multiselect-option';
				const cb = document.createElement( 'input' );
				cb.type = 'checkbox';
				cb.name = baseName + '[]';
				cb.value = opt.value || opt.text;
				cb.checked = opt.selected;
				label.appendChild( cb );
				label.appendChild( document.createTextNode( ' ' + ( opt.text || opt.value ) ) );
				list.appendChild( label );

				cb.addEventListener( 'change', function () {
					opt.selected = cb.checked;
				} );
			} );

			select.classList.add( 'cfb-multiselect-native-hidden' );
			select.setAttribute( 'aria-hidden', 'true' );
			select.tabIndex = -1;
			wrap.appendChild( list );

			const row = select.closest( 'p' ) || wrap.closest( 'p' );
			if ( row ) {
				row.classList.add( 'cfb-field-row', 'cfb-field-multiselect' );
			}
		} );
	}

	function initRepeaters( form ) {
		form.querySelectorAll( '.cfb-repeater' ).forEach( function ( repeater ) {
			const rowsWrap = repeater.querySelector( '.cfb-repeater-rows' );
			const addBtn = repeater.querySelector( '.cfb-repeater-add' );
			if ( ! rowsWrap || ! addBtn ) {
				return;
			}

			const minRows = parseInt( repeater.getAttribute( 'data-min' ) || '1', 10 );
			const maxRows = parseInt( repeater.getAttribute( 'data-max' ) || '5', 10 );

			function visibleRows() {
				return Array.from( rowsWrap.querySelectorAll( '.cfb-repeater-row:not(.cfb-repeater-row--hidden)' ) );
			}

			function syncActions() {
				const visible = visibleRows();
				visible.forEach( function ( row, index ) {
					const removeBtn = row.querySelector( '.cfb-repeater-remove' );
					if ( removeBtn ) {
						removeBtn.style.display = visible.length > minRows ? '' : 'none';
					}
				} );
				addBtn.disabled = visible.length >= maxRows;
			}

			addBtn.addEventListener( 'click', function () {
				const hidden = rowsWrap.querySelector( '.cfb-repeater-row--hidden' );
				if ( ! hidden ) {
					return;
				}
				hidden.classList.remove( 'cfb-repeater-row--hidden' );
				syncActions();
			} );

			rowsWrap.addEventListener( 'click', function ( ev ) {
				const btn = ev.target.closest( '.cfb-repeater-remove' );
				if ( ! btn ) {
					return;
				}
				const row = btn.closest( '.cfb-repeater-row' );
				if ( ! row || visibleRows().length <= minRows ) {
					return;
				}
				row.querySelectorAll( 'input, textarea, select' ).forEach( function ( el ) {
					if ( el.type === 'checkbox' || el.type === 'radio' ) {
						el.checked = false;
					} else {
						el.value = '';
					}
				} );
				row.classList.add( 'cfb-repeater-row--hidden' );
				rowsWrap.appendChild( row );
				syncActions();
			} );

			syncActions();
		} );
	}

	function initEnhancedFields( form ) {
		initSelect2Fields( form );
		initIntlPhoneFields( form );
		initMultiselectFields( form );
	}

	function initForm( wrapper ) {
		const cfg = getFormConfig( wrapper );
		if ( ! cfg ) {
			return;
		}

		// Wrapper is injected inside the CF7 form, not around it.
		const form = wrapper.closest( 'form.wpcf7-form' );
		if ( ! form ) {
			return;
		}

		if ( wrapper.dataset.cfbFormReady === '1' ) {
			initEnhancedFields( form );
			return;
		}

		wrapper.dataset.cfbFormReady = '1';

		fixRequiredMarkers( form );
		initEnhancedFields( form );
		initRepeaters( form );
		track( cfg, 'view', {} );
		initMultistep( form, wrapper, cfg );

		document.addEventListener( 'wpcf7mailsent', function ( ev ) {
			if ( ev.detail && String( ev.detail.contactFormId ) === String( cfg.formId ) ) {
				localStorage.removeItem( 'cfb_autosave_' + cfg.formId );
				const ty = cfg.schema.settings.thankYou || {};
				if ( ev.detail.apiResponse && ev.detail.apiResponse.cfb_redirect ) {
					window.location.href = ev.detail.apiResponse.cfb_redirect;
				} else if ( ty.redirectUrl ) {
					window.location.href = ty.redirectUrl;
				}
			}
		} );
	}

	function boot() {
		document.querySelectorAll( '.cfb-enhanced' ).forEach( initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	document.addEventListener( 'wpcf7init', boot );
	document.addEventListener(
		'wpcf7submit',
		function ( ev ) {
			const form = ev.target;
			if ( form && form.classList && form.classList.contains( 'wpcf7-form' ) ) {
				syncIntlPhonesForSubmit( form );
			}
		},
		true
	);
	document.addEventListener( 'wpcf7submit', function () {
		setTimeout( boot, 100 );
	} );
} )();
