<?php
/**
 * Activation: database tables and default options.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Installer
{
    const DB_VERSION = '1.0.0';

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $redirects = $wpdb->prefix . 'mwrm_redirects';
        $log = $wpdb->prefix . 'mwrm_404_log';

        dbDelta("CREATE TABLE {$redirects} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source varchar(255) NOT NULL,
            target varchar(2083) NOT NULL,
            type smallint(3) NOT NULL DEFAULT 301,
            is_wildcard tinyint(1) NOT NULL DEFAULT 0,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source (source(191))
        ) {$charset};");

        dbDelta("CREATE TABLE {$log} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            referrer varchar(2083) NOT NULL DEFAULT '',
            hits bigint(20) unsigned NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url (url(191))
        ) {$charset};");

        update_option('mwrm_db_version', self::DB_VERSION);
    }
}
