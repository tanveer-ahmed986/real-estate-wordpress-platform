<?php
/**
 * T078 — PHPUnit coverage for upload validation
 * (plugin/includes/security/uploads.php), FR-044: file type, MIME type,
 * size limits, reject executables (including a renamed one — content is
 * sniffed, not just the extension trusted).
 */

class Test_Upload_Validation extends WP_UnitTestCase {

	private array $temp_files = array();

	public function tear_down(): void {
		foreach ( $this->temp_files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->temp_files = array();
		parent::tear_down();
	}

	private function make_temp_file( string $suffix, string $contents ): string {
		$path = sys_get_temp_dir() . '/' . uniqid( 'primeestate_test_', true ) . $suffix;
		file_put_contents( $path, $contents );
		$this->temp_files[] = $path;
		return $path;
	}

	/**
	 * A minimal, genuinely valid 1x1 PNG — same bytes used for the theme's
	 * own screenshot.png placeholder (T007).
	 */
	private function valid_png_bytes(): string {
		return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
	}

	public function test_valid_png_passes_validation(): void {
		$path = $this->make_temp_file( '.png', $this->valid_png_bytes() );

		$result = primeestate_validate_upload_file( $path, 'photo.png', strlen( $this->valid_png_bytes() ) );

		$this->assertTrue( $result );
	}

	public function test_oversized_file_is_rejected(): void {
		$path = $this->make_temp_file( '.png', $this->valid_png_bytes() );

		$result = primeestate_validate_upload_file( $path, 'photo.png', PRIMEESTATE_UPLOAD_MAX_BYTES + 1 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'file_too_large', $result->get_error_code() );
	}

	public function test_empty_file_is_rejected(): void {
		$path = $this->make_temp_file( '.png', '' );

		$result = primeestate_validate_upload_file( $path, 'photo.png', 0 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_file', $result->get_error_code() );
	}

	/**
	 * FR-044's core requirement: a plain-text/PHP payload renamed with an
	 * image extension must still be rejected — `wp_check_filetype_and_ext()`
	 * sniffs actual content, not the claimed extension.
	 */
	public function test_executable_renamed_with_image_extension_is_rejected(): void {
		$path = $this->make_temp_file( '.jpg', "<?php echo 'this is not an image'; ?>" );

		$result = primeestate_validate_upload_file( $path, 'totally-a-photo.jpg', 40 );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_disallowed_extension_is_rejected(): void {
		$path = $this->make_temp_file( '.exe', 'MZ' . str_repeat( "\0", 100 ) ); // PE header magic bytes.

		$result = primeestate_validate_upload_file( $path, 'malware.exe', 102 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_file_type', $result->get_error_code() );
	}

	public function test_svg_is_rejected_not_in_allowlist(): void {
		// SVG can embed <script> — deliberately not in the allowed-mimes list
		// even though it's a common "image" format elsewhere.
		$path = $this->make_temp_file( '.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );

		$result = primeestate_validate_upload_file( $path, 'icon.svg', 45 );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_missing_file_is_rejected(): void {
		$result = primeestate_validate_upload_file( '/nonexistent/path.png', 'photo.png', 100 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_file', $result->get_error_code() );
	}
}
