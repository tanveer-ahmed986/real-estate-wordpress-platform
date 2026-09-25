<?php
/**
 * `[primeestate_related_properties]` (T094) — the one genuinely custom piece
 * of `theme/templates/single-post.html`; everything else on that template
 * (title, featured image, author, date, category, content) is native WP
 * core block markup, since a standard `post` needs no PHP to render those
 * (data-model.md §9's whole point: "standard WordPress blog tooling ...
 * works unmodified"). Renders nothing when the curated list is empty or
 * every curated property has since been unpublished — related properties
 * are optional per FR-051, so an empty section isn't an error state worth
 * an empty-state message the way search results are.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_related_properties_shortcode(): string {
	$post_id = get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$ids = get_post_meta( $post_id, '_pe_related_properties', true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();

	if ( empty( $ids ) ) {
		return '';
	}

	$query = new WP_Query( primeestate_build_property_query_args( array( 'ids' => $ids ) ) );

	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	<section class="pe-related-properties">
		<h2><?php esc_html_e( 'Related Properties', 'primeestate' ); ?></h2>
		<div class="pe-property-grid">
			<?php foreach ( $query->posts as $property_post ) : ?>
				<?php primeestate_render_property_card( $property_post ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_related_properties', 'primeestate_related_properties_shortcode' );
