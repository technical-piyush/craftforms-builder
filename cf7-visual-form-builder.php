<?php
/**
 * Plugin Name:       CF7 Visual Form Builder
 * Description:       Professional drag-and-drop visual form builder for Contact Form 7 — multistep, conditional logic, entries, analytics, GDPR, and more.
 * Version:           1.3.0
 * Author:            Piyush Asthana
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Text Domain:       cf7-visual-form-builder
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CF7VB_VERSION', '1.3.0' );
define( 'CF7VB_PLUGIN_FILE', __FILE__ );
define( 'CF7VB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7VB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CF7VB_DB_VERSION', '1.1.0' );
define( 'CF7VB_SCHEMA_META', '_cf7vb_schema' );

require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-activator.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-parser.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-compiler.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-conditional.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-validation.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-security.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-email.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-thankyou.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-autosave.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-analytics.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-export.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-submissions.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-rest-api.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-admin.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-frontend.php';
require_once CF7VB_PLUGIN_DIR . 'includes/class-cf7vb-plugin.php';

register_activation_hook( __FILE__, array( 'CF7VB_Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WPCF7' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'CF7 Visual Form Builder requires Contact Form 7 to be installed and active.', 'cf7-visual-form-builder' )
					);
				}
			);
			return;
		}
		CF7VB_Plugin::instance()->run();
	}
);
