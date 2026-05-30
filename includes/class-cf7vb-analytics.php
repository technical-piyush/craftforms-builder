<?php
/**
 * Analytics events and dashboard stats.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Analytics {

	const CACHE_GROUP = 'cf7vb_analytics';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 21 );
	}

	/**
	 * Cache invalidation token. Changing it busts every cached read for the analytics table.
	 *
	 * @return string
	 */
	private static function cache_token() {
		$token = wp_cache_get( 'last_changed', self::CACHE_GROUP );
		if ( false === $token ) {
			$token = microtime();
			wp_cache_set( 'last_changed', $token, self::CACHE_GROUP );
		}
		return $token;
	}

	public static function menu() {
		add_submenu_page(
			'wpcf7',
			__( 'Form Analytics', 'cf7-visual-form-builder' ),
			__( 'Analytics', 'cf7-visual-form-builder' ),
			'wpcf7_read_contact_forms',
			'cf7vb-analytics',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $type    Event type.
	 * @param array  $data    Event payload.
	 * @param string $session Session key.
	 */
	public static function track( $form_id, $type, $data = array(), $session = '' ) {
		global $wpdb;
		$db = $wpdb;

		$db->insert(
			$db->prefix . 'cf7vb_analytics',
			array(
				'form_id'     => absint( $form_id ),
				'event_type'  => sanitize_key( $type ),
				'event_data'  => wp_json_encode( $data ),
				'session_key' => sanitize_text_field( $session ),
				'device'      => self::device(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		wp_cache_delete( 'last_changed', self::CACHE_GROUP );
	}

	/**
	 * @param int $form_id Form ID (0 = all).
	 * @return array
	 */
	public static function get_stats( $form_id = 0 ) {
		global $wpdb;
		$db = $wpdb;

		$form_id   = absint( $form_id );
		$cache_key = 'stats_' . $form_id . '_' . self::cache_token();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( $form_id ) {
			$event_rows = $db->get_results( $db->prepare( "SELECT event_type, COUNT(*) AS cnt FROM {$db->prefix}cf7vb_analytics WHERE form_id = %d GROUP BY event_type", $form_id ), ARRAY_A );
			$submits = (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->prefix}cf7vb_entries WHERE form_id = %d", $form_id ) );
			$devices = $db->get_results( $db->prepare( "SELECT device, COUNT(*) as cnt FROM {$db->prefix}cf7vb_entries WHERE form_id = %d GROUP BY device", $form_id ), ARRAY_A );
			$top_fields = $db->get_results( $db->prepare( "SELECT event_data, COUNT(*) as cnt FROM {$db->prefix}cf7vb_analytics WHERE form_id = %d AND event_type = 'field_focus' GROUP BY event_data ORDER BY cnt DESC LIMIT 10", $form_id ), ARRAY_A );
		} else {
			$event_rows = $db->get_results( "SELECT event_type, COUNT(*) AS cnt FROM {$db->prefix}cf7vb_analytics GROUP BY event_type", ARRAY_A );
			$submits = (int) $db->get_var( "SELECT COUNT(*) FROM {$db->prefix}cf7vb_entries" );
			$devices = $db->get_results( "SELECT device, COUNT(*) as cnt FROM {$db->prefix}cf7vb_entries GROUP BY device", ARRAY_A );
			$top_fields = $db->get_results( "SELECT event_data, COUNT(*) as cnt FROM {$db->prefix}cf7vb_analytics WHERE event_type = 'field_focus' GROUP BY event_data ORDER BY cnt DESC LIMIT 10", ARRAY_A );
		}

		$event_counts = array();
		foreach ( (array) $event_rows as $event_row ) {
			$event_counts[ $event_row['event_type'] ] = (int) $event_row['cnt'];
		}

		$views       = isset( $event_counts['view'] ) ? $event_counts['view'] : 0;
		$starts      = isset( $event_counts['start'] ) ? $event_counts['start'] : 0;
		$abandoned   = isset( $event_counts['abandon'] ) ? $event_counts['abandon'] : 0;
		$field_focus = isset( $event_counts['field_focus'] ) ? $event_counts['field_focus'] : 0;
		$step_views  = isset( $event_counts['step_view'] ) ? $event_counts['step_view'] : 0;

		$conversion = $views > 0 ? round( ( $submits / $views ) * 100, 1 ) : 0;

		$stats = array(
			'views'       => $views,
			'starts'      => $starts,
			'submissions' => $submits,
			'abandoned'   => $abandoned,
			'field_focus' => $field_focus,
			'step_views'  => $step_views,
			'conversion'  => $conversion,
			'devices'     => $devices ? $devices : array(),
			'top_fields'  => $top_fields ? $top_fields : array(),
		);

		wp_cache_set( $cache_key, $stats, self::CACHE_GROUP );

		return $stats;
	}

	/**
	 * @param int $form_id Form ID (0 = all).
	 * @param int $limit   Max rows.
	 * @return array
	 */
	public static function get_recent_events( $form_id = 0, $limit = 8 ) {
		global $wpdb;
		$db = $wpdb;

		$form_id = absint( $form_id );
		$limit   = max( 1, min( 20, (int) $limit ) );

		$cache_key = 'recent_' . $form_id . '_' . $limit . '_' . self::cache_token();
		$rows      = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $rows ) {
			if ( $form_id ) {
				$rows = $db->get_results(
					$db->prepare(
						"SELECT event_type, event_data, device, created_at FROM {$db->prefix}cf7vb_analytics WHERE form_id = %d ORDER BY id DESC LIMIT %d",
						$form_id,
						$limit
					)
				);
			} else {
				$rows = $db->get_results(
					$db->prepare(
						"SELECT event_type, event_data, device, created_at FROM {$db->prefix}cf7vb_analytics ORDER BY id DESC LIMIT %d",
						$limit
					)
				);
			}
			wp_cache_set( $cache_key, $rows, self::CACHE_GROUP );
		}

		return $rows ? $rows : array();
	}

	/**
	 * @return string
	 */
	private static function device() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		if ( strpos( $ua, 'mobile' ) !== false ) {
			return 'mobile';
		}
		if ( strpos( $ua, 'tablet' ) !== false ) {
			return 'tablet';
		}
		return 'desktop';
	}

	public static function render_page() {
		$form_id = absint( filter_input( INPUT_GET, 'form_id', FILTER_VALIDATE_INT ) );
		$stats   = self::get_stats( $form_id );
		$forms   = WPCF7_ContactForm::find();
		$recent  = self::get_recent_events( $form_id, 8 );

		$max_device = 1;
		foreach ( $stats['devices'] as $row ) {
			$max_device = max( $max_device, (int) $row['cnt'] );
		}

		$max_field = 1;
		foreach ( $stats['top_fields'] as $row ) {
			$max_field = max( $max_field, (int) $row['cnt'] );
		}

		$funnel_max = max( 1, (int) $stats['views'] );
		$funnel     = array(
			array(
				'label' => __( 'Views', 'cf7-visual-form-builder' ),
				'value' => (int) $stats['views'],
			),
			array(
				'label' => __( 'Started', 'cf7-visual-form-builder' ),
				'value' => (int) $stats['starts'],
			),
			array(
				'label' => __( 'Submissions', 'cf7-visual-form-builder' ),
				'value' => (int) $stats['submissions'],
			),
		);
		?>
		<div class="wrap cf7vb-dash cf7vb-analytics-wrap">
			<header class="cf7vb-dash-header">
				<div>
					<h1><?php esc_html_e( 'Form Analytics', 'cf7-visual-form-builder' ); ?></h1>
					<p><?php esc_html_e( 'Track views, engagement, conversions, and device usage.', 'cf7-visual-form-builder' ); ?></p>
				</div>
				<div class="cf7vb-dash-actions">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cf7vb-entries' ) ); ?>"><?php esc_html_e( 'View entries', 'cf7-visual-form-builder' ); ?></a>
				</div>
			</header>

			<div class="cf7vb-panel">
				<div class="cf7vb-filter-bar">
					<form method="get">
						<input type="hidden" name="page" value="cf7vb-analytics" />
						<select name="form_id">
							<option value="0"><?php esc_html_e( 'All forms', 'cf7-visual-form-builder' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<option value="<?php echo esc_attr( (string) $form->id() ); ?>" <?php selected( $form_id, $form->id() ); ?>><?php echo esc_html( $form->title() ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Apply', 'cf7-visual-form-builder' ), 'secondary', '', false ); ?>
					</form>
				</div>
			</div>

			<div class="cf7vb-kpi-grid">
				<?php
				$cards = array(
					__( 'Views', 'cf7-visual-form-builder' )           => $stats['views'],
					__( 'Started', 'cf7-visual-form-builder' )        => $stats['starts'],
					__( 'Submissions', 'cf7-visual-form-builder' )      => $stats['submissions'],
					__( 'Conversion', 'cf7-visual-form-builder' )     => $stats['conversion'] . '%',
					__( 'Abandoned', 'cf7-visual-form-builder' )      => $stats['abandoned'],
					__( 'Field focus', 'cf7-visual-form-builder' )    => $stats['field_focus'],
				);
				foreach ( $cards as $label => $val ) :
					?>
					<div class="cf7vb-stat-card">
						<span class="cf7vb-stat-label"><?php echo esc_html( $label ); ?></span>
						<p class="cf7vb-stat-value"><?php echo esc_html( (string) $val ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cf7vb-analytics-grid">
				<div class="cf7vb-panel">
					<div class="cf7vb-panel-head">
						<h2><?php esc_html_e( 'Conversion funnel', 'cf7-visual-form-builder' ); ?></h2>
					</div>
					<div class="cf7vb-funnel">
						<?php foreach ( $funnel as $step ) : ?>
							<?php $pct = $funnel_max > 0 ? round( ( $step['value'] / $funnel_max ) * 100 ) : 0; ?>
							<div class="cf7vb-funnel-step">
								<span><?php echo esc_html( $step['label'] ); ?></span>
								<div class="cf7vb-funnel-bar"><i style="width:<?php echo esc_attr( (string) max( 4, $pct ) ); ?>%"></i></div>
								<em><?php echo esc_html( (string) $step['value'] ); ?></em>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="cf7vb-panel">
					<div class="cf7vb-panel-head">
						<h2><?php esc_html_e( 'Recent activity', 'cf7-visual-form-builder' ); ?></h2>
					</div>
					<?php if ( empty( $recent ) ) : ?>
						<div class="cf7vb-empty">
							<span class="dashicons dashicons-chart-bar"></span>
							<p><?php esc_html_e( 'No events recorded yet.', 'cf7-visual-form-builder' ); ?></p>
						</div>
					<?php else : ?>
						<ul class="cf7vb-activity-list">
							<?php
							foreach ( $recent as $event ) :
								$type = sanitize_key( $event->event_type );
								$icon = 'visibility';
								if ( 'submit' === $type ) {
									$icon = 'yes-alt';
								} elseif ( 'abandon' === $type ) {
									$icon = 'dismiss';
								} elseif ( 'field_focus' === $type || 'step_view' === $type ) {
									$icon = 'admin-generic';
								}
								$label = $type;
								$data  = json_decode( $event->event_data, true );
								if ( 'step_view' === $type && is_array( $data ) && isset( $data['step'] ) ) {
									/* translators: %d: step number */
									$label = sprintf( __( 'Step %d viewed', 'cf7-visual-form-builder' ), (int) $data['step'] + 1 );
								} elseif ( 'field_focus' === $type && is_array( $data ) && isset( $data['field'] ) ) {
									/* translators: %s: field name */
									$label = sprintf( __( 'Field: %s', 'cf7-visual-form-builder' ), $data['field'] );
								} else {
									$label = ucfirst( str_replace( '_', ' ', $type ) );
								}
								?>
								<li>
									<span class="cf7vb-activity-icon"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span></span>
									<span>
										<strong><?php echo esc_html( $label ); ?></strong><br />
										<time><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->created_at ) ); ?></time>
										<?php if ( ! empty( $event->device ) ) : ?>
											— <?php echo esc_html( ucfirst( $event->device ) ); ?>
										<?php endif; ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="cf7vb-panel">
					<div class="cf7vb-panel-head">
						<h2><?php esc_html_e( 'Devices', 'cf7-visual-form-builder' ); ?></h2>
					</div>
					<?php if ( empty( $stats['devices'] ) ) : ?>
						<div class="cf7vb-empty"><p><?php esc_html_e( 'No device data yet.', 'cf7-visual-form-builder' ); ?></p></div>
					<?php else : ?>
						<ul class="cf7vb-bar-list">
							<?php foreach ( $stats['devices'] as $row ) : ?>
								<?php $pct = round( ( (int) $row['cnt'] / $max_device ) * 100 ); ?>
								<li>
									<span><?php echo esc_html( ucfirst( $row['device'] ? $row['device'] : 'unknown' ) ); ?></span>
									<span class="cf7vb-bar-track"><span class="cf7vb-bar-fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
									<strong><?php echo esc_html( (string) $row['cnt'] ); ?></strong>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="cf7vb-panel">
					<div class="cf7vb-panel-head">
						<h2><?php esc_html_e( 'Top field interactions', 'cf7-visual-form-builder' ); ?></h2>
					</div>
					<?php if ( empty( $stats['top_fields'] ) ) : ?>
						<div class="cf7vb-empty"><p><?php esc_html_e( 'No field interaction data yet.', 'cf7-visual-form-builder' ); ?></p></div>
					<?php else : ?>
						<ul class="cf7vb-bar-list">
							<?php
							foreach ( $stats['top_fields'] as $row ) :
								$d    = json_decode( $row['event_data'], true );
								$name = is_array( $d ) && isset( $d['field'] ) ? $d['field'] : (string) $row['event_data'];
								$pct  = round( ( (int) $row['cnt'] / $max_field ) * 100 );
								?>
								<li>
									<span><?php echo esc_html( $name ); ?></span>
									<span class="cf7vb-bar-track"><span class="cf7vb-bar-fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
									<strong><?php echo esc_html( (string) $row['cnt'] ); ?></strong>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
