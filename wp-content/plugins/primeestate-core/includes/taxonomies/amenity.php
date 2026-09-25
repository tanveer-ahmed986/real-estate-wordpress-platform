<?php
/**
 * `amenity` taxonomy (data-model.md §2, FR-008, FR-011). Non-hierarchical,
 * multi-select. Seed list is extensible — additional terms can be added via
 * wp-admin without a code change.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_amenity_taxonomy(): void {
	register_taxonomy(
		'amenity',
		array( 'property' ),
		array(
			'labels'            => array(
				'name'          => __( 'Amenities', 'primeestate' ),
				'singular_name' => __( 'Amenity', 'primeestate' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'amenity', 'with_front' => false ),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_amenity_taxonomy' );

function primeestate_seed_amenity_terms(): void {
	$terms = array(
		'Swimming Pool',
		'Gym',
		'Parking',
		'Security',
		'Elevator',
		'Garden',
		'Balcony',
		'Central Air',
		'Backup Generator',
		'Solar',
	);

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, 'amenity' ) ) {
			wp_insert_term( $term, 'amenity' );
		}
	}
}
add_action( 'primeestate_core_activated', 'primeestate_seed_amenity_terms' );
