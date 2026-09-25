<?php
/**
 * `_pe_related_properties` postmeta on the native `post` type (data-model.md
 * §9, FR-051) — an array of `property` post IDs, manually curated via the
 * meta box in admin/dashboard.php (T094). `show_in_rest => false`: curation
 * happens through that meta box's own nonce/capability-checked save
 * handler, not the block editor's generic meta-fields REST surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_register_insight_meta(): void {
	register_post_meta(
		'post',
		'_pe_related_properties',
		array(
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => static function ( $value ) {
				return is_array( $value ) ? array_map( 'absint', $value ) : array();
			},
		)
	);
}
add_action( 'init', 'primeestate_register_insight_meta' );
