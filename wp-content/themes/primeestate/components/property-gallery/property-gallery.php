<?php
/**
 * PropertyGallery component (source doc §67, FR-018) — main image + thumbnail
 * strip, native lazy loading. Fullscreen/lightbox and mobile swipe are
 * progressive-enhancement JS (theme/assets/js/gallery.js); without
 * JavaScript, every thumbnail is a plain anchor to the full-size image, so
 * the gallery degrades to "click to open the image directly" rather than
 * breaking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_property_gallery( WP_Post $post ): void {
	$image_ids = array();

	if ( has_post_thumbnail( $post ) ) {
		$image_ids[] = (int) get_post_thumbnail_id( $post );
	}

	$gallery_ids = get_post_meta( $post->ID, '_pe_gallery', true );
	if ( is_array( $gallery_ids ) ) {
		$image_ids = array_values( array_unique( array_merge( $image_ids, array_map( 'absint', $gallery_ids ) ) ) );
	}

	if ( empty( $image_ids ) ) {
		?>
		<div class="pe-property-gallery pe-property-gallery--placeholder" aria-hidden="true"></div>
		<?php
		return;
	}

	/**
	 * Unified Gallery (competitive review): the floor plan, when set, joins
	 * the same slide/thumb strip as an extra entry rather than a separate
	 * component — gallery.js already treats every slide generically by
	 * index, so appending one more attachment ID here is enough; no JS
	 * special-casing needed. `$photo_count` is kept separate from the final
	 * `$slide_ids` count so the floor plan's aria-label reads "Floor plan",
	 * not "Image N of N+1" like an ordinary photo.
	 */
	$photo_count    = count( $image_ids );
	$floor_plan_id  = absint( get_post_meta( $post->ID, '_pe_floor_plan', true ) );
	$slide_ids      = $image_ids;
	$floor_plan_index = null;

	if ( $floor_plan_id ) {
		$floor_plan_index = count( $slide_ids );
		$slide_ids[]       = $floor_plan_id;
	}

	// The map thumb is a plain jump link, not a slide — deliberately outside
	// gallery.js's slide/thumb index pairing (see the component's own doc
	// comment) rather than an embedded live map, since Leaflet renders at
	// zero size when initialized inside a hidden lightbox slide and only
	// recovers if something remembers to call invalidateSize() at the exact
	// moment it becomes visible; a jump link avoids that class of bug
	// entirely.
	$has_map = primeestate_has_mappable_properties( array( $post ) );
	?>
	<div class="pe-property-gallery" data-component="property-gallery">
		<div class="pe-property-gallery__main">
			<?php foreach ( $slide_ids as $index => $attachment_id ) : ?>
				<a
					href="<?php echo esc_url( (string) wp_get_attachment_image_url( $attachment_id, 'full' ) ); ?>"
					class="pe-property-gallery__slide<?php echo $index === $floor_plan_index ? ' pe-property-gallery__slide--floor-plan' : ''; ?>"
					data-index="<?php echo esc_attr( $index ); ?>"
					aria-label="<?php echo esc_attr( $index === $floor_plan_index ? __( 'Floor plan. Select to view fullscreen.', 'primeestate' ) : sprintf( /* translators: 1: image number, 2: total images */ __( 'Image %1$d of %2$d. Select to view fullscreen.', 'primeestate' ), $index + 1, $photo_count ) ); ?>"
					<?php echo 0 === $index ? '' : 'hidden'; ?>
				>
					<?php if ( $index === $floor_plan_index ) : ?>
						<span class="pe-property-gallery__badge"><?php esc_html_e( 'Floor Plan', 'primeestate' ); ?></span>
					<?php endif; ?>
					<?php echo wp_get_attachment_image( $attachment_id, 'large', false, array( 'loading' => $index > 0 ? 'lazy' : 'eager' ) ); ?>
				</a>
			<?php endforeach; ?>

			<button type="button" class="pe-property-gallery__close screen-reader-text" aria-label="<?php esc_attr_e( 'Close fullscreen view', 'primeestate' ); ?>">
				<?php esc_html_e( 'Close', 'primeestate' ); ?>
			</button>

			<p class="pe-property-gallery__status screen-reader-text" role="status" aria-live="polite"></p>
		</div>

		<?php if ( count( $slide_ids ) > 1 ) : ?>
			<ul class="pe-property-gallery__thumbs">
				<?php foreach ( $slide_ids as $index => $attachment_id ) : ?>
					<li>
						<button
							type="button"
							class="pe-property-gallery__thumb<?php echo $index === $floor_plan_index ? ' pe-property-gallery__thumb--floor-plan' : ''; ?>"
							data-index="<?php echo esc_attr( $index ); ?>"
							aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( $index === $floor_plan_index ? __( 'View floor plan', 'primeestate' ) : sprintf( /* translators: %d: image number */ __( 'View image %d', 'primeestate' ), $index + 1 ) ); ?>"
						>
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'loading' => 'lazy' ) ); ?>
							<?php if ( $index === $floor_plan_index ) : ?>
								<span class="pe-property-gallery__thumb-label"><?php esc_html_e( 'Floor Plan', 'primeestate' ); ?></span>
							<?php endif; ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $has_map ) : ?>
			<a href="#pe-map" class="pe-property-gallery__map-link">
				<span class="pe-property-gallery__map-link-icon" aria-hidden="true">&#128506;</span>
				<span><?php esc_html_e( 'View on Map', 'primeestate' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
	<?php
}
