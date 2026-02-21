<?php
/**
 * Serve Markdown — Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin data from the database:
 *   - Custom log table
 *   - Plugin settings option
 *   - Per-post meta entries
 *   - Transients
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the crawler log table.
$table = $wpdb->prefix . 'serve_md_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete the plugin settings.
delete_option( 'serve_md_settings' );

// Delete all per-post opt-out meta entries.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_serve_md_disabled'" // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
);

// Delete transients.
delete_transient( 'serve_md_flush_rewrite' );
delete_transient( 'serve_md_prune_guard' );
