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
