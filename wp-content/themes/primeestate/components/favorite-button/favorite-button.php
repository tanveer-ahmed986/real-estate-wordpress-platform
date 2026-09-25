<?php
/**
 * FavoriteButton component (source doc §67, FR-019–FR-021). Shared by
 * PropertyCard, the single-property page, and the favorites listing.
 *
 * Initial state is server-rendered correctly for logged-in users (checked
 * via the plugin's per-request-cached `primeestate_is_property_favorited_by_current_user()`,
 * avoiding a flash of the wrong state); guests always start unfavorited
 * server-side (PHP cannot read `localStorage`) and favorites.js corrects the
 * visual state on page load from the guest's local list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_favorite_button( int $property_id ): void {
	$is_favorited = primeestate_is_property_favorited_by_current_user( $property_id );
	?>
	<button
		type="button"
		class="pe-favorite-button"
		data-component="favorite-button"
		data-property-id="<?php echo esc_attr( $property_id ); ?>"
		aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
		aria-label="<?php esc_attr_e( 'Save to favorites', 'primeestate' ); ?>"
	><span aria-hidden="true"><?php echo $is_favorited ? '&#9829;' : '&#9825;'; ?></span></button>
	<?php
}
