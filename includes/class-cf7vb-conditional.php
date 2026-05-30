<?php
/**
 * Conditional logic evaluation (server-side).
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Conditional {

	/**
	 * Evaluate rule group against posted data.
	 *
	 * @param array $group    Rule or group with operator + conditions|groups.
	 * @param array $posted   Posted field values.
	 * @return bool
	 */
	public static function evaluate_group( $group, $posted ) {
		if ( empty( $group['enabled'] ) && isset( $group['enabled'] ) ) {
			return false;
		}

		$operator = isset( $group['operator'] ) ? strtolower( $group['operator'] ) : 'and';
		$results  = array();

		if ( ! empty( $group['groups'] ) && is_array( $group['groups'] ) ) {
			foreach ( $group['groups'] as $nested ) {
				$results[] = self::evaluate_group( $nested, $posted );
			}
		}

		if ( ! empty( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
			foreach ( $group['conditions'] as $cond ) {
				$results[] = self::evaluate_condition( $cond, $posted );
			}
		}

		if ( empty( $results ) ) {
			return true;
		}

		if ( 'or' === $operator ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * @param array $cond   Single condition.
	 * @param array $posted Posted data.
	 * @return bool
	 */
	public static function evaluate_condition( $cond, $posted ) {
		$field   = isset( $cond['field'] ) ? $cond['field'] : '';
		$compare = isset( $cond['compare'] ) ? $cond['compare'] : 'equals';
		$value   = isset( $cond['value'] ) ? $cond['value'] : '';

		if ( '' === $field ) {
			return false;
		}

		$actual = isset( $posted[ $field ] ) ? $posted[ $field ] : '';
		if ( is_array( $actual ) ) {
			$actual = implode( ', ', $actual );
		}
		$actual = (string) $actual;

		switch ( $compare ) {
			case 'not_equals':
				return $actual !== (string) $value;
			case 'contains':
				return false !== stripos( $actual, (string) $value );
			case 'not_contains':
				return false === stripos( $actual, (string) $value );
			case 'empty':
				return '' === trim( $actual );
			case 'not_empty':
				return '' !== trim( $actual );
			case 'greater':
				return floatval( $actual ) > floatval( $value );
			case 'less':
				return floatval( $actual ) < floatval( $value );
			case 'equals':
			default:
				return $actual === (string) $value;
		}
	}

	/**
	 * Apply email/redirect actions for matching rules.
	 *
	 * @param array $schema   Form schema.
	 * @param array $posted   Posted data.
	 * @return array{redirect:string,skip_steps:array,mail_overrides:array}
	 */
	public static function resolve_actions( $schema, $posted ) {
		$out = array(
			'redirect'       => '',
			'skip_steps'     => array(),
			'mail_overrides' => array(),
		);

		if ( empty( $schema['rules'] ) ) {
			return $out;
		}

		foreach ( $schema['rules'] as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( ! self::evaluate_group( $rule, $posted ) ) {
				continue;
			}
			foreach ( (array) ( $rule['actions'] ?? array() ) as $action ) {
				$type = isset( $action['type'] ) ? $action['type'] : '';
				switch ( $type ) {
					case 'redirect':
						if ( ! empty( $action['url'] ) ) {
							$out['redirect'] = esc_url_raw( $action['url'] );
						}
						break;
					case 'skip_step':
						if ( ! empty( $action['target'] ) ) {
							$out['skip_steps'][] = $action['target'];
						}
						break;
					case 'send_mail':
					case 'conditional_email':
						$out['mail_overrides'][] = $action;
						break;
				}
			}
		}

		return $out;
	}
}
