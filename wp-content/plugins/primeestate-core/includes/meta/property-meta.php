<?php
/**
 * Property post meta registration (data-model.md §1). All numeric fields are
 * registered with explicit `type` so `meta_query` range filtering (FR-011)
 * compares numerically, not lexicographically.
 *
 * Assigned Agent is intentionally NOT a separate meta field here — `post_author`
 * is reused as the agent link (data-model.md §1), which keeps WP-native
 * ownership/capability checks (`current_user_can`, `map_meta_cap`) working
 * without a parallel meta-based permission system.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_property_meta(): void {
	$string_fields = array(
		'_pe_short_description' => 'Short description shown on cards/archive.',
		'_pe_reference'         => 'System-generated unique reference number (e.g. PE-000123).',
		'_pe_price_type'        => 'fixed | starting_from | on_request',
		'_pe_currency'          => 'ISO 4217 currency code.',
		'_pe_address'           => 'Street address (may be withheld from public display).',
		'_pe_postal_code'       => 'Postal / ZIP code.',
	);

	foreach ( $string_fields as $meta_key => $description ) {
		register_post_meta(
			'property',
			$meta_key,
			array(
				'type'              => 'string',
				'description'       => $description,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	$number_fields = array( '_pe_price', '_pe_previous_price', '_pe_area', '_pe_land_area', '_pe_lat', '_pe_lng' );

	foreach ( $number_fields as $meta_key ) {
		register_post_meta(
			'property',
			$meta_key,
			array(
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}

	$integer_fields = array(
		'_pe_bedrooms',
		'_pe_bathrooms',
		'_pe_living_rooms',
		'_pe_parking_spaces',
		'_pe_year_built',
		'_pe_floor',
		'_pe_total_floors',
		'_pe_submitted_by',
	);

	foreach ( $integer_fields as $meta_key ) {
		register_post_meta(
			'property',
			$meta_key,
			array(
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}

	register_post_meta(
		'property',
		'_pe_negotiable',
		array(
			'type'         => 'boolean',
			'single'       => true,
			'default'      => false,
			'show_in_rest' => true,
		)
	);

	register_post_meta(
		'property',
		'_primeestate_demo',
		array(
			'type'         => 'boolean',
			'single'       => true,
			'default'      => false,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'property',
		'_pe_gallery',
		array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'sanitize_callback' => 'primeestate_sanitize_gallery_meta',
		)
	);
}
add_action( 'init', 'primeestate_register_property_meta' );

/**
 * Gallery meta must be an array of attachment IDs that actually exist and
 * are images — silently drops anything else rather than erroring, since this
 * runs as a REST/meta sanitize callback.
 */
function primeestate_sanitize_gallery_meta( $value ): array {
	if ( ! is_array( $value ) ) {
		return array();
	}

	return array_values(
		array_filter(
			array_map( 'absint', $value ),
			static function ( $attachment_id ) {
				return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id );
			}
		)
	);
}
