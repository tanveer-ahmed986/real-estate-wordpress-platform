<?php
/**
 * Public Agent Profile page (T077, FR-039/FR-040). Implemented as
 * WordPress's native author-archive template (`templates/author.html`),
 * NOT a literal file named `single-agent.html` as tasks.md's shorthand
 * suggested — WordPress's block-template hierarchy only recognizes
 * `single-{post_type}.html` for a registered post type, and `agent` is
 * deliberately a WP user role, not a post type (data-model.md §3: "No
 * separate CPT"). The author archive is the actual correctly-native
 * mechanism for "a WP user's public profile page", and `author_base` is
 * remapped from the default `/author/` to `/agents/` below so URLs still
 * read naturally (`/agents/{nicename}/`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_set_agent_author_base(): void {
	global $wp_rewrite;
	$wp_rewrite->author_base = 'agents';
}
add_action( 'init', 'primeestate_set_agent_author_base' );

/**
 * Reuses the plugin's own rewrite-flush flag (primeestate-core.php) so this
 * theme-side rewrite change takes effect without a manual "Save Permalinks"
 * click — the flag is polled on the very next `init` regardless of which
 * side (theme or plugin) set it.
 */
function primeestate_flush_rewrite_rules_on_theme_activation(): void {
	update_option( 'primeestate_core_flush_rewrite_rules', '1' );
}
add_action( 'after_switch_theme', 'primeestate_flush_rewrite_rules_on_theme_activation' );

function primeestate_agent_profile_page_shortcode(): string {
	$author_id = get_queried_object_id();
	$author    = get_userdata( $author_id );

	if ( ! $author ) {
		return '<p>' . esc_html__( 'Agent not found.', 'primeestate' ) . '</p>';
	}

	$profile = primeestate_agent_profile_to_schema( $author_id );

	ob_start();
	?>
	<div class="pe-agent-profile">
		<header class="pe-agent-profile__header">
			<?php echo get_avatar( $author_id, 120 ); ?>
			<h1><?php echo esc_html( $author->display_name ); ?></h1>
			<?php if ( $profile['office'] ) : ?>
				<p class="pe-agent-profile__office"><?php echo esc_html( $profile['office'] ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $profile['bio'] ) : ?>
			<section class="pe-agent-profile__about">
				<h2><?php esc_html_e( 'About', 'primeestate' ); ?></h2>
				<div><?php echo wp_kses_post( wpautop( $profile['bio'] ) ); ?></div>
			</section>
		<?php endif; ?>

		<section class="pe-agent-profile__contact">
			<h2><?php esc_html_e( 'Contact', 'primeestate' ); ?></h2>
			<ul>
				<?php if ( $profile['phone'] ) : ?>
					<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile['phone'] ) ); ?>"><?php echo esc_html( $profile['phone'] ); ?></a></li>
				<?php endif; ?>
				<?php if ( $profile['whatsapp'] ) : ?>
					<li><?php echo esc_html__( 'WhatsApp:', 'primeestate' ) . ' ' . esc_html( $profile['whatsapp'] ); ?></li>
				<?php endif; ?>
				<li><a href="mailto:<?php echo esc_attr( $author->user_email ); ?>"><?php echo esc_html( $author->user_email ); ?></a></li>
			</ul>
			<?php if ( ! empty( $profile['specializations'] ) ) : ?>
				<p class="pe-agent-profile__specializations"><?php echo esc_html( implode( ', ', $profile['specializations'] ) ); ?></p>
			<?php endif; ?>
		</section>

		<section class="pe-agent-profile__properties">
			<h2><?php esc_html_e( 'Properties', 'primeestate' ); ?></h2>
			<?php primeestate_render_agent_public_properties( $author_id ); ?>
		</section>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_agent_profile_page', 'primeestate_agent_profile_page_shortcode' );

function primeestate_render_agent_public_properties( int $author_id ): void {
	$query = new WP_Query(
		array(
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'author'         => $author_id,
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( ! $query->have_posts() ) {
		primeestate_render_empty_state( __( 'This agent has no active listings right now.', 'primeestate' ) );
		return;
	}

	echo '<div class="pe-property-grid">';
	foreach ( $query->posts as $post ) {
		primeestate_render_property_card( $post );
	}
	echo '</div>';
}
