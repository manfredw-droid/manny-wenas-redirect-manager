<?php
/**
 * Data access for redirects.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Repository
{
    /**
     * @return string
     */
    public static function table()
    {
        global $wpdb;

        return $wpdb->prefix . 'mwrm_redirects';
    }

    /**
     * Normalize a request path: strip scheme/host/query, lowercase,
     * no trailing slash, always one leading slash. Keeps a trailing "*".
     *
     * @param string $url   URL or path.
     * @param bool   $lower Lowercase the result (default). False keeps the original case.
     * @return string
     */
    public static function normalize_path($url, $lower = true)
    {
        $path = (string) wp_parse_url(trim($url), PHP_URL_PATH);
        $path = '/' . ltrim(rawurldecode($path), '/');

        if ('/' !== $path) {
            $path = untrailingslashit($path);
        }

        return $lower ? strtolower($path) : $path;
    }

    /**
     * Normalized path relative to the site root (subdirectory installs stripped).
     *
     * @param string $url   URL or path.
     * @param bool   $lower Lowercase the result (default).
     * @return string
     */
    public static function relative_path($url, $lower = true)
    {
        $path = self::normalize_path($url, $lower);
        $home = self::normalize_path(home_url(), $lower);

        if ('/' !== $home && 0 === strpos($path, $home)) {
            $path = '/' . ltrim(substr($path, strlen($home)), '/');
        }

        return $path;
    }

    /**
     * @param int $id Redirect ID.
     * @return object|null
     */
    public static function get($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id)
        );
    }

    /**
     * @param string $source Source path or URL.
     * @return bool
     */
    public static function source_exists($source)
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE source = %s LIMIT 1', self::relative_path($source))
        );
    }

    /**
     * Insert or update a redirect.
     *
     * @param array $data {source, target, type}.
     * @param int   $id   Existing ID, 0 to insert.
     * @return int|WP_Error Redirect ID.
     */
    public static function save(array $data, $id = 0)
    {
        global $wpdb;

        $source = self::relative_path($data['source']);
        $target = trim($data['target']);
        $type = (int) $data['type'];

        // A bare relative target would otherwise be turned into "http://target" by esc_url_raw().
        if ('' !== $target && !preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $target)) {
            $target = '/' . $target;
        }

        $target = esc_url_raw($target);

        if ('/' === $source || '' === $target) {
            return new WP_Error('mwrm_invalid', __('Source and target are required.', 'manny-wenas-redirect-manager'));
        }

        // "/*" would redirect the whole site.
        if ('/*' === $source) {
            return new WP_Error('mwrm_root', __('A wildcard on the site root is not allowed.', 'manny-wenas-redirect-manager'));
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE source = %s AND id <> %d LIMIT 1', $source, (int) $id)
        );

        if ($existing) {
            return new WP_Error('mwrm_duplicate', __('A redirect for this source already exists.', 'manny-wenas-redirect-manager'));
        }

        if (!in_array($type, array(301, 302), true)) {
            $type = 301;
        }

        if ($source === self::normalize_path($target) && '/*' !== substr($source, -2)) {
            return new WP_Error('mwrm_loop', __('Source and target are the same.', 'manny-wenas-redirect-manager'));
        }

        $row = array(
            'source' => $source,
            'target' => $target,
            'type' => $type,
            'is_wildcard' => '/*' === substr($source, -2) ? 1 : 0,
        );

        if ($id) {
            $wpdb->update(self::table(), $row, array('id' => (int) $id));

            return (int) $id;
        }

        $row['created_at'] = current_time('mysql');
        $wpdb->insert(self::table(), $row);

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id Redirect ID.
     * @return void
     */
    public static function delete($id)
    {
        global $wpdb;

        $wpdb->delete(self::table(), array('id' => (int) $id));
    }

    /**
     * Find the redirect for a request path. Exact matches win over wildcards.
     *
     * @param string      $path     Normalized (lowercase) request path.
     * @param string|null $original Same path with its original case, used for the wildcard capture.
     * @return object|null Row with an added resolved_target property.
     */
    public static function match($path, $original = null)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE is_wildcard = 0 AND source = %s LIMIT 1', $path)
        );

        if ($row) {
            $row->resolved_target = $row->target;

            return $row;
        }

        $wildcards = $wpdb->get_results(
            'SELECT * FROM ' . self::table() . ' WHERE is_wildcard = 1 ORDER BY CHAR_LENGTH(source) DESC'
        );

        foreach ($wildcards as $rule) {
            $prefix = substr($rule->source, 0, -1); // "/old/".

            if (0 === strpos($path . '/', $prefix)) {
                $rest = substr(($original ? $original : $path) . '/', strlen($prefix));
                $rule->resolved_target = str_replace('*', rtrim($rest, '/'), $rule->target);

                return $rule;
            }
        }

        return null;
    }

    /**
     * Is the URL on this site (relative, or same host)?
     *
     * @param string $url URL or path.
     * @return bool
     */
    public static function is_internal($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        return !$host || strtolower($host) === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    /**
     * Follow a redirect target through other redirects.
     *
     * @param string $source Source of the redirect being checked.
     * @param string $target Its target.
     * @return array {hops:int, loop:bool, final:string} hops = further redirects after the first.
     */
    public static function trace($source, $target)
    {
        $seen = array(self::relative_path($source));
        $current = $target;
        $hops = 0;
        $loop = false;

        while ($hops < 10 && self::is_internal($current)) {
            $path = self::relative_path($current);

            if (in_array($path, $seen, true)) {
                $loop = true;
                break;
            }

            $rule = self::match($path);

            if (!$rule) {
                break;
            }

            $seen[] = $path;
            $current = $rule->resolved_target;
            $hops++;
        }

        return array('hops' => $hops, 'loop' => $loop, 'final' => $current);
    }

    /**
     * Existing redirects that point at the given source (would form a chain).
     *
     * @param string $source Source path.
     * @return array Rows.
     */
    public static function inbound($source)
    {
        global $wpdb;

        $source = self::relative_path($source);

        if ('/*' === substr($source, -2)) {
            return array();
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, source, target FROM ' . self::table() . ' WHERE target LIKE %s',
            '%' . $wpdb->esc_like(basename($source)) . '%'
        ));

        return array_values(array_filter($rows, function ($row) use ($source) {
            return self::is_internal($row->target) && self::relative_path($row->target) === $source;
        }));
    }

    /**
     * @param int $id Redirect ID.
     * @return void
     */
    public static function add_hit($id)
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . ' SET hits = hits + 1 WHERE id = %d', $id));
    }
}
