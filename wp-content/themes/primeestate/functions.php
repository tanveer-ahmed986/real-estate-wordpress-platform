<?php
/**
 * PrimeEstate theme bootstrap.
 *
 * Presentation-only: no business logic, data queries, or capability checks
 * here — those belong to the primeestate-core plugin. This file wires up
 * the theme support declarations and asset enqueueing implemented under
 * inc/, plus reusable template helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PRIMEESTATE_THEME_VERSION', '1.0.0' );
define( 'PRIMEESTATE_THEME_PATH', get_template_directory() );
define( 'PRIMEESTATE_THEME_URL', get_template_directory_uri() );

/**
 * Loads every PHP file under inc/ (theme setup, enqueueing, navigation,
 * breadcrumbs, accessibility, template helpers, archive rendering) and every
 * component's PHP file under components/{name}/ (PropertyCard, SearchForm,
 * FilterPanel, EmptyState, Map, …) — each component owns one directory with
 * one primary PHP file defining its `primeestate_render_*()` function.
 */
function primeestate_theme_autoload_includes(): void {
	foreach ( glob( PRIMEESTATE_THEME_PATH . '/inc/*.php' ) as $file ) {
		require_once $file;
	}

	foreach ( glob( PRIMEESTATE_THEME_PATH . '/components/*/*.php' ) as $file ) {
		require_once $file;
	}
}
primeestate_theme_autoload_includes();
