<?php
/**
 * Property archive rendering orchestration (T039). The archive is a block
 * template (templates/archive-property.html) that embeds this shortcode —
 * the search/filter/grid UI is too dynamic and server-logic-driven to
 * express as static block markup, so it is rendered by PHP and dropped into
 * the block template via `[primeestate_property_archive]`, matching
 * research.md §1's "server-rendered WP_Query + AJAX fragment" decision.
 *
 * `primeestate_render_property_results()` is factored out separately from
 * the full shortcode (which also renders the FilterPanel) so the AJAX
 * fragment handler below can re-render just the results on a filter change,
 * without re-rendering the filter form or page chrome.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_property_archive_shortcode(): string {
	$filters = primeestate_normalize_filters( wp_unslash( $_GET ) );
	$query   = new WP_Query( primeestate_build_property_query_args( $filters ) );

	ob_start();
	?>
	<div class="pe-archive">
		<aside class="pe-archive__filters">
			<?php primeestate_render_filter_panel( $filters ); ?>
		</aside>
		<div class="pe-archive__results" id="pe-archive-results" data-fragment-url="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>">
			<?php primeestate_render_property_results( $query ); ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_property_archive', 'primeestate_property_archive_shortcode' );

function primeestate_render_property_results( WP_Query $query ): void {
	echo '<p class="pe-archive__count">';
	echo esc_html(
		sprintf(
			/* translators: %d: number of matching properties */
			_n( '%d property found', '%d properties found', $query->found_posts, 'primeestate' ),
			$query->found_posts
		)
	);
	echo '</p>';

	if ( ! $query->have_posts() ) {
		primeestate_render_empty_state();
		return;
	}

	if ( primeestate_has_mappable_properties( $query->posts ) ) {
		primeestate_render_map( $query->posts );
	}

	echo '<div class="pe-property-grid">';
	foreach ( $query->posts as $post ) {
		primeestate_render_property_card( $post );
	}
	echo '</div>';

	$pagination = paginate_links(
		array(
			'total'     => $query->max_num_pages,
			'current'   => max( 1, (int) $query->get( 'paged' ) ),
			'prev_text' => __( 'Previous', 'primeestate' ),
			'next_text' => __( 'Next', 'primeestate' ),
			'type'      => 'array',
		)
	);

	if ( ! empty( $pagination ) ) {
		echo '<nav class="pe-archive__pagination" aria-label="' . esc_attr__( 'Property results pages', 'primeestate' ) . '"><ul>';
		foreach ( $pagination as $link ) {
			echo '<li>' . wp_kses_post( $link ) . '</li>';
		}
		echo '</ul></nav>';
	}
}

/**
 * Serves just the results-partial HTML for property-search.js's AJAX
 * requests (identified by the `X-PrimeEstate-Fragment` header, never a
 * query-string param — keeping it out of the canonical, shareable URL).
 * Must run before any page output starts.
 */
function primeestate_maybe_serve_property_fragment(): void {
	if ( ! is_post_type_archive( 'property' ) ) {
		return;
	}

	if ( empty( $_SERVER['HTTP_X_PRIMEESTATE_FRAGMENT'] ) ) {
		return;
	}

	$filters = primeestate_normalize_filters( wp_unslash( $_GET ) );
	$query   = new WP_Query( primeestate_build_property_query_args( $filters ) );

	nocache_headers(); // Fragment responses vary per filter set; let the *page* URL be cached, not this XHR-only path.
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	primeestate_render_property_results( $query );
	exit;
}
add_action( 'template_redirect', 'primeestate_maybe_serve_property_fragment' );
