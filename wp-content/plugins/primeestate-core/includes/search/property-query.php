<?php
/**
 * Property search/filter query builder (FR-011, FR-012, FR-013) and the
 * filter-state ↔ URL query-string codec that keeps a search shareable
 * (SC-013): copying the URL and opening it elsewhere reproduces the same
 * results. Used by the REST endpoint (search-api.php), the server-rendered
 * archive template's initial load, and mirrored client-side by
 * `history.pushState` in property-search.js.
 *
 * "Invalid filter parameters are ignored/clamped, not hard errors" per
 * spec.md's edge cases — `primeestate_normalize_filters()` is the single
 * place that enforces this, so every consumer (REST, template, tests) gets
 * identical, safe values.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_SEARCH_PER_PAGE = 12;

const PRIMEESTATE_SORT_OPTIONS = array( 'newest', 'price_asc', 'price_desc', 'area_desc', 'relevance' );

/**
 * Coerces/validates/clamps raw (e.g. $_GET or REST param) filter input into
 * a canonical shape. Unknown keys are dropped; out-of-range or malformed
 * values fall back to "unset" rather than erroring.
 */
function primeestate_normalize_filters( array $raw ): array {
	$filters = array();

	if ( ! empty( $raw['ids'] ) ) {
		$ids = primeestate_parse_id_list( $raw['ids'] );

		if ( ! empty( $ids ) ) {
			$filters['ids'] = $ids;
		}
	}

	if ( isset( $raw['listing'] ) && in_array( $raw['listing'], array( 'sale', 'rent', 'lease' ), true ) ) {
		$filters['listing'] = $raw['listing'];
	}

	foreach ( array( 'city', 'type', 'status' ) as $slug_field ) {
		if ( ! empty( $raw[ $slug_field ] ) && is_string( $raw[ $slug_field ] ) ) {
			$filters[ $slug_field ] = sanitize_title( $raw[ $slug_field ] );
		}
	}

	foreach ( array( 'min_price', 'max_price', 'min_area', 'max_area' ) as $numeric_field ) {
		if ( isset( $raw[ $numeric_field ] ) && is_numeric( $raw[ $numeric_field ] ) && (float) $raw[ $numeric_field ] >= 0 ) {
			$filters[ $numeric_field ] = (float) $raw[ $numeric_field ];
		}
	}

	// A max below a min is a contradictory range — drop both rather than
	// returning a query that can never match anything.
	if ( isset( $filters['min_price'], $filters['max_price'] ) && $filters['min_price'] > $filters['max_price'] ) {
		unset( $filters['min_price'], $filters['max_price'] );
	}
	if ( isset( $filters['min_area'], $filters['max_area'] ) && $filters['min_area'] > $filters['max_area'] ) {
		unset( $filters['min_area'], $filters['max_area'] );
	}

	foreach ( array( 'bedrooms', 'bathrooms' ) as $int_field ) {
		if ( isset( $raw[ $int_field ] ) && is_numeric( $raw[ $int_field ] ) && (int) $raw[ $int_field ] >= 0 ) {
			$filters[ $int_field ] = (int) $raw[ $int_field ];
		}
	}

	if ( ! empty( $raw['amenities'] ) ) {
		$amenities = is_array( $raw['amenities'] ) ? $raw['amenities'] : explode( ',', (string) $raw['amenities'] );
		$amenities = array_values( array_unique( array_map( 'sanitize_title', array_filter( $amenities ) ) ) );

		if ( ! empty( $amenities ) ) {
			$filters['amenities'] = $amenities;
		}
	}

	if ( isset( $raw['sort'] ) && in_array( $raw['sort'], PRIMEESTATE_SORT_OPTIONS, true ) ) {
		$filters['sort'] = $raw['sort'];
	}

	if ( isset( $raw['page'] ) && is_numeric( $raw['page'] ) && (int) $raw['page'] >= 1 ) {
		$filters['page'] = (int) $raw['page'];
	}

	return $filters;
}

/**
 * Parses a comma-separated string or array of IDs (query param or REST
 * param) into a deduped array of positive integers. Shared by the `ids`
 * search filter, favorites, and comparison.
 *
 * @param string|array $raw
 * @return int[]
 */
function primeestate_parse_id_list( $raw ): array {
	$list = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

	return array_values(
		array_unique(
			array_filter(
				array_map( 'absint', $list ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		)
	);
}

/**
 * Builds a `WP_Query`-ready args array from normalized filters.
 */
function primeestate_build_property_query_args( array $filters ): array {
	$filters = primeestate_normalize_filters( $filters );

	$args = array(
		'post_type'      => 'property',
		'post_status'    => 'publish',
		'posts_per_page' => PRIMEESTATE_SEARCH_PER_PAGE,
		'paged'          => $filters['page'] ?? 1,
	);

	// `ids` is an explicit-ID lookup (favorites, comparison — data-model.md §7/§8
	// are client-side-only, so their "list" pages fetch fresh, still-published
	// data for a known ID set rather than filtering by taxonomy/meta). It
	// short-circuits pagination and returns results in the requested order.
	if ( ! empty( $filters['ids'] ) ) {
		$args['post__in']      = $filters['ids'];
		$args['orderby']       = 'post__in';
		$args['posts_per_page'] = count( $filters['ids'] );
		unset( $args['paged'] );

		return $args;
	}

	$tax_query  = array();
	$meta_query = array();

	if ( isset( $filters['listing'] ) ) {
		$listing_term_map = array( 'sale' => 'for-sale', 'rent' => 'for-rent', 'lease' => 'for-lease' );
		$tax_query[]       = array(
			'taxonomy' => 'listing_type',
			'field'    => 'slug',
			'terms'    => $listing_term_map[ $filters['listing'] ],
		);
	}

	if ( isset( $filters['city'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'location',
			'field'    => 'slug',
			'terms'    => $filters['city'],
		);
	}

	if ( isset( $filters['type'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'property_type',
			'field'    => 'slug',
			'terms'    => $filters['type'],
		);
	}

	if ( isset( $filters['status'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'property_status',
			'field'    => 'slug',
			'terms'    => $filters['status'],
		);
	}

	if ( ! empty( $filters['amenities'] ) ) {
		// AND semantics: a property must have every selected amenity.
		$tax_query[] = array(
			'taxonomy' => 'amenity',
			'field'    => 'slug',
			'terms'    => $filters['amenities'],
			'operator' => 'AND',
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	if ( isset( $filters['min_price'] ) || isset( $filters['max_price'] ) ) {
		$meta_query[] = array(
			'key'     => '_pe_price',
			'value'   => array( $filters['min_price'] ?? 0, $filters['max_price'] ?? PHP_INT_MAX ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	if ( isset( $filters['min_area'] ) || isset( $filters['max_area'] ) ) {
		$meta_query[] = array(
			'key'     => '_pe_area',
			'value'   => array( $filters['min_area'] ?? 0, $filters['max_area'] ?? PHP_INT_MAX ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	if ( isset( $filters['bedrooms'] ) ) {
		$meta_query[] = array(
			'key'     => '_pe_bedrooms',
			'value'   => $filters['bedrooms'],
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	if ( isset( $filters['bathrooms'] ) ) {
		$meta_query[] = array(
			'key'     => '_pe_bathrooms',
			'value'   => $filters['bathrooms'],
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	if ( count( $meta_query ) > 1 ) {
		$meta_query['relation'] = 'AND';
	}

	if ( ! empty( $tax_query ) ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	if ( ! empty( $meta_query ) ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	switch ( $filters['sort'] ?? 'relevance' ) {
		case 'price_asc':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_pe_price';
			$args['order']    = 'ASC';
			break;
		case 'price_desc':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_pe_price';
			$args['order']    = 'DESC';
			break;
		case 'area_desc':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_pe_area';
			$args['order']    = 'DESC';
			break;
		case 'newest':
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		default: // relevance — falls back to newest-first when there is no search term to rank by.
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
	}

	return $args;
}

/**
 * Canonical, stable-ordered query string for a normalized filter set —
 * defaults and unset keys are omitted so two equivalent searches always
 * produce byte-identical URLs (SC-013).
 */
function primeestate_filters_to_query_string( array $filters ): string {
	$filters = primeestate_normalize_filters( $filters );
	$ordered = array();

	$key_order = array( 'ids', 'listing', 'city', 'type', 'status', 'min_price', 'max_price', 'bedrooms', 'bathrooms', 'min_area', 'max_area', 'amenities', 'sort', 'page' );

	foreach ( $key_order as $key ) {
		if ( ! isset( $filters[ $key ] ) ) {
			continue;
		}

		if ( 'page' === $key && 1 === $filters[ $key ] ) {
			continue; // Default page — omit for a cleaner canonical URL.
		}

		if ( 'sort' === $key && 'relevance' === $filters[ $key ] ) {
			continue; // Default sort — omit.
		}

		$value            = $filters[ $key ];
		$ordered[ $key ]  = is_array( $value ) ? implode( ',', $value ) : (string) $value;
	}

	return http_build_query( $ordered );
}

function primeestate_filters_from_query_string( string $query_string ): array {
	parse_str( ltrim( $query_string, '?' ), $raw );

	if ( isset( $raw['amenities'] ) && is_string( $raw['amenities'] ) ) {
		$raw['amenities'] = explode( ',', $raw['amenities'] );
	}

	return primeestate_normalize_filters( $raw );
}

/**
 * Merges `$_GET` filters into the property archive's main query, so a full
 * page load/reload/back-button navigation of `/properties/?...` reflects
 * the same result set the AJAX fragment path (theme/inc/archive-render.php)
 * would produce — WordPress's own pagination (`paged`) is left untouched.
 */
function primeestate_filter_main_property_archive_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'property' ) ) {
		return;
	}

	$filters = primeestate_normalize_filters( wp_unslash( $_GET ) );
	$args    = primeestate_build_property_query_args( $filters );

	if ( isset( $args['tax_query'] ) ) {
		$query->set( 'tax_query', $args['tax_query'] );
	}

	if ( isset( $args['meta_query'] ) ) {
		$query->set( 'meta_query', $args['meta_query'] );
	}

	if ( isset( $args['meta_key'] ) ) {
		$query->set( 'meta_key', $args['meta_key'] );
	}

	$query->set( 'orderby', $args['orderby'] );
	$query->set( 'order', $args['order'] );
	$query->set( 'posts_per_page', PRIMEESTATE_SEARCH_PER_PAGE );
}
add_action( 'pre_get_posts', 'primeestate_filter_main_property_archive_query' );

const PRIMEESTATE_RELATED_PROPERTIES_LIMIT = 6;

/**
 * Bounded related-properties selection (FR-024): candidates share the same
 * city or property type (an OR tax_query — either signal qualifies a
 * candidate), then ranked by price/bedroom similarity to the source
 * property and capped at `$limit`. Never includes the source property
 * itself.
 *
 * @return WP_Post[]
 */
function primeestate_get_related_properties( int $property_id, int $limit = PRIMEESTATE_RELATED_PROPERTIES_LIMIT ): array {
	$source = get_post( $property_id );

	if ( ! $source || 'property' !== $source->post_type ) {
		return array();
	}

	$location_terms = wp_get_post_terms( $property_id, 'location', array( 'fields' => 'ids' ) );
	$type_terms     = wp_get_post_terms( $property_id, 'property_type', array( 'fields' => 'ids' ) );

	$tax_query = array( 'relation' => 'OR' );

	if ( ! empty( $location_terms ) ) {
		$tax_query[] = array( 'taxonomy' => 'location', 'field' => 'term_id', 'terms' => $location_terms );
	}

	if ( ! empty( $type_terms ) ) {
		$tax_query[] = array( 'taxonomy' => 'property_type', 'field' => 'term_id', 'terms' => $type_terms );
	}

	if ( count( $tax_query ) <= 1 ) {
		return array(); // No location/type signal to match candidates against.
	}

	$candidates = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'post__not_in'   => array( $property_id ),
			'posts_per_page' => $limit * 3, // Wider candidate pool, ranked and trimmed below.
			'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$price    = (float) get_post_meta( $property_id, '_pe_price', true );
	$bedrooms = (int) get_post_meta( $property_id, '_pe_bedrooms', true );

	$scored = array_map(
		static function ( WP_Post $candidate ) use ( $price, $bedrooms ) {
			$candidate_price    = (float) get_post_meta( $candidate->ID, '_pe_price', true );
			$candidate_bedrooms = (int) get_post_meta( $candidate->ID, '_pe_bedrooms', true );

			$price_score   = $price > 0 ? abs( $candidate_price - $price ) / $price : 0;
			$bedroom_score = abs( $candidate_bedrooms - $bedrooms ) * 0.1;

			return array( 'post' => $candidate, 'score' => $price_score + $bedroom_score );
		},
		$candidates->posts
	);

	usort( $scored, static function ( $a, $b ) {
		return $a['score'] <=> $b['score'];
	} );

	return array_slice( array_column( $scored, 'post' ), 0, $limit );
}

function primeestate_register_related_properties_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/properties/(?P<id>\d+)/related',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'primeestate_handle_related_properties',
			'permission_callback' => 'primeestate_rest_public_permission',
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_related_properties_route' );

function primeestate_handle_related_properties( WP_REST_Request $request ) {
	$id   = (int) $request['id'];
	$post = get_post( $id );

	if ( ! $post || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
		return primeestate_rest_error( 'not_found', __( 'Property not found or not published.', 'primeestate' ), 404 );
	}

	$related = primeestate_get_related_properties( $id );

	return new WP_REST_Response( array_map( 'primeestate_property_to_summary', $related ), 200 );
}
