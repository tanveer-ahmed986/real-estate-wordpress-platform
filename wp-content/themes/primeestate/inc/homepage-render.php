<?php
/**
 * Homepage sections (source doc §37: Header → Hero → Property Search →
 * Featured Properties → Browse By Property Type → Featured Locations → Why
 * PrimeEstate → Featured Agents → Latest Listings → Market Insights → CTA →
 * Footer). Each section is its own shortcode, hosted by
 * `templates/index.html`, following the same shortcode-hosting pattern used
 * everywhere else in this theme for anything too data-driven for static
 * block markup.
 *
 * Every number/count on this page is real, queried data — source doc §41
 * explicitly forbids unverifiable claims ("10,000+ verified properties"
 * unless actual data exists), so nothing here is hardcoded copy dressed up
 * as a statistic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Featured" = highest-value active listings, not a separate curation flag
 * — there is no `featured` field anywhere in data-model.md, and adding one
 * (plus the admin UI to set it) is more than this section needs. Sorting by
 * price also gives it a genuinely different order from "Latest Listings"
 * below rather than just repeating the same query with a different heading.
 */
function primeestate_featured_properties_shortcode(): string {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'meta_key'       => '_pe_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		)
	);

	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	<div class="pe-property-grid">
		<?php foreach ( $query->posts as $post ) : ?>
			<?php primeestate_render_property_card( $post ); ?>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_featured_properties', 'primeestate_featured_properties_shortcode' );

function primeestate_latest_listings_shortcode(): string {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	<div class="pe-property-grid">
		<?php foreach ( $query->posts as $post ) : ?>
			<?php primeestate_render_property_card( $post ); ?>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_latest_listings', 'primeestate_latest_listings_shortcode' );

/**
 * Source doc §40's example categories (Apartments, Houses, Villas,
 * Commercial, Land, Luxury Properties) are illustrative, not a fixed list —
 * this renders whatever `property_type` terms actually exist and have at
 * least one published listing, so it never links to an empty archive.
 */
function primeestate_browse_by_type_shortcode(): string {
	$terms = get_terms(
		array(
			'taxonomy'   => 'property_type',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 6,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="pe-tile-grid">
		<?php foreach ( $terms as $term ) : ?>
			<a class="pe-tile" href="<?php echo esc_url( add_query_arg( 'type', $term->slug, get_post_type_archive_link( 'property' ) ) ); ?>">
				<span class="pe-tile__label"><?php echo esc_html( $term->name ); ?></span>
				<span class="pe-tile__count"><?php echo esc_html( sprintf( /* translators: %d: number of listings */ _n( '%d listing', '%d listings', $term->count, 'primeestate' ), $term->count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_browse_by_type', 'primeestate_browse_by_type_shortcode' );

/**
 * Leaf `location` terms (cities), matching source doc §39's city-level
 * examples (Karachi, Lahore, Dubai…) — reuses the same leaf-term helper the
 * SearchForm/FilterPanel location dropdowns already use, so "Featured
 * Locations" always matches what's actually searchable.
 */
function primeestate_featured_locations_shortcode(): string {
	$terms = primeestate_get_location_leaf_terms();

	usort(
		$terms,
		static function ( $a, $b ) {
			return $b->count <=> $a->count;
		}
	);

	$terms = array_slice( array_filter( $terms, static fn( $term ) => $term->count > 0 ), 0, 6 );

	if ( empty( $terms ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="pe-tile-grid">
		<?php foreach ( $terms as $term ) : ?>
			<a class="pe-tile" href="<?php echo esc_url( add_query_arg( 'city', $term->slug, get_post_type_archive_link( 'property' ) ) ); ?>">
				<span class="pe-tile__label"><?php echo esc_html( $term->name ); ?></span>
				<span class="pe-tile__count"><?php echo esc_html( sprintf( /* translators: %d: number of listings */ _n( '%d listing', '%d listings', $term->count, 'primeestate' ), $term->count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_featured_locations', 'primeestate_featured_locations_shortcode' );

/**
 * Source doc §41, verbatim list — static copy, no invented numbers.
 */
function primeestate_why_primeestate_shortcode(): string {
	$points = array(
		array(
			'title' => __( 'Verified Listings', 'primeestate' ),
			'body'  => __( 'Every property is reviewed by our team or listed directly by a licensed agent before it goes live.', 'primeestate' ),
		),
		array(
			'title' => __( 'Trusted Agents', 'primeestate' ),
			'body'  => __( 'Work directly with real estate professionals who manage their own listings and respond to your inquiries.', 'primeestate' ),
		),
		array(
			'title' => __( 'Secure Inquiries', 'primeestate' ),
			'body'  => __( 'Your contact details are only ever shared with the agent or team responsible for the property you ask about.', 'primeestate' ),
		),
		array(
			'title' => __( 'Expert Support', 'primeestate' ),
			'body'  => __( 'From your first search to your final viewing, our team is on hand to help you move forward with confidence.', 'primeestate' ),
		),
	);

	ob_start();
	?>
	<div class="pe-trust-grid">
		<?php foreach ( $points as $point ) : ?>
			<div class="pe-trust-grid__item">
				<h3><?php echo esc_html( $point['title'] ); ?></h3>
				<p><?php echo esc_html( $point['body'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_why_primeestate', 'primeestate_why_primeestate_shortcode' );

/**
 * Ordered by how many published listings each agent currently has — a real,
 * derivable measure of "active," rather than an arbitrary/manual pick.
 */
function primeestate_featured_agents_shortcode(): string {
	$agents = get_users(
		array(
			'role'    => 'agent',
			'number'  => 20,
			'orderby' => 'registered',
		)
	);

	if ( empty( $agents ) ) {
		return '';
	}

	$agents_with_counts = array_map(
		static function ( WP_User $agent ) {
			return array(
				'agent' => $agent,
				'count' => count_user_posts( $agent->ID, 'property', true ),
			);
		},
		$agents
	);

	usort(
		$agents_with_counts,
		static function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);

	$agents_with_counts = array_slice( array_filter( $agents_with_counts, static fn( $entry ) => $entry['count'] > 0 ), 0, 4 );

	if ( empty( $agents_with_counts ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="pe-agent-grid">
		<?php foreach ( $agents_with_counts as $entry ) : ?>
			<?php $agent = $entry['agent']; ?>
			<a class="pe-agent-tile" href="<?php echo esc_url( get_author_posts_url( $agent->ID ) ); ?>">
				<?php echo get_avatar( $agent->ID, 96 ); ?>
				<span class="pe-agent-tile__name"><?php echo esc_html( $agent->display_name ); ?></span>
				<span class="pe-agent-tile__count"><?php echo esc_html( sprintf( /* translators: %d: number of listings */ _n( '%d listing', '%d listings', $entry['count'], 'primeestate' ), $entry['count'] ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_featured_agents', 'primeestate_featured_agents_shortcode' );

function primeestate_market_insights_shortcode(): string {
	$query = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( ! $query->have_posts() ) {
		return '';
	}

	$posts_page_id = (int) get_option( 'page_for_posts' );

	ob_start();
	?>
	<div class="pe-insight-grid">
		<?php foreach ( $query->posts as $post ) : ?>
			<?php $categories = get_the_terms( $post, 'insight_category' ); ?>
			<article class="pe-insight-tile">
				<?php if ( has_post_thumbnail( $post ) ) : ?>
					<a class="pe-insight-tile__media" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
					<span class="pe-insight-tile__category"><?php echo esc_html( $categories[0]->name ); ?></span>
				<?php endif; ?>
				<h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
	<?php if ( $posts_page_id ) : ?>
		<a class="pe-link-more" href="<?php echo esc_url( get_permalink( $posts_page_id ) ); ?>"><?php esc_html_e( 'View all insights', 'primeestate' ); ?></a>
	<?php endif; ?>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_market_insights', 'primeestate_market_insights_shortcode' );

/**
 * Source doc §37/§38: the homepage's closing CTA, distinct from the hero's
 * own secondary CTA — the hero targets a visitor who already knows what
 * they want; this one is a last chance for someone who scrolled the whole
 * page without converting yet.
 */
function primeestate_homepage_cta_shortcode(): string {
	ob_start();
	?>
	<div class="pe-cta-banner">
		<p class="pe-cta-banner__eyebrow"><?php esc_html_e( 'Get Started', 'primeestate' ); ?></p>
		<h2><?php esc_html_e( 'Ready to find your next property?', 'primeestate' ); ?></h2>
		<p class="pe-cta-banner__subhead"><?php esc_html_e( 'Search current listings, or list your own property with PrimeEstate today.', 'primeestate' ); ?></p>
		<div class="pe-cta-banner__actions">
			<a class="pe-button pe-button--primary" href="<?php echo esc_url( (string) get_post_type_archive_link( 'property' ) ); ?>"><?php esc_html_e( 'Explore Properties', 'primeestate' ); ?></a>
			<a class="pe-button pe-button--ghost" href="<?php echo esc_url( home_url( '/submit-property/' ) ); ?>"><?php esc_html_e( 'List Your Property', 'primeestate' ); ?></a>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_homepage_cta', 'primeestate_homepage_cta_shortcode' );
