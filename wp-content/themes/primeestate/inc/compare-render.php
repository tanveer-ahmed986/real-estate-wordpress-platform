<?php
/**
 * Comparison page rendering (T063). Comparison is entirely client-side
 * (data-model.md §8 — no server persistence for guests OR logged-in users),
 * so unlike Favorites there is no server-rendered initial state at all: the
 * page always starts empty and comparison.js populates it from
 * `localStorage` via the same fragment-request pattern used elsewhere
 * (X-PrimeEstate-Fragment header), keeping the actual table markup
 * server-rendered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_compare_page_shortcode(): string {
	ob_start();
	?>
	<div id="pe-compare-results" class="pe-compare-results" data-fragment-url="<?php echo esc_url( home_url( '/compare/' ) ); ?>">
		<p><?php esc_html_e( 'Add properties to comparison from any listing to see them here.', 'primeestate' ); ?></p>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_compare_page', 'primeestate_compare_page_shortcode' );

/**
 * Side-by-side comparison table (FR-023): price, bedrooms, bathrooms, area,
 * type, location, parking, amenities, status — one column per property, one
 * row per attribute. Wrapped in a horizontally-scrolling container so it
 * stays usable on mobile without needing a separate stacked layout.
 *
 * @param WP_Post[] $posts
 */
function primeestate_render_comparison_table( array $posts ): void {
	if ( empty( $posts ) ) {
		primeestate_render_empty_state( __( 'Add properties to comparison from any listing to see them here.', 'primeestate' ) );
		return;
	}

	$rows = array(
		'image'     => __( '', 'primeestate' ),
		'price'     => __( 'Price', 'primeestate' ),
		'type'      => __( 'Type', 'primeestate' ),
		'location'  => __( 'Location', 'primeestate' ),
		'bedrooms'  => __( 'Bedrooms', 'primeestate' ),
		'bathrooms' => __( 'Bathrooms', 'primeestate' ),
		'area'      => __( 'Area', 'primeestate' ),
		'parking'   => __( 'Parking spaces', 'primeestate' ),
		'status'    => __( 'Status', 'primeestate' ),
		'amenities' => __( 'Amenities', 'primeestate' ),
	);

	echo '<div class="pe-compare-table-wrapper"><table class="pe-compare-table">';

	foreach ( $rows as $key => $label ) {
		echo '<tr class="pe-compare-table__row pe-compare-table__row--' . esc_attr( $key ) . '">';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';

		foreach ( $posts as $post ) {
			echo '<td data-property-id="' . esc_attr( $post->ID ) . '">' . primeestate_get_comparison_cell( $post, $key ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per-cell below.
		}

		echo '</tr>';
	}

	echo '</table></div>';
}

function primeestate_get_comparison_cell( WP_Post $post, string $key ): string {
	$summary = primeestate_property_to_summary( $post );

	switch ( $key ) {
		case 'image':
			return $summary['featured_image']
				? '<a href="' . esc_url( $summary['permalink'] ) . '"><img src="' . esc_url( $summary['featured_image'] ) . '" alt="" loading="lazy"></a><a href="' . esc_url( $summary['permalink'] ) . '">' . esc_html( $summary['title'] ) . '</a>'
				: '<a href="' . esc_url( $summary['permalink'] ) . '">' . esc_html( $summary['title'] ) . '</a>';
		case 'price':
			return esc_html( primeestate_format_price( $summary['price'], (string) get_post_meta( $post->ID, '_pe_price_type', true ), $summary['currency'] ) );
		case 'type':
			return esc_html( $summary['property_type'] );
		case 'location':
			return esc_html( $summary['city'] );
		case 'bedrooms':
			return esc_html( (string) $summary['bedrooms'] );
		case 'bathrooms':
			return esc_html( (string) $summary['bathrooms'] );
		case 'area':
			return esc_html( number_format_i18n( $summary['area'] ) );
		case 'parking':
			return esc_html( (string) get_post_meta( $post->ID, '_pe_parking_spaces', true ) );
		case 'status':
			return esc_html( $summary['status'] );
		case 'amenities':
			$terms = get_the_terms( $post, 'amenity' );
			return $terms && ! is_wp_error( $terms ) ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) : '';
		default:
			return '';
	}
}

function primeestate_maybe_serve_compare_fragment(): void {
	if ( ! is_page( 'compare' ) || empty( $_SERVER['HTTP_X_PRIMEESTATE_FRAGMENT'] ) ) {
		return;
	}

	$ids = array_slice( primeestate_parse_id_list( wp_unslash( $_GET['ids'] ?? '' ) ), 0, PRIMEESTATE_COMPARISON_MAX );

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	if ( empty( $ids ) ) {
		primeestate_render_comparison_table( array() );
		exit;
	}

	$query = new WP_Query( primeestate_build_property_query_args( array( 'ids' => $ids ) ) );
	primeestate_render_comparison_table( $query->posts );
	exit;
}
add_action( 'template_redirect', 'primeestate_maybe_serve_compare_fragment' );
