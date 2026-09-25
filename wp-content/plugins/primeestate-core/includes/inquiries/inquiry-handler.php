<?php
/**
 * Inquiry validation, storage, notification, and the three REST endpoints
 * that operate on inquiries (FR-025–FR-029), per
 * contracts/primeestate-api.openapi.yaml: `POST/GET /inquiries`,
 * `PATCH /inquiries/{id}`.
 *
 * "Assigned only" vs. "sees all" (FR-029/FR-038) is decided by
 * `edit_others_properties` — a capability `agent` deliberately lacks and
 * `property_manager`/`editor`/`administrator` all hold (agent-permissions.php)
 * — rather than inventing a second custom capability beyond the six named in
 * T022. `view_inquiries` alone only gates "can touch the inquiries API at
 * all"; this function decides *how much* of it a given caller can see.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_INQUIRY_STATUSES = array( 'New', 'Contacted', 'Qualified', 'Viewing Scheduled', 'Closed', 'Spam' );

function primeestate_user_sees_all_inquiries(): bool {
	return current_user_can( 'edit_others_properties' );
}

/**
 * Validates + sanitizes a raw inquiry submission. Returns the sanitized data
 * array on success, or a `WP_Error` (with field-level messages) on failure —
 * never partially sanitizes and stores invalid data.
 */
function primeestate_validate_inquiry_submission( array $params ) {
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

	$message = trim( (string) ( $params['message'] ?? '' ) );
	if ( '' === $message ) {
		$errors->add( 'missing_message', __( 'Message is required.', 'primeestate' ) );
	}

	$contact_method = (string) ( $params['preferred_contact_method'] ?? '' );
	if ( ! in_array( $contact_method, array( 'phone', 'email', 'whatsapp' ), true ) ) {
		$errors->add( 'invalid_contact_method', __( 'Preferred contact method must be phone, email, or whatsapp.', 'primeestate' ) );
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	return array(
		'property_id'              => $property_id,
		'agent_id'                 => (int) $property->post_author,
		'name'                     => primeestate_sanitize_text( $name ),
		'email'                    => primeestate_sanitize_email( $email ),
		'phone'                    => primeestate_sanitize_text( (string) ( $params['phone'] ?? '' ) ),
		'message'                  => primeestate_sanitize_message( $message ),
		'preferred_contact_method' => $contact_method,
		'preferred_viewing_date'   => primeestate_sanitize_text( (string) ( $params['preferred_viewing_date'] ?? '' ) ),
		'budget'                   => isset( $params['budget'] ) && is_numeric( $params['budget'] ) ? (float) $params['budget'] : null,
	);
}

function primeestate_create_inquiry( array $data ): int {
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'pe_inquiry',
			'post_status' => 'publish',
			'post_title'  => sprintf( 'Inquiry from %s for property #%d', $data['name'], $data['property_id'] ),
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
	update_post_meta( $post_id, '_pe_message', $data['message'] );
	update_post_meta( $post_id, '_pe_preferred_contact_method', $data['preferred_contact_method'] );
	update_post_meta( $post_id, '_pe_preferred_viewing_date', $data['preferred_viewing_date'] );
	if ( null !== $data['budget'] ) {
		update_post_meta( $post_id, '_pe_budget', $data['budget'] );
	}
	update_post_meta( $post_id, '_pe_status', 'New' );

	return $post_id;
}

/**
 * FR-028: notify the assigned agent by email. Uses core `wp_mail()` — actual
 * deliverability (SPF/DKIM/DMARC, a transactional email provider) is a
 * documented production requirement (research.md §7), verified at
 * deployment time by tasks.md's Polish-phase T104, not configured here.
 */
function primeestate_notify_agent_of_inquiry( int $inquiry_id ): void {
	$agent_id = (int) get_post_meta( $inquiry_id, '_pe_agent_id', true );
	$agent    = get_userdata( $agent_id );

	if ( ! $agent || ! is_email( $agent->user_email ) ) {
		return;
	}

	$property_id    = (int) get_post_meta( $inquiry_id, '_pe_property_id', true );
	$property_title = get_the_title( $property_id );
	$submitter_name = get_post_meta( $inquiry_id, '_pe_name', true );

	$subject = sprintf(
		/* translators: %s: property title */
		__( 'New inquiry: %s', 'primeestate' ),
		$property_title
	);

	$body = sprintf(
		/* translators: 1: submitter name, 2: property title, 3: inquiry message */
		__( "%1\$s is interested in \"%2\$s\".\n\nMessage:\n%3\$s", 'primeestate' ),
		$submitter_name,
		$property_title,
		get_post_meta( $inquiry_id, '_pe_message', true )
	);

	wp_mail( $agent->user_email, $subject, $body );
}

function primeestate_inquiry_to_schema( WP_Post $post ): array {
	return array(
		'id'                       => $post->ID,
		'property_id'              => (int) get_post_meta( $post->ID, '_pe_property_id', true ),
		'name'                     => (string) get_post_meta( $post->ID, '_pe_name', true ),
		'email'                    => (string) get_post_meta( $post->ID, '_pe_email', true ),
		'phone'                    => (string) get_post_meta( $post->ID, '_pe_phone', true ),
		'message'                  => (string) get_post_meta( $post->ID, '_pe_message', true ),
		'preferred_contact_method' => (string) get_post_meta( $post->ID, '_pe_preferred_contact_method', true ),
		'status'                   => (string) get_post_meta( $post->ID, '_pe_status', true ),
		'assigned_agent_id'        => (int) get_post_meta( $post->ID, '_pe_agent_id', true ),
		'submitted_at'             => $post->post_date_gmt,
	);
}

/**
 * @return WP_Post[]
 */
function primeestate_get_assigned_inquiries( int $agent_id ): array {
	$query = new WP_Query(
		array(
			'post_type'      => 'pe_inquiry',
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
 * "My Inquiries" for the registered-user dashboard (T085, FR-046). Inquiries
 * have no submitter user-ID link in the data model (data-model.md §5) —
 * deliberately, so a guest never needs an account to submit one — so this
 * scopes by matching `_pe_email` against the logged-in user's account email,
 * the same email a logged-in submitter would naturally have typed into the
 * form. Best-effort, not a guaranteed link: a submission made with a
 * different email than the account's won't appear here.
 *
 * @return WP_Post[]
 */
function primeestate_get_own_inquiries( string $email ): array {
	if ( '' === $email ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'pe_inquiry',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_pe_email', 'value' => $email ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	return $query->posts;
}

function primeestate_register_inquiry_routes(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/inquiries',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'primeestate_handle_create_inquiry',
				'permission_callback' => 'primeestate_rest_public_permission',
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'primeestate_handle_list_inquiries',
				'permission_callback' => primeestate_rest_capability_permission( 'view_inquiries' ),
			),
		)
	);

	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/inquiries/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'primeestate_handle_update_inquiry',
			'permission_callback' => primeestate_rest_capability_permission( 'view_inquiries' ),
			'args'                => array(
				'id' => array( 'validate_callback' => static function ( $value ) {
					return is_numeric( $value );
				} ),
			),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_inquiry_routes' );

function primeestate_handle_create_inquiry( WP_REST_Request $request ) {
	$params = $request->get_params();

	if ( primeestate_is_honeypot_triggered( (string) ( $params[ PRIMEESTATE_HONEYPOT_FIELD_NAME ] ?? '' ) ) ) {
		// Pretend success without persisting anything or revealing detection —
		// standard anti-spam practice; does not consume the rate-limit quota.
		return new WP_REST_Response( array( 'status' => 'New' ), 201 );
	}

	if ( ! primeestate_rate_limit_check() ) {
		return primeestate_rest_error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'primeestate' ), 429 );
	}

	$validated = primeestate_validate_inquiry_submission( $params );

	if ( is_wp_error( $validated ) ) {
		return primeestate_rest_error( 'validation_failed', implode( ' ', $validated->get_error_messages() ), 400 );
	}

	$inquiry_id = primeestate_create_inquiry( $validated );

	if ( ! $inquiry_id ) {
		return primeestate_rest_error( 'creation_failed', __( 'Could not save your inquiry. Please try again.', 'primeestate' ), 500 );
	}

	primeestate_rate_limit_record_submission();
	primeestate_notify_agent_of_inquiry( $inquiry_id );

	return new WP_REST_Response( primeestate_inquiry_to_schema( get_post( $inquiry_id ) ), 201 );
}

function primeestate_handle_list_inquiries( WP_REST_Request $request ) {
	$meta_query = array();

	if ( ! primeestate_user_sees_all_inquiries() ) {
		$meta_query[] = array(
			'key'   => '_pe_agent_id',
			'value' => get_current_user_id(),
		);
	} elseif ( $request->get_param( 'assigned_to' ) ) {
		$meta_query[] = array(
			'key'   => '_pe_agent_id',
			'value' => absint( $request->get_param( 'assigned_to' ) ),
		);
	}

	$status = $request->get_param( 'status' );
	if ( $status && in_array( $status, PRIMEESTATE_INQUIRY_STATUSES, true ) ) {
		$meta_query[] = array(
			'key'   => '_pe_status',
			'value' => $status,
		);
	}

	if ( count( $meta_query ) > 1 ) {
		$meta_query['relation'] = 'AND';
	}

	$args = array(
		'post_type'      => 'pe_inquiry',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( ! empty( $meta_query ) ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$query = new WP_Query( $args );

	return new WP_REST_Response( array_map( 'primeestate_inquiry_to_schema', $query->posts ), 200 );
}

function primeestate_handle_update_inquiry( WP_REST_Request $request ) {
	$inquiry_id = (int) $request['id'];
	$post       = get_post( $inquiry_id );

	if ( ! $post || 'pe_inquiry' !== $post->post_type ) {
		return primeestate_rest_error( 'not_found', __( 'Inquiry not found.', 'primeestate' ), 404 );
	}

	$assigned_agent_id = (int) get_post_meta( $inquiry_id, '_pe_agent_id', true );
	$sees_all          = primeestate_user_sees_all_inquiries();

	if ( ! $sees_all && get_current_user_id() !== $assigned_agent_id ) {
		return primeestate_rest_error( 'forbidden', __( 'You are not authorized to update this inquiry.', 'primeestate' ), 403 );
	}

	$status = $request->get_param( 'status' );
	if ( $status && in_array( $status, PRIMEESTATE_INQUIRY_STATUSES, true ) ) {
		update_post_meta( $inquiry_id, '_pe_status', $status );
	}

	// Reassignment is a "sees all" staff action, consistent with FR-029's
	// "admin re-assignable" — an agent may update their own inquiry's status
	// but not hand it to someone else.
	$new_agent_id = $request->get_param( 'assigned_agent_id' );
	if ( $sees_all && $new_agent_id ) {
		update_post_meta( $inquiry_id, '_pe_agent_id', absint( $new_agent_id ) );
	}

	$note = $request->get_param( 'note' );
	if ( $note ) {
		$notes   = get_post_meta( $inquiry_id, '_pe_notes', true );
		$notes   = is_array( $notes ) ? $notes : array();
		$notes[] = array(
			'author' => get_current_user_id(),
			'note'   => primeestate_sanitize_message( $note ),
			'date'   => current_time( 'mysql' ),
		);
		update_post_meta( $inquiry_id, '_pe_notes', $notes );
	}

	return new WP_REST_Response( primeestate_inquiry_to_schema( get_post( $inquiry_id ) ), 200 );
}
