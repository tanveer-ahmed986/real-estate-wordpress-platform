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
	?>
	<div class="pe-property-gallery" data-component="property-gallery">
		<div class="pe-property-gallery__main">
			<?php foreach ( $image_ids as $index => $attachment_id ) : ?>
				<a
					href="<?php echo esc_url( (string) wp_get_attachment_image_url( $attachment_id, 'full' ) ); ?>"
					class="pe-property-gallery__slide"
					data-index="<?php echo esc_attr( $index ); ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: 1: image number, 2: total images */ __( 'Image %1$d of %2$d. Select to view fullscreen.', 'primeestate' ), $index + 1, count( $image_ids ) ) ); ?>"
					<?php echo 0 === $index ? '' : 'hidden'; ?>
				>
					<?php echo wp_get_attachment_image( $attachment_id, 'large', false, array( 'loading' => $index > 0 ? 'lazy' : 'eager' ) ); ?>
				</a>
			<?php endforeach; ?>

			<button type="button" class="pe-property-gallery__close screen-reader-text" aria-label="<?php esc_attr_e( 'Close fullscreen view', 'primeestate' ); ?>">
				<?php esc_html_e( 'Close', 'primeestate' ); ?>
			</button>

			<p class="pe-property-gallery__status screen-reader-text" role="status" aria-live="polite"></p>
		</div>

		<?php if ( count( $image_ids ) > 1 ) : ?>
			<ul class="pe-property-gallery__thumbs">
				<?php foreach ( $image_ids as $index => $attachment_id ) : ?>
					<li>
						<button
							type="button"
							class="pe-property-gallery__thumb"
							data-index="<?php echo esc_attr( $index ); ?>"
							aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'View image %d', 'primeestate' ), $index + 1 ) ); ?>"
						>
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'loading' => 'lazy' ) ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
