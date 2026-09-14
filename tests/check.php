<?php
declare(strict_types=1);

if (isset($argv[1]) && 0 === strpos($argv[1], '--uninstall=')) {
	define('WP_UNINSTALL_PLUGIN', true);
	$multi = '--uninstall=multi' === $argv[1];
	$log = array('cleared' => array(), 'deleted' => array(), 'switched' => array(), 'restored' => 0);
	function is_multisite() { global $multi; return $multi; }
	function get_sites($args) {
		if (!is_multisite()) { return array(); }
		return 0 === $args['offset'] ? range(1, 100) : (100 === $args['offset'] ? array(101) : array());
	}
	function switch_to_blog($id) { global $log; $log['switched'][] = $id; }
	function restore_current_blog() { global $log; ++$log['restored']; }
	function wp_clear_scheduled_hook($hook) { global $log; $log['cleared'][] = $hook; }
	function delete_option($option) { global $log; $log['deleted'][] = $option; }
	require dirname(__DIR__) . '/uninstall.php';
	echo json_encode($log);
	exit;
}

final class WP_Error {}
final class CheckDie extends Exception {}

if (!isset($argv[1]) || 0 !== strpos($argv[1], '--uninstall=')) {
$state = array();
function reset_state() {
	global $state;
	$state = array(
		'actions' => array(), 'shortcodes' => array(), 'activation' => array(), 'deactivation' => array(),
		'events' => array(), 'options' => array(), 'remote' => null, 'http_calls' => 0,
		'updated' => array(), 'fired' => array(), 'update_fails' => false, 'cleared' => array(),
	);
}
function add_action($hook, $callback) { global $state; $state['actions'][$hook] = $callback; }
function add_shortcode($tag, $callback) { global $state; $state['shortcodes'][$tag] = $callback; }
function register_activation_hook($file, $callback) { global $state; $state['activation'][$file] = $callback; }
function register_deactivation_hook($file, $callback) { global $state; $state['deactivation'][$file] = $callback; }
function wp_next_scheduled($hook) { global $state; return $state['events'][$hook] ?? false; }
function wp_schedule_event($time, $recurrence, $hook) { global $state; $state['events'][$hook] = $time; $state['scheduled'][] = array($time, $recurrence, $hook); return true; }
function wp_clear_scheduled_hook($hook) { global $state; unset($state['events'][$hook]); $state['cleared'][] = $hook; }
function wp_remote_get($url, $args) { global $state; ++$state['http_calls']; return $state['remote']; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return is_array($response) ? ($response['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { return is_array($response) ? ($response['body'] ?? '') : ''; }
function get_option($name, $default = false) { global $state; return $state['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { global $state; $state['updated'][] = array($name, $value, $autoload); if ($state['update_fails']) { return false; } $state['options'][$name] = $value; return true; }
function do_action($hook, ...$args) { global $state; $state['fired'][] = array($hook, $args); }
function shortcode_atts($pairs, $atts, $shortcode = '') { return array_merge($pairs, array_intersect_key($atts, $pairs)); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value, $domain) { return esc_html($value); }
function wp_die($message) { throw new CheckDie($message); }
}

function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function response($body, $code = 200) { return array('code' => $code, 'body' => $body); }
function release($tag, $draft = false, $prerelease = false) { return json_encode(array('tag_name' => $tag, 'draft' => $draft, 'prerelease' => $prerelease)); }
function uninstall_log($mode) {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --uninstall=' . $mode;
	exec($command, $lines, $status);
	expect(0 === $status, 'uninstall process exits cleanly');
	return json_decode(implode("\n", $lines), true);
}

reset_state();
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
require dirname(__DIR__) . '/awesomux-version-sync.php';

use function AwesomuxVersionSync\activate;
use function AwesomuxVersionSync\deactivate;
use function AwesomuxVersionSync\refresh;
use function AwesomuxVersionSync\schedule;
use function AwesomuxVersionSync\shortcode;
use const AwesomuxVersionSync\HOOK;
use const AwesomuxVersionSync\OPTION;

try {
	global $state;
	expect(is_callable($state['actions']['init'] ?? null), 'init hook is callable');
	expect(is_callable($state['actions'][HOOK] ?? null), 'refresh hook is callable');
	expect(is_callable($state['shortcodes']['awesomux_version'] ?? null), 'shortcode is callable');
	expect(is_callable(reset($state['activation'])) && is_callable(reset($state['deactivation'])), 'lifecycle hooks are callable');

	reset_state(); schedule(); schedule();
	expect(1 === count($state['scheduled']), 'schedule creates one daily event');
	expect('daily' === $state['scheduled'][0][1] && HOOK === $state['scheduled'][0][2], 'schedule uses the daily refresh hook');

	reset_state(); $state['remote'] = response(release('v1.2.3')); activate(false);
	expect('1.2.3' === $state['options'][OPTION], 'activation fetches and normalizes a stable release');
	expect(1 === count($state['scheduled']), 'activation schedules refresh');

	reset_state(); try { activate(true); expect(false, 'network activation must stop'); } catch (CheckDie $e) {}
	expect(0 === $state['http_calls'] && empty($state['scheduled']), 'network activation has no side effects');

	reset_state(); $state['options'][OPTION] = '1.2.3'; $state['remote'] = response(release('1.2.3'));
	expect(true === refresh() && empty($state['updated']) && empty($state['fired']), 'unchanged version causes no update action');

	foreach (array(
		'invalid JSON' => response('{'), 'network error' => new WP_Error(), 'non-200' => response(release('2.0.0'), 500),
		'draft' => response(release('2.0.0', true)), 'prerelease' => response(release('2.0.0', false, true)),
		'nonstring tag' => response(json_encode(array('tag_name' => 2, 'draft' => false, 'prerelease' => false))),
		'malicious tag' => response(release('<script>alert(1)</script>')),
	) as $name => $remote) {
		reset_state(); $state['options'][OPTION] = '1.2.3'; $state['remote'] = $remote;
		expect(false === refresh(), $name . ' is rejected');
		expect('1.2.3' === $state['options'][OPTION] && empty($state['fired']), $name . ' retains the old version');
	}

	reset_state(); $state['options'][OPTION] = '1.2.3'; $state['remote'] = response(release('2.0.0')); $state['update_fails'] = true;
	expect(false === refresh() && '1.2.3' === $state['options'][OPTION] && empty($state['fired']), 'failed update retains old version without action');

	reset_state(); $state['options'][OPTION] = '<b>bad</b>';
	expect('&lt;b&gt;bad&lt;/b&gt;' === shortcode(array('fallback' => '<i>fallback</i>')), 'shortcode escapes saved version');
	unset($state['options'][OPTION]);
	expect('&lt;i&gt;fallback&lt;/i&gt;' === shortcode(array('fallback' => '<i>fallback</i>')) && 0 === $state['http_calls'], 'shortcode escapes fallback without HTTP');

	reset_state(); $state['options'][OPTION] = '1.2.3'; $state['events'][HOOK] = 1; deactivate();
	expect('1.2.3' === $state['options'][OPTION] && !isset($state['events'][HOOK]), 'deactivate keeps version and clears event');

	$single = uninstall_log('single');
	expect(array(HOOK) === $single['cleared'] && array(OPTION) === $single['deleted'], 'single-site uninstall clears plugin state');
	$multi = uninstall_log('multi');
	expect(101 === count($multi['cleared']) && 101 === count($multi['deleted']) && 101 === $multi['restored'], 'multisite uninstall clears every site across batches');
	expect(1 === $multi['switched'][0] && 101 === $multi['switched'][100], 'multisite uninstall traverses both batches');

	echo "ok\n";
} catch (Throwable $error) {
	fwrite(STDERR, "FAIL: " . $error->getMessage() . "\n");
	exit(1);
}
