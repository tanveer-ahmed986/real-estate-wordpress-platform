<?php
/**
 * Primary/footer navigation rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_primary_navigation(): void {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'pe-nav__list',
				'depth'          => 2,
			)
		);
		return;
	}

	// Fallback so navigation is never empty before an admin assigns a menu.
	echo '<ul class="pe-nav__list">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'primeestate' ) . '</a></li>';
	echo '<li><a href="' . esc_url( get_post_type_archive_link( 'property' ) ) . '">' . esc_html__( 'Properties', 'primeestate' ) . '</a></li>';
	echo '</ul>';
}
