<?php
/**
 * EmptyState component (source doc §67, FR-017) — shown when a search/filter
 * combination returns zero results.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_empty_state( string $message = '' ): void {
	if ( '' === $message ) {
		$message = __( 'No properties match your search. Try adjusting or clearing some filters.', 'primeestate' );
	}
	?>
	<div class="pe-empty-state" role="status">
		<p class="pe-empty-state__message"><?php echo esc_html( $message ); ?></p>
		<a class="pe-empty-state__reset" href="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>">
			<?php esc_html_e( 'Clear all filters', 'primeestate' ); ?>
		</a>
	</div>
	<?php
}
