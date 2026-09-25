<?php
/**
 * T070 — PHPUnit coverage for agent own-property-only capability
 * enforcement (FR-037), via WordPress's own `map_meta_cap()` — the `agent`
 * role holds `edit_properties`/`delete_properties` but deliberately NOT
 * `edit_others_properties`/`delete_others_properties`
 * (agent-permissions.php), so WP core itself enforces "own only" for the
 * `property` CPT (`capability_type => ['property','properties']`,
 * `map_meta_cap => true`, post.php). No custom map_meta_cap filter exists
 * in this codebase for properties — this test is what proves that reliance
 * on WP core behavior is actually correct.
 */

class Test_Agent_Permissions extends WP_UnitTestCase {

	private int $agent_a;
	private int $agent_b;
	private int $property_manager_id;
	private int $own_property_id;
	private int $other_agent_property_id;

	public function set_up(): void {
		parent::set_up();

		$this->agent_a             = self::factory()->user->create( array( 'role' => 'agent' ) );
		$this->agent_b             = self::factory()->user->create( array( 'role' => 'agent' ) );
		$this->property_manager_id = self::factory()->user->create( array( 'role' => 'property_manager' ) );

		$this->own_property_id = self::factory()->post->create(
			array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $this->agent_a )
		);

		$this->other_agent_property_id = self::factory()->post->create(
			array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $this->agent_b )
		);
	}

	public function test_agent_can_edit_own_property(): void {
		wp_set_current_user( $this->agent_a );

		$this->assertTrue( current_user_can( 'edit_post', $this->own_property_id ) );
	}

	public function test_agent_cannot_edit_another_agents_property(): void {
		wp_set_current_user( $this->agent_a );

		$this->assertFalse( current_user_can( 'edit_post', $this->other_agent_property_id ) );
	}

	public function test_agent_can_delete_own_property(): void {
		wp_set_current_user( $this->agent_a );

		$this->assertTrue( current_user_can( 'delete_post', $this->own_property_id ) );
	}

	public function test_agent_cannot_delete_another_agents_property(): void {
		wp_set_current_user( $this->agent_a );

		$this->assertFalse( current_user_can( 'delete_post', $this->other_agent_property_id ) );
	}

	public function test_agent_can_publish_properties(): void {
		wp_set_current_user( $this->agent_a );

		$this->assertTrue( current_user_can( 'publish_properties' ) );
	}

	/**
	 * `property_manager` is explicitly "platform-wide, not own-only"
	 * (data-model.md §3) — holds `edit_others_properties`, unlike `agent`.
	 */
	public function test_property_manager_can_edit_any_property(): void {
		wp_set_current_user( $this->property_manager_id );

		$this->assertTrue( current_user_can( 'edit_post', $this->own_property_id ) );
		$this->assertTrue( current_user_can( 'edit_post', $this->other_agent_property_id ) );
	}

	/**
	 * `moderate_properties` (the approve/reject gate, T043) is deliberately
	 * NOT granted to `property_manager` even though it has broad edit/delete
	 * access — see agent-permissions.php's rationale.
	 */
	public function test_property_manager_cannot_moderate_properties(): void {
		wp_set_current_user( $this->property_manager_id );

		$this->assertFalse( current_user_can( 'moderate_properties' ) );
	}

	public function test_administrator_can_moderate_properties(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( current_user_can( 'moderate_properties' ) );
	}

	public function test_subscriber_cannot_publish_properties(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( current_user_can( 'publish_properties' ) );
		$this->assertFalse( current_user_can( 'edit_post', $this->own_property_id ) );
	}
}
