( function () {
	'use strict';

	const cfg = window.CF7VB_DASH || {};
	const restBase = ( cfg.restUrl || '' ).replace( /\/$/, '' );
	const nonce = cfg.nonce || '';

	function apiFetch( path, options ) {
		options = options || {};
		const headers = Object.assign(
			{ 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			options.headers || {}
		);
		return fetch( restBase + path, Object.assign( {}, options, { headers: headers } ) ).then( function ( res ) {
			if ( ! res.ok ) {
				return res.json().then( function ( err ) {
					throw new Error( err.message || 'Request failed' );
				} );
			}
			return res.json();
		} );
	}

	function showToast( message ) {
		let toast = document.querySelector( '.cf7vb-toast' );
		if ( ! toast ) {
			toast = document.createElement( 'div' );
			toast.className = 'cf7vb-toast';
			document.body.appendChild( toast );
		}
		toast.textContent = message;
		toast.classList.add( 'is-visible' );
		setTimeout( function () {
			toast.classList.remove( 'is-visible' );
		}, 2800 );
	}

	function badgeClass( status ) {
		return 'cf7vb-badge cf7vb-badge--' + ( status || 'read' );
	}

	function formatEventLabel( type, dataRaw ) {
		let data = {};
		try {
			data = typeof dataRaw === 'string' ? JSON.parse( dataRaw ) : dataRaw || {};
		} catch ( e ) {
			data = {};
		}
		if ( type === 'step_view' && typeof data.step !== 'undefined' ) {
			return ( cfg.strings && cfg.strings.stepView ) ? cfg.strings.stepView.replace( '%d', data.step + 1 ) : 'Step ' + ( data.step + 1 ) + ' viewed';
		}
		if ( type === 'field_focus' && data.field ) {
			return ( cfg.strings && cfg.strings.fieldFocus ) ? cfg.strings.fieldFocus.replace( '%s', data.field ) : 'Field: ' + data.field;
		}
		const labels = {
			view: cfg.strings && cfg.strings.eventView || 'Form viewed',
			start: cfg.strings && cfg.strings.eventStart || 'Form started',
			submit: cfg.strings && cfg.strings.eventSubmit || 'Form submitted',
			abandon: cfg.strings && cfg.strings.eventAbandon || 'Form abandoned',
		};
		return labels[ type ] || type;
	}

	/* ——— Entry detail modal ——— */
	const modal = document.getElementById( 'cf7vb-entry-modal' );
	if ( modal ) {
		const overlay = modal;
		const closeBtn = modal.querySelector( '.cf7vb-modal-close' );
		const bodyEl = modal.querySelector( '.cf7vb-modal-body' );
		const titleEl = modal.querySelector( '.cf7vb-modal-title' );
		const metaEl = modal.querySelector( '.cf7vb-modal-meta' );
		const statusSelect = modal.querySelector( '.cf7vb-status-select' );
		const saveStatusBtn = modal.querySelector( '.cf7vb-save-status' );
		let currentEntryId = 0;

		function closeModal() {
			overlay.classList.remove( 'is-open' );
			overlay.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'cf7vb-modal-open' );
			currentEntryId = 0;
		}

		function openModal() {
			overlay.classList.add( 'is-open' );
			overlay.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'cf7vb-modal-open' );
			closeBtn.focus();
		}

		function renderEntry( entry ) {
			titleEl.textContent = ( cfg.strings && cfg.strings.entryTitle ) ? cfg.strings.entryTitle.replace( '%d', entry.id ) : 'Entry #' + entry.id;

			const metaParts = [];
			if ( entry.form_title ) {
				metaParts.push( entry.form_title );
			}
			if ( entry.created_at ) {
				metaParts.push( entry.created_at );
			}
			if ( entry.device ) {
				metaParts.push( entry.device );
			}
			metaEl.innerHTML = metaParts.map( function ( p ) {
				return '<span>' + escapeHtml( p ) + '</span>';
			} ).join( '' );

			if ( statusSelect ) {
				statusSelect.value = entry.status || 'read';
			}

			let html = '<dl class="cf7vb-field-grid">';
			if ( entry.fields && entry.fields.length ) {
				entry.fields.forEach( function ( field ) {
					html += '<div class="cf7vb-field-row"><dt>' + escapeHtml( field.label ) + '</dt><dd>' + escapeHtml( field.value ) + '</dd></div>';
				} );
			} else {
				html += '<p class="cf7vb-empty">' + escapeHtml( ( cfg.strings && cfg.strings.noFields ) || 'No field data.' ) + '</p>';
			}
			html += '</dl>';

			if ( entry.referrer ) {
				html += '<p class="cf7vb-modal-referrer"><strong>' + escapeHtml( ( cfg.strings && cfg.strings.referrer ) || 'Referrer' ) + ':</strong> <a href="' + escapeHtml( entry.referrer ) + '" target="_blank" rel="noopener">' + escapeHtml( entry.referrer ) + '</a></p>';
			}

			bodyEl.innerHTML = html;
		}

		function escapeHtml( str ) {
			const div = document.createElement( 'div' );
			div.textContent = str == null ? '' : String( str );
			return div.innerHTML;
		}

		function showLoading() {
			bodyEl.innerHTML = '<div class="cf7vb-modal-loading"><span class="spinner is-active"></span><p>' + escapeHtml( ( cfg.strings && cfg.strings.loading ) || 'Loading…' ) + '</p></div>';
		}

		function loadEntry( id ) {
			currentEntryId = id;
			openModal();
			showLoading();
			apiFetch( '/entries/' + id )
				.then( renderEntry )
				.catch( function () {
					bodyEl.innerHTML = '<p class="cf7vb-empty">' + escapeHtml( ( cfg.strings && cfg.strings.loadError ) || 'Could not load entry.' ) + '</p>';
				} );
		}

		document.querySelectorAll( '.cf7vb-btn-view[data-entry-id]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				loadEntry( parseInt( btn.getAttribute( 'data-entry-id' ), 10 ) );
			} );
		} );

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closeModal );
		}

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && overlay.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );

		if ( saveStatusBtn && statusSelect ) {
			saveStatusBtn.addEventListener( 'click', function () {
				if ( ! currentEntryId ) {
					return;
				}
				saveStatusBtn.disabled = true;
				apiFetch( '/entries/' + currentEntryId, {
					method: 'POST',
					body: JSON.stringify( { status: statusSelect.value } ),
				} )
					.then( function () {
						showToast( ( cfg.strings && cfg.strings.statusSaved ) || 'Status updated.' );
						const row = document.querySelector( 'tr[data-entry-id="' + currentEntryId + '"]' );
						if ( row ) {
							const badge = row.querySelector( '.cf7vb-badge' );
							if ( badge ) {
								badge.className = badgeClass( statusSelect.value );
								badge.textContent = statusSelect.value;
							}
							row.classList.toggle( 'is-unread', statusSelect.value === 'unread' );
						}
					} )
					.catch( function () {
						showToast( ( cfg.strings && cfg.strings.statusError ) || 'Update failed.' );
					} )
					.finally( function () {
						saveStatusBtn.disabled = false;
					} );
			} );
		}
	}
} )();
