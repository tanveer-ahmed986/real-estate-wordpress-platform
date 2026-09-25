<?php
/**
 * `GET /wp-json/primeestate/v1/properties` (FR-011, FR-012, FR-013), per
 * contracts/primeestate-api.openapi.yaml `searchProperties`. Public, no auth
 * — search itself is unauthenticated per the contract's `security: []`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_search_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/properties',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'primeestate_handle_search_properties',
			'permission_callback' => 'primeestate_rest_public_permission',
			'args'                => array(
				'ids'        => array( 'type' => 'string' ),
				'listing'    => array( 'type' => 'string' ),
				'city'       => array( 'type' => 'string' ),
				'type'       => array( 'type' => 'string' ),
				'min_price'  => array( 'type' => 'number' ),
				'max_price'  => array( 'type' => 'number' ),
				'bedrooms'   => array( 'type' => 'integer' ),
				'bathrooms'  => array( 'type' => 'integer' ),
				'min_area'   => array( 'type' => 'number' ),
				'max_area'   => array( 'type' => 'number' ),
				'amenities'  => array( 'type' => 'array' ),
				'status'     => array( 'type' => 'string' ),
				'sort'       => array( 'type' => 'string' ),
				'page'       => array( 'type' => 'integer' ),
			),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_search_route' );

function primeestate_handle_search_properties( WP_REST_Request $request ) {
	$filters = primeestate_normalize_filters( $request->get_params() );
	$args    = primeestate_build_property_query_args( $filters );

	$query = new WP_Query( $args );

	return new WP_REST_Response(
		array(
			'total'    => (int) $query->found_posts,
			'page'     => $filters['page'] ?? 1,
			'per_page' => PRIMEESTATE_SEARCH_PER_PAGE,
			'results'  => array_map( 'primeestate_property_to_summary', $query->posts ),
		),
		200
	);
}

/**
 * Maps a `property` WP_Post to the OpenAPI `PropertySummary` schema. Shared
 * with `/properties/{id}/related` (T?) once that endpoint exists — kept here
 * since it is the search response's core building block.
 */
function primeestate_property_to_summary( WP_Post $post ): array {
	$listing_terms = get_the_terms( $post, 'listing_type' );
	$type_terms    = get_the_terms( $post, 'property_type' );
	$status_terms  = get_the_terms( $post, 'property_status' );
	$location_terms = get_the_terms( $post, 'location' );

	$lat = get_post_meta( $post->ID, '_pe_lat', true );
	$lng = get_post_meta( $post->ID, '_pe_lng', true );

	return array(
		'id'              => $post->ID,
		'title'           => get_the_title( $post ),
		'price'           => (float) get_post_meta( $post->ID, '_pe_price', true ),
		'currency'        => (string) get_post_meta( $post->ID, '_pe_currency', true ),
		'listing_type'    => $listing_terms && ! is_wp_error( $listing_terms ) ? $listing_terms[0]->name : '',
		'property_type'   => $type_terms && ! is_wp_error( $type_terms ) ? $type_terms[0]->name : '',
		'status'          => $status_terms && ! is_wp_error( $status_terms ) ? $status_terms[0]->name : '',
		'bedrooms'        => (int) get_post_meta( $post->ID, '_pe_bedrooms', true ),
		'bathrooms'       => (int) get_post_meta( $post->ID, '_pe_bathrooms', true ),
		'area'            => (float) get_post_meta( $post->ID, '_pe_area', true ),
		'city'            => $location_terms && ! is_wp_error( $location_terms ) ? $location_terms[0]->name : '',
		'featured_image'  => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
		'permalink'       => get_permalink( $post ),
		'latitude'        => '' !== $lat ? (float) $lat : null,
		'longitude'       => '' !== $lng ? (float) $lng : null,
	);
}
