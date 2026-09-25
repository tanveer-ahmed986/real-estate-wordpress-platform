<?php
/**
 * Agent Dashboard (T072, T075): own properties, an Add Property action, and
 * management tables for assigned inquiries/viewing requests (status change
 * + internal notes), wired to the PATCH endpoints built in Phases 4 and 6
 * (T053, T068). Gated by `primeestate_user_can_access_agent_area()`
 * (security.php) — anyone without `manage_properties` sees the standard
 * access-denied panel instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_agent_dashboard_shortcode(): string {
	if ( ! primeestate_user_can_access_agent_area() ) {
		return primeestate_render_access_denied_notice();
	}

	$user_id = get_current_user_id();

	ob_start();
	?>
	<div class="pe-agent-dashboard">
		<section class="pe-agent-dashboard__section">
			<div class="pe-agent-dashboard__section-header">
				<h2><?php esc_html_e( 'My Properties', 'primeestate' ); ?></h2>
				<a class="pe-button" href="<?php echo esc_url( home_url( '/agent-add-property/' ) ); ?>"><?php esc_html_e( 'Add Property', 'primeestate' ); ?></a>
			</div>
			<?php primeestate_render_agent_properties_table( $user_id ); ?>
		</section>

		<section class="pe-agent-dashboard__section">
			<h2><?php esc_html_e( 'Assigned Inquiries', 'primeestate' ); ?></h2>
			<?php primeestate_render_agent_inquiries_table( primeestate_get_assigned_inquiries( $user_id ) ); ?>
		</section>

		<section class="pe-agent-dashboard__section">
			<h2><?php esc_html_e( 'Assigned Viewing Requests', 'primeestate' ); ?></h2>
			<?php primeestate_render_agent_viewings_table( primeestate_get_assigned_viewing_requests( $user_id ) ); ?>
		</section>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_agent_dashboard', 'primeestate_agent_dashboard_shortcode' );

function primeestate_render_agent_properties_table( int $user_id ): void {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'author'         => $user_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( ! $query->have_posts() ) {
		echo '<p>' . esc_html__( "You haven't added any properties yet.", 'primeestate' ) . '</p>';
		return;
	}
	?>
	<table class="pe-dashboard-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Title', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'primeestate' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $query->posts as $post ) : ?>
				<tr>
					<td><?php echo esc_html( get_the_title( $post ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $post->post_status ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( add_query_arg( 'property_id', $post->ID, home_url( '/agent-add-property/' ) ) ); ?>"><?php esc_html_e( 'Edit', 'primeestate' ); ?></a>
						<?php if ( 'publish' === $post->post_status ) : ?>
							· <a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View', 'primeestate' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Client-side status filter shared by the inquiries and viewings tables —
 * both already carry the full record set in the DOM (no pagination), so
 * filtering by hiding/showing rows avoids a redundant server round-trip for
 * data already present.
 *
 * @param string[] $statuses
 */
function primeestate_render_status_filter( string $target, array $statuses ): void {
	?>
	<label class="pe-status-filter">
		<?php esc_html_e( 'Filter by status:', 'primeestate' ); ?>
		<select class="pe-status-filter__select" data-filter-target="<?php echo esc_attr( $target ); ?>">
			<option value=""><?php esc_html_e( 'All', 'primeestate' ); ?></option>
			<?php foreach ( $statuses as $status ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}

/**
 * @param WP_Post[] $inquiries
 */
function primeestate_render_agent_inquiries_table( array $inquiries ): void {
	if ( empty( $inquiries ) ) {
		echo '<p>' . esc_html__( 'No inquiries assigned to you yet.', 'primeestate' ) . '</p>';
		return;
	}

	primeestate_render_status_filter( 'inquiries', PRIMEESTATE_INQUIRY_STATUSES );
	?>
	<table class="pe-dashboard-table" data-component="agent-records-table" data-table="inquiries">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'From', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Message', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Notes', 'primeestate' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $inquiries as $post ) : ?>
				<?php $schema = primeestate_inquiry_to_schema( $post ); ?>
				<tr data-record-id="<?php echo esc_attr( $post->ID ); ?>" data-record-type="inquiries" data-status="<?php echo esc_attr( $schema['status'] ); ?>">
					<td><?php echo esc_html( $schema['name'] . ' — ' . $schema['email'] ); ?></td>
					<td><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $schema['message'], 15 ) ); ?></td>
					<td>
						<select class="pe-status-select" data-endpoint="inquiries">
							<?php foreach ( PRIMEESTATE_INQUIRY_STATUSES as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $schema['status'], $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<form class="pe-note-form" data-endpoint="inquiries">
							<textarea name="note" rows="1" placeholder="<?php esc_attr_e( 'Add a note…', 'primeestate' ); ?>"></textarea>
							<button type="submit"><?php esc_html_e( 'Save', 'primeestate' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * @param WP_Post[] $viewings
 */
function primeestate_render_agent_viewings_table( array $viewings ): void {
	if ( empty( $viewings ) ) {
		echo '<p>' . esc_html__( 'No viewing requests assigned to you yet.', 'primeestate' ) . '</p>';
		return;
	}

	primeestate_render_status_filter( 'viewings', PRIMEESTATE_VIEWING_STATUSES );
	?>
	<table class="pe-dashboard-table" data-component="agent-records-table" data-table="viewings">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'From', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Requested', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $viewings as $post ) : ?>
				<?php $schema = primeestate_viewing_request_to_schema( $post ); ?>
				<tr data-record-id="<?php echo esc_attr( $post->ID ); ?>" data-record-type="viewings" data-status="<?php echo esc_attr( $schema['status'] ); ?>">
					<td><?php echo esc_html( $schema['name'] . ' — ' . $schema['phone'] ); ?></td>
					<td><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></td>
					<td><?php echo esc_html( $schema['preferred_date'] . ' ' . $schema['preferred_time'] ); ?></td>
					<td>
						<select class="pe-status-select" data-endpoint="viewings">
							<?php foreach ( PRIMEESTATE_VIEWING_STATUSES as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $schema['status'], $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
