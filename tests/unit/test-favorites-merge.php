<?php
/**
 * T057 — PHPUnit coverage for the guest→account favorites merge logic
 * (plugin/includes/favorites/favorites.php), research.md §4.
 */

class Test_Favorites_Merge extends WP_UnitTestCase {

	private int $user_id;

	public function set_up(): void {
		parent::set_up();
		$this->user_id = self::factory()->user->create();
	}

	public function test_merge_adds_guest_favorites_to_empty_account(): void {
		$merged = primeestate_merge_user_favorites( $this->user_id, array( 1, 2, 3 ) );

		$this->assertSame( array( 1, 2, 3 ), $merged );
		$this->assertSame( array( 1, 2, 3 ), primeestate_get_user_favorites( $this->user_id ) );
	}

	public function test_merge_is_a_deduped_union_not_a_replacement(): void {
		primeestate_merge_user_favorites( $this->user_id, array( 1, 2 ) );
		$merged = primeestate_merge_user_favorites( $this->user_id, array( 2, 3 ) );

		sort( $merged );
		$this->assertSame( array( 1, 2, 3 ), $merged );
	}

	public function test_merge_is_idempotent_when_called_repeatedly(): void {
		$first  = primeestate_merge_user_favorites( $this->user_id, array( 5, 6 ) );
		$second = primeestate_merge_user_favorites( $this->user_id, array( 5, 6 ) );

		$this->assertSame( $first, $second );
	}

	public function test_merge_with_empty_guest_list_leaves_existing_favorites_untouched(): void {
		primeestate_merge_user_favorites( $this->user_id, array( 7 ) );
		$merged = primeestate_merge_user_favorites( $this->user_id, array() );

		$this->assertSame( array( 7 ), $merged );
	}

	public function test_toggle_favorite_adds_then_removes(): void {
		$this->assertTrue( primeestate_toggle_user_favorite( $this->user_id, 42 ) );
		$this->assertSame( array( 42 ), primeestate_get_user_favorites( $this->user_id ) );

		$this->assertFalse( primeestate_toggle_user_favorite( $this->user_id, 42 ) );
		$this->assertSame( array(), primeestate_get_user_favorites( $this->user_id ) );
	}

	public function test_favorites_are_isolated_per_user(): void {
		$other_user_id = self::factory()->user->create();

		primeestate_toggle_user_favorite( $this->user_id, 1 );
		primeestate_toggle_user_favorite( $other_user_id, 2 );

		$this->assertSame( array( 1 ), primeestate_get_user_favorites( $this->user_id ) );
		$this->assertSame( array( 2 ), primeestate_get_user_favorites( $other_user_id ) );
	}
}
