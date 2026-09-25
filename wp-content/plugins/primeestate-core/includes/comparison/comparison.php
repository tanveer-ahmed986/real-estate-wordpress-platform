<?php
/**
 * Comparison data-fetch endpoint (FR-022/023, data-model.md §8). The
 * comparison *set* is entirely client-side (no server persistence) — this
 * endpoint's only job is turning a client-held ID list into fresh,
 * still-published property data for the comparison table (T063), the same
 * way `GET /properties?ids=` serves favorites. The 4-item cap is enforced
 * here too, not just in comparison.js, so a client that bypasses the UI
 * limit still can't request more than the product actually supports
 * (constitution Principle II: server-side re-verification, never trust the
 * client).
 *
 * Not part of the original OpenAPI contract — added here because
 * client-side-only comparison/favorites have no way to fetch real data
 * without *some* ID-based lookup; contracts/primeestate-api.openapi.yaml is
 * updated alongside this file to keep the documented contract accurate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_COMPARISON_MAX = 4; // Keep in sync with assets/js/comparison.js's MAX_COMPARE.

function primeestate_register_comparison_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/properties/compare',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'primeestate_handle_compare_properties',
			'permission_callback' => 'primeestate_rest_public_permission',
			'args'                => array(
				'ids' => array( 'type' => 'string', 'required' => true ),
			),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_comparison_route' );

function primeestate_handle_compare_properties( WP_REST_Request $request ) {
	$ids = array_slice( primeestate_parse_id_list( $request->get_param( 'ids' ) ), 0, PRIMEESTATE_COMPARISON_MAX );

	if ( empty( $ids ) ) {
		return new WP_REST_Response( array(), 200 );
	}

	$query = new WP_Query( primeestate_build_property_query_args( array( 'ids' => $ids ) ) );

	return new WP_REST_Response( array_map( 'primeestate_property_to_summary', $query->posts ), 200 );
}
