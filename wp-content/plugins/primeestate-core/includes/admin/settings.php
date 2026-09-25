<?php
/**
 * T102/T103 (FR-057/FR-058): site-wide Analytics and Map Provider settings.
 * Split out from admin/dashboard.php (which owns property-oversight surfaces
 * — moderation, overview, inquiries/viewings — a different kind of "admin"
 * concern than site-wide configuration) rather than added to it, the same
 * kind of file-target substitution as T090's Phase 10 split; see PHR.
 *
 * Both settings groups use the Settings API's own sanitize/escape pipeline,
 * so nothing here ever accepts unsanitized input — and neither ID/key field
 * has a default value, so no credential is ever hardcoded (FR-057): a fresh
 * install ships with analytics and any non-default map provider fully off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRIMEESTATE_MAP_PROVIDERS = array( 'leaflet', 'google', 'mapbox' );

function primeestate_register_settings_page(): void {
	add_options_page(
		__( 'PrimeEstate Settings', 'primeestate' ),
		__( 'PrimeEstate', 'primeestate' ),
		'manage_options',
		'primeestate-settings',
		'primeestate_render_settings_page'
	);
}
add_action( 'admin_menu', 'primeestate_register_settings_page' );

function primeestate_register_settings(): void {
	register_setting( 'primeestate_settings', 'primeestate_analytics_ga_id', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
	register_setting( 'primeestate_settings', 'primeestate_analytics_gtm_id', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
	register_setting( 'primeestate_settings', 'primeestate_analytics_meta_pixel_id', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
	register_setting( 'primeestate_settings', 'primeestate_map_provider', array( 'sanitize_callback' => 'primeestate_sanitize_map_provider', 'default' => 'leaflet' ) );
	register_setting( 'primeestate_settings', 'primeestate_map_api_key', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );

	add_settings_section( 'primeestate_analytics', __( 'Analytics & Tracking', 'primeestate' ), '__return_false', 'primeestate-settings' );
	add_settings_field( 'primeestate_analytics_ga_id', __( 'Google Analytics (GA4) Measurement ID', 'primeestate' ), 'primeestate_render_text_field', 'primeestate-settings', 'primeestate_analytics', array( 'option' => 'primeestate_analytics_ga_id', 'placeholder' => 'G-XXXXXXXXXX' ) );
	add_settings_field( 'primeestate_analytics_gtm_id', __( 'Google Tag Manager Container ID', 'primeestate' ), 'primeestate_render_text_field', 'primeestate-settings', 'primeestate_analytics', array( 'option' => 'primeestate_analytics_gtm_id', 'placeholder' => 'GTM-XXXXXXX' ) );
	add_settings_field( 'primeestate_analytics_meta_pixel_id', __( 'Meta Pixel ID', 'primeestate' ), 'primeestate_render_text_field', 'primeestate-settings', 'primeestate_analytics', array( 'option' => 'primeestate_analytics_meta_pixel_id', 'placeholder' => '000000000000000' ) );

	add_settings_section( 'primeestate_map', __( 'Map Provider', 'primeestate' ), '__return_false', 'primeestate-settings' );
	add_settings_field( 'primeestate_map_provider', __( 'Provider', 'primeestate' ), 'primeestate_render_map_provider_field', 'primeestate-settings', 'primeestate_map' );
	add_settings_field( 'primeestate_map_api_key', __( 'API Key', 'primeestate' ), 'primeestate_render_text_field', 'primeestate-settings', 'primeestate_map', array( 'option' => 'primeestate_map_api_key', 'placeholder' => __( 'Not required for OpenStreetMap', 'primeestate' ) ) );
}
add_action( 'admin_init', 'primeestate_register_settings' );

function primeestate_sanitize_map_provider( string $value ): string {
	return in_array( $value, PRIMEESTATE_MAP_PROVIDERS, true ) ? $value : 'leaflet';
}

function primeestate_render_text_field( array $args ): void {
	printf(
		'<input type="text" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="%3$s" autocomplete="off">',
		esc_attr( $args['option'] ),
		esc_attr( get_option( $args['option'], '' ) ),
		esc_attr( $args['placeholder'] ?? '' )
	);
}

function primeestate_render_map_provider_field(): void {
	$current = get_option( 'primeestate_map_provider', 'leaflet' );
	$labels  = array(
		'leaflet' => __( 'OpenStreetMap (Leaflet) — no API key required', 'primeestate' ),
		'google'  => __( 'Google Maps (requires API key)', 'primeestate' ),
		'mapbox'  => __( 'Mapbox (requires API key)', 'primeestate' ),
	);
	?>
	<select name="primeestate_map_provider" id="primeestate_map_provider">
		<?php foreach ( $labels as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php if ( 'leaflet' !== $current ) : ?>
		<p class="description"><?php esc_html_e( 'Only the OpenStreetMap (Leaflet) renderer is implemented; selecting another provider stores the choice and key for a future integration but the map will not switch renderers yet.', 'primeestate' ); ?></p>
	<?php endif; ?>
	<?php
}

function primeestate_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'primeestate' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PrimeEstate Settings', 'primeestate' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'primeestate_settings' );
			do_settings_sections( 'primeestate-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * The whole point of an ID field: without this, the settings screen would
 * store values nothing ever reads. Front-end only (`is_admin()` guard) —
 * tracking snippets have no business loading inside wp-admin.
 */
function primeestate_output_tracking_snippets(): void {
	if ( is_admin() ) {
		return;
	}

	$ga_id    = get_option( 'primeestate_analytics_ga_id', '' );
	$gtm_id   = get_option( 'primeestate_analytics_gtm_id', '' );
	$pixel_id = get_option( 'primeestate_analytics_meta_pixel_id', '' );

	if ( $ga_id ) {
		printf( '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>' . "\n", esc_attr( $ga_id ) );
		printf(
			"<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','%s');</script>\n",
			esc_js( $ga_id )
		);
	}

	if ( $gtm_id ) {
		printf(
			"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n",
			esc_js( $gtm_id )
		);
	}

	if ( $pixel_id ) {
		printf(
			"<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','%s');fbq('track','PageView');</script>\n",
			esc_js( $pixel_id )
		);
	}
}
add_action( 'wp_head', 'primeestate_output_tracking_snippets' );

/**
 * Wires the stored provider choice into the filter map.php already exposes
 * (`primeestate_map_provider`, added in an earlier phase in anticipation of
 * exactly this) — the Map component itself needed no changes.
 */
add_filter( 'primeestate_map_provider', function () {
	return get_option( 'primeestate_map_provider', 'leaflet' );
} );
