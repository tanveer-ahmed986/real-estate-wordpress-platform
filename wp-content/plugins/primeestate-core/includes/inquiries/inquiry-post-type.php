<?php
/**
 * `pe_inquiry` Custom Post Type (data-model.md §5, FR-025–FR-029).
 *
 * Staff-only record with NO wp-admin UI of its own — inquiries are created
 * via the public REST endpoint (`POST /inquiries`, no auth) and managed via
 * the staff-only REST endpoints (`GET/PATCH /inquiries`, T052/T053) surfaced
 * through the Agent Dashboard / Admin Oversight UI, not a post-edit screen.
 *
 * `capability_type` is left at its default (`post`) so only roles that
 * already hold core `edit_posts`/`edit_others_posts` (administrator, editor)
 * could ever touch these programmatically — but the real "agent sees only
 * their assigned inquiries" access control (FR-029/FR-038) is enforced by
 * the custom `view_inquiries` capability plus an explicit ownership check
 * against `_pe_agent_id`, applied in the REST layer, not by WordPress's
 * post-author meta-cap system (see includes/agents/agent-permissions.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_inquiry_post_type(): void {
	$labels = array(
		'name'               => __( 'Inquiries', 'primeestate' ),
		'singular_name'      => __( 'Inquiry', 'primeestate' ),
		'edit_item'          => __( 'View / Update Inquiry', 'primeestate' ),
		'search_items'       => __( 'Search Inquiries', 'primeestate' ),
		'not_found'          => __( 'No inquiries found', 'primeestate' ),
		'not_found_in_trash' => __( 'No inquiries found in Trash', 'primeestate' ),
		'all_items'          => __( 'Inquiries', 'primeestate' ),
	);

	register_post_type(
		'pe_inquiry',
		array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'custom-fields' ),
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'primeestate_register_inquiry_post_type' );
