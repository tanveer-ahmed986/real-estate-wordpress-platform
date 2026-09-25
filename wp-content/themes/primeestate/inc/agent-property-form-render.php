<?php
/**
 * Agent-facing Add/Edit Property form (T074). Create submits to the custom
 * `POST /wp-json/primeestate/v1/properties` (property.php — owns the
 * FR-061 publish/pending capability branching). Editing an existing
 * property instead submits to WordPress core's own auto-generated
 * `PATCH /wp-json/wp/v2/property-posts/{id}` — the `property` CPT is
 * already `show_in_rest` with every field/meta/taxonomy needed
 * (property-meta.php, T019) registered `show_in_rest`, and core's REST
 * controller already enforces the same `map_meta_cap` own-only rule
 * (T070) — no custom edit endpoint needed, or wanted, for a plain field
 * update with no special business rule attached to it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_agent_add_property_shortcode(): string {
	if ( ! primeestate_user_can_access_agent_area() ) {
		return primeestate_render_access_denied_notice();
	}

	$property_id = isset( $_GET['property_id'] ) ? absint( $_GET['property_id'] ) : 0;
	$existing    = null;

	if ( $property_id ) {
		$candidate = get_post( $property_id );

		if ( ! $candidate || 'property' !== $candidate->post_type || ! current_user_can( 'edit_post', $property_id ) ) {
			return '<p>' . esc_html__( "You don't have access to edit this property.", 'primeestate' ) . '</p>';
		}

		$existing = $candidate;
	}

	ob_start();
	primeestate_render_property_form( $existing, home_url( '/agent-dashboard/' ) );
	return (string) ob_get_clean();
}

/**
 * T080: the public "Submit Property" form — reuses the exact same
 * `primeestate_render_property_form()`/property-form.js pair as the
 * agent-facing Add/Edit page. The only difference is the access gate: any
 * logged-in registered user (FR-041), not `manage_properties`. The create
 * endpoint (`POST /properties`, T073) already determines `pending` vs.
 * `publish` from the submitter's capability — this form has no "mode" of
 * its own to get wrong.
 */
function primeestate_submit_property_shortcode(): string {
	if ( ! is_user_logged_in() ) {
		return primeestate_render_access_denied_notice();
	}

	ob_start();
	primeestate_render_property_form( null );
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_submit_property', 'primeestate_submit_property_shortcode' );
add_shortcode( 'primeestate_agent_add_property', 'primeestate_agent_add_property_shortcode' );

function primeestate_render_property_form( ?WP_Post $existing, string $redirect_on_create = '' ): void {
	$is_edit        = null !== $existing;
	$property_types = get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => false ) );
	$listing_types  = get_terms( array( 'taxonomy' => 'listing_type', 'hide_empty' => false ) );
	$amenities      = get_terms( array( 'taxonomy' => 'amenity', 'hide_empty' => false ) );

	$current_listing_type  = $is_edit ? wp_get_post_terms( $existing->ID, 'listing_type', array( 'fields' => 'ids' ) ) : array();
	$current_property_type = $is_edit ? wp_get_post_terms( $existing->ID, 'property_type', array( 'fields' => 'ids' ) ) : array();
	$current_location      = $is_edit ? wp_get_post_terms( $existing->ID, 'location', array( 'fields' => 'ids' ) ) : array();
	$current_amenities     = $is_edit ? wp_get_post_terms( $existing->ID, 'amenity', array( 'fields' => 'ids' ) ) : array();
	?>
	<form
		class="pe-property-form"
		data-component="property-form"
		data-mode="<?php echo $is_edit ? 'edit' : 'create'; ?>"
		<?php if ( $is_edit ) : ?>data-property-id="<?php echo esc_attr( $existing->ID ); ?>"<?php endif; ?>
		<?php if ( $redirect_on_create ) : ?>data-redirect-on-create="<?php echo esc_attr( $redirect_on_create ); ?>"<?php endif; ?>
		data-create-success-message="<?php echo esc_attr( current_user_can( 'publish_properties' ) ? __( 'Property added.', 'primeestate' ) : __( 'Submitted for review. An administrator will approve it shortly.', 'primeestate' ) ); ?>"
	>
		<div class="pe-property-form__field">
			<label for="pe-property-title"><?php esc_html_e( 'Title', 'primeestate' ); ?></label>
			<input type="text" id="pe-property-title" name="title" required value="<?php echo esc_attr( $is_edit ? $existing->post_title : '' ); ?>">
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-description"><?php esc_html_e( 'Description', 'primeestate' ); ?></label>
			<textarea id="pe-property-description" name="description" rows="6" required><?php echo esc_textarea( $is_edit ? $existing->post_content : '' ); ?></textarea>
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-price"><?php esc_html_e( 'Price', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-property-price" name="price" required value="<?php echo esc_attr( $is_edit ? get_post_meta( $existing->ID, '_pe_price', true ) : '' ); ?>">
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-listing-type"><?php esc_html_e( 'Listing type', 'primeestate' ); ?></label>
			<select id="pe-property-listing-type" name="listing_type" required>
				<option value=""><?php esc_html_e( 'Select…', 'primeestate' ); ?></option>
				<?php foreach ( $listing_types as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current_listing_type, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-type"><?php esc_html_e( 'Property type', 'primeestate' ); ?></label>
			<select id="pe-property-type" name="property_type" required>
				<option value=""><?php esc_html_e( 'Select…', 'primeestate' ); ?></option>
				<?php foreach ( $property_types as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current_property_type, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-city"><?php esc_html_e( 'City', 'primeestate' ); ?></label>
			<select id="pe-property-city" name="city" required>
				<option value=""><?php esc_html_e( 'Select…', 'primeestate' ); ?></option>
				<?php foreach ( primeestate_get_location_leaf_terms() as $term ) : ?>
					<option value="<?php echo esc_attr( $term->name ); ?>" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current_location, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-address"><?php esc_html_e( 'Street address', 'primeestate' ); ?></label>
			<input type="text" id="pe-property-address" name="address" value="<?php echo esc_attr( $is_edit ? get_post_meta( $existing->ID, '_pe_address', true ) : '' ); ?>">
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-bedrooms"><?php esc_html_e( 'Bedrooms', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-property-bedrooms" name="bedrooms" value="<?php echo esc_attr( $is_edit ? get_post_meta( $existing->ID, '_pe_bedrooms', true ) : '0' ); ?>">
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-bathrooms"><?php esc_html_e( 'Bathrooms', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-property-bathrooms" name="bathrooms" value="<?php echo esc_attr( $is_edit ? get_post_meta( $existing->ID, '_pe_bathrooms', true ) : '0' ); ?>">
		</div>

		<div class="pe-property-form__field">
			<label for="pe-property-area"><?php esc_html_e( 'Area', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-property-area" name="area" value="<?php echo esc_attr( $is_edit ? get_post_meta( $existing->ID, '_pe_area', true ) : '' ); ?>">
		</div>

		<fieldset class="pe-property-form__field">
			<legend><?php esc_html_e( 'Amenities', 'primeestate' ); ?></legend>
			<?php foreach ( $amenities as $term ) : ?>
				<label class="pe-property-form__checkbox">
					<input type="checkbox" name="amenities[]" value="<?php echo esc_attr( $term->slug ); ?>" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $current_amenities, true ) ); ?>>
					<span><?php echo esc_html( $term->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="pe-property-form__field">
			<label for="pe-property-images"><?php esc_html_e( 'Photos', 'primeestate' ); ?></label>
			<input type="file" id="pe-property-images" accept="image/jpeg,image/png,image/webp" multiple>
			<ul class="pe-property-form__image-list" aria-live="polite"></ul>
			<input type="hidden" id="pe-property-image-ids" name="image_ids" value="">
		</div>

		<button type="submit" class="pe-property-form__submit">
			<?php echo esc_html( $is_edit ? __( 'Save changes', 'primeestate' ) : __( 'Add property', 'primeestate' ) ); ?>
		</button>
		<p class="pe-property-form__status" role="status" aria-live="polite"></p>
	</form>
	<?php
}
