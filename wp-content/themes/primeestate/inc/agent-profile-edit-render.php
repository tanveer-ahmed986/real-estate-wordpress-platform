<?php
/**
 * Agent profile edit form (T076 frontend half — backend is
 * plugin/includes/agents/agent-profile.php's `PATCH /agents/{id}`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_agent_profile_edit_shortcode(): string {
	if ( ! primeestate_user_can_access_agent_area() ) {
		return primeestate_render_access_denied_notice();
	}

	$user_id = get_current_user_id();
	$profile = primeestate_agent_profile_to_schema( $user_id );

	ob_start();
	?>
	<form class="pe-profile-form" data-component="agent-profile-form" data-agent-id="<?php echo esc_attr( $user_id ); ?>">
		<div class="pe-profile-form__field">
			<label for="pe-profile-bio"><?php esc_html_e( 'Bio', 'primeestate' ); ?></label>
			<textarea id="pe-profile-bio" name="bio" rows="5"><?php echo esc_textarea( $profile['bio'] ); ?></textarea>
		</div>

		<div class="pe-profile-form__field">
			<label for="pe-profile-phone"><?php esc_html_e( 'Phone', 'primeestate' ); ?></label>
			<input type="tel" id="pe-profile-phone" name="phone" value="<?php echo esc_attr( $profile['phone'] ); ?>">
		</div>

		<div class="pe-profile-form__field">
			<label for="pe-profile-whatsapp"><?php esc_html_e( 'WhatsApp', 'primeestate' ); ?></label>
			<input type="tel" id="pe-profile-whatsapp" name="whatsapp" value="<?php echo esc_attr( $profile['whatsapp'] ); ?>">
		</div>

		<div class="pe-profile-form__field">
			<label for="pe-profile-license"><?php esc_html_e( 'License number', 'primeestate' ); ?></label>
			<input type="text" id="pe-profile-license" name="license" value="<?php echo esc_attr( $profile['license'] ); ?>">
		</div>

		<div class="pe-profile-form__field">
			<label for="pe-profile-office"><?php esc_html_e( 'Office', 'primeestate' ); ?></label>
			<input type="text" id="pe-profile-office" name="office" value="<?php echo esc_attr( $profile['office'] ); ?>">
		</div>

		<fieldset class="pe-profile-form__field">
			<legend><?php esc_html_e( 'Areas served', 'primeestate' ); ?></legend>
			<?php foreach ( primeestate_get_location_leaf_terms() as $term ) : ?>
				<label class="pe-profile-form__checkbox">
					<input type="checkbox" name="areas[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $profile['areas'], true ) ); ?>>
					<span><?php echo esc_html( $term->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="pe-profile-form__field">
			<label for="pe-profile-specializations"><?php esc_html_e( 'Specializations (comma-separated)', 'primeestate' ); ?></label>
			<input type="text" id="pe-profile-specializations" name="specializations" value="<?php echo esc_attr( implode( ', ', $profile['specializations'] ) ); ?>">
		</div>

		<button type="submit" class="pe-profile-form__submit"><?php esc_html_e( 'Save profile', 'primeestate' ); ?></button>
		<p class="pe-profile-form__status" role="status" aria-live="polite"></p>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'primeestate_agent_profile_edit', 'primeestate_agent_profile_edit_shortcode' );
