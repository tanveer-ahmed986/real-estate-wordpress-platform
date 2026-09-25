<?php
/**
 * Accessibility helpers (constitution Principle VI, WCAG 2.2 AA, SC-011).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_skip_link(): void {
	printf(
		'<a class="pe-skip-link screen-reader-text" href="#pe-main-content">%s</a>',
		esc_html__( 'Skip to content', 'primeestate' )
	);
}
add_action( 'wp_body_open', 'primeestate_skip_link', 5 );

/**
 * Adds `aria-current="page"` to the current nav menu item's link, beyond
 * core's `current-menu-item` class (a CSS class alone is not exposed to
 * assistive tech).
 */
function primeestate_nav_menu_link_attributes( array $atts, $item ): array {
	if ( in_array( 'current-menu-item', $item->classes, true ) ) {
		$atts['aria-current'] = 'page';
	}

	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'primeestate_nav_menu_link_attributes', 10, 2 );
