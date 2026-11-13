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

        // Prevent trying to move a folder onto itself
        if (untrailingslashit($source) === untrailingslashit($remote_source)) {
            return new WP_Error('cfo_invalid_package', __('The update package must contain the plugin in its own folder.', 'content_freeze_out'));
        }

        // Check if the plugin file exists at the root of the extracted source
        if ($wp_filesystem->exists(trailingslashit($source) . $plugin_file)) {
            // Plugin file is at root of extracted folder
            // If the folder is already named correctly, return it
            if (basename(untrailingslashit($source)) === $plugin_dir) {
                return $source;
            }

            // Need to rename the source folder to match plugin directory
            $renamed_source = trailingslashit($remote_source) . $plugin_dir;
            if (!$wp_filesystem->move($source, $renamed_source)) {
                return new WP_Error('cfo_rename_failed', __('Could not prepare the plugin update folder.', 'content_freeze_out'));
            }

            return trailingslashit($renamed_source);
        }

        // Check if plugin is in a subdirectory (nested structure from zip extraction)
        // This handles cases where git archive creates a folder inside the extracted archive
        $dir_list = $wp_filesystem->dirlist($source);
        if ($dir_list && is_array($dir_list)) {
            foreach ($dir_list as $item_name => $item_info) {
                // Only check directories, and skip if the directory is already the plugin dir
                if ($item_info['type'] === 'd' && $item_name !== $plugin_dir) {
                    $nested_plugin_file = trailingslashit($source) . $item_name . '/' . $plugin_file;
                    if ($wp_filesystem->exists($nested_plugin_file)) {
                        // Found plugin in subdirectory, move it to the correct location
                        $nested_source = trailingslashit($source) . $item_name;
                        $renamed_source = trailingslashit($remote_source) . $plugin_dir;
                        
                        if (!$wp_filesystem->move($nested_source, $renamed_source)) {
                            return new WP_Error('cfo_rename_failed', __('Could not prepare the plugin update folder.', 'content_freeze_out'));
                        }

                        return trailingslashit($renamed_source);
                    }
                }
            }
        }

        // Plugin file not found at expected location
        return new WP_Error('cfo_invalid_package', __('The update package does not contain the plugin file at the expected location.', 'content_freeze_out'));
    }

} // end class
endif;
