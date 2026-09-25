<?php
/**
 * `pe_inquiry` post meta registration (data-model.md §5, FR-025–FR-029).
 * Not exposed via `show_in_rest` here — the custom `primeestate/v1`
 * endpoints (includes/rest/, includes/inquiries/) own read/write access with
 * their own authorization, not the generic WP REST post-meta surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_inquiry_meta(): void {
	$string_fields = array(
		'_pe_name',
		'_pe_email',
		'_pe_phone',
		'_pe_preferred_contact_method', // phone | email | whatsapp
		'_pe_preferred_viewing_date',
		'_pe_status', // New | Contacted | Qualified | Viewing Scheduled | Closed | Spam
	);

	foreach ( $string_fields as $meta_key ) {
		register_post_meta(
			'pe_inquiry',
			$meta_key,
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
	}

	register_post_meta(
		'pe_inquiry',
		'_pe_message',
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'pe_inquiry',
		'_pe_property_id',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'pe_inquiry',
		'_pe_agent_id',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'pe_inquiry',
		'_pe_budget',
		array(
			'type'         => 'number',
			'single'       => true,
			'show_in_rest' => false,
		)
	);

	register_post_meta(
		'pe_inquiry',
		'_pe_notes',
		array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => false,
			'description'  => 'Array of {author, note, date} — staff-only, never exposed to the submitter.',
		)
	);
}
add_action( 'init', 'primeestate_register_inquiry_meta' );
