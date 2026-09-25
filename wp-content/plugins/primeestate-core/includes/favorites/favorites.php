<?php
/**
 * Favorites (FR-019–FR-021, data-model.md §7): `GET/POST /favorites`,
 * `POST /favorites/merge`. Logged-in favorites live in usermeta
 * `_pe_favorites`; guest favorites are `localStorage`-only (favorites.js)
 * until merged into the account on login via the merge endpoint
 * (research.md §4).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return int[]
 */
function primeestate_get_user_favorites( int $user_id ): array {
	$favorites = get_user_meta( $user_id, '_pe_favorites', true );

	return is_array( $favorites ) ? array_values( array_unique( array_map( 'absint', $favorites ) ) ) : array();
}

/**
 * Toggles a property in/out of the user's favorites. Returns the new state
 * (true = now favorited).
 */
function primeestate_toggle_user_favorite( int $user_id, int $property_id ): bool {
	$favorites = primeestate_get_user_favorites( $user_id );
	$index     = array_search( $property_id, $favorites, true );

	if ( false !== $index ) {
		unset( $favorites[ $index ] );
		update_user_meta( $user_id, '_pe_favorites', array_values( $favorites ) );
		return false;
	}

	$favorites[] = $property_id;
	update_user_meta( $user_id, '_pe_favorites', $favorites );
	return true;
}

/**
 * Merges a guest's `localStorage` favorite IDs into the account's favorites
 * (set union, no duplicates). Idempotent — safe to call on every page load
 * (favorites.js does exactly that whenever local guest favorites remain).
 *
 * @param int[] $guest_property_ids
 * @return int[] The merged, deduped favorites list.
 */
function primeestate_merge_user_favorites( int $user_id, array $guest_property_ids ): array {
	$merged = array_values( array_unique( array_merge( primeestate_get_user_favorites( $user_id ), $guest_property_ids ) ) );
	update_user_meta( $user_id, '_pe_favorites', $merged );

	return $merged;
}

/**
 * Per-request cache so rendering N PropertyCards on one page doesn't read
 * usermeta N times.
 *
 * @return int[]
 */
function primeestate_get_current_user_favorites_cached(): array {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$user_id = get_current_user_id();
	$cache   = $user_id ? primeestate_get_user_favorites( $user_id ) : array();

	return $cache;
}

function primeestate_is_property_favorited_by_current_user( int $property_id ): bool {
	return in_array( $property_id, primeestate_get_current_user_favorites_cached(), true );
}

/**
 * Favorites filtered to properties that are still published (T064: a
 * favorited property that was later unpublished/deleted simply disappears
 * from the list — the stored favorite ID is left untouched in usermeta in
 * case the listing returns, rather than being silently pruned).
 *
 * @return WP_Post[]
 */
function primeestate_get_favorited_properties( int $user_id ): array {
	$ids = primeestate_get_user_favorites( $user_id );

	if ( empty( $ids ) ) {
		return array();
	}

	$query = new WP_Query( primeestate_build_property_query_args( array( 'ids' => $ids ) ) );

	return $query->posts;
}

function primeestate_register_favorites_routes(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/favorites',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'primeestate_handle_list_favorites',
				'permission_callback' => 'primeestate_rest_authenticated_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'primeestate_handle_toggle_favorite',
				'permission_callback' => 'primeestate_rest_authenticated_permission',
			),
		)
	);

	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/favorites/merge',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'primeestate_handle_merge_favorites',
			'permission_callback' => 'primeestate_rest_authenticated_permission',
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_favorites_routes' );

function primeestate_handle_list_favorites( WP_REST_Request $request ) {
	$properties = primeestate_get_favorited_properties( get_current_user_id() );

	return new WP_REST_Response( array_map( 'primeestate_property_to_summary', $properties ), 200 );
}

function primeestate_handle_toggle_favorite( WP_REST_Request $request ) {
	$property_id = absint( $request->get_param( 'property_id' ) );
	$property    = get_post( $property_id );

	if ( ! $property || 'property' !== $property->post_type ) {
		return primeestate_rest_error( 'invalid_property', __( 'Property not found.', 'primeestate' ), 400 );
	}

	$favorited = primeestate_toggle_user_favorite( get_current_user_id(), $property_id );

	return new WP_REST_Response(
		array(
			'property_id' => $property_id,
			'favorited'   => $favorited,
		),
		200
	);
}

function primeestate_handle_merge_favorites( WP_REST_Request $request ) {
	$ids    = primeestate_parse_id_list( $request->get_param( 'property_ids' ) ?? array() );
	$merged = primeestate_merge_user_favorites( get_current_user_id(), $ids );

	return new WP_REST_Response( array( 'property_ids' => $merged ), 200 );
}
