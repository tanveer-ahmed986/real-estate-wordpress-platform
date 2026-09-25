<?php
/**
 * Plugin Name:       PrimeEstate Core
 * Plugin URI:        https://primeestate.local
 * Description:       Business logic, data model, and REST API for the PrimeEstate real estate platform (custom post types, taxonomies, capabilities, search, inquiries, favorites, viewing requests). Presentation lives in the primeestate theme.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            PrimeEstate
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       primeestate
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PRIMEESTATE_CORE_VERSION', '1.0.0' );
define( 'PRIMEESTATE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRIMEESTATE_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload every PHP file under includes/, deepest-first-independent (each file
 * is responsible for its own guard clauses / hook registration on include).
 */
function primeestate_core_autoload_includes(): void {
	$includes_dir = PRIMEESTATE_CORE_PATH . 'includes';

	if ( ! is_dir( $includes_dir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $includes_dir, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			require_once $file->getPathname();
		}
	}
}
add_action( 'plugins_loaded', 'primeestate_core_autoload_includes' );

/**
 * Activation: includes/ files are normally required on 'plugins_loaded', which
 * has not fired yet inside an activation-hook callback, so CPTs/taxonomies are
 * not registered at this point in the request. We therefore manually require
 * includes/ now so the 'init' hooks those files register (CPT/taxonomy
 * registration, and the deferred activation-tasks runner below) exist for
 * the *next* time 'init' fires — but 'init' has already fired earlier in
 * *this* request, for every request path that actually activates a plugin
 * (wp-admin's plugins.php, or WP-CLI's `wp plugin activate`): WordPress
 * bootstraps fully, firing 'init' once early, well before the
 * activation-handling code for a plugin that *wasn't already active* runs
 * and `include_once`s this file for the first time. So both the rewrite-rule
 * flush AND `primeestate_core_activated` (taxonomy-term seeding —
 * `wp_insert_term()` silently no-ops against an unregistered taxonomy,
 * exactly the failure this caused) are deferred via an option flag to the
 * *next* 'init', at a priority after CPT/taxonomy registration (default
 * priority 10) has run. `tests/bootstrap.php` already defers
 * `primeestate_core_activate` onto 'init' for exactly this reason when
 * simulating activation under PHPUnit — this makes the plugin's own real
 * activation hook follow the same rule instead of only the test suite doing
 * so.
 */
function primeestate_core_activate(): void {
	primeestate_core_autoload_includes();

	update_option( 'primeestate_core_run_activation_tasks', '1' );
	update_option( 'primeestate_core_flush_rewrite_rules', '1' );
}
register_activation_hook( __FILE__, 'primeestate_core_activate' );

/**
 * Runs activation-time-only setup (role/capability creation, default
 * taxonomy term seeding, default page creation — see
 * includes/agents/agent-permissions.php, includes/taxonomies/*.php, and
 * includes/pages/default-pages.php for listeners) once, on the first 'init'
 * after activation, after CPT/taxonomy registration (also hooked to 'init',
 * at the default priority) has already run in the same request.
 */
function primeestate_core_maybe_run_activation_tasks(): void {
	if ( '1' === get_option( 'primeestate_core_run_activation_tasks' ) ) {
		do_action( 'primeestate_core_activated' );
		delete_option( 'primeestate_core_run_activation_tasks' );
	}
}
add_action( 'init', 'primeestate_core_maybe_run_activation_tasks', 20 );

/**
 * Flush rewrite rules once, on the first 'init' after activation, after CPT
 * and taxonomy registration (both hooked to 'init' at the default priority)
 * have run.
 */
function primeestate_core_maybe_flush_rewrite_rules(): void {
	if ( '1' === get_option( 'primeestate_core_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'primeestate_core_flush_rewrite_rules' );
	}
}
add_action( 'init', 'primeestate_core_maybe_flush_rewrite_rules', 20 );

/**
 * Deactivation: flush rewrite rules only. Data and roles/capabilities are
 * intentionally left intact — destructive cleanup belongs in uninstall.php,
 * gated by an explicit uninstall, not a mere deactivate.
 */
function primeestate_core_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'primeestate_core_deactivate' );
