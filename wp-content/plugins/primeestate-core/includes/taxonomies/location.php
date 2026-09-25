<?php
/**
 * Hierarchical `location` taxonomy — Country → State/Province → City → Area
 * (data-model.md §2, FR-007, FR-052).
 *
 * `'hierarchical' => true` in the rewrite args makes WordPress build nested
 * archive URLs from each term's ancestor chain automatically:
 * `/properties/{country}/{state}/{city}/{area}/` — this is the T010 browsing
 * URL structure. It is distinct from the `property` CPT's own flat
 * `/property/{slug}/` single-property permalink (see
 * includes/post-types/property.php) and from its `/properties/` archive
 * base, which WordPress resolves ahead of the deeper hierarchical term
 * patterns since those are more specific rewrite matches.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_location_taxonomy(): void {
	register_taxonomy(
		'location',
		array( 'property' ),
		array(
			'labels'            => array(
				'name'          => __( 'Locations', 'primeestate' ),
				'singular_name' => __( 'Location', 'primeestate' ),
				'parent_item'   => __( 'Parent Location', 'primeestate' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'         => 'properties',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_location_taxonomy' );
