<?php
/**
 * CSV import for Rank Math, Yoast Premium, AIOSEO and SEOPress exports.
 *
 * Columns are matched by header name (case-insensitive) so one parser serves
 * all four plugins. Without a recognisable header the first three columns are
 * read as source, target, type. Only exact and trailing-wildcard rules are
 * supported; regex, contains/ends-with and query-string rules are skipped.
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWRM_Importer
{
    const MAX_BYTES = 2097152;
    const MAX_REPORTED = 25;

    /**
     * Header aliases per field.
     *
     * @return array
     */
    private static function aliases()
    {
        return array(
            'source' => array('source', 'source_url', 'origin', 'old_url', 'url_redirected', 'from', 'url', 'redirect_from'),
            'target' => array('destination', 'target', 'target_url', 'new_url', 'url_target', 'to', 'redirect_to', 'url_destination'),
            'type' => array('type', 'status_code', 'redirect_type', 'code', 'http_code'),
            'matching' => array('matching', 'match', 'comparison'),
            'regex' => array('regex', 'is_regex', 'format'),
            'active' => array('enabled', 'status', 'active'),
        );
    }

    /**
     * Import a CSV file.
     *
     * @param string $path Readable CSV path.
     * @return array|WP_Error {imported:int, skipped:array of {line, source, reason}, skipped_total:int}
     */
    public static function import_file($path)
    {
        $handle = fopen($path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions

        if (!$handle) {
            return new WP_Error('mwrm_import', __('Could not read the file.', 'manny-wenas-redirect-manager'));
        }

        $first = fgets($handle);
        rewind($handle);

        if (false === $first) {
            fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions

            return new WP_Error('mwrm_import', __('The file is empty.', 'manny-wenas-redirect-manager'));
        }

        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = self::detect_delimiter($first);

        $map = null;
        $line = 0;
        $result = array('imported' => 0, 'skipped' => array(), 'skipped_total' => 0);

        while (false !== ($cells = fgetcsv($handle, 0, $delimiter))) {
            $line++;

            if (1 === $line) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $map = self::map_header($cells);

                if ($map['header']) {
                    continue;
                }
            }

            if (array(null) === $cells) {
                continue;
            }

            $reason = self::import_row(self::read_row($cells, $map['columns']));

            if (true === $reason) {
                $result['imported']++;
                continue;
            }

            $result['skipped_total']++;
            if (count($result['skipped']) < self::MAX_REPORTED) {
                $result['skipped'][] = array(
                    'line' => $line,
                    'source' => isset($cells[$map['columns']['source']]) ? (string) $cells[$map['columns']['source']] : '',
                    'reason' => $reason,
                );
            }
        }

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions

        return $result;
    }

    /**
     * @param string $line First line of the file.
     * @return string
     */
    private static function detect_delimiter($line)
    {
        $best = ',';
        $max = 0;

        foreach (array(',', ';', "\t") as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $max) {
                $max = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    /**
     * Map header cells to column indexes.
     *
     * @param array $cells First row.
     * @return array {header:bool, columns:array field => index}
     */
    private static function map_header(array $cells)
    {
        $columns = array();

        foreach ($cells as $index => $cell) {
            $name = str_replace(array(' ', '-'), '_', strtolower(trim((string) $cell)));

            foreach (self::aliases() as $field => $names) {
                if (!isset($columns[$field]) && in_array($name, $names, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        if (isset($columns['source'], $columns['target'])) {
            return array('header' => true, 'columns' => $columns);
        }

        return array('header' => false, 'columns' => array('source' => 0, 'target' => 1, 'type' => 2));
    }

    /**
     * @param array $cells   Row cells.
     * @param array $columns Field => index.
     * @return array
     */
    private static function read_row(array $cells, array $columns)
    {
        $row = array();

        foreach (array('source', 'target', 'type', 'matching', 'regex', 'active') as $field) {
            $row[$field] = isset($columns[$field], $cells[$columns[$field]]) ? trim((string) $cells[$columns[$field]]) : '';
        }

        return $row;
    }

    /**
     * Validate and store one row.
     *
     * @param array $row Row fields.
     * @return true|string True when imported, otherwise the skip reason.
     */
    private static function import_row(array $row)
    {
        $source = $row['source'];
        $target = $row['target'];

        if ('' === $source || '' === $target) {
            return __('Missing source or target', 'manny-wenas-redirect-manager');
        }

        if (in_array(strtolower($row['active']), array('0', 'no', 'false', 'inactive', 'disabled', 'off'), true)) {
            return __('Inactive rule', 'manny-wenas-redirect-manager');
        }

        if (in_array(strtolower($row['regex']), array('1', 'true', 'yes', 'on', 'regex'), true)
            || 'regex' === strtolower($row['matching'])
            || preg_match('/[\^$()\[\]\\\\|+]/', $source)
        ) {
            return __('Regex rules are not supported', 'manny-wenas-redirect-manager');
        }

        if (false !== strpos($source, '?')) {
            return __('Query strings are not supported', 'manny-wenas-redirect-manager');
        }

        $matching = strtolower($row['matching']);
        if ('start' === $matching || 'starts_with' === $matching) {
            $source = rtrim($source, '*/') . '/*';
        } elseif ('' !== $matching && 'exact' !== $matching) {
            return __('Only exact and "starts with" matching is supported', 'manny-wenas-redirect-manager');
        }

        if (false !== strpos(rtrim($source, '*'), '*') || (false !== strpos($source, '*') && '/*' !== substr($source, -2))) {
            return __('Wildcards are only supported at the end of the source', 'manny-wenas-redirect-manager');
        }

        $type = '' === $row['type'] ? 301 : (int) $row['type'];
        $type = self::map_type($type);

        if (!$type) {
            return __('Unsupported redirect type', 'manny-wenas-redirect-manager');
        }

        if (!preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $target)) {
            $target = '/' . $target;
        }

        if (MWRM_Repository::source_exists($source)) {
            return __('Source already exists', 'manny-wenas-redirect-manager');
        }

        $saved = MWRM_Repository::save(array('source' => $source, 'target' => $target, 'type' => $type));

        return is_wp_error($saved) ? $saved->get_error_message() : true;
    }

    /**
     * @param int $code HTTP status from the export.
     * @return int 301, 302 or 0 when unsupported.
     */
    private static function map_type($code)
    {
        $map = array(301 => 301, 308 => 301, 302 => 302, 303 => 302, 307 => 302);

        return isset($map[$code]) ? $map[$code] : 0;
    }
}
