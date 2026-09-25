<?php
/**
 * Phase 9 (T085-T087, FR-046/FR-049) — "own data" scoping for the
 * registered-user dashboard, and the cache-exclusion coverage list T087
 * was tasked with finalizing.
 */

class Test_User_Dashboard extends WP_UnitTestCase {

	public function test_own_inquiries_scoped_by_account_email_not_by_user_id(): void {
		$property_id = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish' ) );

		$mine  = primeestate_create_inquiry( array(
			'property_id'              => $property_id,
			'agent_id'                 => 0,
			'name'                     => 'Me',
			'email'                    => 'me@example.com',
			'phone'                    => '',
			'message'                  => 'Interested.',
			'preferred_contact_method' => 'email',
			'preferred_viewing_date'   => '',
			'budget'                   => null,
		) );
		primeestate_create_inquiry( array(
			'property_id'              => $property_id,
			'agent_id'                 => 0,
			'name'                     => 'Someone Else',
			'email'                    => 'other@example.com',
			'phone'                    => '',
			'message'                  => 'Also interested.',
			'preferred_contact_method' => 'email',
			'preferred_viewing_date'   => '',
			'budget'                   => null,
		) );

		$own = primeestate_get_own_inquiries( 'me@example.com' );

		$this->assertCount( 1, $own );
		$this->assertSame( $mine, $own[0]->ID );
	}

	public function test_own_inquiries_with_empty_email_returns_nothing(): void {
		$this->assertSame( array(), primeestate_get_own_inquiries( '' ) );
	}

	public function test_own_viewing_requests_scoped_by_account_email(): void {
		$property_id = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish' ) );

		$mine = primeestate_create_viewing_request( array(
			'property_id'    => $property_id,
			'agent_id'       => 0,
			'name'           => 'Me',
			'email'          => 'me@example.com',
			'phone'          => '555-0100',
			'preferred_date' => '2099-01-01',
			'preferred_time' => '10:00',
			'message'        => '',
		) );
		primeestate_create_viewing_request( array(
			'property_id'    => $property_id,
			'agent_id'       => 0,
			'name'           => 'Someone Else',
			'email'          => 'other@example.com',
			'phone'          => '555-0101',
			'preferred_date' => '2099-01-02',
			'preferred_time' => '11:00',
			'message'        => '',
		) );

		$own = primeestate_get_own_viewing_requests( 'me@example.com' );

		$this->assertCount( 1, $own );
		$this->assertSame( $mine, $own[0]->ID );
	}

	/**
	 * T087: regression guard for the coverage list itself — the actual gap
	 * found this phase was that `primeestate_mark_page_uncacheable()` had
	 * existed since Phase 2 but was never called from anywhere, across six
	 * phases of dashboard-style pages. Asserting the full expected slug set
	 * here (rather than just "the list is non-empty") is what would have
	 * caught a single forgotten page the way the real gap went unnoticed.
	 */
	public function test_dashboard_page_slugs_cover_every_personalized_route(): void {
		$this->assertSame(
			array(
				'favorites',
				'compare',
				'agent-dashboard',
				'agent-add-property',
				'agent-profile-edit',
				'user-dashboard',
			),
			primeestate_dashboard_page_slugs()
		);
	}
}
