<?php
/**
 * Agent profile usermeta registration (data-model.md §3, FR-034–FR-040).
 * Applies to any user (agents are a WP user role, not a separate CPT); write
 * access is gated by the `manage_agent_profile` capability plus an own-only
 * check performed by the profile-edit handler (not by `register_meta`'s
 * generic REST auth, which is not object-owner-aware for usermeta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_agent_profile_meta(): void {
	$string_fields = array(
		'_pe_agent_bio'     => 'wp_kses_post',
		'_pe_agent_phone'   => 'sanitize_text_field',
		'_pe_agent_whatsapp' => 'sanitize_text_field',
		'_pe_agent_license' => 'sanitize_text_field',
		'_pe_agent_office'  => 'sanitize_text_field',
	);

	foreach ( $string_fields as $meta_key => $sanitize_callback ) {
		register_meta(
			'user',
			$meta_key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitize_callback,
			)
		);
	}

	register_meta(
		'user',
		'_pe_agent_areas',
		array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => false,
			'description'  => 'Array of `location` taxonomy term IDs the agent serves.',
			'sanitize_callback' => static function ( $value ) {
				return is_array( $value ) ? array_map( 'absint', $value ) : array();
			},
		)
	);

	register_meta(
		'user',
		'_pe_agent_specializations',
		array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => false,
			'description'  => 'Array of free-text specialization strings, e.g. "Luxury", "Commercial".',
			'sanitize_callback' => static function ( $value ) {
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
			},
		)
	);

	register_meta(
		'user',
		'_pe_agent_socials',
		array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => false,
			'description'  => 'Array of {platform, url}; URLs sanitized with esc_url_raw.',
			'sanitize_callback' => 'primeestate_sanitize_agent_socials',
		)
	);
}
add_action( 'init', 'primeestate_register_agent_profile_meta' );

/**
 * `PATCH /wp-json/primeestate/v1/agents/{id}` (T076) — not part of the
 * original OpenAPI contract; added because the agent profile-edit form
 * needs *some* write path for usermeta, and `register_meta( 'user', ...,
 * 'show_in_rest' => false )` deliberately keeps these fields off WP core's
 * generic `/wp/v2/users` endpoint (that endpoint has no owner-aware
 * per-field gating, so exposing writable profile meta through it would
 * let any authenticated user with `edit_users` touch any agent's profile —
 * `manage_agent_profile` plus an explicit ID match here is far narrower).
 * The OpenAPI contract is updated alongside this file.
 */
function primeestate_register_agent_profile_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/agents/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'primeestate_handle_update_agent_profile',
			'permission_callback' => primeestate_rest_capability_permission( 'manage_agent_profile' ),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_agent_profile_route' );

function primeestate_handle_update_agent_profile( WP_REST_Request $request ) {
	$agent_id = (int) $request['id'];

	// "Own only" for `manage_agent_profile` — administrators bypass via
	// `edit_others_properties`, the same "sees/manages everything" signal
	// used everywhere else in this plugin (inquiry-handler.php,
	// viewing-request.php), so no third capability concept is introduced.
	if ( get_current_user_id() !== $agent_id && ! current_user_can( 'edit_others_properties' ) ) {
		return primeestate_rest_error( 'forbidden', __( 'You can only edit your own profile.', 'primeestate' ), 403 );
	}

	if ( ! get_userdata( $agent_id ) ) {
		return primeestate_rest_error( 'not_found', __( 'Agent not found.', 'primeestate' ), 404 );
	}

	// Maps the request's plain field name to its usermeta key + sanitizer —
	// deliberately explicit rather than derived by string-stripping the meta
	// key (a `ltrim( $key, '_pe_agent_' )` first draft of this looked
	// plausible but silently mangled "phone" to "hone", since `ltrim()`
	// strips a character *set*, not a prefix string; every character in
	// "phone" happens to also appear in "_pe_agent_").
	$fields = array(
		'bio'      => array( '_pe_agent_bio', 'wp_kses_post' ),
		'phone'    => array( '_pe_agent_phone', 'sanitize_text_field' ),
		'whatsapp' => array( '_pe_agent_whatsapp', 'sanitize_text_field' ),
		'license'  => array( '_pe_agent_license', 'sanitize_text_field' ),
		'office'   => array( '_pe_agent_office', 'sanitize_text_field' ),
	);

	foreach ( $fields as $param_name => list( $meta_key, $sanitize_callback ) ) {
		$value = $request->get_param( $param_name );

		if ( null !== $value ) {
			update_user_meta( $agent_id, $meta_key, call_user_func( $sanitize_callback, $value ) );
		}
	}

	if ( null !== $request->get_param( 'areas' ) ) {
		update_user_meta( $agent_id, '_pe_agent_areas', array_map( 'absint', (array) $request->get_param( 'areas' ) ) );
	}

	if ( null !== $request->get_param( 'specializations' ) ) {
		update_user_meta( $agent_id, '_pe_agent_specializations', array_map( 'sanitize_text_field', (array) $request->get_param( 'specializations' ) ) );
	}

	if ( null !== $request->get_param( 'socials' ) ) {
		update_user_meta( $agent_id, '_pe_agent_socials', primeestate_sanitize_agent_socials( (array) $request->get_param( 'socials' ) ) );
	}

	return new WP_REST_Response( primeestate_agent_profile_to_schema( $agent_id ), 200 );
}

function primeestate_agent_profile_to_schema( int $agent_id ): array {
	$user = get_userdata( $agent_id );

	return array(
		'id'              => $agent_id,
		'display_name'    => $user ? $user->display_name : '',
		'bio'             => get_user_meta( $agent_id, '_pe_agent_bio', true ),
		'phone'           => get_user_meta( $agent_id, '_pe_agent_phone', true ),
		'whatsapp'        => get_user_meta( $agent_id, '_pe_agent_whatsapp', true ),
		'license'         => get_user_meta( $agent_id, '_pe_agent_license', true ),
		'office'          => get_user_meta( $agent_id, '_pe_agent_office', true ),
		'areas'           => get_user_meta( $agent_id, '_pe_agent_areas', true ) ?: array(),
		'specializations' => get_user_meta( $agent_id, '_pe_agent_specializations', true ) ?: array(),
		'socials'         => get_user_meta( $agent_id, '_pe_agent_socials', true ) ?: array(),
	);
}

function primeestate_sanitize_agent_socials( $value ): array {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$sanitized = array();

	foreach ( $value as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['platform'] ) || empty( $entry['url'] ) ) {
			continue;
		}

		$sanitized[] = array(
			'platform' => sanitize_text_field( $entry['platform'] ),
			'url'      => esc_url_raw( $entry['url'] ),
		);
	}

	return $sanitized;
}
