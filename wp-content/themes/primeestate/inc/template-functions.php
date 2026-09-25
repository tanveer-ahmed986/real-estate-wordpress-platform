<?php
/**
 * Shared template helpers used across multiple components/templates.
 * Grows as later-phase components (PropertyCard, FilterPanel, etc.) are
 * built; kept minimal here since Phase 2 has no rendering logic yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats a property price for display, honoring `_pe_price_type`
 * (data-model.md §1: fixed | starting_from | on_request) and the site's
 * configured currency.
 */
function primeestate_format_price( float $price, string $price_type, string $currency ): string {
	if ( 'on_request' === $price_type ) {
		return __( 'Price on request', 'primeestate' );
	}

	$formatted = $currency . ' ' . number_format_i18n( $price );

	if ( 'starting_from' === $price_type ) {
		/* translators: %s: formatted price */
		return sprintf( __( 'From %s', 'primeestate' ), $formatted );
	}

	return $formatted;
}

/**
 * Renders an inline SVG icon from theme/assets/icons/{name}.svg, or nothing
 * if the icon file doesn't exist yet — keeps templates from fataling while
 * the icon set is still being built out.
 */
function primeestate_get_icon( string $name ): string {
	$path = PRIMEESTATE_THEME_PATH . '/assets/icons/' . sanitize_file_name( $name ) . '.svg';

	if ( ! file_exists( $path ) ) {
		return '';
	}

	return (string) file_get_contents( $path );
}

/**
 * T096 (FR-053): structured data (schema.org JSON-LD) + Open Graph metadata,
 * emitted on `wp_head`. An `Organization` block is always present (every
 * page represents the same business); a second, page-specific block is
 * added for property detail pages (`RealEstateListing`) and Insight
 * articles (`Article`) — the two content types with a natural schema.org
 * mapping. Archive/search pages intentionally get Open Graph only: there is
 * no single schema.org type for "a filtered list of listings," and
 * `ItemList` would need to hand-pick a truncated subset that doesn't match
 * what's actually on the page, worse than omitting it.
 */
function primeestate_output_structured_data_and_opengraph(): void {
	$graphs = array( primeestate_get_organization_schema() );

	$og = array(
		'og:site_name' => get_bloginfo( 'name' ),
		'og:locale'    => get_locale(),
		'og:type'      => 'website',
		'og:title'     => wp_get_document_title(),
		'og:url'       => primeestate_get_current_canonical_url(),
	);

	$description = get_bloginfo( 'description' );
	$image       = '';

	if ( is_singular( 'property' ) ) {
		$post           = get_queried_object();
		$property_graph = primeestate_get_property_schema( $post );
		if ( $property_graph ) {
			$graphs[] = $property_graph;
		}

		$summary       = primeestate_property_to_summary( $post );
		$og['og:type'] = 'product';
		$og['og:title'] = $summary['title'];
		$description    = wp_trim_words( wp_strip_all_tags( get_the_content( null, false, $post ) ), 40 );
		$image           = $summary['featured_image'];
	} elseif ( is_singular( 'post' ) ) {
		$post = get_queried_object();

		$graphs[] = primeestate_get_article_schema( $post );

		$og['og:type']  = 'article';
		$og['og:title'] = get_the_title( $post );
		$description     = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 40 );
		$image            = get_the_post_thumbnail_url( $post, 'large' ) ?: '';
	}

	if ( $description ) {
		$og['og:description'] = $description;
	}

	if ( $image ) {
		$og['og:image'] = $image;
	}

	foreach ( $og as $property => $content ) {
		if ( '' === $content ) {
			continue;
		}
		printf( '<meta property="%s" content="%s" />' . "\n", esc_attr( $property ), esc_attr( $content ) );
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( count( $graphs ) > 1 ? $graphs : $graphs[0], JSON_UNESCAPED_SLASHES )
	);
}
add_action( 'wp_head', 'primeestate_output_structured_data_and_opengraph' );

function primeestate_get_organization_schema(): array {
	return array(
		'@context' => 'https://schema.org',
		'@type'    => 'RealEstateAgent',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'description' => get_bloginfo( 'description' ),
	);
}

function primeestate_get_property_schema( WP_Post $post ): array {
	$summary = primeestate_property_to_summary( $post );

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'RealEstateListing',
		'url'         => $summary['permalink'],
		'name'        => $summary['title'],
		'description' => wp_trim_words( wp_strip_all_tags( get_the_content( null, false, $post ) ), 55 ),
	);

	if ( $summary['featured_image'] ) {
		$schema['image'] = $summary['featured_image'];
	}

	if ( $summary['price'] > 0 ) {
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $summary['price'],
			'priceCurrency' => $summary['currency'] ?: 'USD',
		);
	}

	$address = get_post_meta( $post->ID, '_pe_address', true );
	if ( $address || $summary['city'] ) {
		$schema['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => (string) $address,
			'addressLocality' => $summary['city'],
		);
	}

	if ( null !== $summary['latitude'] && null !== $summary['longitude'] ) {
		$schema['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $summary['latitude'],
			'longitude' => $summary['longitude'],
		);
	}

	if ( $summary['bedrooms'] > 0 ) {
		$schema['numberOfRooms'] = $summary['bedrooms'];
	}

	if ( $summary['area'] > 0 ) {
		$schema['floorSize'] = array(
			'@type' => 'QuantitativeValue',
			'value' => $summary['area'],
		);
	}

	return $schema;
}

function primeestate_get_article_schema( WP_Post $post ): array {
	$author = get_userdata( (int) $post->post_author );

	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => get_the_title( $post ),
		'url'           => get_permalink( $post ),
		'datePublished' => get_the_date( 'c', $post ),
		'dateModified'  => get_the_modified_date( 'c', $post ),
		'author'        => array(
			'@type' => 'Person',
			'name'  => $author ? $author->display_name : '',
		),
	);

	$image = get_the_post_thumbnail_url( $post, 'large' );
	if ( $image ) {
		$schema['image'] = $image;
	}

	return $schema;
}

/**
 * T098 (FR-052): canonical URL for the current request, including
 * filtered/paginated archive views — WordPress core's `rel_canonical()`
 * only handles singular content, so archive/taxonomy/search views get no
 * canonical at all by default. Filtered property views (e.g.
 * `?min_price=&city=miami&utm_source=x`) are normalized down to only their
 * *recognized* filter params, alphabetically ordered, with empty values
 * dropped — so every query-string permutation of "the Miami listings,
 * sorted by price" collapses onto the same canonical URL instead of each
 * being treated as a distinct indexable page (the FR-052 concern T097
 * exists to verify).
 */
function primeestate_get_current_canonical_url(): string {
	if ( is_singular() ) {
		$canonical = wp_get_canonical_url( get_queried_object() );
		return $canonical ?: (string) get_permalink();
	}

	if ( is_post_type_archive( 'property' ) ) {
		return primeestate_build_filtered_canonical( (string) get_post_type_archive_link( 'property' ) );
	}

	if ( is_tax( 'location' ) || is_tax( 'property_type' ) || is_tax( 'property_status' ) ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? home_url( '/' ) : primeestate_build_filtered_canonical( (string) $link );
	}

	if ( is_tax( 'insight_category' ) ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? home_url( '/' ) : (string) $link;
	}

	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_home() ) {
		$posts_page_id = (int) get_option( 'page_for_posts' );
		return $posts_page_id ? (string) get_permalink( $posts_page_id ) : home_url( '/' );
	}

	// Fallback for any other archive (search, author, etc.): current path with no query string.
	return home_url( wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH ) ?: '/' );
}

/**
 * Whitelist-based query-arg normalization shared by every filterable
 * archive type (property archive, and each of the property-related
 * taxonomies, which all use the same FilterPanel field names). Any param
 * not in this list (tracking params, stray query-string noise) is silently
 * dropped rather than preserved, which is the entire point: an unrecognized
 * param must never be able to mint a new "distinct" canonical URL.
 */
function primeestate_build_filtered_canonical( string $base_url ): string {
	$recognized = array( 'listing', 'city', 'type', 'status', 'min_price', 'max_price', 'bedrooms', 'bathrooms', 'min_area', 'max_area', 'sort', 'amenities' );
	$params     = array();

	foreach ( $recognized as $key ) {
		if ( empty( $_GET[ $key ] ) ) {
			continue;
		}

		$value = wp_unslash( $_GET[ $key ] );

		if ( is_array( $value ) ) {
			$value = array_map( 'sanitize_text_field', $value );
			sort( $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		$params[ $key ] = $value;
	}

	ksort( $params );

	if ( ! empty( $_GET['paged'] ) ) {
		$params['paged'] = absint( $_GET['paged'] );
	}

	return empty( $params ) ? $base_url : add_query_arg( $params, $base_url );
}

function primeestate_output_canonical_url(): void {
	$url = primeestate_get_current_canonical_url();

	if ( $url ) {
		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
	}
}
add_action( 'wp_head', 'primeestate_output_canonical_url', 1 );
remove_action( 'wp_head', 'rel_canonical' );
