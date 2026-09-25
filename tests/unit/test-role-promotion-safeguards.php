<?php
/**
 * T092 (FR-034) — `primeestate_enforce_role_promotion_capability_safeguard()`
 * (agent-permissions.php). WordPress core's own `WP_User::set_role()`
 * already clears the previous role's capabilities; this test targets
 * specifically the gap it does NOT cover: capabilities granted directly to
 * a user (`WP_User::add_cap()`) rather than through a role, which survive a
 * role change untouched unless this safeguard strips them.
 */

class Test_Role_Promotion_Safeguards extends WP_UnitTestCase {

	public function test_stray_individual_capability_is_stripped_on_promotion_to_agent(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( 'delete_users' );

		$this->assertTrue( user_can( $user_id, 'delete_users' ), 'Sanity check: the stray cap should be active before the role change.' );

		$user->set_role( 'agent' );

		$this->assertFalse( user_can( $user_id, 'delete_users' ), 'A capability never granted by the agent role should not survive promotion.' );
	}

	public function test_promotion_to_agent_still_grants_the_full_mapped_agent_capability_set(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( 'some_unrelated_stray_cap' );

		$user->set_role( 'agent' );

		$this->assertTrue( user_can( $user_id, 'manage_properties' ) );
		$this->assertTrue( user_can( $user_id, 'edit_properties' ) );
		$this->assertTrue( user_can( $user_id, 'publish_properties' ) );
		$this->assertTrue( user_can( $user_id, 'view_inquiries' ) );
		$this->assertFalse( user_can( $user_id, 'some_unrelated_stray_cap' ) );
	}

	public function test_stray_capability_is_stripped_on_promotion_to_property_manager(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( 'moderate_properties' ); // Not part of property_manager's mapped set (agent-permissions.php).

		$user->set_role( 'property_manager' );

		$this->assertFalse( user_can( $user_id, 'moderate_properties' ) );
		$this->assertTrue( user_can( $user_id, 'edit_others_properties' ) );
	}

	public function test_safeguard_does_not_run_for_roles_outside_agent_and_property_manager(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( 'some_unrelated_stray_cap' );

		$user->set_role( 'editor' );

		$this->assertTrue( user_can( $user_id, 'some_unrelated_stray_cap' ), 'The safeguard only targets promotion to Agent/Property Manager (FR-034 scope); other role changes are untouched.' );
	}
}
