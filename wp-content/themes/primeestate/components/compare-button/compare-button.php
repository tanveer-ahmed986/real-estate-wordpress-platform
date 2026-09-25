<?php
/**
 * CompareButton component (source doc §67, FR-022/023). Comparison is
 * client-side only (data-model.md §8: no server persistence for either
 * guests or logged-in users), so unlike FavoriteButton, PHP has no way to
 * know the initial "already in comparison" state — always renders
 * `aria-pressed="false"`; comparison.js corrects it on page load from
 * `sessionStorage`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_compare_button( int $property_id ): void {
	?>
	<button
		type="button"
		class="pe-compare-button"
		data-component="compare-button"
		data-property-id="<?php echo esc_attr( $property_id ); ?>"
		aria-pressed="false"
		aria-label="<?php esc_attr_e( 'Add to comparison', 'primeestate' ); ?>"
	><?php esc_html_e( 'Compare', 'primeestate' ); ?></button>
	<?php
}
