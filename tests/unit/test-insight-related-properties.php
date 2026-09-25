<?php
/**
 * T094 — `[primeestate_related_properties]` (theme/inc/insight-render.php).
 * Covers the defensive behavior that makes this shortcode safe to leave on
 * every article template: it must render nothing (not an empty-state
 * message — related properties are optional per FR-051) when there's
 * nothing curated, and it must silently drop any curated ID that no longer
 * points at a published property, the same self-healing principle applied
 * to favorites/comparison in Phase 5.
 */

class Test_Insight_Related_Properties extends WP_UnitTestCase {

	private function go_to_post( int $post_id ): void {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
	}

	public function test_renders_nothing_when_no_related_properties_curated(): void {
		$article_id = self::factory()->post->create();
		$this->go_to_post( $article_id );

		$this->assertSame( '', primeestate_related_properties_shortcode() );
	}

	public function test_renders_curated_published_properties(): void {
		$article_id  = self::factory()->post->create();
		$property_id = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Curated Villa' ) );

		update_post_meta( $article_id, '_pe_related_properties', array( $property_id ) );
		$this->go_to_post( $article_id );

		$output = primeestate_related_properties_shortcode();

		$this->assertStringContainsString( 'Curated Villa', $output );
	}

	public function test_silently_drops_a_curated_id_that_is_no_longer_published(): void {
		$article_id       = self::factory()->post->create();
		$published_id     = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'publish', 'post_title' => 'Still Live' ) );
		$unpublished_id   = self::factory()->post->create( array( 'post_type' => 'property', 'post_status' => 'draft', 'post_title' => 'No Longer Public' ) );

		update_post_meta( $article_id, '_pe_related_properties', array( $published_id, $unpublished_id ) );
		$this->go_to_post( $article_id );

		$output = primeestate_related_properties_shortcode();

		$this->assertStringContainsString( 'Still Live', $output );
		$this->assertStringNotContainsString( 'No Longer Public', $output );
	}

	public function test_renders_nothing_when_every_curated_id_is_gone(): void {
		$article_id = self::factory()->post->create();
		update_post_meta( $article_id, '_pe_related_properties', array( 999999 ) );
		$this->go_to_post( $article_id );

		$this->assertSame( '', primeestate_related_properties_shortcode() );
	}
}
