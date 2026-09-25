<?php
/**
 * Breadcrumb trail rendering (T029 scaffold, finished in T097). For a
 * property, the trail walks the full `location` ancestor chain (Country →
 * State → City → Area, per the T010 URL structure) via `get_ancestors()`
 * rather than hardcoding 4 fixed levels — `area` is an optional leaf term
 * (data-model.md §2), so a property whose location term is only 3 levels
 * deep (no `area`) still gets a correct, non-padded trail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `primeestate_render_breadcrumbs()` has existed since Phase 2 and was
 * finished (full location-ancestor walking, BreadcrumbList JSON-LD) in
 * Phase 12 — but nothing ever actually called it from a template. This
 * shortcode wrapper is what templates now embed; see each
 * `templates/*.html` file for where it's used.
 */
function primeestate_breadcrumbs_shortcode(): string {
	ob_start();
	primeestate_render_breadcrumbs();
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_breadcrumbs', 'primeestate_breadcrumbs_shortcode' );

function primeestate_render_breadcrumbs(): void {
	$trail = array(
		array(
			'label' => __( 'Home', 'primeestate' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular( 'property' ) ) {
		$trail[] = array(
			'label' => __( 'Properties', 'primeestate' ),
			'url'   => get_post_type_archive_link( 'property' ),
		);
		$trail   = array_merge( $trail, primeestate_get_location_breadcrumb_segments( get_the_ID() ) );
		$trail[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_post_type_archive( 'property' ) ) {
		$trail[] = array(
			'label' => __( 'Properties', 'primeestate' ),
			'url'   => '',
		);
	} elseif ( is_tax( 'location' ) ) {
		$trail[] = array(
			'label' => __( 'Properties', 'primeestate' ),
			'url'   => get_post_type_archive_link( 'property' ),
		);

		$term     = get_queried_object();
		$ancestor_ids = array_reverse( get_ancestors( $term->term_id, 'location', 'taxonomy' ) );

		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'location' );
			if ( $ancestor instanceof WP_Term ) {
				$trail[] = array( 'label' => $ancestor->name, 'url' => (string) get_term_link( $ancestor ) );
			}
		}

		$trail[] = array( 'label' => $term->name, 'url' => '' );
	} elseif ( is_tax( 'insight_category' ) ) {
		$trail[] = primeestate_get_insights_index_crumb();
		$trail[] = array( 'label' => get_queried_object()->name, 'url' => '' );
	} elseif ( is_singular( 'post' ) ) {
		$trail[] = primeestate_get_insights_index_crumb();

		$categories = get_the_terms( get_the_ID(), 'insight_category' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$trail[] = array( 'label' => $categories[0]->name, 'url' => (string) get_term_link( $categories[0] ) );
		}

		$trail[] = array( 'label' => get_the_title(), 'url' => '' );
	} elseif ( is_singular() || is_category() || is_tag() ) {
		$trail[] = array(
			'label' => wp_get_document_title(),
			'url'   => '',
		);
	}

	echo '<nav class="pe-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'primeestate' ) . '"><ol>';

	foreach ( $trail as $index => $crumb ) {
		$is_last = ( $index === count( $trail ) - 1 );

		echo '<li>';
		if ( ! $is_last && $crumb['url'] ) {
			echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $crumb['label'] ) . '</span>';
		}
		echo '</li>';
	}

	echo '</ol></nav>';

	primeestate_output_breadcrumb_schema( $trail );
}

/**
 * `location` term ancestors for a property's breadcrumb trail, walked via
 * `get_ancestors()` rather than a fixed 4-level assumption — `area` is an
 * optional leaf term (data-model.md §2), so a 3-level-deep location (no
 * `area`) still produces a correct trail instead of a broken/padded one.
 */
function primeestate_get_location_breadcrumb_segments( int $property_id ): array {
	$terms = get_the_terms( $property_id, 'location' );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}

	$term         = $terms[0];
	$ancestor_ids = array_reverse( get_ancestors( $term->term_id, 'location', 'taxonomy' ) );
	$segments     = array();

	foreach ( $ancestor_ids as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'location' );
		if ( $ancestor instanceof WP_Term ) {
			$segments[] = array( 'label' => $ancestor->name, 'url' => (string) get_term_link( $ancestor ) );
		}
	}

	$segments[] = array( 'label' => $term->name, 'url' => (string) get_term_link( $term ) );

	return $segments;
}

/**
 * Insight articles have no dedicated CPT archive (they use native `post`,
 * data-model.md §9) — the closest equivalent to an "index" crumb is
 * whatever page is configured as the site's Posts page. If none is set
 * (e.g. the blog index is the homepage itself), the crumb is unlinked
 * rather than guessing a URL.
 */
function primeestate_get_insights_index_crumb(): array {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	return array(
		'label' => __( 'Insights', 'primeestate' ),
		'url'   => $posts_page_id ? (string) get_permalink( $posts_page_id ) : '',
	);
}

/**
 * BreadcrumbList JSON-LD (FR-053/FR-052) alongside the visible trail —
 * search engines use this independently of the visible markup to render
 * breadcrumb rich results.
 */
function primeestate_output_breadcrumb_schema( array $trail ): void {
	if ( count( $trail ) < 2 ) {
		return;
	}

	$items = array();

	foreach ( $trail as $index => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => $crumb['label'],
		);

		if ( $crumb['url'] ) {
			$item['item'] = $crumb['url'];
		}

		$items[] = $item;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			),
			JSON_UNESCAPED_SLASHES
		)
	);
}
