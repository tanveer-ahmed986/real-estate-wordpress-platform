<?php
/**
 * Shared security helpers (constitution Principle II, FR-045, FR-055).
 * Thin, explicit wrappers around WP core so every call site in this plugin
 * uses the same nonce action naming and capability-check pattern rather than
 * ad hoc `wp_verify_nonce()`/`current_user_can()` calls scattered around.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_NONCE_ACTION = 'primeestate_action';

function primeestate_create_nonce(): string {
	return wp_create_nonce( PRIMEESTATE_NONCE_ACTION );
}

/**
 * Verifies a nonce from either an AJAX/REST request field or the
 * `X-WP-Nonce` header (REST's own `_wpnonce`-equivalent), per FR-045.
 */
function primeestate_verify_nonce( string $nonce ): bool {
	return (bool) wp_verify_nonce( $nonce, PRIMEESTATE_NONCE_ACTION );
}

/**
 * Verifies the standard WordPress REST nonce (`X-WP-Nonce`) for
 * cookie-authenticated state-changing requests, per the OpenAPI contract's
 * `cookieAuth` scheme description.
 */
function primeestate_verify_rest_nonce( \WP_REST_Request $request ): bool {
	$nonce = $request->get_header( 'X-WP-Nonce' );

	return is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * Capability check that is always re-verified server-side, never trusting a
 * client-supplied role/capability claim, per constitution Principle II.
 */
function primeestate_current_user_can( string $capability, ...$args ): bool {
	return current_user_can( $capability, ...$args );
}

/**
 * True if the current user authored the given post — the basis for every
 * "own only" enforcement in this plugin that isn't already covered by
 * WordPress's built-in map_meta_cap post-author check (e.g. inquiries,
 * viewing requests, which use a `_pe_agent_id` meta link rather than
 * `post_author`).
 */
function primeestate_user_owns_post( int $user_id, int $post_id ): bool {
	$post = get_post( $post_id );

	return null !== $post && (int) $post->post_author === $user_id;
}

/**
 * Sanitizes a plain-text field (single line): trims, strips tags, collapses
 * whitespace. Use for names, phone numbers, single-line free text.
 */
function primeestate_sanitize_text( string $value ): string {
	return sanitize_text_field( wp_unslash( $value ) );
}

/**
 * Sanitizes a multi-line message field, preserving line breaks but stripping
 * markup. Use for inquiry/viewing-request messages, property descriptions
 * submitted via plain textareas.
 */
function primeestate_sanitize_message( string $value ): string {
	return sanitize_textarea_field( wp_unslash( $value ) );
}

/**
 * Sanitizes rich text intended for `post_content`-style storage where a
 * constrained set of HTML is allowed (property descriptions, agent bios).
 */
function primeestate_sanitize_rich_text( string $value ): string {
	return wp_kses_post( wp_unslash( $value ) );
}

function primeestate_sanitize_email( string $value ): string {
	return sanitize_email( wp_unslash( $value ) );
}

function primeestate_is_valid_email( string $value ): bool {
	return (bool) is_email( $value );
}

function primeestate_sanitize_url( string $value ): string {
	return esc_url_raw( wp_unslash( $value ) );
}

/**
 * Gate for the agent-facing front-end pages (Dashboard, Add/Edit Property,
 * Profile Edit — Phase 7). `manage_properties` is held by every role that
 * should reach these pages (agent, property_manager, editor, administrator)
 * and by no others (agent-permissions.php).
 */
function primeestate_user_can_access_agent_area(): bool {
	return is_user_logged_in() && current_user_can( 'manage_properties' );
}

/**
 * Standard "you can't be here" panel for a gated front-end page, used by
 * every agent-area shortcode instead of each reimplementing its own.
 */
function primeestate_render_access_denied_notice(): string {
	ob_start();
	?>
	<div class="pe-access-denied">
		<?php if ( is_user_logged_in() ) : ?>
			<p><?php esc_html_e( "You don't have access to this page.", 'primeestate' ); ?></p>
		<?php else : ?>
			<p>
				<?php esc_html_e( 'Please log in to continue.', 'primeestate' ); ?>
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'primeestate' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Cache safety (FR-049): call from any template/handler that renders
 * per-user content (dashboards, favorites, comparison) so page-cache
 * plugins never serve one visitor's personalized page to another. Must be
 * called before any output is sent, ideally on `template_redirect`.
 */
function primeestate_mark_page_uncacheable(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	nocache_headers();
}

/**
 * T087 (FR-049, SC-014): applies `primeestate_mark_page_uncacheable()` to
 * every front-end route that renders per-user data. Each personalized
 * page's render file (favorites-render.php, compare-render.php,
 * agent-dashboard-render.php, agent-property-form-render.php,
 * agent-profile-edit-render.php, user-dashboard-render.php) had been built
 * across Phases 5-9 without ever actually calling this helper — it existed
 * since Phase 2 (T033) but nothing invoked it, which a full-text search
 * across the codebase during this phase confirmed. Centralizing the call
 * list here, on `template_redirect` (before any output, same hook the
 * fragment handlers already use), is the fix rather than scattering a call
 * into each render file individually — one audited list is easier to keep
 * complete than N call sites that could each be forgotten again.
 */
/**
 * Extracted from `primeestate_mark_dashboard_pages_uncacheable()` below so
 * the coverage list itself — the thing T087 is actually meant to verify —
 * is directly unit-testable without depending on `DONOTCACHEPAGE`, a
 * process-wide `define()` that, once set true by one test, cannot be reset
 * to false for a later test in the same PHPUnit run.
 *
 * @return string[]
 */
function primeestate_dashboard_page_slugs(): array {
	return array(
		'favorites',
		'compare',
		'agent-dashboard',
		'agent-add-property',
		'agent-profile-edit',
		'user-dashboard',
	);
}

function primeestate_mark_dashboard_pages_uncacheable(): void {
	if ( is_page( primeestate_dashboard_page_slugs() ) ) {
		primeestate_mark_page_uncacheable();
	}
}
add_action( 'template_redirect', 'primeestate_mark_dashboard_pages_uncacheable' );
