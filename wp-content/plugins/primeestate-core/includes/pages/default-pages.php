<?php
/**
 * Creates the WordPress Pages required by every `page-{slug}.html` block
 * template this theme ships (Favorites/Compare — T061/T063; Agent
 * Dashboard/Add-Property/Profile-Edit — T072/T074/T076; public Submit
 * Property — T080; registered-user Dashboard — T085) on activation.
 * WordPress's block-template page hierarchy only applies `page-{slug}.html`
 * to a Page that already exists with that slug; neither the theme nor any
 * WP core mechanism creates it automatically. `post_content` is left empty
 * since these pages' entire visible content comes from the block template's
 * shortcode, not the post content itself. The agent-area pages are
 * access-gated at render time (`primeestate_user_can_access_agent_area()`),
 * not by hiding the Page itself — WordPress has no native "page visibility
 * by role" mechanism, so the guard lives in each shortcode instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_create_default_pages(): void {
	$pages = array(
		'favorites'           => __( 'Favorites', 'primeestate' ),
		'compare'             => __( 'Compare', 'primeestate' ),
		'agent-dashboard'     => __( 'Agent Dashboard', 'primeestate' ),
		'agent-add-property'  => __( 'Add Property', 'primeestate' ),
		'agent-profile-edit'  => __( 'Edit Profile', 'primeestate' ),
		'submit-property'     => __( 'Submit a Property', 'primeestate' ),
		'user-dashboard'      => __( 'My Dashboard', 'primeestate' ),
	);

	foreach ( $pages as $slug => $title ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing instanceof WP_Post ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => '',
			)
		);
	}
}
add_action( 'primeestate_core_activated', 'primeestate_create_default_pages' );

/**
 * T101 (FR-057/FR-058): a real Privacy Policy page, seeded on activation and
 * registered as WordPress's official privacy policy page
 * (`wp_page_for_privacy_policy`) so it surfaces in the site's privacy
 * notices and in the native "Export/Erase Personal Data" admin tools
 * (privacy/exporters.php, erasers.php) the way a real one would. Unlike the
 * blank shortcode-hosting pages above, this one's content is the actual
 * policy text — it has no block template of its own, so it renders through
 * whatever generic `page.html` template already handles a plain WP Page.
 */
function primeestate_create_privacy_policy_page(): void {
	$existing = get_page_by_path( 'privacy-policy', OBJECT, 'page' );

	if ( $existing instanceof WP_Post ) {
		if ( ! get_option( 'wp_page_for_privacy_policy' ) ) {
			update_option( 'wp_page_for_privacy_policy', $existing->ID );
		}
		return;
	}

	$content = "<!-- wp:heading --><h2>" . esc_html__( 'What we collect', 'primeestate' ) . "</h2><!-- /wp:heading -->\n"
		. '<!-- wp:paragraph --><p>' . esc_html__( 'When you submit an inquiry or request a viewing, we collect your name, email address, phone number, and any message you include, along with which property it relates to. If you create an account, we collect your name and email address. If you save favorites or a comparison list without an account, that list is stored only in your browser (localStorage) and never sent to our server.', 'primeestate' ) . "</p><!-- /wp:paragraph -->\n"
		. '<!-- wp:heading --><h2>' . esc_html__( 'Why we collect it', 'primeestate' ) . "</h2><!-- /wp:heading -->\n"
		. '<!-- wp:paragraph --><p>' . esc_html__( 'We use this information to respond to your inquiry, schedule and confirm viewings, and, if you have an account, to show your own inquiries, viewing requests, favorites, and comparisons on your personal dashboard.', 'primeestate' ) . "</p><!-- /wp:paragraph -->\n"
		. '<!-- wp:heading --><h2>' . esc_html__( 'Third parties who may receive it', 'primeestate' ) . "</h2><!-- /wp:heading -->\n"
		. '<!-- wp:list --><ul>'
		. '<!-- wp:list-item --><li>' . esc_html__( 'Map provider: property location pages load map tiles from OpenStreetMap (or another provider, if configured under Settings → Map Provider), which sees the IP address of anyone viewing a map.', 'primeestate' ) . "</li><!-- /wp:list-item -->\n"
		. '<!-- wp:list-item --><li>' . esc_html__( 'Analytics: if a site administrator has configured Google Analytics, Google Tag Manager, or Meta Pixel under Settings → Analytics, those providers receive standard analytics/advertising data about your visit.', 'primeestate' ) . "</li><!-- /wp:list-item -->\n"
		. '<!-- wp:list-item --><li>' . esc_html__( 'Email service: inquiry and viewing-request notifications are delivered through the site\'s configured transactional email provider.', 'primeestate' ) . "</li><!-- /wp:list-item -->\n"
		. "</ul><!-- /wp:list -->\n"
		. '<!-- wp:heading --><h2>' . esc_html__( 'Your rights', 'primeestate' ) . "</h2><!-- /wp:heading -->\n"
		. '<!-- wp:paragraph --><p>' . esc_html__( 'You can request a copy of the personal data associated with your email address, or request that it be erased, by contacting us. Requests are handled through WordPress\'s built-in personal data export and erasure tools.', 'primeestate' ) . "</p><!-- /wp:paragraph -->";

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => __( 'Privacy Policy', 'primeestate' ),
			'post_name'    => 'privacy-policy',
			'post_content' => $content,
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) && ! get_option( 'wp_page_for_privacy_policy' ) ) {
		update_option( 'wp_page_for_privacy_policy', $page_id );
	}
}
add_action( 'primeestate_core_activated', 'primeestate_create_privacy_policy_page' );
