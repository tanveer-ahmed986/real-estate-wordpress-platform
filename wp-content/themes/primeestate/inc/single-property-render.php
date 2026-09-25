<?php
/**
 * Property detail page rendering (T048, T056; FR-018, FR-054). Same pattern
 * as the archive (theme/inc/archive-render.php): the block template
 * (templates/single-property.html) hosts a `[primeestate_property_detail]`
 * shortcode because the content is too data-driven for static block markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_property_detail_shortcode(): string {
	$post = get_queried_object();

	if ( ! ( $post instanceof WP_Post ) || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
		return primeestate_render_property_unavailable_notice();
	}

	ob_start();

	$status_terms   = get_the_terms( $post, 'property_status' );
	$market_status  = $status_terms && ! is_wp_error( $status_terms ) ? $status_terms[0]->slug : '';
	$is_unavailable = in_array( $market_status, array( 'sold', 'rented', 'off-market' ), true );

	primeestate_render_property_gallery( $post );
	?>
	<div class="pe-property-detail">
		<header class="pe-property-detail__header">
			<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<?php primeestate_render_property_detail_summary( $post ); ?>
		</header>

		<?php if ( $is_unavailable ) : ?>
			<div class="pe-property-detail__unavailable" role="status">
				<?php esc_html_e( 'This property is no longer available. Browse similar listings below.', 'primeestate' ); ?>
			</div>
		<?php endif; ?>

		<div class="pe-property-detail__body">
			<div class="pe-property-detail__description">
				<?php echo wp_kses_post( wpautop( get_the_content( null, false, $post ) ) ); ?>
			</div>

			<?php primeestate_render_property_amenities( $post ); ?>

			<?php primeestate_render_map( array( $post ) ); ?>
		</div>

		<aside class="pe-property-detail__sidebar">
			<?php primeestate_render_agent_card( (int) $post->post_author ); ?>

			<?php if ( ! $is_unavailable ) : ?>
				<?php primeestate_render_inquiry_form( $post->ID ); ?>
				<?php primeestate_render_viewing_form( $post->ID ); ?>
			<?php else : ?>
				<p class="pe-property-detail__unavailable-cta">
					<?php esc_html_e( 'Contact our team to ask about similar available properties.', 'primeestate' ); ?>
				</p>
			<?php endif; ?>
		</aside>
	</div>

	<?php primeestate_render_related_properties( $post->ID ); ?>
	<?php

	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_property_detail', 'primeestate_property_detail_shortcode' );

function primeestate_render_property_detail_summary( WP_Post $post ): void {
	$summary = primeestate_property_to_summary( $post );
	$price   = primeestate_format_price( $summary['price'], (string) get_post_meta( $post->ID, '_pe_price_type', true ), $summary['currency'] );
	?>
	<p class="pe-property-detail__price"><?php echo esc_html( $price ); ?></p>
	<?php if ( $summary['city'] ) : ?>
		<p class="pe-property-detail__location"><?php echo esc_html( $summary['city'] ); ?></p>
	<?php endif; ?>
	<ul class="pe-property-detail__specs">
		<li><?php echo esc_html( sprintf( _n( '%d bed', '%d beds', $summary['bedrooms'], 'primeestate' ), $summary['bedrooms'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( _n( '%d bath', '%d baths', $summary['bathrooms'], 'primeestate' ), $summary['bathrooms'] ) ); ?></li>
		<?php if ( $summary['area'] > 0 ) : ?>
			<li><?php echo esc_html( number_format_i18n( $summary['area'] ) ); ?></li>
		<?php endif; ?>
	</ul>
	<?php
}

function primeestate_render_property_amenities( WP_Post $post ): void {
	$amenities = get_the_terms( $post, 'amenity' );

	if ( ! $amenities || is_wp_error( $amenities ) ) {
		return;
	}
	?>
	<div class="pe-property-detail__amenities">
		<h2><?php esc_html_e( 'Amenities', 'primeestate' ); ?></h2>
		<ul>
			<?php foreach ( $amenities as $term ) : ?>
				<li><?php echo esc_html( $term->name ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

function primeestate_render_agent_card( int $agent_id ): void {
	$agent = get_userdata( $agent_id );

	if ( ! $agent ) {
		return;
	}

	$phone = get_user_meta( $agent_id, '_pe_agent_phone', true );
	$photo = get_avatar( $agent_id, 96 );
	?>
	<div class="pe-agent-card">
		<?php echo wp_kses_post( $photo ); ?>
		<p class="pe-agent-card__name"><?php echo esc_html( $agent->display_name ); ?></p>
		<?php if ( $phone ) : ?>
			<p class="pe-agent-card__phone"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
		<?php endif; ?>
		<p class="pe-agent-card__email"><a href="mailto:<?php echo esc_attr( $agent->user_email ); ?>"><?php echo esc_html( $agent->user_email ); ?></a></p>
	</div>
	<?php
}

function primeestate_render_related_properties( int $property_id ): void {
	$related = primeestate_get_related_properties( $property_id );

	if ( empty( $related ) ) {
		return;
	}
	?>
	<section class="pe-related-properties">
		<h2><?php esc_html_e( 'Similar properties', 'primeestate' ); ?></h2>
		<div class="pe-property-grid">
			<?php foreach ( $related as $related_post ) : ?>
				<?php primeestate_render_property_card( $related_post ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * FR-054: friendly fallback for a property that is missing, unpublished, or
 * otherwise inaccessible — e.g. an old bookmarked/shared link to a listing
 * that was later removed — instead of a bare 404.
 */
function primeestate_render_property_unavailable_notice(): string {
	ob_start();
	?>
	<div class="pe-property-detail__not-found">
		<h1><?php esc_html_e( 'This property is no longer available', 'primeestate' ); ?></h1>
		<p><?php esc_html_e( "The listing you're looking for may have been removed or sold.", 'primeestate' ); ?></p>
		<a class="pe-property-detail__not-found-cta" href="<?php echo esc_url( (string) get_post_type_archive_link( 'property' ) ); ?>">
			<?php esc_html_e( 'Browse all properties', 'primeestate' ); ?>
		</a>
	</div>
	<?php
	return (string) ob_get_clean();
}
