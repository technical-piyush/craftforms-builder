( function () {
	'use strict';

	const globalCfg = window.CF7VB_FRONT || {};
	const strings = globalCfg.strings || {};

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
			row.classList.add( 'cf7vb-field-row' );
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
		const raw = wrapper.getAttribute( 'data-cf7vb-schema' );
		if ( raw ) {
			try {
				return JSON.parse( raw );
			} catch ( e ) {
				// Fall through to inline JSON script.
			}
		}

		const inline = wrapper.querySelector( 'script.cf7vb-schema-json' );
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
			formId: wrapper.getAttribute( 'data-cf7vb-form' ) || '',
			schema: schema,
			restUrl: globalCfg.restUrl || '',
			nonce: globalCfg.nonce || '',
		};
	}

	function getSession() {
		const match = document.cookie.match( /cf7vb_session=([^;]+)/ );
		if ( match ) {
			return match[1];
		}
		const key = 'cf7vb_' + Math.random().toString( 36 ).slice( 2 );
		document.cookie = 'cf7vb_session=' + key + ';path=/;max-age=' + ( 30 * 86400 );
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
			wrap.closest( '.cf7vb-field-row' ) ||
			wrap
		);
	}

	function getFieldValueFromRow( row ) {
		const inp = row.querySelector( 'input, textarea, select' );
		if ( ! inp ) {
			return '';
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
				row.classList.add( 'cf7vb-step-invalid' );
			} else {
				row.classList.remove( 'cf7vb-step-invalid' );
			}
		} );
		return ! invalid;
	}

	function buildGroupsFromSchema( form, schema ) {
		const panels = schema.steps.map( function ( step, index ) {
			const panel = document.createElement( 'div' );
			panel.className = 'cf7vb-step-panel cf7vb-step-group';
			panel.setAttribute( 'data-cf7vb-step', step.id );
			panel.setAttribute( 'data-cf7vb-step-index', String( index ) );
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

		if ( wrapper.dataset.cf7vbMultistepReady === '1' ) {
			return;
		}
		wrapper.dataset.cf7vbMultistepReady = '1';

		let panels = Array.from( form.querySelectorAll( '.cf7vb-step-group' ) );

		panels.sort( function ( a, b ) {
			const ai = parseInt( a.getAttribute( 'data-cf7vb-step-index' ), 10 );
			const bi = parseInt( b.getAttribute( 'data-cf7vb-step-index' ), 10 );
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
			panel.classList.add( 'cf7vb-step-panel' );
		} );

		let current = 0;
		const animated = schema.settings.animatedTransitions !== false;
		const app = getAppearance( schema );

		const progress = document.createElement( 'div' );
		progress.className = 'cf7vb-step-progress cf7vb-progress-style-' + ( app.progressStyle || 'pills' );
		progress.innerHTML =
			'<span class="cf7vb-step-label"></span>' +
			'<div class="cf7vb-step-progress-bar"><div class="cf7vb-step-progress-fill"></div></div>' +
			'<div class="cf7vb-step-dots"></div>' +
			'<div class="cf7vb-step-heading" aria-live="polite"></div>';
		const fill = progress.querySelector( '.cf7vb-step-progress-fill' );
		const label = progress.querySelector( '.cf7vb-step-label' );
		const dots = progress.querySelector( '.cf7vb-step-dots' );
		const heading = progress.querySelector( '.cf7vb-step-heading' );

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
			const panelTitle = panel.getAttribute( 'data-cf7vb-step-title' );
			if ( panelTitle ) {
				step.panelTitle = panelTitle;
			}
			const titleText = stepDisplayTitle( step, i );

			const dotWrap = document.createElement( 'div' );
			dotWrap.className = 'cf7vb-step-dot-wrap';

			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'cf7vb-step-dot';
			dot.textContent = String( i + 1 );
			dot.title = titleText;
			dot.setAttribute( 'aria-label', titleText );
			dot.addEventListener( 'click', function () {
				if ( i < current || validateStepPanel( panels[ current ] ) ) {
					goTo( i );
				}
			} );

			const dotLabel = document.createElement( 'span' );
			dotLabel.className = 'cf7vb-step-dot-label';
			dotLabel.textContent = titleText;

			dotWrap.appendChild( dot );
			dotWrap.appendChild( dotLabel );
			dots.appendChild( dotWrap );
		} );

		const nav = document.createElement( 'div' );
		nav.className = 'cf7vb-step-nav';
		const prevBtn = document.createElement( 'button' );
		prevBtn.type = 'button';
		prevBtn.className = 'cf7vb-prev';
		prevBtn.textContent = strings.prev || 'Previous';
		const nextBtn = document.createElement( 'button' );
		nextBtn.type = 'button';
		nextBtn.className = 'cf7vb-next';
		nextBtn.textContent = strings.next || 'Next';
		nav.appendChild( prevBtn );
		nav.appendChild( nextBtn );

		if ( schema.settings.saveContinue ) {
			const saveBtn = document.createElement( 'button' );
			saveBtn.type = 'button';
			saveBtn.className = 'cf7vb-save-draft';
			saveBtn.textContent = strings.saveContinue || 'Save & continue';
			saveBtn.addEventListener( 'click', function () {
				autosave( form, cfg );
				alert( strings.draftSaved || 'Progress saved.' );
			} );
			nav.insertBefore( saveBtn, nextBtn );
		}

		const existingProgress = wrapper.querySelector( '.cf7vb-step-progress' );
		const existingNav = wrapper.querySelector( '.cf7vb-step-nav' );
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
				panel.classList.toggle( 'cf7vb-step-active', active );
				if ( animated && active ) {
					panel.classList.remove( 'cf7vb-animate-in' );
					void panel.offsetWidth;
					panel.classList.add( 'cf7vb-animate-in' );
				}
			} );

			dots.querySelectorAll( '.cf7vb-step-dot-wrap' ).forEach( function ( wrap, i ) {
				const d = wrap.querySelector( '.cf7vb-step-dot' );
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
				nextBtn.classList.add( 'cf7vb-next--submit' );
			} else {
				nextBtn.textContent = strings.next || 'Next';
				nextBtn.classList.remove( 'cf7vb-next--submit' );
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
		localStorage.setItem( 'cf7vb_autosave_' + cfg.formId, JSON.stringify( data ) );
	}

	function initForm( wrapper ) {
		if ( wrapper.dataset.cf7vbFormReady === '1' ) {
			return;
		}

		const cfg = getFormConfig( wrapper );
		if ( ! cfg ) {
			return;
		}

		// Wrapper is injected inside the CF7 form, not around it.
		const form = wrapper.closest( 'form.wpcf7-form' );
		if ( ! form ) {
			return;
		}

		wrapper.dataset.cf7vbFormReady = '1';

		fixRequiredMarkers( form );
		track( cfg, 'view', {} );
		initMultistep( form, wrapper, cfg );

		document.addEventListener( 'wpcf7mailsent', function ( ev ) {
			if ( ev.detail && String( ev.detail.contactFormId ) === String( cfg.formId ) ) {
				localStorage.removeItem( 'cf7vb_autosave_' + cfg.formId );
				const ty = cfg.schema.settings.thankYou || {};
				if ( ev.detail.apiResponse && ev.detail.apiResponse.cf7vb_redirect ) {
					window.location.href = ev.detail.apiResponse.cf7vb_redirect;
				} else if ( ty.redirectUrl ) {
					window.location.href = ty.redirectUrl;
				}
			}
		} );
	}

	function boot() {
		document.querySelectorAll( '.cf7vb-enhanced' ).forEach( initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	document.addEventListener( 'wpcf7init', boot );
	document.addEventListener( 'wpcf7submit', function () {
		setTimeout( boot, 100 );
	} );
} )();
