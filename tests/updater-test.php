<?php
define('ABSPATH', '/wordpress/');

class WP_Error {
    public $code;

    public function __construct($code, $message) {
        $this->code = $code;
    }
}

function is_wp_error($value) {
    return $value instanceof WP_Error;
}

function plugin_basename($file) {
    return basename(dirname($file)) . '/' . basename($file);
}

function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['filters'][$name] = array($callback, $accepted_args);
}

function trailingslashit($path) {
    return rtrim($path, '/\\') . '/';
}

function untrailingslashit($path) {
    return rtrim($path, '/\\');
}

function __($message, $domain) {
    return $message;
}

function get_transient($key) {
    return $GLOBALS['release'];
}

class Test_Filesystem {
    public $files = array();
    public $moves = array();
    public $fail_move = false;

    public function exists($path) {
        return isset($this->files[$path]);
    }

    public function move($source, $destination, $overwrite = false) {
        $this->moves[] = array($source, $destination, $overwrite);
        if ($this->fail_move) {
            return false;
        }
        foreach ($this->files as $path => $value) {
            if (strpos($path, trailingslashit($source)) === 0) {
                unset($this->files[$path]);
                $this->files[trailingslashit($destination) . substr($path, strlen(trailingslashit($source)))] = $value;
            }
        }
        return true;
    }
}

function expect($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/updater.php';

$updater = new Content_Freeze_Out_Updater('/plugins/content-freeze-out/content-freeze-out.php', 'TwisterMc/content-freeze-out');
$hook = array('plugin' => 'content-freeze-out/content-freeze-out.php');
$remote = '/wordpress/wp-content/upgrade/package/';
$wp_filesystem = new Test_Filesystem();

expect(isset($filters['upgrader_source_selection']) && $filters['upgrader_source_selection'][1] === 4, 'source selection filter is registered');
expect(!isset($filters['upgrader_post_install']), 'no post-install move is registered');

$source = $remote . 'content-freeze-out/';
$wp_filesystem->files[$source . 'content-freeze-out.php'] = true;
expect($updater->select_source($source, $remote, null, $hook) === $source, 'release archive keeps its existing directory');
expect(count($wp_filesystem->moves) === 0, 'correctly named directory is never moved onto itself');

$source = $remote . 'TwisterMc-content-freeze-out-1234567/';
$wp_filesystem->files = array($source . 'content-freeze-out.php' => true);
$selected = $updater->select_source($source, $remote, null, $hook);
expect($selected === $remote . 'content-freeze-out/', 'GitHub archive is renamed before installation');
expect($wp_filesystem->exists($selected . 'content-freeze-out.php'), 'renamed archive contains the plugin file');
expect($wp_filesystem->moves[0][2] === false, 'renaming never overwrites an existing folder');
expect(basename(untrailingslashit($selected)) . '/content-freeze-out.php' === $hook['plugin'], 'WordPress installs to the original plugin path');

$custom_updater = new Content_Freeze_Out_Updater('/plugins/custom-folder/content-freeze-out.php', 'TwisterMc/content-freeze-out');
$custom_hook = array('plugin' => 'custom-folder/content-freeze-out.php');
$wp_filesystem->files = array($source . 'content-freeze-out.php' => true);
$selected = $custom_updater->select_source($source, $remote, null, $custom_hook);
expect($selected === $remote . 'custom-folder/', 'existing custom plugin folder is preserved');

$wp_filesystem->moves = array();
expect($updater->select_source($source, $remote, null, array('plugin' => 'another/plugin.php')) === $source, 'other plugins are untouched');
expect(count($wp_filesystem->moves) === 0, 'other plugins are never moved');
$error = new WP_Error('previous_error', 'Previous error');
expect($updater->select_source($error, $remote, null, $hook) === $error, 'previous errors are preserved');

$wp_filesystem->files = array();
$result = $updater->select_source($source, $remote, null, $hook);
expect(is_wp_error($result) && $result->code === 'cfo_invalid_package', 'missing plugin file fails before installation');

$wp_filesystem->files[$source . 'content-freeze-out.php'] = true;
$wp_filesystem->fail_move = true;
$result = $updater->select_source($source, $remote, null, $hook);
expect(is_wp_error($result) && $result->code === 'cfo_rename_failed', 'failed rename fails the update');

$wp_filesystem->files = array($remote . 'content-freeze-out.php' => true);
$result = $updater->select_source($remote, $remote, null, $hook);
expect(is_wp_error($result) && $result->code === 'cfo_invalid_package', 'flat archive with the wrong folder name fails safely');

$release = (object) array(
    'tag_name' => 'v0.3.2',
    'assets' => array((object) array('name' => 'content-freeze-out.zip', 'browser_download_url' => 'https://example.test/release.zip')),
    'zipball_url' => 'https://example.test/source.zip',
);
$transient = (object) array('checked' => array($hook['plugin'] => '0.3.1'));
$updated = $updater->check_for_update($transient);
expect($updated->response[$hook['plugin']]->package === 'https://example.test/release.zip', 'release ZIP is selected for updates');
$release->assets = array();
$updated = $updater->check_for_update((object) array('checked' => array($hook['plugin'] => '0.3.1')));
expect($updated->response[$hook['plugin']]->package === 'https://example.test/source.zip', 'GitHub archive is used when no ZIP asset exists');

echo "Updater tests passed.\n";
