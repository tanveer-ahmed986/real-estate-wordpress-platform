<?php
/**
 * `property_status` taxonomy (data-model.md §2, FR-006).
 * Market status — independent of the `post_status` publish workflow;
 * staff-editable at any time (see data-model.md §1 "Workflow / state
 * transitions").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_property_status_taxonomy(): void {
	register_taxonomy(
		'property_status',
		array( 'property' ),
		array(
			'labels'            => array(
				'name'          => __( 'Property Statuses', 'primeestate' ),
				'singular_name' => __( 'Property Status', 'primeestate' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'property-status', 'with_front' => false ),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_property_status_taxonomy' );

function primeestate_seed_property_status_terms(): void {
	$terms = array( 'Available', 'Pending', 'Sold', 'Rented', 'Off Market' );

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, 'property_status' ) ) {
			wp_insert_term( $term, 'property_status' );
		}
	}
}
add_action( 'primeestate_core_activated', 'primeestate_seed_property_status_terms' );
