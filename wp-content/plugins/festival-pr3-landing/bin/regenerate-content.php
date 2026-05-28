<?php
/**
 * Пересобирает landing-blocks.html в валидном формате Gutenberg.
 *
 * wp eval-file wp-content/plugins/festival-pr3-landing/bin/regenerate-content.php
 */

$plugin_dir = dirname( __DIR__ );
$file       = $plugin_dir . '/content/landing-blocks.html';

if ( ! is_readable( $file ) ) {
	WP_CLI::error( 'landing-blocks.html not found' );
}

require_once $plugin_dir . '/includes/normalize-blocks.php';

$raw        = file_get_contents( $file );
$normalized = festival_pr3_normalize_block_content( $raw );

file_put_contents( $file, $normalized );

$page_id = (int) get_option( 'festival_pr3_page_id', 0 );
if ( $page_id > 0 ) {
	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => $normalized,
		)
	);
	update_option( 'festival_pr3_content_hash', md5( $normalized ) );
}

WP_CLI::success( 'Normalized content written. Page ID: ' . $page_id );
