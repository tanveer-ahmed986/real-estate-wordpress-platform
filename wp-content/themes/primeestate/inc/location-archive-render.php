<?php
/**
 * Location drill-down directory (competitive review — Zameen's
 * city → sector → street directory, each level with a live count). The
 * `location` taxonomy (Country → State → City → Area, data-model.md §2) has
 * existed since Foundational, but had no archive template of its own —
 * `taxonomy-location.html` didn't exist, so every location term URL the
 * breadcrumbs/filter panel already link to fell through to a generic
 * fallback with no location-specific content at all.
 *
 * Two modes, chosen by whether the current term has children:
 * - Has children (Country/State/City-with-an-Area-below-it): show a
 *   directory of the next level down, each with an aggregate listing
 *   count, exactly like Zameen's location pages.
 * - No children (a leaf term — City or Area, wherever this data set
 *   stops): show the actual filtered property results for that term,
 *   reusing the archive's own result renderer so this never duplicates
 *   `archive-render.php`'s query-building/pagination logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_location_archive_shortcode(): string {
	$term = get_queried_object();

	if ( ! ( $term instanceof WP_Term ) || 'location' !== $term->taxonomy ) {
		return '';
	}

	$children = get_terms(
		array(
			'taxonomy'   => 'location',
			'parent'     => $term->term_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $children ) ) {
		$children = array();
	}

	ob_start();

	if ( ! empty( $children ) ) {
		primeestate_render_location_directory( $children );
	} else {
		primeestate_render_location_leaf_results( $term );
	}

	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_location_archive', 'primeestate_location_archive_shortcode' );

/**
 * @param WP_Term[] $terms
 */
function primeestate_render_location_directory( array $terms ): void {
	$rows = array_map(
		static function ( WP_Term $term ) {
			return array(
				'term'  => $term,
				'count' => primeestate_get_location_term_total_count( $term ),
			);
		},
		$terms
	);

	usort(
		$rows,
		static function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);
	?>
	<div class="pe-location-directory">
		<?php foreach ( $rows as $row ) : ?>
			<a class="pe-location-directory__item" href="<?php echo esc_url( (string) get_term_link( $row['term'] ) ); ?>">
				<span class="pe-location-directory__name"><?php echo esc_html( $row['term']->name ); ?></span>
				<span class="pe-location-directory__count">
					<?php echo esc_html( sprintf( /* translators: %d: number of listings */ _n( '%d listing', '%d listings', $row['count'], 'primeestate' ), $row['count'] ) ); ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

function primeestate_render_location_leaf_results( WP_Term $term ): void {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => PRIMEESTATE_SEARCH_PER_PAGE,
			'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		)
	);
	?>
	<div class="pe-archive__results">
		<?php primeestate_render_property_results( $query ); ?>
	</div>
	<?php
}

/**
 * A hierarchical taxonomy term's own `$term->count` only tallies posts
 * assigned directly to that exact term — a City term shows 0 here even
 * though it "contains" listings, because every property is tagged to its
 * leaf term only. `WP_Query`'s `tax_query`, unlike `get_terms()`, resolves
 * a parent term to include all of its descendants automatically, so
 * `found_posts` against a single lightweight query gives the correct
 * roll-up without hand-walking the term tree.
 */
function primeestate_get_location_term_total_count( WP_Term $term ): int {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		)
	);

	return (int) $query->found_posts;
}
