<?php
/**
 * T034 — PHPUnit coverage for the property search/filter query builder
 * (plugin/includes/search/property-query.php), FR-011.
 */

class Test_Property_Query extends WP_UnitTestCase {

	public function test_defaults_to_publish_property_query(): void {
		$args = primeestate_build_property_query_args( array() );

		$this->assertSame( 'property', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( PRIMEESTATE_SEARCH_PER_PAGE, $args['posts_per_page'] );
		$this->assertSame( 1, $args['paged'] );
		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_listing_filter_builds_tax_query(): void {
		$args = primeestate_build_property_query_args( array( 'listing' => 'rent' ) );

		$this->assertSame(
			array(
				array(
					'taxonomy' => 'listing_type',
					'field'    => 'slug',
					'terms'    => 'for-rent',
				),
			),
			$args['tax_query']
		);
	}

	public function test_price_range_builds_between_meta_query(): void {
		$args = primeestate_build_property_query_args(
			array(
				'min_price' => 100000,
				'max_price' => 500000,
			)
		);

		$this->assertSame(
			array(
				'key'     => '_pe_price',
				'value'   => array( 100000.0, 500000.0 ),
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			),
			$args['meta_query'][0]
		);
	}

	public function test_bedrooms_uses_greater_than_or_equal(): void {
		$args = primeestate_build_property_query_args( array( 'bedrooms' => 3 ) );

		$this->assertSame(
			array(
				'key'     => '_pe_bedrooms',
				'value'   => 3,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			),
			$args['meta_query'][0]
		);
	}

	public function test_amenities_use_and_relation_within_taxonomy(): void {
		$args = primeestate_build_property_query_args( array( 'amenities' => array( 'pool', 'garage' ) ) );

		$this->assertSame(
			array(
				'taxonomy' => 'amenity',
				'field'    => 'slug',
				'terms'    => array( 'pool', 'garage' ),
				'operator' => 'AND',
			),
			$args['tax_query'][0]
		);
	}

	public function test_multiple_tax_query_clauses_get_and_relation(): void {
		$args = primeestate_build_property_query_args(
			array(
				'listing' => 'sale',
				'type'    => 'villa',
			)
		);

		$this->assertSame( 'AND', $args['tax_query']['relation'] );
	}

	/**
	 * Invalid params are ignored/clamped, never hard errors (spec.md edge cases).
	 */
	public function test_negative_price_is_ignored(): void {
		$args = primeestate_build_property_query_args( array( 'min_price' => -500 ) );

		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_contradictory_price_range_is_dropped(): void {
		$args = primeestate_build_property_query_args(
			array(
				'min_price' => 900000,
				'max_price' => 100000,
			)
		);

		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_invalid_sort_falls_back_to_default(): void {
		$args = primeestate_build_property_query_args( array( 'sort' => 'not-a-real-sort' ) );

		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
	}

	public function test_price_asc_sort_orders_by_price_meta(): void {
		$args = primeestate_build_property_query_args( array( 'sort' => 'price_asc' ) );

		$this->assertSame( 'meta_value_num', $args['orderby'] );
		$this->assertSame( '_pe_price', $args['meta_key'] );
		$this->assertSame( 'ASC', $args['order'] );
	}

	public function test_page_param_sets_paged(): void {
		$args = primeestate_build_property_query_args( array( 'page' => 3 ) );

		$this->assertSame( 3, $args['paged'] );
	}
}
