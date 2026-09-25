<?php
/**
 * Map component (research.md §3, FR-015/FR-016) — Leaflet + OpenStreetMap by
 * default. Renders nothing (graceful fallback) when no property in the
 * current result set has coordinates; the grid/list view of PropertyCards
 * remains fully usable either way. Provider is filterable
 * (`primeestate_map_provider`) for a future Google/Mapbox implementation —
 * only the Leaflet/OSM default is implemented now, per research.md's
 * decision.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_LEAFLET_VERSION = '1.9.4';

/**
 * @param WP_Post[] $posts
 */
function primeestate_render_map( array $posts ): void {
	$markers = array();

	foreach ( $posts as $post ) {
		$lat = get_post_meta( $post->ID, '_pe_lat', true );
		$lng = get_post_meta( $post->ID, '_pe_lng', true );

		if ( '' === $lat || '' === $lng ) {
			continue; // Property omitted from the map, not an error (data-model.md §1).
		}

		$markers[] = array(
			'lat'       => (float) $lat,
			'lng'       => (float) $lng,
			'title'     => get_the_title( $post ),
			'permalink' => get_permalink( $post ),
			'price'     => primeestate_format_price(
				(float) get_post_meta( $post->ID, '_pe_price', true ),
				(string) get_post_meta( $post->ID, '_pe_price_type', true ),
				(string) get_post_meta( $post->ID, '_pe_currency', true )
			),
		);
	}

	if ( empty( $markers ) ) {
		return;
	}
	?>
	<div
		id="pe-map"
		class="pe-map"
		data-provider="<?php echo esc_attr( apply_filters( 'primeestate_map_provider', 'leaflet' ) ); ?>"
		data-markers="<?php echo esc_attr( wp_json_encode( $markers ) ); ?>"
		role="application"
		aria-label="<?php esc_attr_e( 'Map of search results', 'primeestate' ); ?>"
	></div>
	<?php
}

/**
 * True if the current result set has at least one mappable property —
 * lets the template decide whether to reserve map UI chrome at all.
 *
 * @param WP_Post[] $posts
 */
function primeestate_has_mappable_properties( array $posts ): bool {
	foreach ( $posts as $post ) {
		if ( '' !== get_post_meta( $post->ID, '_pe_lat', true ) && '' !== get_post_meta( $post->ID, '_pe_lng', true ) ) {
			return true;
		}
	}

	return false;
}
