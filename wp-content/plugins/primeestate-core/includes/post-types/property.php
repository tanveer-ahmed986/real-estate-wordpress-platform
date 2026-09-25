<?php
/**
 * `property` Custom Post Type (data-model.md §1) and its permalink/rewrite
 * architecture (FR-052, tasks.md T010).
 *
 * URL decision (T010): the single-property permalink stays flat —
 * `/property/{slug}/` — rather than nested under location terms, so a
 * property never has more than one canonical URL (nesting under a
 * hierarchical taxonomy risks a property being reachable, non-canonically,
 * from every ancestor term's path). Location-based *browsing* instead uses
 * the hierarchical `location` taxonomy's own archive URLs,
 * `/properties/{country}/{state}/{city}/{area}/`, registered in
 * includes/taxonomies/location.php via a hierarchical rewrite slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_property_post_type(): void {
	$labels = array(
		'name'                  => __( 'Properties', 'primeestate' ),
		'singular_name'         => __( 'Property', 'primeestate' ),
		'add_new_item'          => __( 'Add New Property', 'primeestate' ),
		'edit_item'             => __( 'Edit Property', 'primeestate' ),
		'new_item'              => __( 'New Property', 'primeestate' ),
		'view_item'             => __( 'View Property', 'primeestate' ),
		'search_items'          => __( 'Search Properties', 'primeestate' ),
		'not_found'             => __( 'No properties found', 'primeestate' ),
		'not_found_in_trash'    => __( 'No properties found in Trash', 'primeestate' ),
		'all_items'             => __( 'All Properties', 'primeestate' ),
		'archives'              => __( 'Property Archives', 'primeestate' ),
		'featured_image'        => __( 'Featured Image', 'primeestate' ),
		'set_featured_image'    => __( 'Set featured image', 'primeestate' ),
		'remove_featured_image' => __( 'Remove featured image', 'primeestate' ),
	);

	register_post_type(
		'property',
		array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => 'property-posts',
			'menu_icon'           => 'dashicons-admin-home',
			'menu_position'       => 5,
			'hierarchical'        => false,
			'has_archive'         => 'properties',
			'rewrite'             => array(
				'slug'       => 'property',
				'with_front' => false,
			),
			'query_var'           => true,
			'capability_type'     => array( 'property', 'properties' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields', 'revisions' ),
			'exclude_from_search' => false,
		)
	);
}
add_action( 'init', 'primeestate_register_property_post_type' );

/**
 * `POST /wp-json/primeestate/v1/properties` (T073) — covers both the
 * agent-direct-publish path (FR-061, FR-047) and the public-submission
 * pending-review path (FR-041/FR-042) through ONE endpoint and ONE
 * capability-branching decision, so there is exactly one place in the
 * codebase that decides `post_status` — never a client-supplied flag
 * (constitution Principle II; this exact ambiguity was E2 in the earlier
 * `/sp.analyze` pass, and FR-061's branching is independently covered by a
 * dedicated PHPUnit test, T071, precisely because it is the platform's most
 * explicitly clarified business rule).
 */

/**
 * FR-061: an account holding `publish_properties` (agent, property_manager,
 * editor, administrator — see agent-permissions.php) gets an immediately
 * published listing; anyone else (a plain registered user, FR-042) always
 * gets `pending`, regardless of any `status` field the client might send —
 * `primeestate_validate_property_submission()` never reads one.
 */
function primeestate_determine_property_publish_status( int $user_id ): string {
	return user_can( $user_id, 'publish_properties' ) ? 'publish' : 'pending';
}

/**
 * Validates + sanitizes a raw property submission against the
 * `PropertySubmission` schema. Location is resolved to an *existing*
 * `location` taxonomy term by city name (never creates a new term from
 * public input — avoids near-duplicate location terms accumulating from
 * free text; the same constraint the SearchForm/FilterPanel dropdowns
 * already impose).
 */
function primeestate_validate_property_submission( array $params ) {
	$errors = new WP_Error();

	$title = trim( (string) ( $params['title'] ?? '' ) );
	if ( '' === $title ) {
		$errors->add( 'missing_title', __( 'Title is required.', 'primeestate' ) );
	}

	$description = trim( (string) ( $params['description'] ?? '' ) );
	if ( '' === $description ) {
		$errors->add( 'missing_description', __( 'Description is required.', 'primeestate' ) );
	}

	$price = $params['price'] ?? null;
	if ( ! is_numeric( $price ) || (float) $price < 0 ) {
		$errors->add( 'invalid_price', __( 'A valid, non-negative price is required.', 'primeestate' ) );
	}

	$listing_type_term = null;
	$listing_type_slug = sanitize_title( (string) ( $params['listing_type'] ?? '' ) );
	if ( $listing_type_slug ) {
		$listing_type_term = get_term_by( 'slug', $listing_type_slug, 'listing_type' ) ?: get_term_by( 'name', (string) $params['listing_type'], 'listing_type' );
	}
	if ( ! $listing_type_term ) {
		$errors->add( 'invalid_listing_type', __( 'A valid listing type is required.', 'primeestate' ) );
	}

	$property_type_term = null;
	$property_type_slug = sanitize_title( (string) ( $params['property_type'] ?? '' ) );
	if ( $property_type_slug ) {
		$property_type_term = get_term_by( 'slug', $property_type_slug, 'property_type' ) ?: get_term_by( 'name', (string) $params['property_type'], 'property_type' );
	}
	if ( ! $property_type_term ) {
		$errors->add( 'invalid_property_type', __( 'A valid property type is required.', 'primeestate' ) );
	}

	$location      = is_array( $params['location'] ?? null ) ? $params['location'] : array();
	$city          = trim( (string) ( $location['city'] ?? '' ) );
	$location_term = $city ? ( get_term_by( 'name', $city, 'location' ) ?: get_term_by( 'slug', sanitize_title( $city ), 'location' ) ) : null;

	if ( ! $location_term ) {
		$errors->add( 'invalid_location', __( 'A valid, existing city is required.', 'primeestate' ) );
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	$amenity_term_ids = array();
	if ( ! empty( $params['amenities'] ) && is_array( $params['amenities'] ) ) {
		foreach ( $params['amenities'] as $amenity_slug ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $amenity_slug ), 'amenity' );
			if ( $term ) {
				$amenity_term_ids[] = (int) $term->term_id;
			}
		}
	}

	return array(
		'title'             => primeestate_sanitize_text( $title ),
		'description'       => primeestate_sanitize_rich_text( $description ),
		'price'             => (float) $price,
		'listing_type_id'   => (int) $listing_type_term->term_id,
		'property_type_id'  => (int) $property_type_term->term_id,
		'location_id'       => (int) $location_term->term_id,
		'address'           => primeestate_sanitize_text( (string) ( $location['address'] ?? '' ) ),
		'bedrooms'          => isset( $params['bedrooms'] ) ? absint( $params['bedrooms'] ) : 0,
		'bathrooms'         => isset( $params['bathrooms'] ) ? absint( $params['bathrooms'] ) : 0,
		'area'              => isset( $params['area'] ) && is_numeric( $params['area'] ) ? (float) $params['area'] : 0.0,
		'amenity_term_ids'  => $amenity_term_ids,
		'gallery'           => primeestate_sanitize_gallery_meta( $params['images'] ?? array() ),
	);
}

function primeestate_create_property_from_submission( array $data, int $user_id ): int {
	$status = primeestate_determine_property_publish_status( $user_id );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'property',
			'post_status'  => $status,
			'post_author'  => $user_id,
			'post_title'   => $data['title'],
			'post_content' => $data['description'],
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return 0;
	}

	update_post_meta( $post_id, '_pe_reference', sprintf( 'PE-%06d', $post_id ) );
	update_post_meta( $post_id, '_pe_price', $data['price'] );
	update_post_meta( $post_id, '_pe_price_type', 'fixed' );
	update_post_meta( $post_id, '_pe_currency', get_option( 'primeestate_default_currency', 'USD' ) );
	update_post_meta( $post_id, '_pe_bedrooms', $data['bedrooms'] );
	update_post_meta( $post_id, '_pe_bathrooms', $data['bathrooms'] );
	update_post_meta( $post_id, '_pe_area', $data['area'] );
	update_post_meta( $post_id, '_pe_address', $data['address'] );

	if ( ! empty( $data['gallery'] ) ) {
		update_post_meta( $post_id, '_pe_gallery', $data['gallery'] );
		set_post_thumbnail( $post_id, $data['gallery'][0] );
	}

	if ( 'pending' === $status ) {
		update_post_meta( $post_id, '_pe_submitted_by', $user_id );
	}

	wp_set_post_terms( $post_id, array( $data['listing_type_id'] ), 'listing_type' );
	wp_set_post_terms( $post_id, array( $data['property_type_id'] ), 'property_type' );
	wp_set_post_terms( $post_id, array( $data['location_id'] ), 'location' );

	if ( ! empty( $data['amenity_term_ids'] ) ) {
		wp_set_post_terms( $post_id, $data['amenity_term_ids'], 'amenity' );
	}

	return $post_id;
}

function primeestate_register_property_creation_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/properties',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'primeestate_handle_create_property',
			'permission_callback' => 'primeestate_rest_authenticated_permission',
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_property_creation_route' );

function primeestate_handle_create_property( WP_REST_Request $request ) {
	$params = $request->get_params();

	// Only the public submission path is honeypot/rate-limited (FR-056) — an
	// authenticated agent adding their own listing via the dashboard is not
	// an anonymous-abuse surface. `publish_properties` is exactly the same
	// capability that decides the resulting post_status, so this reuses that
	// signal rather than introducing a second concept of "trusted submitter".
	$is_trusted_submitter = current_user_can( 'publish_properties' );

	if ( ! $is_trusted_submitter ) {
		if ( primeestate_is_honeypot_triggered( (string) ( $params[ PRIMEESTATE_HONEYPOT_FIELD_NAME ] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'status' => 'pending' ), 201 );
		}

		if ( ! primeestate_rate_limit_check() ) {
			return primeestate_rest_error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'primeestate' ), 429 );
		}
	}

	$validated = primeestate_validate_property_submission( $params );

	if ( is_wp_error( $validated ) ) {
		return primeestate_rest_error( 'validation_failed', implode( ' ', $validated->get_error_messages() ), 400 );
	}

	$user_id = get_current_user_id();
	$post_id = primeestate_create_property_from_submission( $validated, $user_id );

	if ( ! $post_id ) {
		return primeestate_rest_error( 'creation_failed', __( 'Could not save the property. Please try again.', 'primeestate' ), 500 );
	}

	if ( ! $is_trusted_submitter ) {
		primeestate_rate_limit_record_submission();
	}

	return new WP_REST_Response( primeestate_property_to_summary( get_post( $post_id ) ), 201 );
}

/**
 * `PATCH /wp-json/primeestate/v1/properties/{id}/moderate` (T082, FR-043).
 * Shared core logic (`primeestate_moderate_property()`) also backs the
 * wp-admin moderation queue (T083, plugin/includes/admin/dashboard.php) —
 * one decision function, two entry points, so approve/reject behaves
 * identically regardless of which UI triggered it.
 */
function primeestate_register_property_moderation_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/properties/(?P<id>\d+)/moderate',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'primeestate_handle_moderate_property',
			'permission_callback' => primeestate_rest_capability_permission( 'moderate_properties' ),
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_property_moderation_route' );

function primeestate_handle_moderate_property( WP_REST_Request $request ) {
	$id       = (int) $request['id'];
	$decision = (string) $request->get_param( 'decision' );
	$reason   = (string) ( $request->get_param( 'reason' ) ?? '' );

	if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
		return primeestate_rest_error( 'invalid_decision', __( 'Decision must be "approve" or "reject".', 'primeestate' ), 400 );
	}

	$result = primeestate_moderate_property( $id, $decision, $reason );

	if ( is_wp_error( $result ) ) {
		$status = 'not_found' === $result->get_error_code() ? 404 : 400;
		return primeestate_rest_error( $result->get_error_code(), $result->get_error_message(), $status );
	}

	return new WP_REST_Response( array( 'id' => $id, 'decision' => $decision ), 200 );
}

/**
 * @return true|WP_Error
 */
function primeestate_moderate_property( int $property_id, string $decision, string $reason = '' ) {
	$post = get_post( $property_id );

	if ( ! $post || 'property' !== $post->post_type ) {
		return new WP_Error( 'not_found', __( 'Property not found.', 'primeestate' ) );
	}

	if ( 'pending' !== $post->post_status ) {
		return new WP_Error( 'already_moderated', __( 'This submission has already been moderated.', 'primeestate' ) );
	}

	if ( 'approve' === $decision ) {
		wp_update_post( array( 'ID' => $property_id, 'post_status' => 'publish' ) );

		if ( empty( wp_get_post_terms( $property_id, 'property_status' ) ) ) {
			$available = get_term_by( 'slug', 'available', 'property_status' );
			if ( $available ) {
				wp_set_post_terms( $property_id, array( $available->term_id ), 'property_status' );
			}
		}
	} else {
		wp_update_post( array( 'ID' => $property_id, 'post_status' => 'draft' ) );
		update_post_meta( $property_id, '_pe_moderation_reason', primeestate_sanitize_text( $reason ) );
	}

	primeestate_notify_submitter_of_moderation_outcome( $property_id, $decision, $reason );

	return true;
}

/**
 * T084: notifies the submitter (the registered user who submitted the
 * property, FR-041) of the moderation outcome. Falls back to `post_author`
 * for the rare case `_pe_submitted_by` is absent — shouldn't happen for a
 * property that was ever `pending` (primeestate_create_property_from_submission()
 * always sets it for that path), but a missing notification is worse than a
 * redundant lookup.
 */
function primeestate_notify_submitter_of_moderation_outcome( int $property_id, string $decision, string $reason = '' ): void {
	$submitted_by = (int) get_post_meta( $property_id, '_pe_submitted_by', true );
	$user         = get_userdata( $submitted_by ?: (int) get_post_field( 'post_author', $property_id ) );

	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}

	$property_title = get_the_title( $property_id );

	if ( 'approve' === $decision ) {
		$subject = sprintf( /* translators: %s: property title */ __( 'Your listing "%s" is now live', 'primeestate' ), $property_title );
		$body    = sprintf( /* translators: %s: property title */ __( 'Good news — your property submission "%s" has been approved and is now visible to the public.', 'primeestate' ), $property_title );
	} else {
		$subject = sprintf( /* translators: %s: property title */ __( 'Your listing "%s" was not approved', 'primeestate' ), $property_title );
		$body    = sprintf(
			/* translators: 1: property title, 2: rejection reason */
			__( "Your property submission \"%1\$s\" was not approved.\n\nReason: %2\$s", 'primeestate' ),
			$property_title,
			$reason ?: __( 'No reason provided.', 'primeestate' )
		);
	}

	wp_mail( $user->user_email, $subject, $body );
}
