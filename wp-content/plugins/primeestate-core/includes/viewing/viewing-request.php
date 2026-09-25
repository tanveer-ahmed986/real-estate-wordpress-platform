<?php
/**
 * `pe_viewing_request` Custom Post Type (data-model.md §6, FR-030–FR-033).
 * Same rationale as `pe_inquiry` (see inquiry-post-type.php): no wp-admin UI,
 * no public front end — managed entirely via REST + the same `view_inquiries`
 * capability and per-record ownership check (data-model.md §10 lumps
 * "view all inquiries/viewings" into a single capability matrix row).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_viewing_request_post_type(): void {
	$labels = array(
		'name'               => __( 'Viewing Requests', 'primeestate' ),
		'singular_name'      => __( 'Viewing Request', 'primeestate' ),
		'edit_item'          => __( 'View / Update Viewing Request', 'primeestate' ),
		'search_items'       => __( 'Search Viewing Requests', 'primeestate' ),
		'not_found'          => __( 'No viewing requests found', 'primeestate' ),
		'not_found_in_trash' => __( 'No viewing requests found in Trash', 'primeestate' ),
		'all_items'          => __( 'Viewing Requests', 'primeestate' ),
	);

	register_post_type(
		'pe_viewing_request',
		array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'custom-fields' ),
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'primeestate_register_viewing_request_post_type' );

/**
 * Validation, storage, notification, and the two REST endpoints
 * (FR-030–FR-033), per contracts/primeestate-api.openapi.yaml:
 * `POST /viewings`, `PATCH /viewings/{id}`.
 */

const PRIMEESTATE_VIEWING_STATUSES = array( 'Requested', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled' );

/**
 * data-model.md §6's transition graph: `Cancelled` is reachable from any
 * status; otherwise Requested→{Confirmed,Rescheduled,Cancelled},
 * Confirmed→{Completed,Rescheduled,Cancelled}, Rescheduled→{Confirmed,Cancelled},
 * Completed is terminal.
 */
function primeestate_is_valid_viewing_transition( string $from, string $to ): bool {
	if ( 'Cancelled' === $to ) {
		return true;
	}

	$allowed = array(
		'Requested'   => array( 'Confirmed', 'Rescheduled' ),
		'Confirmed'   => array( 'Completed', 'Rescheduled' ),
		'Rescheduled' => array( 'Confirmed' ),
		'Completed'   => array(),
		'Cancelled'   => array(),
	);

	return in_array( $to, $allowed[ $from ] ?? array(), true );
}

/**
 * Validates + sanitizes a raw viewing-request submission. FR-031: the
 * preferred date must not be in the past — compared as a calendar date in
 * the site's configured timezone (`current_time()`), not a combined
 * date+time instant, since the form collects date and time as separate
 * fields and "is today still valid" shouldn't depend on what time it
 * currently is.
 */
function primeestate_validate_viewing_submission( array $params ) {
	$errors = new WP_Error();

	$property_id = absint( $params['property_id'] ?? 0 );
	$property    = get_post( $property_id );

	if ( ! $property || 'property' !== $property->post_type || 'publish' !== $property->post_status ) {
		$errors->add( 'invalid_property', __( 'This property is no longer available.', 'primeestate' ) );
	}

	$name = trim( (string) ( $params['name'] ?? '' ) );
	if ( '' === $name ) {
		$errors->add( 'missing_name', __( 'Name is required.', 'primeestate' ) );
	}

	$email = trim( (string) ( $params['email'] ?? '' ) );
	if ( '' === $email || ! primeestate_is_valid_email( $email ) ) {
		$errors->add( 'invalid_email', __( 'A valid email address is required.', 'primeestate' ) );
	}

	$phone = trim( (string) ( $params['phone'] ?? '' ) );
	if ( '' === $phone ) {
		$errors->add( 'missing_phone', __( 'Phone is required.', 'primeestate' ) );
	}

	$preferred_date = trim( (string) ( $params['preferred_date'] ?? '' ) );
	$date_object    = DateTime::createFromFormat( 'Y-m-d', $preferred_date );

	if ( ! $preferred_date || ! $date_object || $date_object->format( 'Y-m-d' ) !== $preferred_date ) {
		$errors->add( 'invalid_date', __( 'A valid preferred date is required.', 'primeestate' ) );
	} elseif ( $preferred_date < current_time( 'Y-m-d' ) ) {
		$errors->add( 'past_date', __( 'The preferred date cannot be in the past.', 'primeestate' ) );
	}

	$preferred_time = trim( (string) ( $params['preferred_time'] ?? '' ) );
	if ( '' === $preferred_time ) {
		$errors->add( 'missing_time', __( 'A preferred time is required.', 'primeestate' ) );
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	return array(
		'property_id'     => $property_id,
		'agent_id'        => (int) $property->post_author,
		'name'            => primeestate_sanitize_text( $name ),
		'email'           => primeestate_sanitize_email( $email ),
		'phone'           => primeestate_sanitize_text( $phone ),
		'preferred_date'  => $preferred_date,
		'preferred_time'  => primeestate_sanitize_text( $preferred_time ),
		'message'         => primeestate_sanitize_message( (string) ( $params['message'] ?? '' ) ),
	);
}

function primeestate_create_viewing_request( array $data ): int {
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'pe_viewing_request',
			'post_status' => 'publish',
			'post_title'  => sprintf( 'Viewing request from %s for property #%d', $data['name'], $data['property_id'] ),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return 0;
	}

	update_post_meta( $post_id, '_pe_property_id', $data['property_id'] );
	update_post_meta( $post_id, '_pe_agent_id', $data['agent_id'] );
	update_post_meta( $post_id, '_pe_name', $data['name'] );
	update_post_meta( $post_id, '_pe_email', $data['email'] );
	update_post_meta( $post_id, '_pe_phone', $data['phone'] );
	update_post_meta( $post_id, '_pe_preferred_date', $data['preferred_date'] );
	update_post_meta( $post_id, '_pe_preferred_time', $data['preferred_time'] );
	update_post_meta( $post_id, '_pe_message', $data['message'] );
	update_post_meta( $post_id, '_pe_status', 'Requested' );

	return $post_id;
}

/**
 * FR-033: notify the assigned agent by email. Same deliverability caveat as
 * inquiry-handler.php's notification — verified at deployment via T104.
 */
function primeestate_notify_agent_of_viewing_request( int $viewing_id ): void {
	$agent_id = (int) get_post_meta( $viewing_id, '_pe_agent_id', true );
	$agent    = get_userdata( $agent_id );

	if ( ! $agent || ! is_email( $agent->user_email ) ) {
		return;
	}

	$property_id    = (int) get_post_meta( $viewing_id, '_pe_property_id', true );
	$property_title = get_the_title( $property_id );

	$subject = sprintf(
		/* translators: %s: property title */
		__( 'New viewing request: %s', 'primeestate' ),
		$property_title
	);

	$body = sprintf(
		/* translators: 1: submitter name, 2: property title, 3: preferred date, 4: preferred time */
		__( "%1\$s requested a viewing of \"%2\$s\" on %3\$s at %4\$s.", 'primeestate' ),
		get_post_meta( $viewing_id, '_pe_name', true ),
		$property_title,
		get_post_meta( $viewing_id, '_pe_preferred_date', true ),
		get_post_meta( $viewing_id, '_pe_preferred_time', true )
	);

	wp_mail( $agent->user_email, $subject, $body );
}

function primeestate_viewing_request_to_schema( WP_Post $post ): array {
	return array(
		'id'              => $post->ID,
		'property_id'     => (int) get_post_meta( $post->ID, '_pe_property_id', true ),
		'name'            => (string) get_post_meta( $post->ID, '_pe_name', true ),
		'email'           => (string) get_post_meta( $post->ID, '_pe_email', true ),
		'phone'           => (string) get_post_meta( $post->ID, '_pe_phone', true ),
		'preferred_date'  => (string) get_post_meta( $post->ID, '_pe_preferred_date', true ),
		'preferred_time'  => (string) get_post_meta( $post->ID, '_pe_preferred_time', true ),
		'message'         => (string) get_post_meta( $post->ID, '_pe_message', true ),
		'status'          => (string) get_post_meta( $post->ID, '_pe_status', true ),
		'assigned_agent_id' => (int) get_post_meta( $post->ID, '_pe_agent_id', true ),
	);
}

/**
 * @return WP_Post[]
 */
function primeestate_get_assigned_viewing_requests( int $agent_id ): array {
	$query = new WP_Query(
		array(
			'post_type'      => 'pe_viewing_request',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_pe_agent_id', 'value' => $agent_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	return $query->posts;
}

/**
 * "My Viewing Requests" for the registered-user dashboard (T085, FR-046) —
 * same email-matching rationale as `primeestate_get_own_inquiries()`
 * (inquiry-handler.php): no submitter user-ID link exists in the data model
 * (data-model.md §6), so this scopes by the logged-in user's account email.
 *
 * @return WP_Post[]
 */
function primeestate_get_own_viewing_requests( string $email ): array {
	if ( '' === $email ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'pe_viewing_request',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_pe_email', 'value' => $email ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	return $query->posts;
}

function primeestate_register_viewing_routes(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/viewings',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'primeestate_handle_create_viewing_request',
			'permission_callback' => 'primeestate_rest_public_permission',
		)
	);

	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/viewings/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'primeestate_handle_update_viewing_request',
			'permission_callback' => primeestate_rest_capability_permission( 'view_inquiries' ),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_viewing_routes' );

function primeestate_handle_create_viewing_request( WP_REST_Request $request ) {
	$params = $request->get_params();

	if ( primeestate_is_honeypot_triggered( (string) ( $params[ PRIMEESTATE_HONEYPOT_FIELD_NAME ] ?? '' ) ) ) {
		return new WP_REST_Response( array( 'status' => 'Requested' ), 201 );
	}

	if ( ! primeestate_rate_limit_check() ) {
		return primeestate_rest_error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'primeestate' ), 429 );
	}

	$validated = primeestate_validate_viewing_submission( $params );

	if ( is_wp_error( $validated ) ) {
		return primeestate_rest_error( 'validation_failed', implode( ' ', $validated->get_error_messages() ), 400 );
	}

	$viewing_id = primeestate_create_viewing_request( $validated );

	if ( ! $viewing_id ) {
		return primeestate_rest_error( 'creation_failed', __( 'Could not save your viewing request. Please try again.', 'primeestate' ), 500 );
	}

	primeestate_rate_limit_record_submission();
	primeestate_notify_agent_of_viewing_request( $viewing_id );

	return new WP_REST_Response( primeestate_viewing_request_to_schema( get_post( $viewing_id ) ), 201 );
}

function primeestate_handle_update_viewing_request( WP_REST_Request $request ) {
	$viewing_id = (int) $request['id'];
	$post       = get_post( $viewing_id );

	if ( ! $post || 'pe_viewing_request' !== $post->post_type ) {
		return primeestate_rest_error( 'not_found', __( 'Viewing request not found.', 'primeestate' ), 404 );
	}

	$assigned_agent_id = (int) get_post_meta( $viewing_id, '_pe_agent_id', true );

	if ( ! primeestate_user_sees_all_inquiries() && get_current_user_id() !== $assigned_agent_id ) {
		return primeestate_rest_error( 'forbidden', __( 'You are not authorized to update this viewing request.', 'primeestate' ), 403 );
	}

	$new_status = (string) $request->get_param( 'status' );
	$current_status = (string) get_post_meta( $viewing_id, '_pe_status', true );

	if ( '' === $new_status || ! in_array( $new_status, PRIMEESTATE_VIEWING_STATUSES, true ) ) {
		return primeestate_rest_error( 'invalid_status', __( 'A valid status is required.', 'primeestate' ), 400 );
	}

	if ( $new_status !== $current_status && ! primeestate_is_valid_viewing_transition( $current_status, $new_status ) ) {
		return primeestate_rest_error( 'invalid_transition', sprintf(
			/* translators: 1: current status, 2: requested status */
			__( 'Cannot change status from %1$s to %2$s.', 'primeestate' ),
			$current_status,
			$new_status
		), 400 );
	}

	update_post_meta( $viewing_id, '_pe_status', $new_status );

	return new WP_REST_Response( primeestate_viewing_request_to_schema( get_post( $viewing_id ) ), 200 );
}
