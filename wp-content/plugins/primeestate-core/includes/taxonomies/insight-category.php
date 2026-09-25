<?php
/**
 * `insight_category` taxonomy (data-model.md §2, FR-050) — categorizes the
 * native `post` Insight/Blog articles (see data-model.md §9).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_insight_category_taxonomy(): void {
	register_taxonomy(
		'insight_category',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Insight Categories', 'primeestate' ),
				'singular_name' => __( 'Insight Category', 'primeestate' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'insights/category', 'with_front' => false ),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_insight_category_taxonomy' );

function primeestate_seed_insight_category_terms(): void {
	$terms = array(
		'Buying Guide',
		'Selling Guide',
		'Investment',
		'Market Insights',
		'Interior Design',
		'Neighborhood Guides',
	);

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, 'insight_category' ) ) {
			wp_insert_term( $term, 'insight_category' );
		}
	}
}
add_action( 'primeestate_core_activated', 'primeestate_seed_insight_category_terms' );
