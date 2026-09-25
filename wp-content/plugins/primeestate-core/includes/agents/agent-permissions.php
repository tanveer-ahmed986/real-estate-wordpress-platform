<?php
/**
 * Roles and capabilities (data-model.md §3, §10; FR-034–FR-040).
 *
 * Runs once on plugin activation via the `primeestate_core_activated` action
 * (see primeestate-core.php) — capability grants are not re-applied on every
 * request. If new capabilities are added in a future update, bump
 * PRIMEESTATE_CORE_VERSION and re-run this on upgrade (not implemented yet —
 * out of scope for initial setup).
 *
 * Capability model:
 * - `edit_properties` / `publish_properties` / `delete_properties` are the
 *   WordPress-core-generated meta capabilities for the `property` CPT
 *   (registered with `capability_type => ['property','properties']`,
 *   `map_meta_cap => true` in includes/post-types/property.php). WordPress's
 *   own map_meta_cap() enforces "own post only" automatically for any role
 *   that holds `edit_properties`/`delete_properties` but NOT
 *   `edit_others_properties`/`delete_others_properties` — that is how the
 *   `agent` role's own-only restriction is enforced, with no custom code.
 * - `manage_properties` is a coarse custom capability gating general
 *   properties-admin-area access (menus, dashboards) — not itself an
 *   ownership check.
 * - `moderate_properties` is a custom capability (beyond the six named in
 *   tasks.md T022) that gates the `PATCH /properties/{id}/moderate`
 *   endpoint (FR-043, T043). It is required in addition to
 *   `edit_others_properties` so that `property_manager` — which needs
 *   platform-wide edit/delete for day-to-day management — is NOT thereby
 *   able to approve/reject public submissions, matching the capability
 *   matrix (data-model.md §10: "Approve/reject public submissions" is
 *   Administrator/Editor only).
 * - `view_inquiries` gates both inquiry and viewing-request staff access
 *   (data-model.md §10 lumps these into one matrix row). "Assigned only"
 *   for the `agent` role is enforced by an explicit ownership check against
 *   `_pe_agent_id` in the REST layer (includes/inquiries/, includes/viewing/),
 *   not by WordPress's meta-cap system, since usermeta/postmeta ownership
 *   there isn't expressed via `post_author`.
 * - `manage_agent_profile` gates writing agent-profile usermeta; "own only"
 *   for the `agent` role is enforced by an explicit user-ID check in the
 *   profile-edit handler, for the same reason.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string[] Full set of WP-core-pattern meta capabilities for the
 *                   `property` CPT's capability_type => ['property','properties'].
 */
function primeestate_full_property_capabilities(): array {
	return array(
		'edit_properties',
		'edit_others_properties',
		'edit_published_properties',
		'edit_private_properties',
		'publish_properties',
		'read_private_properties',
		'delete_properties',
		'delete_others_properties',
		'delete_published_properties',
		'delete_private_properties',
	);
}

function primeestate_register_roles_and_capabilities(): void {
	$property_manager_caps = array_fill_keys(
		array_merge(
			array( 'read', 'upload_files', 'manage_properties', 'view_inquiries' ),
			primeestate_full_property_capabilities()
		),
		true
	);
	add_role( 'property_manager', __( 'Property Manager', 'primeestate' ), $property_manager_caps );

	$agent_caps = array(
		'read'                       => true,
		'upload_files'               => true,
		'manage_properties'          => true,
		'edit_properties'            => true,
		'edit_published_properties'  => true,
		'publish_properties'         => true,
		'read_private_properties'    => true,
		'delete_properties'          => true,
		'delete_published_properties' => true,
		'view_inquiries'             => true,
		'manage_agent_profile'       => true,
	);
	add_role( 'agent', __( 'Agent', 'primeestate' ), $agent_caps );

	// Re-apply caps if the role already existed from a previous activation
	// (add_role() is a no-op when the role key already exists).
	primeestate_grant_caps_to_role( 'property_manager', $property_manager_caps );
	primeestate_grant_caps_to_role( 'agent', $agent_caps );

	$staff_caps = array_merge(
		primeestate_full_property_capabilities(),
		array( 'manage_properties', 'moderate_properties', 'view_inquiries', 'manage_agent_profile' )
	);
	primeestate_grant_caps_to_role( 'administrator', array_fill_keys( $staff_caps, true ) );
	primeestate_grant_caps_to_role( 'editor', array_fill_keys( $staff_caps, true ) );
}
add_action( 'primeestate_core_activated', 'primeestate_register_roles_and_capabilities' );

function primeestate_grant_caps_to_role( string $role_name, array $caps ): void {
	$role = get_role( $role_name );

	if ( null === $role ) {
		return;
	}

	foreach ( $caps as $cap => $grant ) {
		if ( ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap, (bool) $grant );
		}
	}
}

/**
 * T092 (FR-034): a role-promotion safeguard, not a duplicate of the role
 * registration above. `WP_User::set_role()` (fired by wp-admin's own Users
 * screen when an administrator promotes someone to Agent/Property Manager)
 * already clears the *previous* role's capabilities and adds only the new
 * role's — but it does not touch any capability ever granted directly to
 * that specific user via `WP_User::add_cap()` rather than through a role
 * (e.g. a stray leftover from an earlier, different role change, or a
 * one-off grant an administrator made by hand). Those per-user capabilities
 * survive a role change untouched by WordPress core, which would silently
 * violate FR-034's "exactly the mapped capability set and nothing more" the
 * moment such a stray grant existed. This hook closes that gap by stripping
 * anything in the user's own capability list that isn't the new role itself
 * or part of that role's exact registered set.
 */
function primeestate_enforce_role_promotion_capability_safeguard( int $user_id, string $new_role, array $old_roles ): void {
	if ( ! in_array( $new_role, array( 'agent', 'property_manager' ), true ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	$role = get_role( $new_role );

	if ( ! $user || ! $role ) {
		return;
	}

	$allowed_caps = array_merge( array( $new_role => true ), $role->capabilities );

	// `$user->caps` (distinct from `$user->allcaps`) holds only per-user
	// capabilities — the ones a role change does not automatically clean up.
	foreach ( $user->caps as $cap => $granted ) {
		if ( ! array_key_exists( $cap, $allowed_caps ) ) {
			$user->remove_cap( $cap );
		}
	}
}
add_action( 'set_user_role', 'primeestate_enforce_role_promotion_capability_safeguard', 10, 3 );
