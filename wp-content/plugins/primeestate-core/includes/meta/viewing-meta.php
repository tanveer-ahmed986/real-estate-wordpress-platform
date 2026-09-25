<?php
/**
 * `pe_viewing_request` post meta registration (data-model.md §6, FR-030–FR-033).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_viewing_meta(): void {
	$string_fields = array(
		'_pe_name',
		'_pe_email',
		'_pe_phone',
		'_pe_preferred_date',
		'_pe_preferred_time',
		'_pe_message',
		'_pe_status', // Requested | Confirmed | Rescheduled | Completed | Cancelled
	);

	foreach ( $string_fields as $meta_key ) {
		register_post_meta(
			'pe_viewing_request',
			$meta_key,
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
	}

	register_post_meta(
		'pe_viewing_request',
		'_pe_property_id',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'pe_viewing_request',
		'_pe_agent_id',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		)
	);
}
add_action( 'init', 'primeestate_register_viewing_meta' );
