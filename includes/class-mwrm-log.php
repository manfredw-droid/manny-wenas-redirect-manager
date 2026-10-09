<?php
/**
 * 404 monitor: logging and data access.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Log
{
    const LIMIT = 100;
    const MAX_ROWS = 5000;

    /**
     * @return void
     */
    public static function init()
    {
        // After the redirect handler (priority 1), so redirected URLs are never logged.
        add_action('template_redirect', array(__CLASS__, 'maybe_log'), 99);
    }

    /**
     * @return string
     */
    public static function table()
    {
        global $wpdb;

        return $wpdb->prefix . 'mwrm_404_log';
    }

    /**
     * @return void
     */
    public static function maybe_log()
    {
        global $wpdb;

        if (!is_404() || is_admin() || !isset($_SERVER['REQUEST_URI']) || 'GET' !== (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) {
            return;
        }

        $path = MWRM_Repository::relative_path(wp_unslash($_SERVER['REQUEST_URI'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        // Skip static assets such as images and favicons.
        if (preg_match('/\.(?:jpe?g|png|gif|webp|svg|ico|css|js|map|woff2?|ttf)$/', $path)) {
            return;
        }

        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (url, referrer, hits, last_seen) VALUES (%s, %s, 1, %s)
            ON DUPLICATE KEY UPDATE hits = hits + 1, referrer = VALUES(referrer), last_seen = VALUES(last_seen)',
            substr($path, 0, 255),
            substr($referrer, 0, 2083),
            current_time('mysql')
        ));

        // Bots can request endless unique URLs; keep the table bounded.
        if (1 === wp_rand(1, 50)) {
            self::prune();
        }
    }

    /**
     * Drop the least-hit, oldest rows beyond MAX_ROWS.
     *
     * @return void
     */
    private static function prune()
    {
        global $wpdb;

        $table = self::table();
        $cutoff = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT 1 OFFSET %d",
            self::MAX_ROWS
        ));

        if ($cutoff) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE id IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT 18446744073709551615 OFFSET %d) AS old)",
                self::MAX_ROWS
            ));
        }
    }

    /**
     * @return array
     */
    public static function top()
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' ORDER BY hits DESC, last_seen DESC LIMIT %d', self::LIMIT)
        );
    }

    /**
     * @param int $id Log ID.
     * @return void
     */
    public static function delete($id)
    {
        global $wpdb;

        $wpdb->delete(self::table(), array('id' => (int) $id));
    }

    /**
     * @param string $url Normalized path.
     * @return void
     */
    public static function delete_by_url($url)
    {
        global $wpdb;

        $wpdb->delete(self::table(), array('url' => $url));
    }

    /**
     * @return void
     */
    public static function clear()
    {
        global $wpdb;

        $wpdb->query('TRUNCATE TABLE ' . self::table());
    }
}
