<?php
/**
 * T045 — PHPUnit coverage for inquiry validation/sanitization/storage
 * (plugin/includes/inquiries/inquiry-handler.php), FR-026/FR-027.
 */

class Test_Inquiry_Handler extends WP_UnitTestCase {

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

	private function valid_params(): array {
		return array(
			'property_id'              => $this->property_id,
			'name'                     => 'Jane Buyer',
			'email'                    => 'jane@example.com',
			'phone'                    => '555-0100',
			'message'                  => 'Is this still available?',
			'preferred_contact_method' => 'email',
		);
	}

	public function test_valid_submission_passes_validation(): void {
		$result = primeestate_validate_inquiry_submission( $this->valid_params() );

		$this->assertIsArray( $result );
		$this->assertSame( 'jane@example.com', $result['email'] );
		$this->assertSame( $this->agent_id, $result['agent_id'] );
	}

	public function test_missing_name_fails_validation(): void {
		$params = $this->valid_params();
		unset( $params['name'] );

		$result = primeestate_validate_inquiry_submission( $params );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'missing_name', $result->get_error_codes() );
	}

	public function test_invalid_email_fails_validation(): void {
		$params          = $this->valid_params();
		$params['email'] = 'not-an-email';

		$result = primeestate_validate_inquiry_submission( $params );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_email', $result->get_error_codes() );
	}

	public function test_invalid_contact_method_fails_validation(): void {
		$params                             = $this->valid_params();
		$params['preferred_contact_method'] = 'carrier_pigeon';

		$result = primeestate_validate_inquiry_submission( $params );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_contact_method', $result->get_error_codes() );
	}

	public function test_nonexistent_property_fails_validation(): void {
		$params                = $this->valid_params();
		$params['property_id'] = 999999;

		$result = primeestate_validate_inquiry_submission( $params );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_property', $result->get_error_codes() );
	}

	public function test_message_is_sanitized_and_html_stripped(): void {
		$params            = $this->valid_params();
		$params['message'] = '<script>alert(1)</script>Interested!';

		$result = primeestate_validate_inquiry_submission( $params );

		$this->assertIsArray( $result );
		$this->assertStringNotContainsString( '<script>', $result['message'] );
	}

	public function test_create_inquiry_stores_correct_meta_with_new_status(): void {
		$data       = primeestate_validate_inquiry_submission( $this->valid_params() );
		$inquiry_id = primeestate_create_inquiry( $data );

		$this->assertGreaterThan( 0, $inquiry_id );
		$this->assertSame( 'pe_inquiry', get_post_type( $inquiry_id ) );
		$this->assertSame( 'New', get_post_meta( $inquiry_id, '_pe_status', true ) );
		$this->assertSame( $this->property_id, (int) get_post_meta( $inquiry_id, '_pe_property_id', true ) );
		$this->assertSame( $this->agent_id, (int) get_post_meta( $inquiry_id, '_pe_agent_id', true ) );
	}
}
