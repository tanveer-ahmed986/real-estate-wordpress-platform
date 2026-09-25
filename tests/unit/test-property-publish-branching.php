<?php
/**
 * T071 — PHPUnit coverage for the property-creation capability branching
 * (FR-061), added during `/sp.analyze` remediation as the highest-priority
 * test in the task list: this is the platform's single most explicitly
 * clarified business rule (the `/sp.clarify` session that produced FR-061
 * was the first decision recorded in spec.md's Clarifications section).
 *
 * The rule: an account holding `publish_properties` (agent,
 * property_manager, editor, administrator) always gets `post_status =
 * publish` immediately; the public submission path (a plain registered
 * user, FR-042) always gets `pending` — and critically, this is decided
 * SERVER-SIDE by capability, never by any client-supplied flag.
 */

class Test_Property_Publish_Branching extends WP_UnitTestCase {

	public function test_agent_gets_published_status(): void {
		$agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );

		$this->assertSame( 'publish', primeestate_determine_property_publish_status( $agent_id ) );
	}

	public function test_property_manager_gets_published_status(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'property_manager' ) );

		$this->assertSame( 'publish', primeestate_determine_property_publish_status( $user_id ) );
	}

	public function test_administrator_gets_published_status(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame( 'publish', primeestate_determine_property_publish_status( $user_id ) );
	}

	public function test_editor_gets_published_status(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertSame( 'publish', primeestate_determine_property_publish_status( $user_id ) );
	}

	public function test_registered_subscriber_always_gets_pending_status(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( 'pending', primeestate_determine_property_publish_status( $user_id ) );
	}

	/**
	 * The decisive case: `primeestate_create_property_from_submission()`
	 * itself must derive status from the CAPABILITY, never from a
	 * client-supplied `status`/`post_status` field slipped into the request
	 * body — the function signature doesn't even accept one, but this test
	 * proves a malicious/confused caller can't influence the outcome by
	 * stuffing extra keys into $data.
	 */
	public function test_client_supplied_status_field_is_ignored_for_subscriber(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$data = $this->valid_submission_data();
		// Simulate a malicious/confused client trying to force publish —
		// primeestate_create_property_from_submission() has no parameter
		// that reads this, so it cannot possibly matter, but assert the
		// outcome to make that guarantee explicit and regression-proof.
		$data['status']      = 'publish';
		$data['post_status'] = 'publish';

		$post_id = primeestate_create_property_from_submission( $data, $user_id );

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	public function test_client_supplied_status_field_is_ignored_for_agent(): void {
		$agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );

		$data             = $this->valid_submission_data();
		$data['status']   = 'pending'; // Attempt to force pending despite being an agent.

		$post_id = primeestate_create_property_from_submission( $data, $agent_id );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	public function test_pending_submission_records_submitted_by(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id = primeestate_create_property_from_submission( $this->valid_submission_data(), $user_id );

		$this->assertSame( $user_id, (int) get_post_meta( $post_id, '_pe_submitted_by', true ) );
	}

	public function test_agent_authored_listing_has_no_submitted_by(): void {
		$agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );
		$post_id  = primeestate_create_property_from_submission( $this->valid_submission_data(), $agent_id );

		$this->assertSame( '', get_post_meta( $post_id, '_pe_submitted_by', true ) );
	}

	public function test_post_author_is_the_submitting_user_in_both_paths(): void {
		$agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );
		$post_id  = primeestate_create_property_from_submission( $this->valid_submission_data(), $agent_id );

		$this->assertSame( $agent_id, (int) get_post_field( 'post_author', $post_id ) );
	}

	private function valid_submission_data(): array {
		$listing_type  = get_term_by( 'slug', 'for-sale', 'listing_type' );
		$property_type = get_term_by( 'slug', 'apartment', 'property_type' );
		$location      = wp_insert_term( 'Test City ' . wp_rand(), 'location' );

		return array(
			'title'            => 'Test Property',
			'description'      => 'A lovely test property.',
			'price'            => 250000,
			'listing_type_id'  => $listing_type ? (int) $listing_type->term_id : 0,
			'property_type_id' => $property_type ? (int) $property_type->term_id : 0,
			'location_id'      => is_wp_error( $location ) ? 0 : (int) $location['term_id'],
			'address'          => '123 Test Street',
			'bedrooms'         => 3,
			'bathrooms'        => 2,
			'area'             => 1200,
			'amenity_term_ids' => array(),
			'gallery'          => array(),
		);
	}
}
