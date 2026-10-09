<?php
/**
 * Removes all plugin data on uninstall.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mwrm_redirects, {$wpdb->prefix}mwrm_404_log");

delete_option('mwrm_db_version');

// Per-user transients (slug prompts, import results).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_mwrm\\_%' OR option_name LIKE '\\_transient\\_timeout\\_mwrm\\_%'");
