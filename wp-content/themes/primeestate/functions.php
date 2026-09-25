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
 * `PRIMEESTATE_THEME_VERSION` alone, as every asset's cache-busting `?ver=`
 * query param, means a browser (or any downstream cache/CDN) keeps serving
 * a stale style.css/*.js indefinitely after any edit until someone
 * remembers to manually bump that constant — exactly the class of "invisible
 * without a real browser" bug this project's standing no-runtime caveat
 * warned about; it was only caught here because live visual verification
 * kept showing edits that didn't take effect. `filemtime()` on the actual
 * asset file makes the version — and therefore the cache — self-invalidate
 * on every change, with the constant kept only as a fallback for a path
 * that can't be read (e.g. a registered inline-script handle with no file).
 */
function primeestate_asset_version( string $relative_path ): string {
	$path = PRIMEESTATE_THEME_PATH . $relative_path;
	$mtime = file_exists( $path ) ? filemtime( $path ) : false;

	return $mtime ? (string) $mtime : PRIMEESTATE_THEME_VERSION;
}

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
