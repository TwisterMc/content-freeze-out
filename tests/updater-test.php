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

    public function dirlist($path, $include_hidden = true, $recursive = false) {
        $dirlist = array();
        $prefix = trailingslashit($path);
        foreach ($this->files as $file_path => $value) {
            if (strpos($file_path, $prefix) === 0) {
                $relative = substr($file_path, strlen($prefix));
                $parts = explode('/', trim($relative, '/'));
                if (!empty($parts[0]) && !isset($dirlist[$parts[0]])) {
                    // Only mark as directory if there are more path components
                    $is_dir = count($parts) > 1;
                    $dirlist[$parts[0]] = array(
                        'name' => $parts[0],
                        'type' => $is_dir ? 'd' : 'f'
                    );
                }
            }
        }
        return $dirlist;
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
$remote = '/wordpress/wp-content/upgrade/content-freeze-out/';
$wp_filesystem = new Test_Filesystem();

expect(isset($filters['upgrader_source_selection']) && $filters['upgrader_source_selection'][1] === 4, 'source selection filter is registered');
expect(!isset($filters['upgrader_post_install']), 'no post-install move is registered');

// Standard case: files at root level (new workflow)
$wp_filesystem->files = array($remote . 'content-freeze-out.php' => true);
$result = $updater->select_source($remote, $remote, null, $hook);
expect($result === $remote, 'standard package with files at root is accepted');

// Legacy case: files nested in subfolder (old workflow with prefix)
$wp_filesystem->files = array($remote . 'content-freeze-out/content-freeze-out.php' => true);
$result = $updater->select_source($remote, $remote, null, $hook);
expect($result === $remote . 'content-freeze-out/', 'nested folder structure is handled');
expect($wp_filesystem->exists($result . 'content-freeze-out.php'), 'nested archive contains the plugin file');

$custom_updater = new Content_Freeze_Out_Updater('/plugins/custom-folder/content-freeze-out.php', 'TwisterMc/content-freeze-out');
$custom_hook = array('plugin' => 'custom-folder/content-freeze-out.php');
$wp_filesystem->files = array($remote . 'content-freeze-out.php' => true);
$result = $custom_updater->select_source($remote, $remote, null, $custom_hook);
expect($result === $remote, 'different plugin slugs work correctly');

$wp_filesystem->files = array($remote . 'content-freeze-out.php' => true);
expect($updater->select_source($remote, $remote, null, array('plugin' => 'another/plugin.php')) === $remote, 'other plugins are untouched');
$error = new WP_Error('previous_error', 'Previous error');
expect($updater->select_source($error, $remote, null, $hook) === $error, 'previous errors are preserved');

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
