<?php
/**
 * Multiposter Uninstall
 *
 * Cleans up plugin options and database tables.
 * Does NOT delete vacancy posts.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Delete all plugin options
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$multiposter_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name LIKE 'multiposter_%'"
);

foreach ($multiposter_options as $multiposter_option) {
    delete_option($multiposter_option);
}

// Drop the import log table
$multiposter_table = esc_sql($wpdb->prefix . 'multiposter_import_log');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `$multiposter_table`");

// Also clean up old jobit-prefixed options if they exist (migration leftover)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$multiposter_old_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name LIKE 'jobit_%'"
);

foreach ($multiposter_old_options as $multiposter_option) {
    delete_option($multiposter_option);
}

// Drop old jobit import log table if it exists
$multiposter_old_table = esc_sql($wpdb->prefix . 'jobit_import_log');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `$multiposter_old_table`");

// Clean up transients
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_multiposter_%'
        OR option_name LIKE '_transient_timeout_multiposter_%'"
);
