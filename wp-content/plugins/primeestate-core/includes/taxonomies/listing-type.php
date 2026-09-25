<?php
/**
 * `listing_type` taxonomy (data-model.md §2, FR-006, FR-010).
 * Drives the Buy/Rent/Lease search mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_listing_type_taxonomy(): void {
	register_taxonomy(
		'listing_type',
		array( 'property' ),
		array(
			'labels'            => array(
				'name'          => __( 'Listing Types', 'primeestate' ),
				'singular_name' => __( 'Listing Type', 'primeestate' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'listing-type', 'with_front' => false ),
			'query_var'         => true,
		)
	);
}
add_action( 'init', 'primeestate_register_listing_type_taxonomy' );

function primeestate_seed_listing_type_terms(): void {
	$terms = array( 'For Sale', 'For Rent', 'For Lease' );

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, 'listing_type' ) ) {
			wp_insert_term( $term, 'listing_type' );
		}
	}
}
add_action( 'primeestate_core_activated', 'primeestate_seed_listing_type_terms' );
