<?php
/**
 * SearchForm component (source doc §67, FR-010) — the primary Buy/Rent
 * entry point (homepage hero and/or archive header). Plain GET form: fully
 * functional with JavaScript disabled, since the archive template's
 * `pre_get_posts` filter (property-query.php) reads these same `$_GET`
 * params server-side. property-search.js (T040) progressively enhances the
 * same fields with AJAX + `history.pushState`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_search_form( array $current_filters = array() ): void {
	$listing_options = array(
		'sale' => __( 'Buy', 'primeestate' ),
		'rent' => __( 'Rent', 'primeestate' ),
		'lease' => __( 'Lease', 'primeestate' ),
	);

	$property_types = get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => false ) );
	$current_listing = $current_filters['listing'] ?? 'sale';
	?>
	<form class="pe-search-form" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>" role="search">
		<div class="pe-search-form__mode" role="radiogroup" aria-label="<?php esc_attr_e( 'Listing type', 'primeestate' ); ?>">
			<?php foreach ( $listing_options as $value => $label ) : ?>
				<label class="pe-search-form__mode-option">
					<input
						type="radio"
						name="listing"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $current_listing, $value ); ?>
					>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="pe-search-form__field">
			<label for="pe-search-city"><?php esc_html_e( 'Location', 'primeestate' ); ?></label>
			<select id="pe-search-city" name="city">
				<option value=""><?php esc_html_e( 'Any location', 'primeestate' ); ?></option>
				<?php foreach ( primeestate_get_location_leaf_terms() as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_filters['city'] ?? '', $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-search-form__field">
			<label for="pe-search-type"><?php esc_html_e( 'Property type', 'primeestate' ); ?></label>
			<select id="pe-search-type" name="type">
				<option value=""><?php esc_html_e( 'Any type', 'primeestate' ); ?></option>
				<?php foreach ( $property_types as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_filters['type'] ?? '', $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<button type="submit" class="pe-search-form__submit"><?php esc_html_e( 'Search', 'primeestate' ); ?></button>
	</form>
	<?php
}

/**
 * `[primeestate_search_form]` — this component previously had no caller
 * anywhere in the theme (every other dynamic component follows the
 * shortcode-hosting pattern; this one had been built but never wired up,
 * since no homepage template existed to host it until `templates/index.html`
 * was added). Reads the same `$_GET` filters as the archive so a homepage
 * search and a direct archive visit behave identically.
 */
function primeestate_search_form_shortcode(): string {
	ob_start();
	primeestate_render_search_form( primeestate_normalize_filters( wp_unslash( $_GET ) ) );
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_search_form', 'primeestate_search_form_shortcode' );

/**
 * Leaf (city) `location` terms for the search form's location typeahead —
 * shared with the seed command's own hierarchy so demo data is searchable.
 *
 * @return WP_Term[]
 */
function primeestate_get_location_leaf_terms(): array {
	$terms = get_terms( array( 'taxonomy' => 'location', 'hide_empty' => false ) );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$parent_ids = wp_list_pluck( $terms, 'parent' );

	return array_values(
		array_filter(
			$terms,
			static function ( $term ) use ( $parent_ids ) {
				return ! in_array( $term->term_id, $parent_ids, true );
			}
		)
	);
}
