( function () {
	'use strict';

	const { createElement: el } = wp.element;
	const cfg = window.CFB_BUILDER || {};
	const L = cfg.strings || {};

	const COMPARES = [
		{ id: 'equals', label: L.cmpEquals || 'equals' },
		{ id: 'not_equals', label: L.cmpNotEquals || 'does not equal' },
		{ id: 'contains', label: L.cmpContains || 'contains' },
		{ id: 'not_contains', label: L.cmpNotContains || 'does not contain' },
		{ id: 'empty', label: L.cmpEmpty || 'is empty' },
		{ id: 'not_empty', label: L.cmpNotEmpty || 'is not empty' },
		{ id: 'greater', label: L.cmpGreater || 'greater than' },
		{ id: 'less', label: L.cmpLess || 'less than' },
	];

	const ACTIONS = [
		{ id: 'show', label: L.actShow || 'Show field' },
		{ id: 'hide', label: L.actHide || 'Hide field' },
		{ id: 'require', label: L.actRequire || 'Require field' },
		{ id: 'skip_step', label: L.actSkipStep || 'Skip step' },
		{ id: 'redirect', label: L.actRedirect || 'Redirect to URL' },
		{ id: 'conditional_email', label: L.actEmail || 'Send email' },
	];

	function fieldOptions( schema ) {
		const opts = [];
		( schema.steps || [] ).forEach( function ( step ) {
			( step.sections || [] ).forEach( function ( sec ) {
				( sec.fields || [] ).forEach( function ( f ) {
					if ( f.name && f.type !== 'submit' ) {
						opts.push( { value: f.name, label: f.label || f.name } );
					}
				} );
			} );
		} );
		return opts;
	}

	function stepOptions( schema ) {
		return ( schema.steps || [] ).map( function ( s, i ) {
			return { value: s.id, label: s.title || ( ( L.stepPlaceholder || 'Step %d' ).replace( '%d', String( i + 1 ) ) ) };
		} );
	}

	function needsValue( compare ) {
		return compare !== 'empty' && compare !== 'not_empty';
	}

	function ConditionRow( props ) {
		const { cond, ci, fields, onChange, onRemove, showRemove } = props;
		const hideValue = ! needsValue( cond.compare || 'equals' );

		return el(
			'div',
			{ className: 'cfb-logic-condition' },
			el(
				'select',
				{
					className: 'cfb-logic-select',
					value: cond.field || '',
					onChange: function ( e ) { onChange( ci, { field: e.target.value } ); },
				},
				el( 'option', { value: '' }, L.selectField || '— Select field —' ),
				fields.map( function ( f ) {
					return el( 'option', { key: f.value, value: f.value }, f.label );
				} )
			),
			el(
				'select',
				{
					className: 'cfb-logic-select cfb-logic-select--compare',
					value: cond.compare || 'equals',
					onChange: function ( e ) { onChange( ci, { compare: e.target.value } ); },
				},
				COMPARES.map( function ( c ) {
					return el( 'option', { key: c.id, value: c.id }, c.label );
				} )
			),
			hideValue
				? el( 'span', { className: 'cfb-logic-value-placeholder' }, '—' )
				: el( 'input', {
					type: 'text',
					className: 'cfb-logic-input',
					placeholder: L.conditionValue || 'Value',
					value: cond.value || '',
					onChange: function ( e ) { onChange( ci, { value: e.target.value } ); },
				} ),
			showRemove
				? el( 'button', {
					type: 'button',
					className: 'cfb-logic-icon-btn',
					title: L.remove || 'Remove',
					onClick: function () { onRemove( ci ); },
				}, el( 'span', { className: 'dashicons dashicons-no-alt' } ) )
				: null
		);
	}

	window.CFB_LOGIC = function LogicPanel( props ) {
		const { schema, pushHistory, clone } = props;
		const rules = schema.rules || [];
		const fields = fieldOptions( schema );
		const steps = stepOptions( schema );

		function updateRules( next ) {
			const s = clone( schema );
			s.rules = next;
			pushHistory( s );
		}

		function updateRule( ri, patch ) {
			updateRules( rules.map( function ( r, i ) {
				return i === ri ? Object.assign( {}, r, patch ) : r;
			} ) );
		}

		function addRule() {
			updateRules( rules.concat( [ {
				id: 'rule-' + Math.random().toString( 36 ).slice( 2, 8 ),
				enabled: true,
				operator: 'and',
				conditions: [ { field: fields[0] ? fields[0].value : '', compare: 'equals', value: '' } ],
				actions: [ { type: 'show', target: fields[0] ? fields[0].value : '' } ],
				groups: [],
			} ] ) );
		}

		function renderConditions( rule, ri, listKey ) {
			const list = rule[ listKey ] || [];
			return el(
				'div',
				{ className: 'cfb-logic-conditions' },
				list.map( function ( cond, ci ) {
					return el( ConditionRow, {
						key: ci,
						cond: cond,
						ci: ci,
						fields: fields,
						showRemove: list.length > 1,
						onChange: function ( index, patch ) {
							const next = clone( list );
							next[ index ] = Object.assign( {}, next[ index ], patch );
							updateRule( ri, { [ listKey ]: next } );
						},
						onRemove: function ( index ) {
							updateRule( ri, { [ listKey ]: list.filter( function ( _, i ) { return i !== index; } ) } );
						},
					} );
				} ),
				el(
					'button',
					{
						type: 'button',
						className: 'cfb-btn cfb-btn--ghost cfb-btn--sm',
						onClick: function () {
							const next = list.concat( [ { field: '', compare: 'equals', value: '' } ] );
							updateRule( ri, { [ listKey ]: next } );
						},
					},
					el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
					L.addCondition || 'Add condition'
				)
			);
		}

		function renderActions( rule, ri ) {
			return el(
				'div',
				{ className: 'cfb-logic-actions' },
				( rule.actions || [] ).map( function ( act, ai ) {
					const isUrl = act.type === 'redirect';
					const isEmail = act.type === 'conditional_email';
					const isStep = act.type === 'skip_step';

					return el(
						'div',
						{ key: ai, className: 'cfb-logic-action' },
						el(
							'select',
							{
								className: 'cfb-logic-select',
								value: act.type || 'show',
								onChange: function ( e ) {
									const acts = clone( rule.actions );
									acts[ ai ] = { type: e.target.value, target: acts[ ai ].target || '' };
									updateRule( ri, { actions: acts } );
								},
							},
							ACTIONS.map( function ( a ) {
								return el( 'option', { key: a.id, value: a.id }, a.label );
							} )
						),
						isUrl
							? el( 'input', {
								type: 'url',
								className: 'cfb-logic-input',
								placeholder: 'https://',
								value: act.url || '',
								onChange: function ( e ) {
									const acts = clone( rule.actions );
									acts[ ai ].url = e.target.value;
									updateRule( ri, { actions: acts } );
								},
							} )
							: isEmail
								? el( 'input', {
									type: 'email',
									className: 'cfb-logic-input',
									placeholder: L.emailPlaceholder || 'recipient@example.com',
									value: act.recipient || '',
									onChange: function ( e ) {
										const acts = clone( rule.actions );
										acts[ ai ].recipient = e.target.value;
										updateRule( ri, { actions: acts } );
									},
								} )
								: el(
									'select',
									{
										className: 'cfb-logic-select',
										value: act.target || '',
										onChange: function ( e ) {
											const acts = clone( rule.actions );
											acts[ ai ].target = e.target.value;
											updateRule( ri, { actions: acts } );
										},
									},
									el( 'option', { value: '' }, isStep ? ( L.selectStep || '— Select step —' ) : ( L.selectField || '— Select field —' ) ),
									( isStep ? steps : fields ).map( function ( o ) {
										return el( 'option', { key: o.value, value: o.value }, o.label );
									} )
								),
						( rule.actions || [] ).length > 1
							? el( 'button', {
								type: 'button',
								className: 'cfb-logic-icon-btn',
								onClick: function () {
									updateRule( ri, { actions: rule.actions.filter( function ( _, i ) { return i !== ai; } ) } );
								},
							}, el( 'span', { className: 'dashicons dashicons-trash' } ) )
							: null
					);
				} ),
				el(
					'button',
					{
						type: 'button',
						className: 'cfb-btn cfb-btn--ghost cfb-btn--sm',
						onClick: function () {
							updateRule( ri, { actions: ( rule.actions || [] ).concat( [ { type: 'show', target: '' } ] ) } );
						},
					},
					el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
					L.addAction || 'Add action'
				)
			);
		}

		return el(
			'div',
			{ className: 'cfb-logic-panel' },
			el(
				'header',
				{ className: 'cfb-panel-intro' },
				el( 'h2', null, L.logicTitle || 'Conditional logic' ),
				el( 'p', null, L.logicIntro || 'Create rules to show, hide, or require fields based on answers. Step titles from the Build tab are used for skip-step actions.' )
			),
			! fields.length
				? el(
					'div',
					{ className: 'cfb-logic-empty' },
					el( 'span', { className: 'dashicons dashicons-randomize' } ),
					el( 'p', null, L.logicNoFields || 'Add fields on the Build tab before creating rules.' )
				)
				: null,
			rules.length
				? el(
					'div',
					{ className: 'cfb-logic-rules' },
					rules.map( function ( rule, ri ) {
						return el(
							'article',
							{ key: rule.id, className: 'cfb-logic-rule' + ( rule.enabled === false ? ' is-disabled' : '' ) },
							el(
								'header',
								{ className: 'cfb-logic-rule-head' },
								el(
									'div',
									{ className: 'cfb-logic-rule-title' },
									el( 'span', { className: 'cfb-logic-rule-num' }, String( ri + 1 ) ),
									el( 'strong', null, ( L.ruleLabel || 'Rule %d' ).replace( '%d', String( ri + 1 ) ) )
								),
								el(
									'div',
									{ className: 'cfb-logic-rule-controls' },
									el(
										'label',
										{ className: 'cfb-logic-match' },
										el( 'span', null, L.match || 'Match' ),
										el(
											'select',
											{
												value: rule.operator || 'and',
												onChange: function ( e ) { updateRule( ri, { operator: e.target.value } ); },
											},
											el( 'option', { value: 'and' }, L.matchAll || 'ALL conditions' ),
											el( 'option', { value: 'or' }, L.matchAny || 'ANY condition' )
										)
									),
									el(
										'label',
										{ className: 'cfb-logic-enabled' },
										el( 'input', {
											type: 'checkbox',
											checked: rule.enabled !== false,
											onChange: function ( e ) { updateRule( ri, { enabled: e.target.checked } ); },
										} ),
										L.enabled || 'Active'
									),
									el( 'button', {
										type: 'button',
										className: 'cfb-logic-icon-btn cfb-logic-icon-btn--danger',
										title: L.deleteRule || 'Delete rule',
										onClick: function () {
											updateRules( rules.filter( function ( _, i ) { return i !== ri; } ) );
										},
									}, el( 'span', { className: 'dashicons dashicons-trash' } ) )
								)
							),
							el(
								'div',
								{ className: 'cfb-logic-rule-body' },
								el(
									'div',
									{ className: 'cfb-logic-block cfb-logic-block--if' },
									el( 'span', { className: 'cfb-logic-block-label' }, L.ifLabel || 'IF' ),
									renderConditions( rule, ri, 'conditions' )
								),
								( rule.groups || [] ).map( function ( group, gi ) {
									return el(
										'div',
										{ key: gi, className: 'cfb-logic-block cfb-logic-block--nested' },
										el(
											'div',
											{ className: 'cfb-logic-nested-head' },
											el( 'span', { className: 'cfb-logic-block-label' }, L.nestedGroup || 'AND ALSO' ),
											el(
												'select',
												{
													value: group.operator || 'or',
													onChange: function ( e ) {
														const groups = clone( rule.groups );
														groups[ gi ].operator = e.target.value;
														updateRule( ri, { groups: groups } );
													},
												},
												el( 'option', { value: 'and' }, L.matchAll || 'ALL' ),
												el( 'option', { value: 'or' }, L.matchAny || 'ANY' )
											),
											el( 'button', {
												type: 'button',
												className: 'cfb-logic-icon-btn',
												onClick: function () {
													updateRule( ri, { groups: rule.groups.filter( function ( _, i ) { return i !== gi; } ) } );
												},
											}, el( 'span', { className: 'dashicons dashicons-trash' } ) )
										),
										( group.conditions || [] ).map( function ( cond, ci ) {
											return el( ConditionRow, {
												key: ci,
												cond: cond,
												ci: ci,
												fields: fields,
												showRemove: ( group.conditions || [] ).length > 1,
												onChange: function ( index, patch ) {
													const groups = clone( rule.groups );
													const conds = clone( groups[ gi ].conditions );
													conds[ index ] = Object.assign( {}, conds[ index ], patch );
													groups[ gi ].conditions = conds;
													updateRule( ri, { groups: groups } );
												},
												onRemove: function ( index ) {
													const groups = clone( rule.groups );
													groups[ gi ].conditions = groups[ gi ].conditions.filter( function ( _, i ) {
														return i !== index;
													} );
													updateRule( ri, { groups: groups } );
												},
											} );
										} ),
										el( 'button', {
											type: 'button',
											className: 'cfb-btn cfb-btn--ghost cfb-btn--sm',
											onClick: function () {
												const groups = clone( rule.groups );
												groups[ gi ].conditions = ( groups[ gi ].conditions || [] ).concat( [
													{ field: '', compare: 'equals', value: '' },
												] );
												updateRule( ri, { groups: groups } );
											},
										}, L.addCondition || 'Add condition' )
									);
								} ),
								el( 'button', {
									type: 'button',
									className: 'cfb-btn cfb-btn--ghost cfb-btn--sm',
									onClick: function () {
										const groups = ( rule.groups || [] ).concat( [ {
											operator: 'or',
											conditions: [ { field: '', compare: 'equals', value: '' } ],
										} ] );
										updateRule( ri, { groups: groups } );
									},
								}, el( 'span', { className: 'dashicons dashicons-plus-alt2' } ), L.addNested || 'Add nested group' ),
								el(
									'div',
									{ className: 'cfb-logic-block cfb-logic-block--then' },
									el( 'span', { className: 'cfb-logic-block-label' }, L.thenLabel || 'THEN' ),
									renderActions( rule, ri )
								)
							)
						);
					} )
				)
				: el(
					'div',
					{ className: 'cfb-logic-empty' },
					el( 'span', { className: 'dashicons dashicons-randomize' } ),
					el( 'p', null, L.rulesEmpty || 'No rules yet. Add your first rule to control the form dynamically.' )
				),
			el(
				'footer',
				{ className: 'cfb-logic-footer' },
				el(
					'button',
					{
						type: 'button',
						className: 'cfb-btn cfb-btn--primary',
						onClick: addRule,
						disabled: ! fields.length,
					},
					el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
					L.addRule || 'Add rule'
				)
			)
		);
	};
} )();
