<?php
/**
 * Core theme support declarations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'align-wide' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'primeestate' ),
			'footer'  => __( 'Footer Menu', 'primeestate' ),
		)
	);

	load_theme_textdomain( 'primeestate', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'primeestate_theme_setup' );
