<?php
/**
 * Uninstall handler for PrimeEstate Core.
 *
 * Runs only when the plugin is deleted from wp-admin (never on deactivate),
 * per the standard WordPress uninstall contract. Deliberately left as a
 * guarded no-op scaffold until Phase 2 defines exactly what demo vs. real
 * data should be removed (see plugin/includes/cli/reset.php for the
 * equivalent _primeestate_demo-scoped cleanup used in development).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
