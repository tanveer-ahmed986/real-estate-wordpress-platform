<?php
/**
 * `property_type` taxonomy (data-model.md §2, FR-006, FR-011).
 * Non-hierarchical, single-select in the UI (enforced at the form layer).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_property_type_taxonomy(): void {
	register_taxonomy(
		'property_type',
		array( 'property' ),
		array(
			'labels'            => array(
				'name'          => __( 'Property Types', 'primeestate' ),
				'singular_name' => __( 'Property Type', 'primeestate' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'property-type', 'with_front' => false ),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_property_type_taxonomy' );

function primeestate_seed_property_type_terms(): void {
	$terms = array( 'Apartment', 'House', 'Villa', 'Office', 'Shop', 'Warehouse', 'Land', 'Farmhouse', 'Penthouse', 'Commercial Building' );

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, 'property_type' ) ) {
			wp_insert_term( $term, 'property_type' );
		}
	}
}
add_action( 'primeestate_core_activated', 'primeestate_seed_property_type_terms' );
