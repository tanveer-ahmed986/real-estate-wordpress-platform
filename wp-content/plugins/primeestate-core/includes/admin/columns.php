<?php
/**
 * T090 (FR-048's "manages ... platform-wide"): quick filters and bulk
 * actions for the Property CPT's native wp-admin list table
 * (`show_ui => true`, post-types/property.php). `property_status` and
 * `listing_type` already have `show_admin_column => true` from their own
 * taxonomy registration, so this file only adds what WordPress doesn't
 * generate automatically: the `restrict_manage_posts` filter dropdowns and a
 * bulk market-status action.
 *
 * `pe_inquiry`/`pe_viewing_request` deliberately have `show_ui => false`
 * (inquiry-post-type.php, viewing-request.php) — they were never meant to
 * get WordPress's generic per-post edit screen, only the purpose-built
 * "Admin Oversight" list pages those files' own doc comments describe.
 * Those pages (with their own quick filter + bulk status action, covering
 * the rest of T090's scope) live in admin/dashboard.php alongside the
 * Moderation Queue (T083) and the inline status/notes UI (T091) they share
 * a page with, rather than being split across two files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_property_admin_filters(): void {
	global $typenow;

	if ( 'property' !== $typenow ) {
		return;
	}

	primeestate_render_admin_taxonomy_filter( 'property_status', __( 'All statuses', 'primeestate' ) );
	primeestate_render_admin_taxonomy_filter( 'listing_type', __( 'All listing types', 'primeestate' ) );
}
add_action( 'restrict_manage_posts', 'primeestate_render_property_admin_filters' );

function primeestate_render_admin_taxonomy_filter( string $taxonomy, string $all_label ): void {
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return;
	}

	$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
	?>
	<select name="<?php echo esc_attr( $taxonomy ); ?>">
		<option value=""><?php echo esc_html( $all_label ); ?></option>
		<?php foreach ( $terms as $term ) : ?>
			<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

function primeestate_apply_property_admin_filters( WP_Query $query ): void {
	global $pagenow, $typenow;

	if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow || 'property' !== $typenow ) {
		return;
	}

	$tax_query = array();

	foreach ( array( 'property_status', 'listing_type' ) as $taxonomy ) {
		if ( ! empty( $_GET[ $taxonomy ] ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ),
			);
		}
	}

	if ( $tax_query ) {
		$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}
}
add_action( 'pre_get_posts', 'primeestate_apply_property_admin_filters' );

/**
 * Bulk market-status change — gated by `edit_others_properties` (the
 * established "platform-wide, not own-only" signal, agent-permissions.php)
 * rather than `moderate_properties`: this changes a listing's Available/
 * Sold/Rented market status, a normal editing action property_manager
 * should be able to do, not the approve/reject workflow decision
 * `moderate_properties` specifically gates (data-model.md §10).
 */
function primeestate_register_property_bulk_actions( array $actions ): array {
	$actions['pe_mark_available'] = __( 'Mark as Available', 'primeestate' );
	$actions['pe_mark_sold']      = __( 'Mark as Sold', 'primeestate' );
	$actions['pe_mark_rented']    = __( 'Mark as Rented', 'primeestate' );

	return $actions;
}
add_filter( 'bulk_actions-edit-property', 'primeestate_register_property_bulk_actions' );

function primeestate_handle_property_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
	$status_by_action = array(
		'pe_mark_available' => 'Available',
		'pe_mark_sold'      => 'Sold',
		'pe_mark_rented'    => 'Rented',
	);

	if ( ! isset( $status_by_action[ $action ] ) || ! current_user_can( 'edit_others_properties' ) ) {
		return $redirect_to;
	}

	$updated = 0;

	foreach ( $post_ids as $post_id ) {
		if ( current_user_can( 'edit_post', $post_id )
			&& ! is_wp_error( wp_set_post_terms( $post_id, array( $status_by_action[ $action ] ), 'property_status' ) )
		) {
			++$updated;
		}
	}

	return add_query_arg( 'pe_bulk_status_updated', $updated, $redirect_to );
}
add_filter( 'handle_bulk_actions-edit-property', 'primeestate_handle_property_bulk_actions', 10, 3 );

function primeestate_property_bulk_action_admin_notice(): void {
	if ( empty( $_GET['pe_bulk_status_updated'] ) ) {
		return;
	}

	$count = (int) $_GET['pe_bulk_status_updated'];
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of properties updated */
					_n( '%d property updated.', '%d properties updated.', $count, 'primeestate' ),
					$count
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'primeestate_property_bulk_action_admin_notice' );
