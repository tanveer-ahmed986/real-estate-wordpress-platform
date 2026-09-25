<?php
/**
 * Personal-data erasers (research.md §9, constitution Principle XI) — wires
 * PrimeEstate's personal-data-bearing records into WordPress's native
 * "Erase Personal Data" tool. Inquiries/viewing requests are anonymized
 * (contact fields blanked) rather than deleted outright, preserving the
 * operational record (status history, which property was involved) for the
 * business, consistent with research.md §9's indefinite-retention decision
 * applying to non-personal fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_privacy_erasers( array $erasers ): array {
	$erasers['primeestate-inquiries'] = array(
		'eraser_friendly_name' => __( 'PrimeEstate Inquiries', 'primeestate' ),
		'callback'              => 'primeestate_erase_inquiries',
	);

	$erasers['primeestate-viewing-requests'] = array(
		'eraser_friendly_name' => __( 'PrimeEstate Viewing Requests', 'primeestate' ),
		'callback'              => 'primeestate_erase_viewing_requests',
	);

	$erasers['primeestate-favorites'] = array(
		'eraser_friendly_name' => __( 'PrimeEstate Favorites', 'primeestate' ),
		'callback'              => 'primeestate_erase_favorites',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'primeestate_register_privacy_erasers' );

function primeestate_erase_inquiries( string $email_address, int $page = 1 ): array {
	return primeestate_anonymize_records_by_email( 'pe_inquiry', $email_address, $page );
}

function primeestate_erase_viewing_requests( string $email_address, int $page = 1 ): array {
	return primeestate_anonymize_records_by_email( 'pe_viewing_request', $email_address, $page );
}

function primeestate_anonymize_records_by_email( string $post_type, string $email_address, int $page ): array {
	$per_page = 100;

	$query = new WP_Query(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_key'       => '_pe_email',
			'meta_value'     => $email_address,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$items_removed = false;

	foreach ( $query->posts as $post ) {
		update_post_meta( $post->ID, '_pe_name', __( 'Redacted', 'primeestate' ) );
		update_post_meta( $post->ID, '_pe_email', '' );
		update_post_meta( $post->ID, '_pe_phone', '' );
		update_post_meta( $post->ID, '_pe_message', '' );
		$items_removed = true;
	}

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => $query->max_num_pages <= $page,
	);
}

function primeestate_erase_favorites( string $email_address, int $page = 1 ): array {
	$user = get_user_by( 'email', $email_address );

	if ( $user && $page <= 1 ) {
		delete_user_meta( $user->ID, '_pe_favorites' );
	}

	return array(
		'items_removed'  => (bool) $user,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}
