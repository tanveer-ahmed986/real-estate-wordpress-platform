<?php
/**
 * `wp primeestate seed` — demo data generator (FR-060, data-model.md,
 * quickstart.md §2). Creates 50+ properties, 8+ agents, a handful of demo
 * registered users with pre-existing favorites/inquiries, and example
 * inquiries — all clearly fictional (FR-059) and tagged `_primeestate_demo`
 * so `wp primeestate reset` (T032) can remove them safely and repeatedly.
 *
 * Depends on T009–T023 (CPTs, taxonomies, meta, roles) already having run —
 * this file only registers the WP-CLI command; it does not itself register
 * any data structures.
 *
 * Does not fetch any external images/data (no network calls) so the command
 * is reliable offline and in CI; property listings are seeded without a
 * featured image and templates fall back to a placeholder (data-model.md §1).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * A fixed, publicly-documented password for every seeded agent/user
 * (quickstart.md §2) — a random `wp_generate_password()` per account, as an
 * earlier draft of this file used, would make every demo login unusable
 * (nobody, including the E2E suite's login-flow tests, would ever know
 * what it was). Safe specifically because seeded accounts use `.test`
 * emails, are clearly fictional (FR-059), and `wp primeestate reset`
 * removes them entirely — this is not a pattern to reuse for real accounts.
 */
const PRIMEESTATE_DEMO_PASSWORD = 'PrimeEstateDemo123!';

function primeestate_cli_seed( array $args, array $assoc_args ): void {
	$autoload = ABSPATH . 'vendor/autoload.php';

	if ( ! file_exists( $autoload ) ) {
		WP_CLI::error( 'Composer dependencies not installed. Run `composer install` at the repository root first.' );
		return;
	}

	require_once $autoload;

	if ( ! class_exists( \Faker\Factory::class ) ) {
		WP_CLI::error( 'fakerphp/faker is not available. Run `composer install --dev` at the repository root.' );
		return;
	}

	$faker = \Faker\Factory::create();

	WP_CLI::log( 'Seeding locations…' );
	$location_term_ids = primeestate_seed_demo_locations();

	WP_CLI::log( 'Seeding editor…' );
	primeestate_seed_demo_editor( $faker );

	WP_CLI::log( 'Seeding agents…' );
	$agent_ids = primeestate_seed_demo_agents( $faker, $location_term_ids );

	WP_CLI::log( 'Seeding registered users…' );
	$user_ids = primeestate_seed_demo_users( $faker );

	WP_CLI::log( 'Seeding properties…' );
	$property_ids = primeestate_seed_demo_properties( $faker, $agent_ids, $location_term_ids );

	WP_CLI::log( 'Seeding inquiries…' );
	$inquiry_count = primeestate_seed_demo_inquiries( $faker, $property_ids, $user_ids );

	WP_CLI::log( 'Seeding viewing requests…' );
	$viewing_count = primeestate_seed_demo_viewing_requests( $faker, $property_ids, $user_ids );

	WP_CLI::log( 'Seeding favorites…' );
	primeestate_seed_demo_favorites( $user_ids, $property_ids );

	WP_CLI::log( 'Seeding insight articles…' );
	$insight_count = primeestate_seed_demo_insights( $faker, $property_ids );

	WP_CLI::success(
		sprintf(
			'Seeded %d agents, %d registered users, %d properties, %d inquiries, %d viewing requests, %d insight articles.',
			count( $agent_ids ),
			count( $user_ids ),
			count( $property_ids ),
			$inquiry_count,
			$viewing_count,
			$insight_count
		)
	);
}
WP_CLI::add_command( 'primeestate seed', 'primeestate_cli_seed' );

/**
 * @return int[] Leaf (city) `location` term IDs, one per seeded city.
 */
function primeestate_seed_demo_locations(): array {
	$tree = array(
		'United States' => array(
			'California' => array( 'Los Angeles', 'San Francisco', 'San Diego' ),
			'Texas'      => array( 'Austin', 'Houston' ),
			'New York'   => array( 'New York City' ),
		),
		'United Kingdom' => array(
			'England' => array( 'London', 'Manchester' ),
		),
	);

	$city_term_ids = array();

	foreach ( $tree as $country => $states ) {
		$country_term_id = primeestate_get_or_create_term( $country, 'location' );

		foreach ( $states as $state => $cities ) {
			$state_term_id = primeestate_get_or_create_term( $state, 'location', $country_term_id );

			foreach ( $cities as $city ) {
				$city_term_ids[] = primeestate_get_or_create_term( $city, 'location', $state_term_id );
			}
		}
	}

	return $city_term_ids;
}

function primeestate_get_or_create_term( string $name, string $taxonomy, int $parent = 0 ): int {
	$existing = get_term_by( 'name', $name, $taxonomy );

	if ( $existing instanceof WP_Term && (int) $existing->parent === $parent ) {
		return (int) $existing->term_id;
	}

	$result = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );

	return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
}

/**
 * A single seeded `editor`-role account, distinct from the `administrator`
 * account `wp core install` already creates (quickstart.md §1) — lets a
 * reviewer exercise moderation (FR-043) as an editor specifically, not only
 * as a full administrator.
 */
function primeestate_seed_demo_editor( \Faker\Generator $faker ): void {
	if ( username_exists( 'demo_editor' ) ) {
		return;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => 'demo_editor',
			'user_email'   => 'demo_editor@example.test',
			'user_pass'    => PRIMEESTATE_DEMO_PASSWORD,
			'first_name'   => $faker->firstName(),
			'last_name'    => $faker->lastName(),
			'role'         => 'editor',
		)
	);

	if ( ! is_wp_error( $user_id ) ) {
		update_user_meta( $user_id, '_primeestate_demo', true );
	}
}

/**
 * @return int[] Newly created agent user IDs.
 */
function primeestate_seed_demo_agents( \Faker\Generator $faker, array $location_term_ids ): array {
	$specializations = array( 'Luxury', 'Commercial', 'First-time buyers', 'Investment', 'New Developments' );
	$agent_ids       = array();

	for ( $i = 0; $i < 8; $i++ ) {
		$first_name = $faker->firstName();
		$last_name  = $faker->lastName();
		// Deterministic login (demo_agent_0, demo_agent_1, …) rather than
		// embedding the random display name — an earlier draft did the
		// latter, which meant nobody (including a human exploring the site,
		// or an E2E test) could know a seeded account's exact username in
		// advance to log in with it. Display name stays realistic.
		$username = 'demo_agent_' . $i;
		$email    = $username . '@example.test'; // .test TLD: guaranteed non-deliverable, per FR-059's "clearly fictional" requirement.

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => PRIMEESTATE_DEMO_PASSWORD,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => "$first_name $last_name",
				'role'         => 'agent',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		update_user_meta( $user_id, '_pe_agent_bio', $faker->paragraph( 3 ) );
		update_user_meta( $user_id, '_pe_agent_phone', $faker->phoneNumber() );
		update_user_meta( $user_id, '_pe_agent_whatsapp', $faker->phoneNumber() );
		update_user_meta( $user_id, '_pe_agent_license', strtoupper( $faker->bothify( 'LIC-#####' ) ) );
		update_user_meta( $user_id, '_pe_agent_office', $faker->company() );
		update_user_meta( $user_id, '_pe_agent_areas', primeestate_random_subset( $location_term_ids, 3 ) );
		update_user_meta( $user_id, '_pe_agent_specializations', primeestate_random_subset( $specializations, 2 ) );
		update_user_meta( $user_id, '_primeestate_demo', true );

		$agent_ids[] = $user_id;
	}

	return $agent_ids;
}

/**
 * @return int[] Newly created subscriber user IDs.
 */
function primeestate_seed_demo_users( \Faker\Generator $faker ): array {
	$user_ids = array();

	for ( $i = 0; $i < 5; $i++ ) {
		$first_name = $faker->firstName();
		$last_name  = $faker->lastName();
		$username   = 'demo_user_' . $i; // Deterministic — see the matching comment in primeestate_seed_demo_agents().

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $username . '@example.test',
				'user_pass'    => PRIMEESTATE_DEMO_PASSWORD,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => "$first_name $last_name",
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		update_user_meta( $user_id, '_primeestate_demo', true );
		$user_ids[] = $user_id;
	}

	return $user_ids;
}

/**
 * @return int[] Newly created property post IDs.
 */
function primeestate_seed_demo_properties( \Faker\Generator $faker, array $agent_ids, array $location_term_ids ): array {
	if ( empty( $agent_ids ) || empty( $location_term_ids ) ) {
		WP_CLI::warning( 'No agents or locations to assign — skipping property seeding.' );
		return array();
	}

	$property_types = get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => false, 'fields' => 'ids' ) );
	$listing_types  = get_terms( array( 'taxonomy' => 'listing_type', 'hide_empty' => false, 'fields' => 'ids' ) );
	$statuses       = get_terms( array( 'taxonomy' => 'property_status', 'hide_empty' => false, 'fields' => 'ids' ) );
	$amenities      = get_terms( array( 'taxonomy' => 'amenity', 'hide_empty' => false, 'fields' => 'ids' ) );

	$property_ids = array();
	$total        = 55; // 50+ required by FR-060.

	for ( $i = 0; $i < $total; $i++ ) {
		$property_type = $faker->randomElement( $property_types );
		$bedrooms       = wp_rand( 0, 6 );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'property',
				'post_status'  => 'publish',
				'post_author'  => $faker->randomElement( $agent_ids ),
				'post_title'   => primeestate_generate_property_title( $faker, $bedrooms ),
				'post_content' => $faker->paragraphs( 3, true ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		$price       = wp_rand( 800, 25000 ) * 100; // Cents-free integer-ish price, wide enough range for sale vs. rent.
		$price_types = array( 'fixed', 'fixed', 'fixed', 'starting_from', 'on_request' );

		update_post_meta( $post_id, '_pe_reference', sprintf( 'PE-%06d', $post_id ) );
		update_post_meta( $post_id, '_pe_short_description', $faker->sentence( 12 ) );
		update_post_meta( $post_id, '_pe_price', $price );
		update_post_meta( $post_id, '_pe_price_type', $faker->randomElement( $price_types ) );
		update_post_meta( $post_id, '_pe_currency', 'USD' );
		update_post_meta( $post_id, '_pe_negotiable', (bool) wp_rand( 0, 1 ) );
		update_post_meta( $post_id, '_pe_bedrooms', $bedrooms );
		update_post_meta( $post_id, '_pe_bathrooms', wp_rand( 1, min( $bedrooms + 1, 5 ) ) );
		update_post_meta( $post_id, '_pe_living_rooms', wp_rand( 1, 3 ) );
		update_post_meta( $post_id, '_pe_parking_spaces', wp_rand( 0, 3 ) );
		update_post_meta( $post_id, '_pe_area', wp_rand( 450, 6500 ) );
		update_post_meta( $post_id, '_pe_year_built', wp_rand( 1970, 2025 ) );
		update_post_meta( $post_id, '_pe_address', $faker->streetAddress() );
		update_post_meta( $post_id, '_pe_postal_code', $faker->postcode() );
		update_post_meta( $post_id, '_pe_lat', $faker->latitude( 25, 49 ) );
		update_post_meta( $post_id, '_pe_lng', $faker->longitude( -122, -74 ) );
		update_post_meta( $post_id, '_primeestate_demo', true );

		wp_set_post_terms( $post_id, array( $property_type ), 'property_type' );
		wp_set_post_terms( $post_id, array( $faker->randomElement( $listing_types ) ), 'listing_type' );
		wp_set_post_terms( $post_id, array( $faker->randomElement( $statuses ) ), 'property_status' );
		wp_set_post_terms( $post_id, array( $faker->randomElement( $location_term_ids ) ), 'location' );
		wp_set_post_terms( $post_id, primeestate_random_subset( $amenities, wp_rand( 2, 6 ) ), 'amenity' );

		$property_ids[] = $post_id;
	}

	return $property_ids;
}

function primeestate_generate_property_title( \Faker\Generator $faker, int $bedrooms ): string {
	$adjectives = array( 'Elegant', 'Modern', 'Charming', 'Spacious', 'Stunning', 'Renovated', 'Sunlit', 'Contemporary' );
	$noun       = 0 === $bedrooms ? 'Studio' : "{$bedrooms}-Bedroom Home";

	return sprintf( '%s %s in %s', $faker->randomElement( $adjectives ), $noun, $faker->city() );
}

/**
 * A little over half of seeded inquiries (T085/FR-046's "existing activity"
 * demo requirement) are attributed to an actual seeded registered user's own
 * account email/name rather than a fully random Faker identity — otherwise
 * `primeestate_get_own_inquiries()`'s email-match scoping (theme/inc/
 * user-dashboard-render.php) would find nothing to show for *any* demo user,
 * and quickstart.md §US7 ("log in as a demo user with existing activity")
 * would be untestable as written. The remainder stay fully random, matching
 * how a real guest inquiry (no account at all) looks.
 */
function primeestate_seed_demo_inquiries( \Faker\Generator $faker, array $property_ids, array $user_ids ): int {
	if ( empty( $property_ids ) ) {
		return 0;
	}

	$statuses    = array( 'New', 'Contacted', 'Qualified', 'Viewing Scheduled', 'Closed' );
	$methods     = array( 'phone', 'email', 'whatsapp' );
	$sample_size = min( 15, count( $property_ids ) );
	$count       = 0;

	foreach ( primeestate_random_subset( $property_ids, $sample_size ) as $property_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pe_inquiry',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Inquiry for property #%d', $property_id ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		$as_demo_user = ! empty( $user_ids ) && 0 === wp_rand( 0, 1 );
		$submitter    = $as_demo_user ? get_userdata( $faker->randomElement( $user_ids ) ) : null;

		update_post_meta( $post_id, '_pe_name', $submitter ? $submitter->display_name : $faker->name() );
		update_post_meta( $post_id, '_pe_email', $submitter ? $submitter->user_email : $faker->safeEmail() );
		update_post_meta( $post_id, '_pe_phone', $faker->phoneNumber() );
		update_post_meta( $post_id, '_pe_property_id', $property_id );
		update_post_meta( $post_id, '_pe_agent_id', (int) get_post_field( 'post_author', $property_id ) );
		update_post_meta( $post_id, '_pe_message', $faker->paragraph( 2 ) );
		update_post_meta( $post_id, '_pe_preferred_contact_method', $faker->randomElement( $methods ) );
		update_post_meta( $post_id, '_pe_status', $faker->randomElement( $statuses ) );
		update_post_meta( $post_id, '_primeestate_demo', true );

		++$count;
	}

	return $count;
}

/**
 * No prior phase seeded any `pe_viewing_request` posts at all — Phase 6
 * (US4) built the CPT and REST endpoints but seed data stopped at
 * inquiries. Added now because T085's Viewing Requests dashboard section
 * would otherwise have nothing to demo for any seeded account, the same gap
 * just fixed for inquiries above.
 */
function primeestate_seed_demo_viewing_requests( \Faker\Generator $faker, array $property_ids, array $user_ids ): int {
	if ( empty( $property_ids ) ) {
		return 0;
	}

	$statuses    = array( 'Requested', 'Confirmed', 'Rescheduled', 'Completed' );
	$sample_size = min( 10, count( $property_ids ) );
	$count       = 0;

	foreach ( primeestate_random_subset( $property_ids, $sample_size ) as $property_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pe_viewing_request',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Viewing request for property #%d', $property_id ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		$as_demo_user = ! empty( $user_ids ) && 0 === wp_rand( 0, 1 );
		$submitter    = $as_demo_user ? get_userdata( $faker->randomElement( $user_ids ) ) : null;

		update_post_meta( $post_id, '_pe_name', $submitter ? $submitter->display_name : $faker->name() );
		update_post_meta( $post_id, '_pe_email', $submitter ? $submitter->user_email : $faker->safeEmail() );
		update_post_meta( $post_id, '_pe_phone', $faker->phoneNumber() );
		update_post_meta( $post_id, '_pe_property_id', $property_id );
		update_post_meta( $post_id, '_pe_agent_id', (int) get_post_field( 'post_author', $property_id ) );
		update_post_meta( $post_id, '_pe_preferred_date', $faker->dateTimeBetween( 'now', '+3 weeks' )->format( 'Y-m-d' ) );
		update_post_meta( $post_id, '_pe_preferred_time', $faker->randomElement( array( '09:00', '11:00', '13:00', '15:00', '17:00' ) ) );
		update_post_meta( $post_id, '_pe_message', $faker->boolean( 40 ) ? $faker->sentence( 8 ) : '' );
		update_post_meta( $post_id, '_pe_status', $faker->randomElement( $statuses ) );
		update_post_meta( $post_id, '_primeestate_demo', true );

		++$count;
	}

	return $count;
}

function primeestate_seed_demo_favorites( array $user_ids, array $property_ids ): void {
	if ( empty( $user_ids ) || empty( $property_ids ) ) {
		return;
	}

	foreach ( $user_ids as $user_id ) {
		if ( 0 === wp_rand( 0, 1 ) ) {
			continue; // Not every demo user has pre-existing favorites.
		}

		update_user_meta( $user_id, '_pe_favorites', primeestate_random_subset( $property_ids, wp_rand( 1, 4 ) ) );
	}
}

/**
 * T095 (FR-050/FR-051, quickstart.md §US9): two articles per
 * `insight_category` term — the exact six seeded by
 * insight-category.php's `primeestate_seed_insight_category_terms()` — so
 * the Insights archive template (T093) has something to render in every
 * category, plus at least one article with `_pe_related_properties` set
 * (T094's meta box target) so the "related properties show where curated"
 * half of the independent test is actually demonstrable, not just
 * structurally present.
 */
function primeestate_seed_demo_insights( \Faker\Generator $faker, array $property_ids ): int {
	$categories = array(
		'Buying Guide',
		'Selling Guide',
		'Investment',
		'Market Insights',
		'Interior Design',
		'Neighborhood Guides',
	);

	$editor    = get_user_by( 'login', 'demo_editor' );
	$author_id = $editor ? $editor->ID : get_current_user_id();
	$count     = 0;

	foreach ( $categories as $index => $category ) {
		$term = get_term_by( 'name', $category, 'insight_category' );

		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		for ( $i = 0; $i < 2; $i++ ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_author'  => $author_id,
					'post_title'   => ucfirst( $faker->sentence( wp_rand( 4, 8 ) ) ),
					'post_content' => $faker->paragraphs( wp_rand( 4, 7 ), true ),
					'post_excerpt' => $faker->sentence( 20 ),
					'post_date'    => $faker->dateTimeBetween( '-6 months', 'now' )->format( 'Y-m-d H:i:s' ),
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			wp_set_post_terms( $post_id, array( (int) $term->term_id ), 'insight_category' );
			update_post_meta( $post_id, '_primeestate_demo', true );

			// The very first article (Buying Guide's first) always references
			// related properties, so the independent test's "related properties
			// show where curated" case never depends on random chance.
			if ( 0 === $index && 0 === $i && ! empty( $property_ids ) ) {
				update_post_meta( $post_id, '_pe_related_properties', primeestate_random_subset( $property_ids, wp_rand( 2, 4 ) ) );
			}

			++$count;
		}
	}

	return $count;
}

/**
 * @param int[] $items
 * @return int[]
 */
function primeestate_random_subset( array $items, int $count ): array {
	$items = array_values( $items );
	$count = min( $count, count( $items ) );

	if ( 0 === $count ) {
		return array();
	}

	$keys = (array) array_rand( $items, $count );

	return array_map(
		static function ( $key ) use ( $items ) {
			return $items[ $key ];
		},
		$keys
	);
}
