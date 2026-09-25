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
