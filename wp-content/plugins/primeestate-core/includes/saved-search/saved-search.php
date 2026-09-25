<?php
/**
 * Save Search + Email Alerts (competitive review: Zameen/Zillow both let a
 * user save a filter set and get emailed when new matches appear). Storage
 * mirrors favorites.php exactly — usermeta `_pe_saved_searches`, an array of
 * entries — rather than a new CPT, since a saved search is per-user private
 * data with no need for WP_Query-able post relationships or admin-list
 * moderation.
 *
 * Each entry:
 * - id: random slug, stable identity for delete/dedupe.
 * - label: user-supplied or auto-generated from the filter set.
 * - query_string: canonical form (primeestate_filters_to_query_string), so
 *   it round-trips through primeestate_filters_from_query_string() exactly
 *   like a shared archive URL would (SC-013's shareability guarantee reused
 *   here, not reinvented).
 * - created_at / last_notified_at: mysql datetimes.
 * - seen_ids: the property IDs that matched at save time (or last alert) —
 *   the daily cron diffs *against this*, so a user is only ever alerted
 *   about listings that appeared after they saved the search, never the
 *   existing result set they already saw when they clicked "Save".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_SAVED_SEARCH_MAX_PER_USER = 20;
const PRIMEESTATE_SAVED_SEARCH_RESULT_CAP   = 200;
const PRIMEESTATE_SAVED_SEARCH_EMAIL_PREVIEW = 10;

/**
 * @return array<int, array{id: string, label: string, query_string: string, created_at: string, last_notified_at: ?string, seen_ids: int[]}>
 */
function primeestate_get_user_saved_searches( int $user_id ): array {
	$searches = get_user_meta( $user_id, '_pe_saved_searches', true );

	return is_array( $searches ) ? $searches : array();
}

/**
 * @return array{id: string, label: string, query_string: string, created_at: string, last_notified_at: ?string, seen_ids: int[]}|WP_Error
 */
function primeestate_create_user_saved_search( int $user_id, string $label, string $raw_query_string ) {
	$searches = primeestate_get_user_saved_searches( $user_id );

	if ( count( $searches ) >= PRIMEESTATE_SAVED_SEARCH_MAX_PER_USER ) {
		return primeestate_rest_error( 'saved_search_limit', __( 'You have reached the maximum number of saved searches.', 'primeestate' ), 400 );
	}

	$filters      = primeestate_filters_from_query_string( $raw_query_string );
	$query_string = primeestate_filters_to_query_string( $filters );

	$entry = array(
		'id'                => wp_generate_password( 12, false, false ),
		'label'             => '' !== trim( $label ) ? sanitize_text_field( $label ) : primeestate_describe_saved_search_filters( $filters ),
		'query_string'      => $query_string,
		'created_at'        => current_time( 'mysql' ),
		'last_notified_at'  => null,
		'seen_ids'          => primeestate_get_saved_search_matching_ids( $query_string ),
	);

	$searches[] = $entry;
	update_user_meta( $user_id, '_pe_saved_searches', $searches );

	return $entry;
}

function primeestate_delete_user_saved_search( int $user_id, string $search_id ): bool {
	$searches = primeestate_get_user_saved_searches( $user_id );
	$filtered = array_values(
		array_filter(
			$searches,
			static function ( array $search ) use ( $search_id ) {
				return $search['id'] !== $search_id;
			}
		)
	);

	if ( count( $filtered ) === count( $searches ) ) {
		return false; // Nothing matched — id unknown or already deleted.
	}

	update_user_meta( $user_id, '_pe_saved_searches', $filtered );

	return true;
}

/**
 * @return int[]
 */
function primeestate_get_saved_search_matching_ids( string $query_string ): array {
	$args             = primeestate_build_property_query_args( primeestate_filters_from_query_string( $query_string ) );
	$args['fields']   = 'ids';
	$args['paged']    = 1;
	$args['posts_per_page'] = PRIMEESTATE_SAVED_SEARCH_RESULT_CAP;

	$query = new WP_Query( $args );

	return array_map( 'absint', $query->posts );
}

/**
 * Falls back to a readable auto-label ("For Sale in Austin", "3+ bed
 * Apartment") when the user leaves the name field blank — a saved search
 * with no distinguishing label is useless in a list of several.
 */
function primeestate_describe_saved_search_filters( array $filters ): string {
	$parts = array();

	if ( isset( $filters['bedrooms'] ) ) {
		$parts[] = $filters['bedrooms'] . '+ bed';
	}

	if ( isset( $filters['type'] ) ) {
		$term = get_term_by( 'slug', $filters['type'], 'property_type' );
		if ( $term instanceof WP_Term ) {
			$parts[] = $term->name;
		}
	}

	$listing_label_map = array( 'sale' => 'For Sale', 'rent' => 'For Rent', 'lease' => 'For Lease' );
	if ( isset( $filters['listing'] ) && isset( $listing_label_map[ $filters['listing'] ] ) ) {
		$parts[] = $listing_label_map[ $filters['listing'] ];
	}

	if ( isset( $filters['city'] ) ) {
		$term = get_term_by( 'slug', $filters['city'], 'location' );
		if ( $term instanceof WP_Term ) {
			$parts[] = 'in ' . $term->name;
		}
	}

	return ! empty( $parts ) ? implode( ' ', $parts ) : __( 'All properties', 'primeestate' );
}

function primeestate_register_saved_search_routes(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/saved-searches',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'primeestate_handle_list_saved_searches',
				'permission_callback' => 'primeestate_rest_authenticated_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'primeestate_handle_create_saved_search',
				'permission_callback' => 'primeestate_rest_authenticated_permission',
			),
		)
	);

	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/saved-searches/(?P<id>[\w-]+)',
		array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => 'primeestate_handle_delete_saved_search',
			'permission_callback' => 'primeestate_rest_authenticated_permission',
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_saved_search_routes' );

function primeestate_saved_search_to_schema( array $search ): array {
	return array(
		'id'               => $search['id'],
		'label'            => $search['label'],
		'query_string'     => $search['query_string'],
		'url'              => get_post_type_archive_link( 'property' ) . ( $search['query_string'] ? '?' . $search['query_string'] : '' ),
		'created_at'       => $search['created_at'],
		'last_notified_at' => $search['last_notified_at'],
		'result_count'     => count( primeestate_get_saved_search_matching_ids( $search['query_string'] ) ),
	);
}

function primeestate_handle_list_saved_searches( WP_REST_Request $request ) {
	$searches = primeestate_get_user_saved_searches( get_current_user_id() );

	return new WP_REST_Response( array_map( 'primeestate_saved_search_to_schema', $searches ), 200 );
}

function primeestate_handle_create_saved_search( WP_REST_Request $request ) {
	$label        = (string) ( $request->get_param( 'label' ) ?? '' );
	$query_string = (string) ( $request->get_param( 'query_string' ) ?? '' );

	$result = primeestate_create_user_saved_search( get_current_user_id(), $label, $query_string );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( primeestate_saved_search_to_schema( $result ), 201 );
}

function primeestate_handle_delete_saved_search( WP_REST_Request $request ) {
	$deleted = primeestate_delete_user_saved_search( get_current_user_id(), (string) $request['id'] );

	if ( ! $deleted ) {
		return primeestate_rest_error( 'not_found', __( 'Saved search not found.', 'primeestate' ), 404 );
	}

	return new WP_REST_Response( array( 'deleted' => true ), 200 );
}

/**
 * Idempotent daily-cron registration (checked on every 'init' rather than
 * tied to the plugin's activation hook — register_activation_hook() only
 * fires for the *main* plugin file's own __FILE__, so a hook registered from
 * an includes/ file would silently never run; see primeestate-core.php's own
 * comment on this exact pitfall). `wp_next_scheduled()` is a cheap single
 * options lookup, safe to call on every request.
 */
function primeestate_maybe_schedule_saved_search_cron(): void {
	if ( ! wp_next_scheduled( 'primeestate_check_saved_search_alerts' ) ) {
		wp_schedule_event( time(), 'daily', 'primeestate_check_saved_search_alerts' );
	}
}
add_action( 'init', 'primeestate_maybe_schedule_saved_search_cron' );

/**
 * Runs once a day: for every user with at least one saved search, re-runs
 * each search's filter set and emails them about any property ID that
 * wasn't in `seen_ids` last time — then advances `seen_ids` to the current
 * result set so the same listing is never reported twice.
 */
function primeestate_run_saved_search_alerts_check(): void {
	$user_ids = get_users(
		array(
			'meta_key' => '_pe_saved_searches', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'fields'   => 'ID',
		)
	);

	foreach ( $user_ids as $user_id ) {
		$searches = primeestate_get_user_saved_searches( (int) $user_id );

		if ( empty( $searches ) ) {
			continue;
		}

		$changed = false;

		foreach ( $searches as &$search ) {
			$current_ids = primeestate_get_saved_search_matching_ids( $search['query_string'] );
			$new_ids     = array_values( array_diff( $current_ids, $search['seen_ids'] ) );

			if ( empty( $new_ids ) ) {
				continue;
			}

			primeestate_send_saved_search_alert_email( (int) $user_id, $search, $new_ids );

			$search['seen_ids']         = $current_ids;
			$search['last_notified_at'] = current_time( 'mysql' );
			$changed                    = true;
		}
		unset( $search );

		if ( $changed ) {
			update_user_meta( (int) $user_id, '_pe_saved_searches', $searches );
		}
	}
}
add_action( 'primeestate_check_saved_search_alerts', 'primeestate_run_saved_search_alerts_check' );

/**
 * @param int[] $new_ids
 */
function primeestate_send_saved_search_alert_email( int $user_id, array $search, array $new_ids ): void {
	$user = get_userdata( $user_id );

	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}

	$subject = sprintf(
		/* translators: %s: saved search label */
		__( 'New matches for your saved search "%s"', 'primeestate' ),
		$search['label']
	);

	$preview_ids = array_slice( $new_ids, 0, PRIMEESTATE_SAVED_SEARCH_EMAIL_PREVIEW );
	$lines       = array();

	foreach ( $preview_ids as $property_id ) {
		$lines[] = get_the_title( $property_id ) . ' — ' . get_permalink( $property_id );
	}

	$remaining = count( $new_ids ) - count( $preview_ids );
	if ( $remaining > 0 ) {
		/* translators: %d: number of additional new listings not shown individually */
		$lines[] = sprintf( _n( '…and %d more new listing.', '…and %d more new listings.', $remaining, 'primeestate' ), $remaining );
	}

	$search_url = get_post_type_archive_link( 'property' ) . ( $search['query_string'] ? '?' . $search['query_string'] : '' );

	$body = sprintf(
		/* translators: 1: list of new listings, 2: URL to view all results */
		__( "New properties matching your saved search \"%3\$s\":\n\n%1\$s\n\nView all results: %2\$s", 'primeestate' ),
		implode( "\n", $lines ),
		$search_url,
		$search['label']
	);

	wp_mail( $user->user_email, $subject, $body );
}

/**
 * Unschedules the cron event on deactivation. `register_deactivation_hook()`
 * resolves its target plugin from the *file path passed in*, not from which
 * file the call physically lives in — so this passes the main plugin file's
 * path explicitly (via PRIMEESTATE_CORE_PATH, defined in primeestate-core.php)
 * rather than this file's own __FILE__, which would resolve to a basename
 * WordPress's activation machinery never fires a hook for.
 */
register_deactivation_hook(
	PRIMEESTATE_CORE_PATH . 'primeestate-core.php',
	static function (): void {
		$timestamp = wp_next_scheduled( 'primeestate_check_saved_search_alerts' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'primeestate_check_saved_search_alerts' );
		}
	}
);
