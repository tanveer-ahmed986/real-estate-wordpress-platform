<?php
/**
 * `primeestate/v1` REST namespace bootstrap — shared constant, permission
 * helpers, and the extension point that concrete endpoint files (added in
 * later phases: includes/search/search-api.php, includes/inquiries/,
 * includes/viewing/, includes/favorites/, per
 * contracts/primeestate-api.openapi.yaml) hook into, rather than each
 * calling `add_action( 'rest_api_init', ... )` independently with a
 * hardcoded namespace string.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_REST_NAMESPACE = 'primeestate/v1';

function primeestate_rest_api_init(): void {
	/**
	 * Fires once, inside WordPress's own `rest_api_init`, so every endpoint
	 * file can call `register_rest_route( PRIMEESTATE_REST_NAMESPACE, ... )`
	 * from a single, guaranteed-correctly-timed hook.
	 */
	do_action( 'primeestate_register_rest_routes' );
}
add_action( 'rest_api_init', 'primeestate_rest_api_init' );

/**
 * Permission callback for endpoints with no auth requirement in the OpenAPI
 * contract (`security: []`) — e.g. `GET /properties`, `POST /inquiries`.
 * Still subject to honeypot + rate-limit checks at the handler level for
 * state-changing routes (see includes/security/rate-limit.php).
 */
function primeestate_rest_public_permission(): bool {
	return true;
}

/**
 * Permission callback for endpoints requiring only a logged-in session
 * (`cookieAuth`) with no further capability restriction — e.g. `GET/POST
 * /favorites`. WordPress's REST infrastructure already re-verifies the
 * `X-WP-Nonce` header against the authenticated cookie before this runs.
 */
function primeestate_rest_authenticated_permission(): bool {
	return is_user_logged_in();
}

/**
 * Permission-callback factory for endpoints gated by a specific capability
 * (e.g. `view_inquiries` for `GET/PATCH /inquiries`, `moderate_properties`
 * for `PATCH /properties/{id}/moderate`). Capability re-verified server-side
 * per constitution Principle II — never trusts a client-supplied claim.
 */
function primeestate_rest_capability_permission( string $capability ): callable {
	return static function () use ( $capability ): bool {
		return current_user_can( $capability );
	};
}

/**
 * Standard error response shape used across every custom endpoint, keeping
 * REST error bodies consistent regardless of which handler file produced
 * them.
 */
function primeestate_rest_error( string $code, string $message, int $status = 400 ): \WP_Error {
	return new \WP_Error( $code, $message, array( 'status' => $status ) );
}
