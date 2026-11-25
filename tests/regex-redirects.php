<?php
// Focused repository regression test. Run with: php tests/regex-redirects.php.
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$transients = array();

class WP_Error
{
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
class MWRM_Installer
{
    public static function is_healthy($part = 'redirects') { return true; }
    public static function check_error($part, $error) { return false; }
}
function __($text, $domain = '') { return $text; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function home_url($path = '') { return 'https://example.test/blog' . $path; }
function untrailingslashit($value) { return rtrim($value, '/'); }
function esc_url_raw($url) { return $url; }
function current_time($type) { return '2026-11-25 12:00:00'; }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
function delete_transient($key) { global $transients; unset($transients[$key]); }

class Test_DB
{
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 0;
    public $rows = array();
    public $regex_queries = 0;
    public function prepare($sql, ...$args) { return array($sql, $args); }
    public function get_var($query) {
        if (strpos($query[0], 'source = %s') !== false) {
            foreach ($this->rows as $row) {
                if ($row->source === $query[1][1] && $row->id !== $query[1][2]) {
                    return $row->id;
                }
            }
        }
        return null;
    }
    public function get_row($query) {
        foreach ($this->rows as $row) {
            if (!$row->is_regex && !$row->is_wildcard && $row->source === $query[1][1]) {
                return clone $row;
            }
        }
        return null;
    }
    public function get_results($query) {
        if (strpos($query[0], 'is_regex = 1') !== false) {
            ++$this->regex_queries;
            return array_values(array_filter($this->rows, function ($row) { return $row->is_regex; }));
        }
        $rows = array_values(array_filter($this->rows, function ($row) { return $row->is_wildcard; }));
        usort($rows, function ($a, $b) { return strlen($b->source) <=> strlen($a->source); });
        return $rows;
    }
    public function insert($table, $data) {
        $this->insert_id = count($this->rows) + 1;
        $data['id'] = $this->insert_id;
        $this->rows[] = (object) $data;
        return 1;
    }
    public function update($table, $data, $where) {
        foreach ($this->rows as $row) {
            if ($row->id === $where['id']) {
                foreach ($data as $key => $value) { $row->$key = $value; }
                return 1;
            }
        }
        return false;
    }
    public function delete($table, $where) {
        $this->rows = array_values(array_filter($this->rows, function ($row) use ($where) { return $row->id !== $where['id']; }));
        return 1;
    }
}
$wpdb = new Test_DB();
require dirname(__DIR__) . '/includes/class-mwrm-repository.php';

function check($condition, $label) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "PASS: $label\n";
}
function find_rule($path) {
    return MWRM_Repository::match(
        MWRM_Repository::relative_path($path),
        MWRM_Repository::relative_path($path, false)
    );
}

check(MWRM_Repository::valid_regex('^/old/([0-9]+)$'), 'anchored regex accepted');
foreach (array('/old/(.*)', '^/.*$', '^/old/($', '^/old/#$', '^/old/[a-z]+') as $bad) {
    check(!MWRM_Repository::valid_regex($bad), "unsafe or invalid regex rejected: $bad");
}
$id = MWRM_Repository::save(array('source' => '^/old/([0-9]+)$', 'target' => '/new/$1', 'type' => 301, 'matching' => 'regex'));
check(is_int($id), 'regex rule stored');
check($wpdb->rows[0]->is_regex === 1 && $wpdb->rows[0]->is_wildcard === 0, 'regex stored separately from wildcard');
check(find_rule('/blog/old/123')->resolved_target === '/new/123', 'subdirectory root stripped and capture substituted');
check(find_rule('/blog/old/abc') === null, 'nonmatching path unchanged');
check($wpdb->regex_queries === 1, 'regex rules cached across requests');

$wild = MWRM_Repository::save(array('source' => '/old/*', 'target' => '/wild/*', 'type' => 301));
check(find_rule('/blog/old/123')->resolved_target === '/wild/123', 'existing wildcard wins over regex');
$exact = MWRM_Repository::save(array('source' => '/old/123', 'target' => '/exact', 'type' => 301));
check(find_rule('/blog/old/123')->resolved_target === '/exact', 'exact wins over wildcard and regex');
MWRM_Repository::delete($exact);
MWRM_Repository::delete($wild);

$own = MWRM_Repository::save(array('source' => '^/loop/(.*)$', 'target' => '/loop/new/$1', 'type' => 301, 'matching' => 'regex'));
check(is_int($own), 'self-matching target can be stored but is guarded at runtime');
check(find_rule('/blog/loop/a') === null, 'self-matching regex target does not redirect');
$missing = MWRM_Repository::save(array('source' => '^/missing/(.*)$', 'target' => '/new/$2', 'type' => 301, 'matching' => 'regex'));
check(find_rule('/blog/missing/a') === null, 'missing capture does not make a broken URL');
$invalid = MWRM_Repository::save(array('source' => '^/.*$', 'target' => '/new', 'type' => 301, 'matching' => 'regex'));
check($invalid instanceof WP_Error && $invalid->get_error_code() === 'mwrm_regex', 'home-page-catching regex cannot be saved');
