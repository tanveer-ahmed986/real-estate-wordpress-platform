<?php
/**
 * Registered User Dashboard (T085-T086, FR-046): a single strictly-own-data
 * view combining Favorites (T061), Comparisons (T063), Inquiries, Viewing
 * Requests, and Account Settings. Gated by `is_user_logged_in()` only — any
 * authenticated role may view their own dashboard, unlike the agent-area
 * pages which require `manage_properties`.
 *
 * Favorites and Comparisons reuse the exact rendering already built for
 * their own pages (favorites-render.php, compare-render.php) rather than
 * duplicating markup: Favorites via the already-logged-in-user code path of
 * `primeestate_get_favorited_properties()`, Comparisons by embedding the
 * compare page's own shortcode output verbatim (its container is populated
 * client-side from `localStorage` by the existing comparison.js — see
 * data-model.md §8, comparison has zero server persistence for anyone).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_user_dashboard_shortcode(): string {
	if ( ! is_user_logged_in() ) {
		return primeestate_render_access_denied_notice();
	}

	$user = wp_get_current_user();

	ob_start();
	?>
	<div class="pe-user-dashboard">
		<section class="pe-user-dashboard__section" id="pe-dashboard-favorites">
			<h2><?php esc_html_e( 'My Favorites', 'primeestate' ); ?></h2>
			<?php primeestate_render_favorites_results( primeestate_get_favorited_properties( $user->ID ) ); ?>
		</section>

		<section class="pe-user-dashboard__section" id="pe-dashboard-compare">
			<h2><?php esc_html_e( 'My Comparisons', 'primeestate' ); ?></h2>
			<?php echo primeestate_compare_page_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is already escaped internally. ?>
		</section>

		<section class="pe-user-dashboard__section" id="pe-dashboard-inquiries">
			<h2><?php esc_html_e( 'My Inquiries', 'primeestate' ); ?></h2>
			<?php primeestate_render_user_inquiries_table( primeestate_get_own_inquiries( $user->user_email ) ); ?>
		</section>

		<section class="pe-user-dashboard__section" id="pe-dashboard-viewings">
			<h2><?php esc_html_e( 'My Viewing Requests', 'primeestate' ); ?></h2>
			<?php primeestate_render_user_viewings_table( primeestate_get_own_viewing_requests( $user->user_email ) ); ?>
		</section>

		<section class="pe-user-dashboard__section" id="pe-dashboard-account">
			<h2><?php esc_html_e( 'Account Settings', 'primeestate' ); ?></h2>
			<?php primeestate_render_account_settings_form( $user ); ?>
		</section>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_user_dashboard', 'primeestate_user_dashboard_shortcode' );

/**
 * Read-only — unlike the agent dashboard's inquiries/viewings tables, a
 * registered user reviewing their own submissions has no status-change or
 * internal-notes actions (those are staff-only, gated by `view_inquiries`
 * elsewhere); this table exists purely so they can see what they submitted
 * and its current status.
 *
 * @param WP_Post[] $inquiries
 */
function primeestate_render_user_inquiries_table( array $inquiries ): void {
	if ( empty( $inquiries ) ) {
		echo '<p>' . esc_html__( "You haven't submitted any inquiries yet.", 'primeestate' ) . '</p>';
		return;
	}
	?>
	<table class="pe-dashboard-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Message', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Submitted', 'primeestate' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $inquiries as $post ) : ?>
				<?php $schema = primeestate_inquiry_to_schema( $post ); ?>
				<tr>
					<td><a href="<?php echo esc_url( get_permalink( $schema['property_id'] ) ); ?>"><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></a></td>
					<td><?php echo esc_html( wp_trim_words( $schema['message'], 15 ) ); ?></td>
					<td><?php echo esc_html( $schema['status'] ); ?></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $schema['submitted_at'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * @param WP_Post[] $viewings
 */
function primeestate_render_user_viewings_table( array $viewings ): void {
	if ( empty( $viewings ) ) {
		echo '<p>' . esc_html__( "You haven't requested any viewings yet.", 'primeestate' ) . '</p>';
		return;
	}
	?>
	<table class="pe-dashboard-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Property', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Requested', 'primeestate' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'primeestate' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $viewings as $post ) : ?>
				<?php $schema = primeestate_viewing_request_to_schema( $post ); ?>
				<tr>
					<td><a href="<?php echo esc_url( get_permalink( $schema['property_id'] ) ); ?>"><?php echo esc_html( get_the_title( $schema['property_id'] ) ); ?></a></td>
					<td><?php echo esc_html( $schema['preferred_date'] . ' ' . $schema['preferred_time'] ); ?></td>
					<td><?php echo esc_html( $schema['status'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * T086: profile fields (first name, last name, email) + an optional password
 * change, submitted via `user-dashboard.js` to WordPress core's own
 * `PATCH /wp/v2/users/me` endpoint rather than a new custom one — the same
 * "don't build what core already provides" call made for property edits
 * (`/wp/v2/property-posts/{id}`, Phase 7) and agent-profile.php's own code
 * comment about the generic users endpoint. Core's REST layer already
 * enforces the nonce (`X-WP-Nonce`) and capability check T024 standardizes
 * elsewhere in this plugin: `edit_user` map-meta-caps to "always allowed"
 * when a user is editing themselves, so no separate custom capability gate
 * is needed here beyond the `is_user_logged_in()` render-gate above.
 */
function primeestate_render_account_settings_form( WP_User $user ): void {
	?>
	<form class="pe-account-settings-form" data-component="account-settings-form">
		<div class="pe-account-settings-form__field">
			<label for="pe-account-first-name"><?php esc_html_e( 'First name', 'primeestate' ); ?></label>
			<input type="text" id="pe-account-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>">
		</div>

		<div class="pe-account-settings-form__field">
			<label for="pe-account-last-name"><?php esc_html_e( 'Last name', 'primeestate' ); ?></label>
			<input type="text" id="pe-account-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>">
		</div>

		<div class="pe-account-settings-form__field">
			<label for="pe-account-email"><?php esc_html_e( 'Email', 'primeestate' ); ?></label>
			<input type="email" id="pe-account-email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
		</div>

		<div class="pe-account-settings-form__field">
			<label for="pe-account-password"><?php esc_html_e( 'New password (leave blank to keep current)', 'primeestate' ); ?></label>
			<input type="password" id="pe-account-password" name="password" autocomplete="new-password">
		</div>

		<div class="pe-account-settings-form__field">
			<label for="pe-account-password-confirm"><?php esc_html_e( 'Confirm new password', 'primeestate' ); ?></label>
			<input type="password" id="pe-account-password-confirm" name="password_confirm" autocomplete="new-password">
		</div>

		<button type="submit" class="pe-account-settings-form__submit"><?php esc_html_e( 'Save changes', 'primeestate' ); ?></button>
		<p class="pe-account-settings-form__status" role="status" aria-live="polite"></p>
	</form>
	<?php
}
