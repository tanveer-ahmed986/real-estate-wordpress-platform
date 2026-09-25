<?php
/**
 * T088 — verifies the admin overview counts (T089, FR-048) against known
 * seeded data. Integration-suite rather than unit because it exercises the
 * `property_status` taxonomy's own maintained term counts (WordPress core
 * machinery, not something this plugin computes itself) alongside multiple
 * CPTs and `count_users()`.
 */

class Test_Admin_Overview extends WP_UnitTestCase {

	public function test_counts_match_known_seeded_data(): void {
		$agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );
		self::factory()->user->create_many( 3, array( 'role' => 'subscriber' ) );

		$available_term = get_term_by( 'name', 'Available', 'property_status' );
		$sold_term      = get_term_by( 'name', 'Sold', 'property_status' );

		$this->assertInstanceOf( WP_Term::class, $available_term, 'property_status terms should already exist from activation seeding.' );
		$this->assertInstanceOf( WP_Term::class, $sold_term );

		// 2 published+Available, 1 published+Sold, 1 still pending (not yet approved).
		$published_available_1 = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $agent_id ) );
		$published_available_2 = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $agent_id ) );
		$published_sold        = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $agent_id ) );
		$pending_property       = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'pending', 'post_author' => $agent_id ) );

		wp_set_post_terms( $published_available_1, array( (int) $available_term->term_id ), 'property_status' );
		wp_set_post_terms( $published_available_2, array( (int) $available_term->term_id ), 'property_status' );
		wp_set_post_terms( $published_sold, array( (int) $sold_term->term_id ), 'property_status' );

		$property_id = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_author' => $agent_id ) );

		primeestate_create_inquiry( array(
			'property_id'              => $property_id,
			'agent_id'                 => $agent_id,
			'name'                     => 'A',
			'email'                    => 'a@example.com',
			'phone'                    => '',
			'message'                  => 'Hi',
			'preferred_contact_method' => 'email',
			'preferred_viewing_date'   => '',
			'budget'                   => null,
		) );
		$closed_inquiry = primeestate_create_inquiry( array(
			'property_id'              => $property_id,
			'agent_id'                 => $agent_id,
			'name'                     => 'B',
			'email'                    => 'b@example.com',
			'phone'                    => '',
			'message'                  => 'Hi',
			'preferred_contact_method' => 'email',
			'preferred_viewing_date'   => '',
			'budget'                   => null,
		) );
		update_post_meta( $closed_inquiry, '_pe_status', 'Closed' );

		primeestate_create_viewing_request( array(
			'property_id'    => $property_id,
			'agent_id'       => $agent_id,
			'name'           => 'C',
			'email'          => 'c@example.com',
			'phone'          => '555-0100',
			'preferred_date' => '2099-01-01',
			'preferred_time' => '10:00',
			'message'        => '',
		) );

		$counts = primeestate_get_admin_overview_counts();

		$this->assertSame( 5, $counts['total_properties'] );
		$this->assertSame( 2, $counts['active_properties'] );
		$this->assertSame( 1, $counts['sold_properties'] );
		$this->assertSame( 0, $counts['rented_properties'] );
		$this->assertSame( 1, $counts['pending_approval_properties'] );
		$this->assertSame( 1, $counts['agent_count'] );
		$this->assertSame( 3, $counts['user_count'] );
		$this->assertSame( 1, $counts['new_inquiry_count'] );
		$this->assertSame( 1, $counts['viewing_request_count'] );
		$this->assertCount( 1, $counts['recent_submissions'] );
		$this->assertSame( $pending_property, $counts['recent_submissions'][0]->ID );
	}

	public function test_counts_are_zero_on_a_fresh_install_with_no_data(): void {
		$counts = primeestate_get_admin_overview_counts();

		$this->assertSame( 0, $counts['total_properties'] );
		$this->assertSame( 0, $counts['agent_count'] );
		$this->assertSame( 0, $counts['new_inquiry_count'] );
		$this->assertSame( 0, $counts['viewing_request_count'] );
		$this->assertSame( array(), $counts['recent_properties'] );
	}
}
