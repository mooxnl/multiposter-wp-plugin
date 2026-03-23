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
$options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name LIKE 'multiposter_%'"
);

foreach ($options as $option) {
    delete_option($option);
}

// Drop the import log table
$table = esc_sql($wpdb->prefix . 'multiposter_import_log');
$wpdb->query("DROP TABLE IF EXISTS `$table`");

// Also clean up old jobit-prefixed options if they exist (migration leftover)
$old_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options}
     WHERE option_name LIKE 'jobit_%'"
);

foreach ($old_options as $option) {
    delete_option($option);
}

// Drop old jobit import log table if it exists
$old_table = esc_sql($wpdb->prefix . 'jobit_import_log');
$wpdb->query("DROP TABLE IF EXISTS `$old_table`");

// Clean up transients
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_multiposter_%'
        OR option_name LIKE '_transient_timeout_multiposter_%'"
);
