<?php
/**
 * "My Favorites" page rendering (T061). Logged-in visitors get a fully
 * server-rendered list (usermeta is readable server-side). Guests' favorites
 * live only in `localStorage`, which PHP cannot read — so a guest instead
 * gets an empty container that favorites.js populates via the same
 * fragment-request pattern the archive uses (X-PrimeEstate-Fragment header),
 * keeping all HTML rendering server-side either way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_favorites_page_shortcode(): string {
	ob_start();

	if ( is_user_logged_in() ) {
		$properties = primeestate_get_favorited_properties( get_current_user_id() );
		primeestate_render_favorites_results( $properties );
	} else {
		?>
		<div id="pe-favorites-results" class="pe-favorites-results" data-fragment-url="<?php echo esc_url( home_url( '/favorites/' ) ); ?>">
			<p><?php esc_html_e( 'Loading your saved favorites…', 'primeestate' ); ?></p>
		</div>
		<?php
	}

	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_favorites_page', 'primeestate_favorites_page_shortcode' );

/**
 * @param WP_Post[] $properties
 */
function primeestate_render_favorites_results( array $properties ): void {
	if ( empty( $properties ) ) {
		primeestate_render_empty_state( __( "You haven't saved any favorites yet.", 'primeestate' ) );
		return;
	}

	echo '<div class="pe-property-grid">';
	foreach ( $properties as $post ) {
		primeestate_render_property_card( $post );
	}
	echo '</div>';
}

/**
 * Serves the results-partial for a guest's `?ids=` fragment request — same
 * pattern as archive-render.php's `primeestate_maybe_serve_property_fragment()`.
 */
function primeestate_maybe_serve_favorites_fragment(): void {
	if ( ! is_page( 'favorites' ) || empty( $_SERVER['HTTP_X_PRIMEESTATE_FRAGMENT'] ) ) {
		return;
	}

	$ids = primeestate_parse_id_list( wp_unslash( $_GET['ids'] ?? '' ) );

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	if ( empty( $ids ) ) {
		primeestate_render_empty_state( __( "You haven't saved any favorites yet.", 'primeestate' ) );
		exit;
	}

	$query = new WP_Query( primeestate_build_property_query_args( array( 'ids' => $ids ) ) );
	primeestate_render_favorites_results( $query->posts );
	exit;
}
add_action( 'template_redirect', 'primeestate_maybe_serve_favorites_fragment' );
