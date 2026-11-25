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

    const OK_TRANSIENT = 'mwrm_tables_ok';
    const LOCK_TRANSIENT = 'mwrm_repair_lock';
    const ERROR_OPTION = 'mwrm_install_error';
    const LOCK_WAIT = 2; // Seconds a request waits for a repair already running.

    /**
     * Per-request results of is_healthy(), by part.
     *
     * @var array
     */
    private static $healthy = array();

    /**
     * Set after a confirmed schema error: ignore the positive cache flag for the rest of
     * this request. A concurrent request may have deleted the transient while this process
     * still holds the old value in its object cache.
     *
     * @var bool
     */
    private static $distrust_cache = false;

    /**
     * Set when this request already waited for the repair lock in vain: it waits at most once.
     *
     * @var bool
     */
    private static $lock_timed_out = false;

    /**
     * Single source of truth for the schema: one CREATE TABLE per table
     * (keyed by name without prefix). The expected column list is derived from
     * these statements, so create and verify cannot drift apart.
     *
     * @return array
     */
    private static function definitions()
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        return array(
            'mwrm_redirects' => "CREATE TABLE {$wpdb->prefix}mwrm_redirects (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source varchar(255) NOT NULL,
            target varchar(2083) NOT NULL,
            type smallint(3) NOT NULL DEFAULT 301,
            is_wildcard tinyint(1) NOT NULL DEFAULT 0,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source (source(191))
        ) {$charset};",
            'mwrm_404_log' => "CREATE TABLE {$wpdb->prefix}mwrm_404_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            referrer varchar(2083) NOT NULL DEFAULT '',
            hits bigint(20) unsigned NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url (url(191))
        ) {$charset};",
        );
    }

    /**
     * Column names declared in a CREATE TABLE statement.
     *
     * @param string $sql CREATE TABLE statement.
     * @return string[]
     */
    private static function columns_of($sql)
    {
        preg_match_all('/^\s*(?!PRIMARY\b|UNIQUE\b|KEY\b|INDEX\b|CREATE\b|\))(\w+)\s+\w+/m', $sql, $m);

        return $m[1];
    }

    /**
     * Do the tables exist with every column of their CREATE definition?
     * Types, defaults and indexes are NOT verified (see notes in the report).
     *
     * @param string|null $only 'redirects' or '404_log' to check one table; null for both.
     * @return bool
     */
    public static function schema_ok($only = null)
    {
        global $wpdb;

        foreach (self::definitions() as $suffix => $sql) {
            if (null !== $only && 'mwrm_' . $only !== $suffix) {
                continue;
            }

            $table = $wpdb->prefix . $suffix;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($found !== $table) {
                return false;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $have = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
            if (!is_array($have) || array_diff(self::columns_of($sql), $have)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create or complete the tables. dbDelta adds only what is missing (tables,
     * columns, keys) and never empties a table, so existing rows are kept.
     *
     * @return void
     */
    public static function create_tables()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (self::definitions() as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate()
    {
        self::repair(true);
    }

    /**
     * Install or repair. The version option is written only after the
     * schema has been verified.
     *
     * @param bool $force Ignore the retry lock (activation).
     * @return bool
     */
    public static function repair($force = false)
    {
        global $wpdb;

        // A recent failed attempt is remembered for the lock period: no new attempt.
        if (!$force && is_array(get_transient(self::LOCK_TRANSIENT))) {
            return false;
        }

        // A request waits for the repair lock at most once.
        if (!$force && self::$lock_timed_out) {
            return false;
        }

        // Serialise concurrent repairs across PHP processes with a database lock (the
        // transient alone is not atomic). Waiters block briefly so that, once the first
        // request has repaired the schema, they can continue instead of dropping their work.
        // NULL means GET_LOCK is unsupported: carry on without it.
        $lock_name = $wpdb->prefix . 'mwrm_repair';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, $force ? 5 : self::LOCK_WAIT));

        if (null !== $got && '1' !== (string) $got) {
            self::$lock_timed_out = true;

            return false; // The other repair is still running; skip this request's work.
        }

        $ok = self::repair_locked($force);

        if (null !== $got) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }

        return $ok;
    }

    /**
     * Repair body; runs while holding the database lock.
     *
     * @param bool $force Ignore a remembered failure (activation).
     * @return bool
     */
    private static function repair_locked($force)
    {
        global $wpdb;

        // The holder we waited for may have failed in the meantime: do not retry.
        if (!$force && is_array(get_transient(self::LOCK_TRANSIENT))) {
            return false;
        }

        set_transient(self::LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);

        // The holder we waited for may already have repaired everything. A changed
        // DB_VERSION always runs dbDelta (it can alter indexes and types too).
        $create_error = '';
        if (!self::schema_ok() || get_option('mwrm_db_version') !== self::DB_VERSION) {
            self::create_tables();
            $create_error = (string) $wpdb->last_error; // Captured before the verification queries reset it.
        }

        if (self::schema_ok()) {
            update_option('mwrm_db_version', self::DB_VERSION);
            delete_option(self::ERROR_OPTION);
            delete_transient(self::LOCK_TRANSIENT);
            set_transient(self::OK_TRANSIENT, self::DB_VERSION, 12 * HOUR_IN_SECONDS);

            return true;
        }

        // Remember which parts are broken for the whole wait period, so requests
        // during the lock neither query the broken table nor re-run SHOW checks.
        $broken = array();
        foreach (array('redirects', '404_log') as $part) {
            if (!self::schema_ok($part)) {
                $broken[] = $part;
            }
        }
        set_transient(self::LOCK_TRANSIENT, array('broken' => $broken), 5 * MINUTE_IN_SECONDS);

        $message = sanitize_text_field('' !== $create_error ? $create_error : (string) $wpdb->last_error);
        if ('' === $message) {
            $message = 'Tables still incomplete after repair: ' . implode(', ', $broken) . '.';
        }
        update_option(self::ERROR_OPTION, $message, false);

        return false;
    }

    /**
     * Is the database ready for one part? Plain front-end requests trust a
     * cached flag (valid only for the current DB_VERSION); admin, REST, AJAX
     * and cron requests always verify for real. A broken log table does not
     * pause working redirects, and the other way round. When a part is not
     * ready, one throttled repair is attempted; otherwise callers must skip
     * their queries.
     *
     * @param string $part 'redirects' or '404_log'.
     * @return bool
     */
    public static function is_healthy($part = 'redirects')
    {
        if (isset(self::$healthy[$part])) {
            return self::$healthy[$part];
        }

        $plain_frontend = !is_admin() && !defined('REST_REQUEST') && !wp_doing_ajax() && !wp_doing_cron();

        if ($plain_frontend && !self::$distrust_cache && get_transient(self::OK_TRANSIENT) === self::DB_VERSION) {
            return self::$healthy[$part] = true;
        }

        // A failed repair is remembered for the lock period: no new queries, no new attempts.
        $lock = get_transient(self::LOCK_TRANSIENT);
        if (is_array($lock) && isset($lock['broken'])) {
            return self::$healthy[$part] = !in_array($part, $lock['broken'], true);
        }

        if (get_option('mwrm_db_version') === self::DB_VERSION && self::schema_ok()) {
            delete_option(self::ERROR_OPTION);
            set_transient(self::OK_TRANSIENT, self::DB_VERSION, 12 * HOUR_IN_SECONDS);

            return self::$healthy[$part] = true;
        }

        // Missing table or column, missing option, or an older schema: dbDelta, then verify.
        self::repair();

        // The version option is only written when both tables verify; the part
        // itself may still be usable (for example redirects while the log failed).
        return self::$healthy[$part] = self::schema_ok($part);
    }

    /**
     * Call right after a query on one of our tables, passing the error string
     * captured immediately after THAT query (never a stale $wpdb->last_error).
     *
     * Recognised: a missing table of this part, or an unknown-column error.
     * An unknown-column message does not name the table, so it is tied to the
     * part the caller just queried and then confirmed against the real schema
     * before anything is repaired. If the schema is fine, nothing happens.
     *
     * @param string $part  'redirects' or '404_log'.
     * @param string $error Error captured right after the query.
     * @return bool True when the error was a schema error of this part AND the part is usable now (repaired by us or by a concurrent request); the caller may retry its query once. False for unrelated errors or when repair failed.
     */
    public static function check_error($part, $error)
    {
        global $wpdb;

        $error = (string) $error;

        if ('' === $error) {
            return false;
        }

        $missing = preg_match("/doesn'?t exist|does not exist|\b1146\b/i", $error)
            && false !== stripos($error, $wpdb->prefix . 'mwrm_' . $part);
        $column = preg_match('/unknown column|\b1054\b|has no column/i', $error);

        if (!$missing && !$column) {
            return false;
        }

        // Confirm against the real schema of the part that was queried.
        if (self::schema_ok($part)) {
            // The query failed on a real schema error, but the schema is complete again:
            // another request repaired it between our failed query and this check.
            // Nothing to repair; the caller may retry its own query once (bounded).
            return true;
        }

        delete_transient(self::OK_TRANSIENT);
        self::$distrust_cache = true;
        self::$healthy = array();

        return self::is_healthy($part);
    }

    /**
     * Admin notice while the tables could not be created or repaired.
     *
     * @return void
     */
    public static function render_error_notice()
    {
        $error = get_option(self::ERROR_OPTION, null);

        if (null === $error || !current_user_can('manage_options')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s %s</p></div>',
            esc_html__('Manny Wenas Redirect Manager could not create or repair its database tables. The affected feature is paused until this succeeds.', 'manny-wenas-redirect-manager'),
            esc_html((string) $error)
        );
    }
}
