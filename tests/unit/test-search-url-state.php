<?php
/**
 * T035 — PHPUnit coverage for filter-state ↔ URL query-string
 * round-tripping (FR-013, SC-013): a copied filtered URL must reproduce the
 * same result set when opened elsewhere.
 */

class Test_Search_Url_State extends WP_UnitTestCase {

	public function test_round_trip_preserves_all_filter_values(): void {
		$filters = array(
			'listing'    => 'rent',
			'city'       => 'los-angeles',
			'type'       => 'apartment',
			'min_price'  => 1000,
			'max_price'  => 3000,
			'bedrooms'   => 2,
			'bathrooms'  => 1,
			'min_area'   => 500,
			'max_area'   => 1200,
			'amenities'  => array( 'pool', 'garage' ),
			'sort'       => 'price_asc',
			'page'       => 2,
		);

		$query_string = primeestate_filters_to_query_string( $filters );
		$round_tripped = primeestate_filters_from_query_string( $query_string );

		$this->assertSame( primeestate_normalize_filters( $filters ), $round_tripped );
	}

	public function test_default_page_and_sort_are_omitted_from_canonical_url(): void {
		$query_string = primeestate_filters_to_query_string(
			array(
				'listing' => 'sale',
				'sort'    => 'relevance',
				'page'    => 1,
			)
		);

		$this->assertStringNotContainsString( 'page=', $query_string );
		$this->assertStringNotContainsString( 'sort=', $query_string );
		$this->assertStringContainsString( 'listing=sale', $query_string );
	}

	public function test_equivalent_filters_produce_identical_urls(): void {
		$a = primeestate_filters_to_query_string( array( 'listing' => 'sale', 'city' => 'Los Angeles' ) );
		$b = primeestate_filters_to_query_string( array( 'city' => 'los-angeles', 'listing' => 'sale' ) );

		$this->assertSame( $a, $b );
	}

	public function test_empty_filters_produce_empty_query_string(): void {
		$this->assertSame( '', primeestate_filters_to_query_string( array() ) );
	}

	public function test_malformed_query_string_yields_only_valid_filters(): void {
		$filters = primeestate_filters_from_query_string( 'listing=not-real&bedrooms=3&min_price=-100' );

		$this->assertSame( array( 'bedrooms' => 3 ), $filters );
	}

	public function test_amenities_survive_comma_encoding_round_trip(): void {
		$query_string  = primeestate_filters_to_query_string( array( 'amenities' => array( 'pool', 'garden', 'gym' ) ) );
		$round_tripped = primeestate_filters_from_query_string( $query_string );

		$this->assertSame( array( 'pool', 'garden', 'gym' ), $round_tripped['amenities'] );
	}
}
