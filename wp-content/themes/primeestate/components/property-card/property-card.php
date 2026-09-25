<?php
/**
 * PropertyCard component (source doc §67). Presentation only — all field
 * values come from `primeestate_property_to_summary()` (plugin's public
 * data-shaping API, includes/search/search-api.php), so the theme never
 * reads raw postmeta/taxonomy terms directly, keeping the theme/plugin
 * business-logic separation intact.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_property_card( WP_Post $post ): void {
	$property = primeestate_property_to_summary( $post );
	$price    = primeestate_format_price( $property['price'], get_post_meta( $post->ID, '_pe_price_type', true ), $property['currency'] );
	$is_new   = primeestate_is_recently_listed( $post );
	?>
	<article class="pe-property-card" data-property-id="<?php echo esc_attr( $property['id'] ); ?>">
		<a class="pe-property-card__media" href="<?php echo esc_url( $property['permalink'] ); ?>">
			<?php if ( $property['featured_image'] ) : ?>
				<img src="<?php echo esc_url( $property['featured_image'] ); ?>" alt="<?php echo esc_attr( $property['title'] ); ?>" loading="lazy">
			<?php else : ?>
				<div class="pe-property-card__media-placeholder" aria-hidden="true"></div>
			<?php endif; ?>
			<?php if ( $property['status'] ) : ?>
				<span class="pe-property-card__status"><?php echo esc_html( $property['status'] ); ?></span>
			<?php endif; ?>
			<?php if ( $is_new ) : ?>
				<span class="pe-property-card__new"><?php esc_html_e( 'New', 'primeestate' ); ?></span>
			<?php endif; ?>
		</a>

		<div class="pe-property-card__body">
			<p class="pe-property-card__price"><?php echo esc_html( $price ); ?></p>
			<h3 class="pe-property-card__title">
				<a href="<?php echo esc_url( $property['permalink'] ); ?>"><?php echo esc_html( $property['title'] ); ?></a>
			</h3>
			<?php if ( $property['city'] ) : ?>
				<p class="pe-property-card__location"><?php echo esc_html( $property['city'] ); ?></p>
			<?php endif; ?>

			<ul class="pe-property-card__meta">
				<li><?php echo esc_html( sprintf( _n( '%d bed', '%d beds', $property['bedrooms'], 'primeestate' ), $property['bedrooms'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( _n( '%d bath', '%d baths', $property['bathrooms'], 'primeestate' ), $property['bathrooms'] ) ); ?></li>
				<?php if ( $property['area'] > 0 ) : ?>
					<li><?php echo esc_html( number_format_i18n( $property['area'] ) ); ?></li>
				<?php endif; ?>
			</ul>

			<p class="pe-property-card__added"><?php echo esc_html( primeestate_get_listed_ago_text( $post ) ); ?></p>

			<div class="pe-property-card__actions">
				<?php
				primeestate_render_favorite_button( $property['id'] );
				primeestate_render_compare_button( $property['id'] );
				?>
			</div>
		</div>
	</article>
	<?php
}

/**
 * "New" threshold, matching the review's Zameen/Zillow observation that
 * freshness signals make a static grid feel alive — 7 days is a common
 * real-estate-portal convention (long enough to be meaningful at this
 * catalog's scale, short enough that "New" stays true).
 */
function primeestate_is_recently_listed( WP_Post $post ): bool {
	return ( current_time( 'timestamp' ) - get_the_time( 'U', $post ) ) <= ( 7 * DAY_IN_SECONDS );
}

/**
 * "Added 3 days ago" — built on core's own `human_time_diff()` rather than
 * a hand-rolled relative-time formatter, so translations/pluralization
 * already work correctly (matches the pattern used at data-model.md §1's
 * scale: no property needs second-level precision, so this is intentionally
 * coarse).
 */
function primeestate_get_listed_ago_text( WP_Post $post ): string {
	$ago = human_time_diff( get_the_time( 'U', $post ), current_time( 'timestamp' ) );

	/* translators: %s: human-readable relative time, e.g. "3 days" */
	return sprintf( __( 'Added %s ago', 'primeestate' ), $ago );
}
