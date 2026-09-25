<?php
/**
 * T065 — PHPUnit coverage for viewing-request date/time validation
 * (plugin/includes/viewing/viewing-request.php), FR-031: reject past dates
 * and incomplete fields.
 */

class Test_Viewing_Request extends WP_UnitTestCase {

	private int $agent_id;
	private int $property_id;

	public function set_up(): void {
		parent::set_up();

		$this->agent_id = self::factory()->user->create( array( 'role' => 'agent' ) );

		$this->property_id = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'publish',
				'post_author' => $this->agent_id,
			)
		);
	}

	private function valid_params( array $overrides = array() ): array {
		return array_merge(
			array(
				'property_id'     => $this->property_id,
				'name'            => 'Jane Buyer',
				'email'           => 'jane@example.com',
				'phone'           => '555-0100',
				'preferred_date'  => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
				'preferred_time'  => '14:00',
			),
			$overrides
		);
	}

	public function test_valid_future_submission_passes_validation(): void {
		$result = primeestate_validate_viewing_submission( $this->valid_params() );

		$this->assertIsArray( $result );
		$this->assertSame( $this->agent_id, $result['agent_id'] );
	}

	public function test_past_date_is_rejected(): void {
		$result = primeestate_validate_viewing_submission(
			$this->valid_params( array( 'preferred_date' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ) ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'past_date', $result->get_error_codes() );
	}

	public function test_todays_date_is_accepted_not_treated_as_past(): void {
		$result = primeestate_validate_viewing_submission(
			$this->valid_params( array( 'preferred_date' => current_time( 'Y-m-d' ) ) )
		);

		$this->assertIsArray( $result );
	}

	public function test_malformed_date_is_rejected(): void {
		$result = primeestate_validate_viewing_submission(
			$this->valid_params( array( 'preferred_date' => 'not-a-date' ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_date', $result->get_error_codes() );
	}

	public function test_missing_time_is_rejected(): void {
		$result = primeestate_validate_viewing_submission(
			$this->valid_params( array( 'preferred_time' => '' ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'missing_time', $result->get_error_codes() );
	}

	public function test_missing_phone_is_rejected(): void {
		$result = primeestate_validate_viewing_submission(
			$this->valid_params( array( 'phone' => '' ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'missing_phone', $result->get_error_codes() );
	}

	public function test_create_viewing_request_stores_requested_status(): void {
		$data       = primeestate_validate_viewing_submission( $this->valid_params() );
		$viewing_id = primeestate_create_viewing_request( $data );

		$this->assertGreaterThan( 0, $viewing_id );
		$this->assertSame( 'Requested', get_post_meta( $viewing_id, '_pe_status', true ) );
	}

	public function test_valid_status_transitions(): void {
		$this->assertTrue( primeestate_is_valid_viewing_transition( 'Requested', 'Confirmed' ) );
		$this->assertTrue( primeestate_is_valid_viewing_transition( 'Confirmed', 'Completed' ) );
		$this->assertTrue( primeestate_is_valid_viewing_transition( 'Requested', 'Cancelled' ) );
		$this->assertTrue( primeestate_is_valid_viewing_transition( 'Rescheduled', 'Confirmed' ) );
	}

	public function test_invalid_status_transitions_are_rejected(): void {
		$this->assertFalse( primeestate_is_valid_viewing_transition( 'Completed', 'Requested' ) );
		$this->assertFalse( primeestate_is_valid_viewing_transition( 'Requested', 'Completed' ) );
		$this->assertFalse( primeestate_is_valid_viewing_transition( 'Cancelled', 'Confirmed' ) );
	}
}
