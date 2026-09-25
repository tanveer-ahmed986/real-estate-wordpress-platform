<?php
/**
 * MortgageCalculator component (competitive review — quick win: unlike an
 * automated valuation, a monthly-payment estimate needs no comparables data
 * or external service, just arithmetic against the listing's own price, so
 * it's genuinely buildable now rather than flagged for later). Pure client
 * side (mortgage-calculator.js) — no server round-trip, no new REST
 * endpoint, nothing to validate server-side since it never submits
 * anything or persists a value.
 *
 * Skipped entirely for `on_request` pricing (data-model.md §1) — there is
 * no number to amortize — and left to the caller to skip for
 * sold/rented/off-market listings, the same way the inquiry/viewing forms
 * already are on `templates/single-property.html`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function primeestate_render_mortgage_calculator( WP_Post $post ): void {
	$price      = (float) get_post_meta( $post->ID, '_pe_price', true );
	$price_type = (string) get_post_meta( $post->ID, '_pe_price_type', true );

	if ( 'on_request' === $price_type || $price <= 0 ) {
		return;
	}
	?>
	<section class="pe-mortgage-calc" data-component="mortgage-calculator" data-price="<?php echo esc_attr( $price ); ?>" data-currency="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_pe_currency', true ) ); ?>">
		<h2><?php esc_html_e( 'Mortgage calculator', 'primeestate' ); ?></h2>
		<p class="pe-mortgage-calc__note"><?php esc_html_e( 'Estimate only — actual rates and terms depend on your lender.', 'primeestate' ); ?></p>

		<div class="pe-mortgage-calc__inputs">
			<div class="pe-mortgage-calc__field">
				<label for="pe-mortgage-price"><?php esc_html_e( 'Home price', 'primeestate' ); ?></label>
				<input type="number" id="pe-mortgage-price" min="0" step="1000" value="<?php echo esc_attr( (int) $price ); ?>">
			</div>

			<div class="pe-mortgage-calc__field">
				<label for="pe-mortgage-down"><?php esc_html_e( 'Down payment (%)', 'primeestate' ); ?></label>
				<input type="number" id="pe-mortgage-down" min="0" max="100" step="1" value="20">
			</div>

			<div class="pe-mortgage-calc__field">
				<label for="pe-mortgage-rate"><?php esc_html_e( 'Interest rate (%/yr)', 'primeestate' ); ?></label>
				<input type="number" id="pe-mortgage-rate" min="0" max="30" step="0.1" value="6.5">
			</div>

			<div class="pe-mortgage-calc__field">
				<label for="pe-mortgage-term"><?php esc_html_e( 'Loan term (years)', 'primeestate' ); ?></label>
				<select id="pe-mortgage-term">
					<option value="30" selected>30</option>
					<option value="20">20</option>
					<option value="15">15</option>
					<option value="10">10</option>
				</select>
			</div>
		</div>

		<div class="pe-mortgage-calc__result">
			<span class="pe-mortgage-calc__result-label"><?php esc_html_e( 'Estimated monthly payment', 'primeestate' ); ?></span>
			<span class="pe-mortgage-calc__result-value" id="pe-mortgage-result">—</span>
		</div>
	</section>
	<?php
}
