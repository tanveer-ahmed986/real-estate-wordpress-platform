<?php
/**
 * `wp primeestate reset` — removes only content/users tagged
 * `_primeestate_demo` by `wp primeestate seed` (T031). Safe to re-run;
 * never touches real (non-demo) properties, inquiries, viewing requests,
 * insight articles, or users. `post` was added to `$post_types` in Phase 11
 * alongside T095's insight-article seeding — without it, seeded demo
 * articles would accumulate on every `seed`/`reset` cycle instead of being
 * cleaned up like every other demo content type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

function primeestate_cli_reset( array $args, array $assoc_args ): void {
	$post_types    = array( 'property', 'pe_inquiry', 'pe_viewing_request', 'post' );
	$deleted_posts = 0;

	foreach ( $post_types as $post_type ) {
		$demo_post_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_primeestate_demo',
				'meta_value'     => '1',
			)
		);

		foreach ( $demo_post_ids as $post_id ) {
			if ( wp_delete_post( $post_id, true ) ) {
				++$deleted_posts;
			}
		}
	}

	$demo_user_ids = get_users(
		array(
			'meta_key' => '_primeestate_demo',
			'meta_value' => '1',
			'fields'   => 'ID',
		)
	);

	$deleted_users = 0;

	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	foreach ( $demo_user_ids as $user_id ) {
		if ( wp_delete_user( $user_id ) ) {
			++$deleted_users;
		}
	}

	WP_CLI::success( sprintf( 'Removed %d demo posts and %d demo users.', $deleted_posts, $deleted_users ) );
}
WP_CLI::add_command( 'primeestate reset', 'primeestate_cli_reset' );
