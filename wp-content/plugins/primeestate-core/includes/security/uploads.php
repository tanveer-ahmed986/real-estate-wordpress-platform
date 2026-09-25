<?php
/**
 * Secure image upload handling (T081, FR-044/FR-046) — used by the property
 * submission/edit forms (public submission and agent-facing alike) to turn
 * a raw uploaded file into a WordPress attachment ID before it's referenced
 * in a property's gallery.
 *
 * Deliberately a custom `POST /uploads` endpoint rather than WordPress
 * core's `/wp/v2/media` — core's endpoint requires the `upload_files`
 * capability, which the `subscriber` role (FR-041's audience) does not
 * hold and should not be granted just for this; this endpoint instead
 * authenticates any logged-in user and does its own, narrower validation
 * (image types only, size-capped, content-sniffed — not just extension
 * matched) as the actual security boundary (constitution Principle II).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_UPLOAD_MAX_BYTES = 5 * MB_IN_BYTES;

/**
 * @return array<string, string> extension(s) => allowed MIME type, in the
 *                                shape `wp_check_filetype_and_ext()` expects.
 */
function primeestate_allowed_upload_mimes(): array {
	return array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	);
}

/**
 * Validates an uploaded file's size and *actual* content type — not just
 * its claimed extension or `Content-Type` header, both of which a renamed
 * executable can spoof. `wp_check_filetype_and_ext()` sniffs real file
 * content (via `finfo`/`getimagesize()`), which is what actually defends
 * against FR-044's "reject executables" requirement.
 */
function primeestate_validate_upload_file( string $tmp_path, string $original_filename, int $size ) {
	if ( $size <= 0 ) {
		return new WP_Error( 'empty_file', __( 'The uploaded file is empty.', 'primeestate' ) );
	}

	if ( $size > PRIMEESTATE_UPLOAD_MAX_BYTES ) {
		return new WP_Error( 'file_too_large', __( 'Images must be 5MB or smaller.', 'primeestate' ) );
	}

	if ( ! file_exists( $tmp_path ) ) {
		return new WP_Error( 'missing_file', __( 'No file was uploaded.', 'primeestate' ) );
	}

	$filetype = wp_check_filetype_and_ext( $tmp_path, $original_filename, primeestate_allowed_upload_mimes() );

	if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
		return new WP_Error( 'invalid_file_type', __( 'Only JPG, PNG, and WEBP images are allowed.', 'primeestate' ) );
	}

	// Belt-and-suspenders beyond the MIME sniff: confirm it's a real,
	// decodable image (catches a handful of polyglot-file edge cases
	// `wp_check_filetype_and_ext()`'s finfo check alone can miss).
	if ( false === @getimagesize( $tmp_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'invalid_image', __( 'The file is not a valid image.', 'primeestate' ) );
	}

	return true;
}

/**
 * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
 * @return int|WP_Error Attachment ID on success.
 */
function primeestate_handle_property_image_upload( array $file ) {
	if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
		return new WP_Error( 'upload_error', __( 'The upload failed. Please try again.', 'primeestate' ) );
	}

	$validation = primeestate_validate_upload_file( $file['tmp_name'] ?? '', $file['name'] ?? '', (int) ( $file['size'] ?? 0 ) );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$overrides = array(
		'test_form' => false,
		'mimes'     => primeestate_allowed_upload_mimes(),
	);

	$uploaded = wp_handle_upload( $file, $overrides );

	if ( isset( $uploaded['error'] ) ) {
		return new WP_Error( 'upload_failed', $uploaded['error'] );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		),
		$uploaded['file']
	);

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] ) );

	return $attachment_id;
}

function primeestate_register_upload_route(): void {
	register_rest_route(
		PRIMEESTATE_REST_NAMESPACE,
		'/uploads',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'primeestate_handle_upload_request',
			'permission_callback' => 'primeestate_rest_authenticated_permission',
		)
	);
}
add_action( 'primeestate_register_rest_routes', 'primeestate_register_upload_route' );

function primeestate_handle_upload_request( WP_REST_Request $request ) {
	$files = $request->get_file_params();

	if ( empty( $files['file'] ) ) {
		return primeestate_rest_error( 'missing_file', __( 'No file uploaded.', 'primeestate' ), 400 );
	}

	// Deliberately NOT rate-limited via primeestate_rate_limit_check(): that
	// counter is FR-056's shared 3/hour budget across the inquiry,
	// viewing-request, and property-submission *forms* — a single property
	// submission legitimately needs several photo uploads, which would
	// exhaust that budget before the form itself is even submitted.
	// Authentication (required) plus per-file type/size validation is this
	// endpoint's actual abuse defense.
	$result = primeestate_handle_property_image_upload( $files['file'] );

	if ( is_wp_error( $result ) ) {
		return primeestate_rest_error( $result->get_error_code(), $result->get_error_message(), 400 );
	}

	return new WP_REST_Response(
		array(
			'attachment_id' => $result,
			'url'           => wp_get_attachment_image_url( $result, 'thumbnail' ),
		),
		201
	);
}
