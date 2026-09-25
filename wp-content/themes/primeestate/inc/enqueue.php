<?php
/**
 * Base asset enqueueing, the conditional-loading mechanism (Phase 2), and
 * (T044) the map/search script registrations that use it — kept to the
 * property archive only for now; a single-property page will add its own
 * `is_singular( 'property' )` condition for the map when that template is
 * built in a later phase.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_enqueue_base_assets(): void {
	wp_enqueue_style( 'primeestate-style', get_stylesheet_uri(), array(), primeestate_asset_version( '/style.css' ) );
}
add_action( 'wp_enqueue_scripts', 'primeestate_enqueue_base_assets' );

/**
 * @var array<string, array{handle: string, src: string, deps: string[], condition: callable}>
 */
$GLOBALS['primeestate_conditional_assets'] = array();

/**
 * Registers a script/style that should only load when `$condition()`
 * returns true for the current request (e.g. only on the property archive,
 * or only on a single property page) — avoids shipping map/comparison JS
 * to every page.
 */
function primeestate_register_conditional_asset( string $handle, string $src, array $deps, callable $condition, string $type = 'script', string $version = PRIMEESTATE_THEME_VERSION ): void {
	$GLOBALS['primeestate_conditional_assets'][] = compact( 'handle', 'src', 'deps', 'condition', 'type', 'version' );
}

function primeestate_enqueue_conditional_assets(): void {
	foreach ( $GLOBALS['primeestate_conditional_assets'] as $asset ) {
		if ( ! call_user_func( $asset['condition'] ) ) {
			continue;
		}

		if ( 'style' === $asset['type'] ) {
			wp_enqueue_style( $asset['handle'], $asset['src'], $asset['deps'], $asset['version'] );
		} else {
			wp_enqueue_script( $asset['handle'], $asset['src'], $asset['deps'], $asset['version'], true );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'primeestate_enqueue_conditional_assets', 20 );

/**
 * T044 + Phase 4 additions: registers property-search.js (archive filter
 * AJAX), the Leaflet/OSM map assets (archive + single-property), and the
 * single-property page's gallery.js/inquiry-form.js — each scoped to only
 * the page(s) that use it. Registration must happen before priority 20's
 * enqueue pass, and after conditional tags like `is_post_type_archive()` are
 * reliable (available from `wp_enqueue_scripts` onward).
 */
function primeestate_register_map_and_search_assets(): void {
	primeestate_register_conditional_asset(
		'primeestate-property-search',
		PRIMEESTATE_THEME_URL . '/assets/js/property-search.js',
		array(),
		static function () {
			return is_post_type_archive( 'property' );
		},
		'script',
		primeestate_asset_version( '/assets/js/property-search.js' )
	);

	primeestate_register_conditional_asset(
		'primeestate-save-search',
		PRIMEESTATE_THEME_URL . '/assets/js/save-search.js',
		array( 'primeestate-rest-config' ),
		static function () {
			return is_post_type_archive( 'property' );
		},
		'script',
		primeestate_asset_version( '/assets/js/save-search.js' )
	);

	// Map appears on the archive (result pins) and the single-property page
	// (its own location) — everywhere else it stays unloaded.
	$on_map_page = static function () {
		return is_post_type_archive( 'property' ) || is_singular( 'property' );
	};

	primeestate_register_conditional_asset(
		'leaflet',
		'https://unpkg.com/leaflet@' . PRIMEESTATE_LEAFLET_VERSION . '/dist/leaflet.css',
		array(),
		$on_map_page,
		'style'
	);

	primeestate_register_conditional_asset(
		'leaflet',
		'https://unpkg.com/leaflet@' . PRIMEESTATE_LEAFLET_VERSION . '/dist/leaflet.js',
		array(),
		$on_map_page
	);

	primeestate_register_conditional_asset(
		'primeestate-map',
		PRIMEESTATE_THEME_URL . '/assets/js/map.js',
		array( 'leaflet' ),
		$on_map_page,
		'script',
		primeestate_asset_version( '/assets/js/map.js' )
	);

	$on_single_property = static function () {
		return is_singular( 'property' );
	};

	primeestate_register_conditional_asset(
		'primeestate-gallery',
		PRIMEESTATE_THEME_URL . '/assets/js/gallery.js',
		array(),
		$on_single_property,
		'script',
		primeestate_asset_version( '/assets/js/gallery.js' )
	);

	primeestate_register_conditional_asset(
		'primeestate-inquiry-form',
		PRIMEESTATE_THEME_URL . '/assets/js/inquiry-form.js',
		array(),
		$on_single_property,
		'script',
		primeestate_asset_version( '/assets/js/inquiry-form.js' )
	);

	primeestate_register_conditional_asset(
		'primeestate-viewing-form',
		PRIMEESTATE_THEME_URL . '/assets/js/viewing-form.js',
		array(),
		$on_single_property,
		'script',
		primeestate_asset_version( '/assets/js/viewing-form.js' )
	);

	primeestate_register_conditional_asset(
		'primeestate-mortgage-calculator',
		PRIMEESTATE_THEME_URL . '/assets/js/mortgage-calculator.js',
		array(),
		$on_single_property,
		'script',
		primeestate_asset_version( '/assets/js/mortgage-calculator.js' )
	);
}
add_action( 'wp_enqueue_scripts', 'primeestate_register_map_and_search_assets', 5 );

/**
 * A dependency-only handle (empty src, inline data attached via
 * `wp_localize_script()`) carrying `window.primeEstateFavorites = {
 * restUrl, nonce, isLoggedIn }` — every script that talks to the REST API
 * from the browser (favorites.js, comparison.js, agent-dashboard.js,
 * property-form.js, agent-profile-form.js) declares it as a dependency
 * instead of each re-localizing its own copy. The name predates the
 * dashboard/profile/property-form scripts (Phase 5 only had favorites in
 * mind) but is kept as-is rather than churning every dependent script's
 * `window.primeEstateFavorites` reference for a cosmetic rename.
 */
function primeestate_register_rest_config_script(): void {
	wp_register_script( 'primeestate-rest-config', '', array(), PRIMEESTATE_THEME_VERSION, true );
	wp_localize_script(
		'primeestate-rest-config',
		'primeEstateFavorites',
		array(
			'restUrl'     => esc_url_raw( rest_url( PRIMEESTATE_REST_NAMESPACE . '/' ) ),
			// T086: the Account Settings form PATCHes WP core's own
			// `/wp/v2/users/me`, not a custom `primeestate/v1` route — needs
			// core's REST base, not this plugin's namespaced one.
			'coreRestUrl' => esc_url_raw( rest_url( 'wp/v2/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'  => is_user_logged_in(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'primeestate_register_rest_config_script', 1 );

/**
 * T058/T062: favorites.js and comparison.js load anywhere a Favorite/Compare
 * button (or the Favorites/Compare pages themselves) can appear.
 */
function primeestate_enqueue_favorites_and_comparison_assets(): void {
	$shows_property_widgets = is_post_type_archive( 'property' )
		|| is_singular( 'property' )
		|| is_page( 'favorites' )
		|| is_page( 'compare' )
		|| is_page( 'user-dashboard' );

	if ( ! $shows_property_widgets ) {
		return;
	}

	wp_enqueue_script( 'primeestate-favorites', PRIMEESTATE_THEME_URL . '/assets/js/favorites.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/favorites.js' ), true );
	wp_enqueue_script( 'primeestate-comparison', PRIMEESTATE_THEME_URL . '/assets/js/comparison.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/comparison.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'primeestate_enqueue_favorites_and_comparison_assets' );

/**
 * Phase 7: Agent Dashboard, Add/Edit Property, and Profile Edit pages —
 * each is access-gated server-side (security.php) but still needs its JS
 * loaded to actually function for an authorized visitor.
 */
function primeestate_enqueue_agent_area_assets(): void {
	if ( is_page( 'agent-dashboard' ) ) {
		wp_enqueue_script( 'primeestate-agent-dashboard', PRIMEESTATE_THEME_URL . '/assets/js/agent-dashboard.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/agent-dashboard.js' ), true );
	}

	if ( is_page( 'agent-add-property' ) || is_page( 'submit-property' ) ) {
		wp_enqueue_script( 'primeestate-property-form', PRIMEESTATE_THEME_URL . '/assets/js/property-form.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/property-form.js' ), true );
	}

	if ( is_page( 'agent-profile-edit' ) ) {
		wp_enqueue_script( 'primeestate-agent-profile-form', PRIMEESTATE_THEME_URL . '/assets/js/agent-profile-form.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/agent-profile-form.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'primeestate_enqueue_agent_area_assets' );

/**
 * Phase 9: the User Dashboard's Favorites/Compare sections reuse
 * favorites.js/comparison.js as-is (enqueued above via
 * `primeestate_enqueue_favorites_and_comparison_assets()`, extended to
 * include this page); user-dashboard.js only needs to own the Account
 * Settings form, which nothing else on the page handles.
 */
function primeestate_enqueue_user_dashboard_assets(): void {
	if ( ! is_page( 'user-dashboard' ) ) {
		return;
	}

	wp_enqueue_script( 'primeestate-user-dashboard', PRIMEESTATE_THEME_URL . '/assets/js/user-dashboard.js', array( 'primeestate-rest-config' ), primeestate_asset_version( '/assets/js/user-dashboard.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'primeestate_enqueue_user_dashboard_assets' );
