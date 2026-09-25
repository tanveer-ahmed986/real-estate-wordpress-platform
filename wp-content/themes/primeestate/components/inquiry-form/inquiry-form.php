<?php
/**
 * InquiryForm component (source doc §67, FR-025–FR-027). Posts directly to
 * the public `POST /wp-json/primeestate/v1/inquiries` endpoint — that
 * endpoint accepts standard form-encoded POST bodies (not just JSON), so
 * this form creates an inquiry correctly even with JavaScript disabled; the
 * user just sees the endpoint's raw JSON response instead of an inline
 * thank-you message. inquiry-form.js (progressive enhancement) intercepts
 * submit for an AJAX request + inline success/error state instead.
 *
 * Client-side `required` attributes are a UX nicety only — the REST
 * endpoint (inquiry-handler.php) re-validates everything server-side
 * regardless (constitution Principle II).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_inquiry_form( int $property_id ): void {
	$action = rest_url( PRIMEESTATE_REST_NAMESPACE . '/inquiries' );
	?>
	<form class="pe-inquiry-form" method="post" action="<?php echo esc_url( $action ); ?>" data-component="inquiry-form" data-property-id="<?php echo esc_attr( $property_id ); ?>">
		<input type="hidden" name="property_id" value="<?php echo esc_attr( $property_id ); ?>">

		<?php primeestate_render_honeypot_field(); ?>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-name"><?php esc_html_e( 'Name', 'primeestate' ); ?></label>
			<input type="text" id="pe-inquiry-name" name="name" required autocomplete="name">
		</div>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-email"><?php esc_html_e( 'Email', 'primeestate' ); ?></label>
			<input type="email" id="pe-inquiry-email" name="email" required autocomplete="email">
		</div>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-phone"><?php esc_html_e( 'Phone', 'primeestate' ); ?></label>
			<input type="tel" id="pe-inquiry-phone" name="phone" autocomplete="tel">
		</div>

		<fieldset class="pe-inquiry-form__field">
			<legend><?php esc_html_e( 'Preferred contact method', 'primeestate' ); ?></legend>
			<label><input type="radio" name="preferred_contact_method" value="email" checked> <?php esc_html_e( 'Email', 'primeestate' ); ?></label>
			<label><input type="radio" name="preferred_contact_method" value="phone"> <?php esc_html_e( 'Phone', 'primeestate' ); ?></label>
			<label><input type="radio" name="preferred_contact_method" value="whatsapp"> <?php esc_html_e( 'WhatsApp', 'primeestate' ); ?></label>
		</fieldset>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-viewing-date"><?php esc_html_e( 'Preferred viewing date (optional)', 'primeestate' ); ?></label>
			<input type="date" id="pe-inquiry-viewing-date" name="preferred_viewing_date">
		</div>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-budget"><?php esc_html_e( 'Budget (optional)', 'primeestate' ); ?></label>
			<input type="number" min="0" id="pe-inquiry-budget" name="budget">
		</div>

		<div class="pe-inquiry-form__field">
			<label for="pe-inquiry-message"><?php esc_html_e( 'Message', 'primeestate' ); ?></label>
			<textarea id="pe-inquiry-message" name="message" rows="4" required></textarea>
		</div>

		<button type="submit" class="pe-inquiry-form__submit"><?php esc_html_e( 'Send inquiry', 'primeestate' ); ?></button>
		<p class="pe-inquiry-form__status" role="status" aria-live="polite"></p>
	</form>
	<?php
}
