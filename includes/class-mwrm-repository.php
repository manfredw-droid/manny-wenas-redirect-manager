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

        if ('/' !== $home && 0 === strpos($path, $home) && (strlen($path) === strlen($home) || '/' === $path[strlen($home)])) {
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

        if (!MWRM_Installer::is_healthy('redirects')) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d', self::table(), $id)
        );
    }

    /**
     * @param string $source Source path or URL.
     * @return bool
     */
    public static function source_exists($source)
    {
        global $wpdb;

        if (!MWRM_Installer::is_healthy('redirects')) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM %i WHERE source = %s LIMIT 1', self::table(), self::relative_path($source))
        );
    }

    /**
     * Insert or update a redirect.
     *
     * @param array $data {source, target, type, matching (optional: regex)}.
     * @param int   $id   Existing ID, 0 to insert.
     * @return int|WP_Error Redirect ID.
     */
    public static function save(array $data, $id = 0)
    {
        global $wpdb;

        if (!MWRM_Installer::is_healthy('redirects')) {
            return new WP_Error('mwrm_db', __('The redirect tables are not available.', 'manny-wenas-redirect-manager'));
        }

        $is_regex = isset($data['matching']) && 'regex' === $data['matching'];
        $source = $is_regex ? trim((string) $data['source']) : self::relative_path($data['source']);
        $target = trim($data['target']);
        $type = (int) $data['type'];

        // A bare relative target would otherwise be turned into "http://target" by esc_url_raw().
        if ('' !== $target && !preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $target)) {
            $target = '/' . $target;
        }

        $target = esc_url_raw($target);

        if ('' === $source || ('/' === $source && !$is_regex) || '' === $target) {
            return new WP_Error('mwrm_invalid', __('Source and target are required.', 'manny-wenas-redirect-manager'));
        }

        if ($is_regex && !self::valid_regex($source)) {
            return new WP_Error('mwrm_regex', __('Use an anchored path pattern such as ^/old/([0-9]+)$; it must be valid and must not match the home page.', 'manny-wenas-redirect-manager'));
        }

        // "/*" would redirect the whole site.
        if (!$is_regex && '/*' === $source) {
            return new WP_Error('mwrm_root', __('A wildcard on the site root is not allowed.', 'manny-wenas-redirect-manager'));
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM %i WHERE source = %s AND id <> %d LIMIT 1', self::table(), $source, (int) $id)
        );

        if ($existing) {
            return new WP_Error('mwrm_duplicate', __('A redirect for this source already exists.', 'manny-wenas-redirect-manager'));
        }

        if (!in_array($type, array(301, 302), true)) {
            $type = 301;
        }

        if (!$is_regex && $source === self::normalize_path($target) && '/*' !== substr($source, -2)) {
            return new WP_Error('mwrm_loop', __('Source and target are the same.', 'manny-wenas-redirect-manager'));
        }

        if (!$is_regex && self::is_inert($source, $target)) {
            return new WP_Error('mwrm_self', __('This wildcard would send every matching URL back into its own source path, so it would never apply.', 'manny-wenas-redirect-manager'));
        }

        $row = array(
            'source' => $source,
            'target' => $target,
            'type' => $type,
            'is_wildcard' => !$is_regex && '/*' === substr($source, -2) ? 1 : 0,
            'is_regex' => $is_regex ? 1 : 0,
        );

        if ($id) {
            $result = $wpdb->update(self::table(), $row, array('id' => (int) $id));

            if (false === $result) {
                return new WP_Error('mwrm_db', __('Could not update the redirect.', 'manny-wenas-redirect-manager') . ' ' . $wpdb->last_error);
            }

            delete_transient('mwrm_regex_rules');
            return (int) $id;
        }

        $row['created_at'] = current_time('mysql');
        $result = $wpdb->insert(self::table(), $row);

        if (false === $result) {
            return new WP_Error('mwrm_db', __('Could not save the redirect.', 'manny-wenas-redirect-manager') . ' ' . $wpdb->last_error);
        }

        delete_transient('mwrm_regex_rules');
        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id Redirect ID.
     * @return void
     */
    public static function delete($id)
    {
        global $wpdb;

        if (!MWRM_Installer::is_healthy('redirects')) {
            return;
        }

        $wpdb->delete(self::table(), array('id' => (int) $id));
        delete_transient('mwrm_regex_rules');
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
        if (!MWRM_Installer::is_healthy('redirects')) {
            return null;
        }

        $error = '';
        $rule = self::match_query($path, $original, $error);

        // Missing table or column: confirmed, repaired once (throttled), retried once.
        if (MWRM_Installer::check_error('redirects', $error)) {
            $rule = self::match_query($path, $original, $error);
        }

        return $rule;
    }

    /**
     * @param string      $path     Normalized path.
     * @param string|null $original Path with original case.
     * @param string      $error    Receives the error of the last query ('' when none).
     * @return object|null
     */
    private static function match_query($path, $original, &$error)
    {
        global $wpdb;

        $error = '';

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE is_wildcard = 0 AND is_regex = 0 AND source = %s LIMIT 1', self::table(), $path)
        );

        if ($row) {
            $row->resolved_target = $row->target;

            return $row;
        }

        $error = (string) $wpdb->last_error;

        if ('' !== $error) {
            return null;
        }

        $wildcards = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE is_wildcard = 1 AND is_regex = 0 ORDER BY CHAR_LENGTH(source) DESC', self::table())
        );

        $error = (string) $wpdb->last_error;

        foreach ($wildcards as $rule) {
            $prefix = substr($rule->source, 0, -1); // "/old/".

            if (0 === strpos($path . '/', $prefix) && !self::is_own_target($rule, $path)) {
                $rest = substr(($original ? $original : $path) . '/', strlen($prefix));
                $rule->resolved_target = str_replace('*', rtrim($rest, '/'), $rule->target);

                return $rule;
            }
        }

        if ('' !== $error) {
            return null;
        }

        // Regex is deliberately last: existing exact and wildcard rules keep priority.
        $rules = get_transient('mwrm_regex_rules');
        if (false === $rules) {
            $rules = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM %i WHERE is_regex = 1 ORDER BY id ASC', self::table())
            );
            $error = (string) $wpdb->last_error;
            if ('' !== $error) {
                return null;
            }
            set_transient('mwrm_regex_rules', $rules, 5 * MINUTE_IN_SECONDS);
        }

        $request = null !== $original ? $original : $path;
        foreach ($rules as $rule) {
            $pattern = self::regex_pattern($rule->source);
            if (null === $pattern || 1 !== @preg_match($pattern, $request, $matches)) {
                continue;
            }

            $resolved = self::regex_target($rule->target, $matches);
            if (null === $resolved) {
                continue;
            }

            // Never redirect to the same URL or into the same regex rule again.
            if (self::is_internal($resolved)) {
                $destination = self::relative_path($resolved, false);
                if ($destination === $request || 1 === @preg_match($pattern, $destination)) {
                    continue;
                }
            }

            $rule->resolved_target = $resolved;
            return $rule;
        }

        return null;
    }

    /**
     * Regexes match the decoded path relative to the site's home URL, without
     * a trailing slash. The source must be anchored so it cannot catch a whole
     * site by accident. A per-match PCRE limit prevents costly expressions.
     *
     * @param string $source Pattern without delimiters.
     * @return string|null PCRE pattern, or null when invalid.
     */
    private static function regex_pattern($source)
    {
        if (strlen($source) > 255 || 0 !== strpos($source, '^/') || '$' !== substr($source, -1)
            || false !== strpos($source, '#') || preg_match('/[\x00\r\n]/', $source)) {
            return null;
        }

        $pattern = '#(*LIMIT_MATCH=100000)(*LIMIT_RECURSION=10000)' . $source . '#i';
        return false === @preg_match($pattern, '') ? null : $pattern;
    }

    /**
     * @param string $source Pattern without delimiters.
     * @return bool
     */
    public static function valid_regex($source)
    {
        $pattern = self::regex_pattern($source);
        if (null === $pattern) {
            return false;
        }
        return 0 === @preg_match($pattern, '/');
    }

    /**
     * Replace $1, $2, etc. with captures without interpreting replacement code.
     * An absent capture means this rule does not apply to this request.
     *
     * @param string $target  Destination template.
     * @param array  $matches PCRE captures.
     * @return string|null
     */
    private static function regex_target($target, array $matches)
    {
        $missing = false;
        $resolved = preg_replace_callback('/\$(\d{1,2})/', function ($token) use ($matches, &$missing) {
            $number = (int) $token[1];
            if (!isset($matches[$number])) {
                $missing = true;
                return '';
            }
            return $matches[$number];
        }, $target);
        return $missing ? null : $resolved;
    }

    /**
     * Is the path one this wildcard rule redirects to itself?
     *
     * When the target lies inside the rule's own source (for example /blog/* to
     * /blog/archive/*), every redirected URL matches the rule again and grows without
     * end. The rule is therefore not applied to the URLs it points to: it redirects
     * once, and its own target area is left alone.
     *
     * @param object $rule Wildcard rule (source ends in "/*").
     * @param string $path Normalized (lowercase) request path.
     * @return bool
     */
    private static function is_own_target($rule, $path)
    {
        $target = (string) $rule->target;

        if (!self::is_internal($target)) {
            return false;
        }

        // Fixed target: only that URL itself.
        if (false === strpos($target, '*')) {
            return self::relative_path($target) === $path;
        }

        $area = self::target_area($target);
        $is_folder = '/' === substr($area, -1);
        $prefix = substr($rule->source, 0, -1); // "/blog/".

        // Only a target inside the rule's own source can feed back into the rule.
        if (0 !== strpos($is_folder ? $area : $area . '/', $prefix)) {
            return false;
        }

        if ($is_folder) {
            return 0 === strpos($path . '/', $area);
        }

        return 0 === strpos($path, $area) && '/' !== substr($path, strlen($area), 1);
    }

    /**
     * Target path before the first "*", relative to the site root, keeping a trailing slash.
     *
     * @param string $target Wildcard target containing "*".
     * @return string For example "/blog/archive/" or "/blog/new-".
     */
    private static function target_area($target)
    {
        $static = substr($target, 0, strpos($target, '*'));
        $area = self::relative_path($static);

        if ('/' !== $area && '/' === substr((string) wp_parse_url($static, PHP_URL_PATH), -1)) {
            $area .= '/';
        }

        return $area;
    }

    /**
     * Would this wildcard rule never apply, because every URL it matches is its own target?
     *
     * True when the target's folder is the source folder itself, for example /blog/* to
     * /blog/* or /blog/* to /blog/*.html.
     *
     * @param string $source Normalized source.
     * @param string $target Target.
     * @return bool
     */
    public static function is_inert($source, $target)
    {
        $target = (string) $target;

        if ('/*' !== substr($source, -2) || false === strpos($target, '*') || !self::is_internal($target)) {
            return false;
        }

        return self::target_area($target) === substr($source, 0, -1);
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
     * @return array {hops:int, loop:bool, limit:bool, inert:bool, final:string}
     *               hops  = further redirects after the first;
     *               loop  = a URL repeats, so the redirect never ends (proven);
     *               limit = still redirecting after the hop limit: possibly a loop, not proven;
     *               inert = a wildcard rule that never applies (see is_inert()).
     */
    public static function trace($source, $target)
    {
        if (self::is_inert(self::relative_path($source), $target)) {
            return array('hops' => 0, 'loop' => false, 'limit' => false, 'inert' => true, 'final' => $target);
        }

        $seen = array(self::relative_path($source));
        $current = $target;
        $hops = 0;
        $loop = false;
        $limit = false;

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

        // Still redirecting at the limit without a repeated URL: possibly a loop, not proven.
        if (!$loop && $hops >= 10 && self::is_internal($current) && self::match(self::relative_path($current))) {
            $limit = true;
        }

        return array('hops' => $hops, 'loop' => $loop, 'limit' => $limit, 'inert' => false, 'final' => $current);
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

        if (!MWRM_Installer::is_healthy('redirects')) {
            return array();
        }

        $source = self::relative_path($source);

        if ('/*' === substr($source, -2)) {
            return array();
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, source, target FROM %i WHERE target LIKE %s',
            self::table(),
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

        if (!MWRM_Installer::is_healthy('redirects')) {
            return;
        }

        $wpdb->query($wpdb->prepare('UPDATE %i SET hits = hits + 1 WHERE id = %d', self::table(), $id));

        if (MWRM_Installer::check_error('redirects', $wpdb->last_error)) {
            $wpdb->query($wpdb->prepare('UPDATE %i SET hits = hits + 1 WHERE id = %d', self::table(), $id));
        }
    }
}
