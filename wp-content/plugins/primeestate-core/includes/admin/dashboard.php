<?php
/**
 * Admin moderation queue (T083, FR-043) — a wp-admin submenu page under the
 * Properties CPT listing every `pending` submission with Approve/Reject
 * actions. Uses `primeestate_moderate_property()` (property.php) — the same
 * function the `PATCH /properties/{id}/moderate` REST endpoint (T082)
 * calls — so wp-admin and the REST API can never disagree about what
 * "approve" or "reject" actually does.
 *
 * Phase 10 additions: the admin overview dashboard widget (T089), and the
 * Inquiries / Viewing Requests admin pages (T090's quick-filter/bulk-action
 * requirement for those two CPTs, plus T091's inline status/notes/reassign
 * UI) — kept in this file alongside the Moderation Queue rather than split
 * into admin/columns.php (which owns only the Property CPT's native
 * list-table customizations) since all three are the same kind of surface:
 * a purpose-built "Admin Oversight" page for a CPT that deliberately has no
 * native wp-admin list table of its own (see inquiry-post-type.php /
 * viewing-request.php's own doc comments, written in Phase 2, anticipating
 * exactly this).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_admin_moderation_menu(): void {
	add_submenu_page(
		'edit.php?post_type=property',
		__( 'Moderation Queue', 'primeestate' ),
		__( 'Moderation Queue', 'primeestate' ),
		'moderate_properties',
		'primeestate-moderation',
		'primeestate_render_moderation_queue_page'
	);
}
add_action( 'admin_menu', 'primeestate_register_admin_moderation_menu' );

function primeestate_render_moderation_queue_page(): void {
	if ( ! current_user_can( 'moderate_properties' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'primeestate' ) );
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'pending',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Property Moderation Queue', 'primeestate' ); ?></h1>

		<?php if ( isset( $_GET['pe_notice'] ) ) : ?>
			<?php if ( 'approved' === $_GET['pe_notice'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Property approved and published.', 'primeestate' ); ?></p></div>
			<?php elseif ( 'rejected' === $_GET['pe_notice'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Property rejected.', 'primeestate' ); ?></p></div>
			<?php elseif ( 'error' === $_GET['pe_notice'] ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not moderate this submission.', 'primeestate' ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! $query->have_posts() ) : ?>
			<p><?php esc_html_e( 'No pending submissions.', 'primeestate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Submitted By', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'primeestate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $query->posts as $post ) : ?>
						<?php
						$submitted_by = (int) get_post_meta( $post->ID, '_pe_submitted_by', true );
						$submitter    = get_userdata( $submitted_by );
						$nonce_action = 'primeestate_moderate_' . $post->ID;
						?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
							<td><?php echo esc_html( $submitter ? $submitter->display_name . ' (' . $submitter->user_email . ')' : __( 'Unknown', 'primeestate' ) ); ?></td>
							<td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
							<td>
								<a
									class="button button-primary"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=primeestate_moderate_property&id=' . $post->ID . '&decision=approve' ), $nonce_action ) ); ?>"
								><?php esc_html_e( 'Approve', 'primeestate' ); ?></a>
								<a
									class="button"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=primeestate_moderate_property&id=' . $post->ID . '&decision=reject' ), $nonce_action ) ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Reject this submission?', 'primeestate' ) ); ?>');"
								><?php esc_html_e( 'Reject', 'primeestate' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function primeestate_handle_admin_moderate_action(): void {
	if ( ! current_user_can( 'moderate_properties' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'primeestate' ) );
	}

	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'primeestate_moderate_' . $id );

	$decision = isset( $_GET['decision'] ) ? sanitize_text_field( wp_unslash( $_GET['decision'] ) ) : '';
	$result   = in_array( $decision, array( 'approve', 'reject' ), true )
		? primeestate_moderate_property( $id, $decision )
		: new WP_Error( 'invalid_decision', __( 'Invalid decision.', 'primeestate' ) );

	$notice = is_wp_error( $result ) ? 'error' : ( 'approve' === $decision ? 'approved' : 'rejected' );

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'primeestate-moderation', 'pe_notice' => $notice ),
			admin_url( 'edit.php?post_type=property' )
		)
	);
	exit;
}
add_action( 'admin_post_primeestate_moderate_property', 'primeestate_handle_admin_moderate_action' );

/**
 * T088/T089/FR-048: the exact set of counts the spec names, factored out of
 * the widget's render function so `tests/integration/test-admin-overview.php`
 * can assert against known seeded data directly rather than scraping
 * rendered HTML.
 *
 * "Active/sold/rented" come from the `property_status` taxonomy's own
 * maintained term counts, which WordPress's default counting callback only
 * tallies for `publish`-status posts — correctly excluding pending
 * submissions, which are counted separately via `pending_approval` (the
 * `post_status` workflow state, a different axis from the taxonomy's market
 * status per data-model.md §1). "Viewing request count" is a total, not
 * qualified by status, unlike "new inquiry count" which FR-048 explicitly
 * scopes to new — read literally from the requirement's own wording.
 */
function primeestate_get_admin_overview_counts(): array {
	$post_counts     = wp_count_posts( 'property' );
	$published_count = (int) ( $post_counts->publish ?? 0 );
	$pending_count   = (int) ( $post_counts->pending ?? 0 );

	$available_term = get_term_by( 'name', 'Available', 'property_status' );
	$sold_term      = get_term_by( 'name', 'Sold', 'property_status' );
	$rented_term    = get_term_by( 'name', 'Rented', 'property_status' );

	$user_counts = count_users();

	$new_inquiries_query = new WP_Query(
		array(
			'post_type'      => 'pe_inquiry',
			'post_status'    => 'publish',
			'meta_key'       => '_pe_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'New', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	$viewing_counts = wp_count_posts( 'pe_viewing_request' );

	return array(
		'total_properties'            => $published_count + $pending_count,
		'active_properties'           => $available_term instanceof WP_Term ? (int) $available_term->count : 0,
		'sold_properties'             => $sold_term instanceof WP_Term ? (int) $sold_term->count : 0,
		'rented_properties'           => $rented_term instanceof WP_Term ? (int) $rented_term->count : 0,
		'pending_approval_properties' => $pending_count,
		'agent_count'                 => (int) ( $user_counts['avail_roles']['agent'] ?? 0 ),
		'user_count'                  => (int) ( $user_counts['avail_roles']['subscriber'] ?? 0 ),
		'new_inquiry_count'           => (int) $new_inquiries_query->found_posts,
		'viewing_request_count'       => (int) ( $viewing_counts->publish ?? 0 ),
		'recent_properties'           => get_posts( array( 'post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => 5, 'orderby' => 'date', 'order' => 'DESC' ) ),
		'recent_inquiries'            => get_posts( array( 'post_type' => 'pe_inquiry', 'post_status' => 'publish', 'posts_per_page' => 5, 'orderby' => 'date', 'order' => 'DESC' ) ),
		'recent_submissions'          => get_posts( array( 'post_type' => 'property', 'post_status' => 'pending', 'posts_per_page' => 5, 'orderby' => 'date', 'order' => 'DESC' ) ),
	);
}

/**
 * T089: a native WP-admin Dashboard widget (`wp_add_dashboard_widget`), the
 * literal reading of "WordPress-native admin overview" (Phase 10's goal) —
 * distinct from the Moderation Queue page above, which is a full page for
 * acting on pending submissions, not a glance-level summary. Gated by
 * `moderate_properties`, the same capability the Moderation Queue itself
 * requires — the established "platform oversight staff" signal.
 */
function primeestate_register_admin_overview_widget(): void {
	if ( ! current_user_can( 'moderate_properties' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'primeestate_overview',
		__( 'PrimeEstate Overview', 'primeestate' ),
		'primeestate_render_admin_overview_widget'
	);
}
add_action( 'wp_dashboard_setup', 'primeestate_register_admin_overview_widget' );

function primeestate_render_admin_overview_widget(): void {
	$counts = primeestate_get_admin_overview_counts();
	?>
	<ul class="pe-admin-overview__counts">
		<li><?php echo esc_html( sprintf( __( 'Total properties: %d', 'primeestate' ), $counts['total_properties'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Active: %d', 'primeestate' ), $counts['active_properties'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Sold: %d', 'primeestate' ), $counts['sold_properties'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Rented: %d', 'primeestate' ), $counts['rented_properties'] ) ); ?></li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=property&page=primeestate-moderation' ) ); ?>">
				<?php echo esc_html( sprintf( __( 'Pending approval: %d', 'primeestate' ), $counts['pending_approval_properties'] ) ); ?>
			</a>
		</li>
		<li><?php echo esc_html( sprintf( __( 'Agents: %d', 'primeestate' ), $counts['agent_count'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Registered users: %d', 'primeestate' ), $counts['user_count'] ) ); ?></li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=property&page=primeestate-inquiries&status=New' ) ); ?>">
				<?php echo esc_html( sprintf( __( 'New inquiries: %d', 'primeestate' ), $counts['new_inquiry_count'] ) ); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=property&page=primeestate-viewings' ) ); ?>">
				<?php echo esc_html( sprintf( __( 'Viewing requests: %d', 'primeestate' ), $counts['viewing_request_count'] ) ); ?>
			</a>
		</li>
	</ul>

	<h4><?php esc_html_e( 'Recent properties', 'primeestate' ); ?></h4>
	<?php primeestate_render_admin_overview_recent_list( $counts['recent_properties'] ); ?>

	<h4><?php esc_html_e( 'Recent inquiries', 'primeestate' ); ?></h4>
	<?php primeestate_render_admin_overview_recent_list( $counts['recent_inquiries'], false ); ?>

	<h4><?php esc_html_e( 'Recent submissions awaiting review', 'primeestate' ); ?></h4>
	<?php primeestate_render_admin_overview_recent_list( $counts['recent_submissions'] ); ?>
	<?php
}

/**
 * `$linkable` must stay false for `pe_inquiry`/`pe_viewing_request` posts:
 * both are registered with `show_ui => false` and are deliberately "not a
 * post-edit screen" (inquiry-post-type.php's own doc comment) — `property`
 * posts, which do have a real wp-admin edit screen, are the only ones this
 * should link to `get_edit_post_link()`.
 *
 * @param WP_Post[] $posts
 */
function primeestate_render_admin_overview_recent_list( array $posts, bool $linkable = true ): void {
	if ( empty( $posts ) ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'primeestate' ) . '</p>';
		return;
	}

	echo '<ul>';
	foreach ( $posts as $post ) {
		if ( $linkable ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
		} else {
			echo '<li>' . esc_html( get_the_title( $post ) ) . '</li>';
		}
	}
	echo '</ul>';
}

/**
 * T090/T091: Inquiries and Viewing Requests admin pages — quick status
 * filter, bulk status change, and (inquiries only, since only inquiries
 * have a notes field, inquiry-meta.php vs. viewing-meta.php) per-row
 * status/notes/reassign controls. Every write here goes through
 * `rest_do_request()` against the exact same `/inquiries/{id}` and
 * `/viewings/{id}` PATCH endpoints (T053/T068) the front-end Agent
 * Dashboard already uses (agent-dashboard.js) — `rest_do_request()` calls
 * `WP_REST_Server::dispatch()` directly, which runs each route's
 * `permission_callback` but skips the cookie-nonce check `serve_request()`
 * applies for real HTTP calls (there is no `X-WP-Nonce` header to check in
 * an in-process call), so wp-admin's own nonce (`check_admin_referer()`,
 * enforced below before dispatch) is what actually protects the action —
 * the standard, safe pattern for reusing REST logic from an admin-post.php
 * handler.
 */
function primeestate_register_inquiries_admin_menu(): void {
	add_submenu_page(
		'edit.php?post_type=property',
		__( 'Inquiries', 'primeestate' ),
		__( 'Inquiries', 'primeestate' ),
		'view_inquiries',
		'primeestate-inquiries',
		'primeestate_render_inquiries_admin_page'
	);

	add_submenu_page(
		'edit.php?post_type=property',
		__( 'Viewing Requests', 'primeestate' ),
		__( 'Viewing Requests', 'primeestate' ),
		'view_inquiries',
		'primeestate-viewings',
		'primeestate_render_viewings_admin_page'
	);
}
add_action( 'admin_menu', 'primeestate_register_inquiries_admin_menu' );

function primeestate_render_inquiries_admin_page(): void {
	if ( ! current_user_can( 'view_inquiries' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'primeestate' ) );
	}

	$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$meta_query = in_array( $status, PRIMEESTATE_INQUIRY_STATUSES, true )
		? array( array( 'key' => '_pe_status', 'value' => $status ) )
		: array();

	$args = array(
		'post_type'      => 'pe_inquiry',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( ! primeestate_user_sees_all_inquiries() ) {
		$meta_query[] = array( 'key' => '_pe_agent_id', 'value' => get_current_user_id() );
	}

	if ( $meta_query ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$query   = new WP_Query( $args );
	$staff   = get_users( array( 'role__in' => array( 'agent', 'property_manager', 'administrator', 'editor' ) ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Inquiries', 'primeestate' ); ?></h1>

		<?php primeestate_render_admin_notice_from_query_arg(); ?>

		<form method="get">
			<input type="hidden" name="post_type" value="property">
			<input type="hidden" name="page" value="primeestate-inquiries">
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'primeestate' ); ?></option>
				<?php foreach ( PRIMEESTATE_INQUIRY_STATUSES as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'primeestate' ); ?></button>
		</form>

		<?php if ( ! $query->have_posts() ) : ?>
			<p><?php esc_html_e( 'No inquiries found.', 'primeestate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'From', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Assigned agent', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'primeestate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $query->posts as $post ) : ?>
						<?php $schema = primeestate_inquiry_to_schema( $post ); ?>
						<tr>
							<td><?php echo esc_html( $schema['name'] . ' — ' . $schema['email'] ); ?></td>
							<td><a href="<?php echo esc_url( get_permalink( $schema['property_id'] ) ); ?>"><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></a></td>
							<td><?php echo esc_html( wp_trim_words( $schema['message'], 12 ) ); ?></td>
							<td>
								<?php if ( primeestate_user_sees_all_inquiries() ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'primeestate_admin_update_inquiry_' . $post->ID ); ?>
										<input type="hidden" name="action" value="primeestate_admin_update_inquiry">
										<input type="hidden" name="id" value="<?php echo esc_attr( $post->ID ); ?>">
										<select name="status">
											<?php foreach ( PRIMEESTATE_INQUIRY_STATUSES as $option ) : ?>
												<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $schema['status'], $option ); ?>><?php echo esc_html( $option ); ?></option>
											<?php endforeach; ?>
										</select>
										<select name="assigned_agent_id">
											<?php foreach ( $staff as $staff_member ) : ?>
												<option value="<?php echo esc_attr( $staff_member->ID ); ?>" <?php selected( $schema['assigned_agent_id'], $staff_member->ID ); ?>><?php echo esc_html( $staff_member->display_name ); ?></option>
											<?php endforeach; ?>
										</select>
										<textarea name="note" rows="1" placeholder="<?php esc_attr_e( 'Add a note…', 'primeestate' ); ?>"></textarea>
										<button type="submit" class="button"><?php esc_html_e( 'Save', 'primeestate' ); ?></button>
									</form>
								<?php else : ?>
									<?php echo esc_html( $schema['status'] ); ?>
								<?php endif; ?>
							</td>
							<?php $assigned_user = get_userdata( $schema['assigned_agent_id'] ); ?>
						<td><?php echo esc_html( $assigned_user ? $assigned_user->display_name : '' ); ?></td>
							<td>
								<?php
								$notes = get_post_meta( $post->ID, '_pe_notes', true );
								if ( is_array( $notes ) && ! empty( $notes ) ) {
									echo '<ul>';
									foreach ( $notes as $note ) {
										$author = get_userdata( (int) ( $note['author'] ?? 0 ) );
										echo '<li>' . esc_html( ( $author ? $author->display_name : __( 'Unknown', 'primeestate' ) ) . ': ' . ( $note['note'] ?? '' ) ) . '</li>';
									}
									echo '</ul>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function primeestate_render_viewings_admin_page(): void {
	if ( ! current_user_can( 'view_inquiries' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'primeestate' ) );
	}

	$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$meta_query = in_array( $status, PRIMEESTATE_VIEWING_STATUSES, true )
		? array( array( 'key' => '_pe_status', 'value' => $status ) )
		: array();

	$args = array(
		'post_type'      => 'pe_viewing_request',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( ! primeestate_user_sees_all_inquiries() ) {
		$meta_query[] = array( 'key' => '_pe_agent_id', 'value' => get_current_user_id() );
	}

	if ( $meta_query ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$query = new WP_Query( $args );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Viewing Requests', 'primeestate' ); ?></h1>

		<?php primeestate_render_admin_notice_from_query_arg(); ?>

		<form method="get">
			<input type="hidden" name="post_type" value="property">
			<input type="hidden" name="page" value="primeestate-viewings">
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'primeestate' ); ?></option>
				<?php foreach ( PRIMEESTATE_VIEWING_STATUSES as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'primeestate' ); ?></button>
		</form>

		<?php if ( ! $query->have_posts() ) : ?>
			<p><?php esc_html_e( 'No viewing requests found.', 'primeestate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'From', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Requested', 'primeestate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $query->posts as $post ) : ?>
						<?php $schema = primeestate_viewing_request_to_schema( $post ); ?>
						<tr>
							<td><?php echo esc_html( $schema['name'] . ' — ' . $schema['phone'] ); ?></td>
							<td><a href="<?php echo esc_url( get_permalink( $schema['property_id'] ) ); ?>"><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></a></td>
							<td><?php echo esc_html( $schema['preferred_date'] . ' ' . $schema['preferred_time'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'primeestate_admin_update_viewing_' . $post->ID ); ?>
									<input type="hidden" name="action" value="primeestate_admin_update_viewing">
									<input type="hidden" name="id" value="<?php echo esc_attr( $post->ID ); ?>">
									<select name="status">
										<?php foreach ( PRIMEESTATE_VIEWING_STATUSES as $option ) : ?>
											<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $schema['status'], $option ); ?>><?php echo esc_html( $option ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button"><?php esc_html_e( 'Save', 'primeestate' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function primeestate_render_admin_notice_from_query_arg(): void {
	if ( empty( $_GET['pe_notice'] ) ) {
		return;
	}

	$type    = 'error' === $_GET['pe_notice'] ? 'notice-error' : 'notice-success';
	$message = 'error' === $_GET['pe_notice'] ? __( 'Could not save. Please try again.', 'primeestate' ) : __( 'Saved.', 'primeestate' );
	?>
	<div class="notice <?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php
}

function primeestate_handle_admin_update_inquiry(): void {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'primeestate_admin_update_inquiry_' . $id );

	if ( ! current_user_can( 'view_inquiries' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'primeestate' ) );
	}

	$request = new WP_REST_Request( 'PATCH', '/' . PRIMEESTATE_REST_NAMESPACE . '/inquiries/' . $id );
	$request->set_param( 'status', sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ) );

	if ( ! empty( $_POST['assigned_agent_id'] ) ) {
		$request->set_param( 'assigned_agent_id', absint( $_POST['assigned_agent_id'] ) );
	}

	if ( ! empty( $_POST['note'] ) ) {
		$request->set_param( 'note', sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) );
	}

	$response = rest_do_request( $request );
	$failed   = is_wp_error( $response ) || $response->get_status() >= 400;

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'primeestate-inquiries', 'pe_notice' => $failed ? 'error' : 'saved' ),
			admin_url( 'edit.php?post_type=property' )
		)
	);
	exit;
}
add_action( 'admin_post_primeestate_admin_update_inquiry', 'primeestate_handle_admin_update_inquiry' );

function primeestate_handle_admin_update_viewing(): void {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'primeestate_admin_update_viewing_' . $id );

	if ( ! current_user_can( 'view_inquiries' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'primeestate' ) );
	}

	$request = new WP_REST_Request( 'PATCH', '/' . PRIMEESTATE_REST_NAMESPACE . '/viewings/' . $id );
	$request->set_param( 'status', sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ) );

	$response = rest_do_request( $request );
	$failed   = is_wp_error( $response ) || $response->get_status() >= 400;

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'primeestate-viewings', 'pe_notice' => $failed ? 'error' : 'saved' ),
			admin_url( 'edit.php?post_type=property' )
		)
	);
	exit;
}
add_action( 'admin_post_primeestate_admin_update_viewing', 'primeestate_handle_admin_update_viewing' );

/**
 * T094 (FR-051, data-model.md §9): the "Related Properties" meta box on the
 * native `post` edit screen — a plain checkbox picker over published
 * properties rather than a fancier searchable/AJAX widget, since the
 * property count in this demo-scale platform (FR-060: 50+ seeded) comfortably
 * fits a scrollable list without needing Select2-style async search
 * infrastructure this codebase doesn't otherwise have. Saves to
 * `_pe_related_properties` (insight-meta.php); read back and rendered by
 * `[primeestate_related_properties]` (theme/inc/insight-render.php) on
 * `theme/templates/single-post.html`.
 */
function primeestate_register_related_properties_meta_box(): void {
	add_meta_box(
		'primeestate_related_properties',
		__( 'Related Properties', 'primeestate' ),
		'primeestate_render_related_properties_meta_box',
		'post',
		'side'
	);
}
add_action( 'add_meta_boxes', 'primeestate_register_related_properties_meta_box' );

function primeestate_render_related_properties_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'primeestate_save_related_properties_' . $post->ID, 'primeestate_related_properties_nonce' );

	$selected = get_post_meta( $post->ID, '_pe_related_properties', true );
	$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();

	$properties = get_posts(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	if ( empty( $properties ) ) {
		echo '<p>' . esc_html__( 'No published properties yet.', 'primeestate' ) . '</p>';
		return;
	}
	?>
	<p><?php esc_html_e( 'Select properties to feature alongside this article.', 'primeestate' ); ?></p>
	<div class="pe-related-properties-picker" style="max-height:220px;overflow-y:auto;">
		<?php foreach ( $properties as $property ) : ?>
			<label style="display:block;">
				<input type="checkbox" name="primeestate_related_properties[]" value="<?php echo esc_attr( $property->ID ); ?>" <?php checked( in_array( $property->ID, $selected, true ) ); ?>>
				<?php echo esc_html( get_the_title( $property ) ); ?>
			</label>
		<?php endforeach; ?>
	</div>
	<?php
}

function primeestate_save_related_properties_meta_box( int $post_id ): void {
	if ( ! isset( $_POST['primeestate_related_properties_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['primeestate_related_properties_nonce'] ) ), 'primeestate_save_related_properties_' . $post_id )
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$ids = isset( $_POST['primeestate_related_properties'] )
		? array_map( 'absint', (array) wp_unslash( $_POST['primeestate_related_properties'] ) )
		: array();

	update_post_meta( $post_id, '_pe_related_properties', $ids );
}
add_action( 'save_post_post', 'primeestate_save_related_properties_meta_box' );
