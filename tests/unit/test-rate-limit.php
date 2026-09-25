<?php
/**
 * T046 — PHPUnit coverage for the anti-spam rate limiter
 * (plugin/includes/security/rate-limit.php), FR-056: 3 submissions per
 * visitor per rolling hour, combined across inquiry/viewing/submission forms.
 */

class Test_Rate_Limit extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_transient( primeestate_rate_limit_transient_key() );
		parent::tear_down();
	}

	public function test_allows_first_three_submissions_then_blocks_the_fourth(): void {
		$this->assertTrue( primeestate_rate_limit_check() );
		primeestate_rate_limit_record_submission();

		$this->assertTrue( primeestate_rate_limit_check() );
		primeestate_rate_limit_record_submission();

		$this->assertTrue( primeestate_rate_limit_check() );
		primeestate_rate_limit_record_submission();

		$this->assertFalse( primeestate_rate_limit_check() );
	}

	public function test_limit_is_shared_across_form_types(): void {
		// FR-056 (clarified): one shared counter, not one per form — recording
		// three submissions of any kind exhausts the quota for all of them.
		primeestate_rate_limit_record_submission();
		primeestate_rate_limit_record_submission();
		primeestate_rate_limit_record_submission();

		$this->assertFalse( primeestate_rate_limit_check() );
	}

	public function test_failed_validation_does_not_consume_quota(): void {
		// The REST handler only calls record_submission() after a successful
		// create — checking alone must never itself decrement the allowance.
		primeestate_rate_limit_check();
		primeestate_rate_limit_check();
		primeestate_rate_limit_check();

		$this->assertTrue( primeestate_rate_limit_check() );
	}

	public function test_honeypot_triggered_detection(): void {
		$this->assertTrue( primeestate_is_honeypot_triggered( 'i-am-a-bot' ) );
		$this->assertFalse( primeestate_is_honeypot_triggered( '' ) );
		$this->assertFalse( primeestate_is_honeypot_triggered( '   ' ) );
	}

	public function test_identity_differs_between_logged_in_users(): void {
		$user_a = self::factory()->user->create();
		$user_b = self::factory()->user->create();

		wp_set_current_user( $user_a );
		$key_a = primeestate_rate_limit_transient_key();

		wp_set_current_user( $user_b );
		$key_b = primeestate_rate_limit_transient_key();

		$this->assertNotSame( $key_a, $key_b );

		wp_set_current_user( 0 );
	}
}
