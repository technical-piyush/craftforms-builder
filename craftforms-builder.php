<?php
/**
 * Plugin Name:       CraftForms Builder
 * Description:       Professional drag-and-drop visual form builder for Contact Form 7 — multistep, conditional logic, entries, analytics, GDPR, and more.
 * Version:           1.0.0
 * Author:            Piyush Asthana
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  contact-form-7
 * Text Domain:       craftforms-builder
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CFB_VERSION', '1.0.0' );
define( 'CFB_PLUGIN_FILE', __FILE__ );
define( 'CFB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFB_DB_VERSION', '1.1.0' );
define( 'CFB_SCHEMA_META', '_cfb_schema' );

require_once CFB_PLUGIN_DIR . 'includes/class-cfb-activator.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-parser.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-compiler.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-conditional.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-validation.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-security.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-email.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-thankyou.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-autosave.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-analytics.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-export.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-submissions.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-rest-api.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-admin.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-frontend.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-plugin.php';

register_activation_hook( __FILE__, array( 'CFB_Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WPCF7' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'CraftForms Builder requires Contact Form 7 to be installed and active.', 'craftforms-builder' )
					);
				}
			);
			return;
		}
		CFB_Plugin::instance()->run();
	}
);
