<?php
/**
 * FilterPanel component (source doc §67) — the full FR-011 filter set plus
 * the FR-012 sort control, used on the property archive. Superset of
 * SearchForm's fields (same `name` attributes: listing/city/type), so a URL
 * built by either component is interchangeable and shareable (SC-013).
 * Plain GET form — functional without JavaScript.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_filter_panel( array $current_filters ): void {
	$property_types = get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => false ) );
	$statuses       = get_terms( array( 'taxonomy' => 'property_status', 'hide_empty' => false ) );
	$amenities      = get_terms( array( 'taxonomy' => 'amenity', 'hide_empty' => false ) );
	$current_amenities = $current_filters['amenities'] ?? array();

	$sort_options = array(
		'relevance'  => __( 'Most relevant', 'primeestate' ),
		'newest'     => __( 'Newest', 'primeestate' ),
		'price_asc'  => __( 'Price: low to high', 'primeestate' ),
		'price_desc' => __( 'Price: high to low', 'primeestate' ),
		'area_desc'  => __( 'Largest area', 'primeestate' ),
	);
	?>
	<form class="pe-filter-panel" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>">
		<div class="pe-filter-panel__group">
			<label for="pe-filter-listing"><?php esc_html_e( 'Listing type', 'primeestate' ); ?></label>
			<select id="pe-filter-listing" name="listing">
				<option value=""><?php esc_html_e( 'Any', 'primeestate' ); ?></option>
				<option value="sale" <?php selected( $current_filters['listing'] ?? '', 'sale' ); ?>><?php esc_html_e( 'For Sale', 'primeestate' ); ?></option>
				<option value="rent" <?php selected( $current_filters['listing'] ?? '', 'rent' ); ?>><?php esc_html_e( 'For Rent', 'primeestate' ); ?></option>
				<option value="lease" <?php selected( $current_filters['listing'] ?? '', 'lease' ); ?>><?php esc_html_e( 'For Lease', 'primeestate' ); ?></option>
			</select>
		</div>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-city"><?php esc_html_e( 'Location', 'primeestate' ); ?></label>
			<select id="pe-filter-city" name="city">
				<option value=""><?php esc_html_e( 'Any location', 'primeestate' ); ?></option>
				<?php foreach ( primeestate_get_location_leaf_terms() as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_filters['city'] ?? '', $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-type"><?php esc_html_e( 'Property type', 'primeestate' ); ?></label>
			<select id="pe-filter-type" name="type">
				<option value=""><?php esc_html_e( 'Any type', 'primeestate' ); ?></option>
				<?php foreach ( $property_types as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_filters['type'] ?? '', $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-status"><?php esc_html_e( 'Status', 'primeestate' ); ?></label>
			<select id="pe-filter-status" name="status">
				<option value=""><?php esc_html_e( 'Any status', 'primeestate' ); ?></option>
				<?php foreach ( $statuses as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_filters['status'] ?? '', $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-filter-panel__group pe-filter-panel__group--range">
			<span class="pe-filter-panel__legend"><?php esc_html_e( 'Price range', 'primeestate' ); ?></span>
			<label class="screen-reader-text" for="pe-filter-min-price"><?php esc_html_e( 'Minimum price', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-filter-min-price" name="min_price" placeholder="<?php esc_attr_e( 'Min', 'primeestate' ); ?>" value="<?php echo esc_attr( $current_filters['min_price'] ?? '' ); ?>">
			<label class="screen-reader-text" for="pe-filter-max-price"><?php esc_html_e( 'Maximum price', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-filter-max-price" name="max_price" placeholder="<?php esc_attr_e( 'Max', 'primeestate' ); ?>" value="<?php echo esc_attr( $current_filters['max_price'] ?? '' ); ?>">
		</div>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-bedrooms"><?php esc_html_e( 'Bedrooms (min)', 'primeestate' ); ?></label>
			<select id="pe-filter-bedrooms" name="bedrooms">
				<option value=""><?php esc_html_e( 'Any', 'primeestate' ); ?></option>
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $current_filters['bedrooms'] ?? '', $i ); ?>><?php echo esc_html( $i ); ?>+</option>
				<?php endfor; ?>
			</select>
		</div>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-bathrooms"><?php esc_html_e( 'Bathrooms (min)', 'primeestate' ); ?></label>
			<select id="pe-filter-bathrooms" name="bathrooms">
				<option value=""><?php esc_html_e( 'Any', 'primeestate' ); ?></option>
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $current_filters['bathrooms'] ?? '', $i ); ?>><?php echo esc_html( $i ); ?>+</option>
				<?php endfor; ?>
			</select>
		</div>

		<div class="pe-filter-panel__group pe-filter-panel__group--range">
			<span class="pe-filter-panel__legend"><?php esc_html_e( 'Area', 'primeestate' ); ?></span>
			<label class="screen-reader-text" for="pe-filter-min-area"><?php esc_html_e( 'Minimum area', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-filter-min-area" name="min_area" placeholder="<?php esc_attr_e( 'Min', 'primeestate' ); ?>" value="<?php echo esc_attr( $current_filters['min_area'] ?? '' ); ?>">
			<label class="screen-reader-text" for="pe-filter-max-area"><?php esc_html_e( 'Maximum area', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-filter-max-area" name="max_area" placeholder="<?php esc_attr_e( 'Max', 'primeestate' ); ?>" value="<?php echo esc_attr( $current_filters['max_area'] ?? '' ); ?>">
		</div>

		<fieldset class="pe-filter-panel__group">
			<legend><?php esc_html_e( 'Amenities', 'primeestate' ); ?></legend>
			<?php foreach ( $amenities as $term ) : ?>
				<label class="pe-filter-panel__checkbox">
					<input
						type="checkbox"
						name="amenities[]"
						value="<?php echo esc_attr( $term->slug ); ?>"
						<?php checked( in_array( $term->slug, $current_amenities, true ) ); ?>
					>
					<span><?php echo esc_html( $term->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="pe-filter-panel__group">
			<label for="pe-filter-sort"><?php esc_html_e( 'Sort by', 'primeestate' ); ?></label>
			<select id="pe-filter-sort" name="sort">
				<?php foreach ( $sort_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_filters['sort'] ?? 'relevance', $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<button type="submit" class="pe-filter-panel__submit"><?php esc_html_e( 'Apply filters', 'primeestate' ); ?></button>
		<a class="pe-filter-panel__clear" href="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>"><?php esc_html_e( 'Clear all', 'primeestate' ); ?></a>
	</form>
	<?php
}
