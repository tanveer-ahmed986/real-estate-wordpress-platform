<?php
/**
 * Anti-spam: honeypot field + transient-backed rolling rate limiter
 * (FR-056: 3 submissions per visitor per rolling hour, COMBINED across the
 * inquiry, viewing-request, and property-submission forms — per the
 * /sp.clarify session recorded in spec.md).
 *
 * The rate-limit key deliberately does NOT segment by form type — a single
 * shared counter per visitor is what "combined" means in FR-056. (An earlier
 * task description mentioned a "hashed IP+form-type key"; that phrasing
 * predates the clarification and is superseded by FR-056's final wording,
 * which this implementation follows.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_RATE_LIMIT_MAX      = 3;
const PRIMEESTATE_RATE_LIMIT_WINDOW   = HOUR_IN_SECONDS;
const PRIMEESTATE_HONEYPOT_FIELD_NAME = 'pe_website'; // Deliberately looks like a plausible real field to bait bots.

/**
 * Renders a visually hidden honeypot input for server-rendered forms. Real
 * users never see or fill it (CSS-hidden, not `type="hidden"`, so basic bots
 * that skip hidden inputs still get caught); any non-empty value on submit
 * is treated as spam.
 */
function primeestate_render_honeypot_field(): void {
	printf(
		'<div class="pe-field-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1"><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="" autocomplete="off" tabindex="-1"></div>',
		esc_attr( PRIMEESTATE_HONEYPOT_FIELD_NAME ),
		esc_html__( 'Leave this field empty', 'primeestate' )
	);
}

function primeestate_is_honeypot_triggered( string $submitted_value ): bool {
	return '' !== trim( $submitted_value );
}

/**
 * Visitor identity for rate-limiting: the logged-in user ID when
 * authenticated, otherwise a salted hash of the request IP — never the raw
 * IP itself, so the rate-limit transient does not itself become a store of
 * personal data (constitution Principle XI).
 */
function primeestate_rate_limit_identity(): string {
	$user_id = get_current_user_id();

	if ( $user_id > 0 ) {
		return 'user_' . $user_id;
	}

	$ip = primeestate_get_client_ip();

	return 'ip_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
}

function primeestate_get_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function primeestate_rate_limit_transient_key(): string {
	return 'primeestate_rl_' . primeestate_rate_limit_identity();
}

/**
 * True if the visitor is still within their rolling-hour submission
 * allowance. Does NOT itself increment the counter — call
 * `primeestate_rate_limit_record_submission()` once the submission is
 * accepted, so rejected/validation-failed attempts don't consume the quota.
 */
function primeestate_rate_limit_check(): bool {
	$count = (int) get_transient( primeestate_rate_limit_transient_key() );

	return $count < PRIMEESTATE_RATE_LIMIT_MAX;
}

function primeestate_rate_limit_record_submission(): void {
	$key   = primeestate_rate_limit_transient_key();
	$count = (int) get_transient( $key );

	set_transient( $key, $count + 1, PRIMEESTATE_RATE_LIMIT_WINDOW );
}
