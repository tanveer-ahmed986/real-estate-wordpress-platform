<?php
/**
 * ViewingForm component (source doc §67, FR-030/FR-031). Same
 * form-posts-directly-to-REST pattern as InquiryForm (works without
 * JavaScript; viewing-form.js progressively enhances with inline
 * success/error state). The `min` attribute on the date field is a UX
 * nicety only — FR-031's past-date rejection is enforced server-side in
 * `primeestate_validate_viewing_submission()` regardless of what the
 * browser allowed the visitor to pick.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_viewing_form( int $property_id ): void {
	$action = rest_url( PRIMEESTATE_REST_NAMESPACE . '/viewings' );
	?>
	<div class="pe-viewing-form-wrapper" data-component="viewing-form-wrapper">
	<form class="pe-viewing-form" method="post" action="<?php echo esc_url( $action ); ?>" data-component="viewing-form" data-property-id="<?php echo esc_attr( $property_id ); ?>">
		<input type="hidden" name="property_id" value="<?php echo esc_attr( $property_id ); ?>">

		<?php primeestate_render_honeypot_field(); ?>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-name"><?php esc_html_e( 'Name', 'primeestate' ); ?></label>
			<input type="text" id="pe-viewing-name" name="name" required autocomplete="name">
		</div>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-email"><?php esc_html_e( 'Email', 'primeestate' ); ?></label>
			<input type="email" id="pe-viewing-email" name="email" required autocomplete="email">
		</div>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-phone"><?php esc_html_e( 'Phone', 'primeestate' ); ?></label>
			<input type="tel" id="pe-viewing-phone" name="phone" required autocomplete="tel">
		</div>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-date"><?php esc_html_e( 'Preferred date', 'primeestate' ); ?></label>
			<input type="date" id="pe-viewing-date" name="preferred_date" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
		</div>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-time"><?php esc_html_e( 'Preferred time', 'primeestate' ); ?></label>
			<input type="time" id="pe-viewing-time" name="preferred_time" required>
		</div>

		<div class="pe-viewing-form__field">
			<label for="pe-viewing-message"><?php esc_html_e( 'Message (optional)', 'primeestate' ); ?></label>
			<textarea id="pe-viewing-message" name="message" rows="3"></textarea>
		</div>

		<button type="submit" class="pe-viewing-form__submit"><?php esc_html_e( 'Request viewing', 'primeestate' ); ?></button>
		<p class="pe-viewing-form__status" role="status" aria-live="polite"></p>
	</form>
	</div>
	<?php
}
