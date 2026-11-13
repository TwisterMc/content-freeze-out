<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Content_Freeze_Out_Updater')) :

class Content_Freeze_Out_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $github_repo;
    private $transient_key;

    public function __construct($plugin_file, $github_repo) {
        $this->plugin_file   = $plugin_file;
        $this->plugin_slug   = plugin_basename($plugin_file);
        $this->github_repo   = $github_repo;
        $this->transient_key = 'cfo_update_' . md5($github_repo);

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'select_source'), 10, 4);
    }

    private function get_release_info() {
        $cached = get_transient($this->transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(
            "https://api.github.com/repos/{$this->github_repo}/releases/latest",
            array(
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version'),
                ),
                'timeout' => 10,
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release->tag_name)) {
            return false;
        }

        set_transient($this->transient_key, $release, 12 * HOUR_IN_SECONDS);
        return $release;
    }

    private function get_zip_url($release) {
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (substr($asset->name, -4) === '.zip') {
                    return $asset->browser_download_url;
                }
            }
        }
        return $release->zipball_url ?? null;
    }

    public function check_for_update($transient) {
        if (empty($transient->checked[$this->plugin_slug])) {
            return $transient;
        }

        $release = $this->get_release_info();
        if (!$release) {
            return $transient;
        }

        $remote_version = ltrim($release->tag_name, 'v');
        $local_version  = $transient->checked[$this->plugin_slug];

        if (version_compare($remote_version, $local_version, '>')) {
            $zip_url = $this->get_zip_url($release);
            if ($zip_url) {
                $transient->response[$this->plugin_slug] = (object) array(
                    'slug'        => dirname($this->plugin_slug),
                    'plugin'      => $this->plugin_slug,
                    'new_version' => $remote_version,
                    'url'         => "https://github.com/{$this->github_repo}",
                    'package'     => $zip_url,
                );
            }
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_release_info();
        if (!$release) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);
        $zip_url     = $this->get_zip_url($release);

        return (object) array(
            'name'          => $plugin_data['Name'],
            'slug'          => dirname($this->plugin_slug),
            'version'       => ltrim($release->tag_name, 'v'),
            'author'        => $plugin_data['Author'],
            'homepage'      => "https://github.com/{$this->github_repo}",
            'download_link' => $zip_url,
            'sections'      => array(
                'description' => $plugin_data['Description'],
                'changelog'   => nl2br(esc_html($release->body ?? '')),
            ),
        );
    }

    public function select_source($source, $remote_source, $upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $source;
        }
        if (is_wp_error($source)) {
            return $source;
        }

        global $wp_filesystem;
        $plugin_dir = dirname($this->plugin_slug);
        $plugin_file = basename($this->plugin_slug);

        // The standard case: WordPress extracts to /wp-content/upgrade/content-freeze-out/
        // and the zip contains files at root level or in a content-freeze-out/ folder
        
        // Case 1: Files are directly in the extraction folder (expected with our new workflow)
        if ($wp_filesystem->exists(trailingslashit($source) . $plugin_file)) {
            return $source;
        }

        // Case 2: Files are in a subfolder matching the plugin dir (from old workflow with prefix)
        $expected_subfolder = trailingslashit($source) . $plugin_dir . '/';
        if ($wp_filesystem->exists($expected_subfolder . $plugin_file)) {
            return $expected_subfolder;
        }

        // For any other structure, let WordPress handle it or fail appropriately
        return $source;
    }

} // end class
endif;
