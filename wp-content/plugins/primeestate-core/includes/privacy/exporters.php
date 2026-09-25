<?php
/**
 * Personal-data exporters (research.md §9, constitution Principle XI) —
 * wires PrimeEstate's three personal-data-bearing records (Inquiry, Viewing
 * Request, Favorite) into WordPress's native "Export Personal Data" tool
 * (Tools → Export Personal Data) instead of building a custom GDPR/export
 * flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_privacy_exporters( array $exporters ): array {
	$exporters['primeestate-inquiries'] = array(
		'exporter_friendly_name' => __( 'PrimeEstate Inquiries', 'primeestate' ),
		'callback'               => 'primeestate_export_inquiries',
	);

	$exporters['primeestate-viewing-requests'] = array(
		'exporter_friendly_name' => __( 'PrimeEstate Viewing Requests', 'primeestate' ),
		'callback'               => 'primeestate_export_viewing_requests',
	);

	$exporters['primeestate-favorites'] = array(
		'exporter_friendly_name' => __( 'PrimeEstate Favorites', 'primeestate' ),
		'callback'               => 'primeestate_export_favorites',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'primeestate_register_privacy_exporters' );

function primeestate_export_inquiries( string $email_address, int $page = 1 ): array {
	return primeestate_export_records_by_email( 'pe_inquiry', '_pe_email', $email_address, $page );
}

function primeestate_export_viewing_requests( string $email_address, int $page = 1 ): array {
	return primeestate_export_records_by_email( 'pe_viewing_request', '_pe_email', $email_address, $page );
}

/**
 * Shared query for both Inquiry and Viewing Request exports — both store the
 * submitter's email in the same meta key and need identical pagination
 * handling for the Personal Data Exporter tool's batched-request contract.
 */
function primeestate_export_records_by_email( string $post_type, string $email_meta_key, string $email_address, int $page ): array {
	$per_page = 100;

	$query = new WP_Query(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_key'       => $email_meta_key,
			'meta_value'     => $email_address,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$export_items = array();

	foreach ( $query->posts as $post ) {
		$data = array();
		foreach ( get_post_meta( $post->ID ) as $meta_key => $meta_values ) {
			if ( 0 === strpos( $meta_key, '_pe_' ) ) {
				$data[] = array(
					'name'  => ltrim( $meta_key, '_' ),
					'value' => maybe_unserialize( $meta_values[0] ?? '' ),
				);
			}
		}

		$export_items[] = array(
			'group_id'    => $post_type,
			'group_label' => $post_type,
			'item_id'     => $post_type . '-' . $post->ID,
			'data'        => $data,
		);
	}

	return array(
		'data' => $export_items,
		'done' => $query->max_num_pages <= $page,
	);
}

function primeestate_export_favorites( string $email_address, int $page = 1 ): array {
	$user = get_user_by( 'email', $email_address );

	if ( ! $user || $page > 1 ) {
		return array( 'data' => array(), 'done' => true );
	}

	$favorites = get_user_meta( $user->ID, '_pe_favorites', true );

	if ( ! is_array( $favorites ) || empty( $favorites ) ) {
		return array( 'data' => array(), 'done' => true );
	}

	return array(
		'data' => array(
			array(
				'group_id'    => 'primeestate_favorites',
				'group_label' => __( 'Favorites', 'primeestate' ),
				'item_id'     => 'favorites-' . $user->ID,
				'data'        => array(
					array(
						'name'  => __( 'Favorited property IDs', 'primeestate' ),
						'value' => implode( ', ', array_map( 'absint', $favorites ) ),
					),
				),
			),
		),
		'done' => true,
	);
}
