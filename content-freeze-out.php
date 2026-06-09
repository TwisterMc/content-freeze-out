<?php
/*
Plugin Name: Content Freeze Out
Plugin URI: https://www.twistermc.com/
Description: Enable a content freeze, locking all users out of the site.
Version: 0.2.0
Requires at least: 3.9.2
Requires PHP: 7.4
Tested up to: 7.0
Author: TwisterMc & Wholegrain Digital
Author URI: https://www.twistermc.com/
Network: TRUE
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: content_freeze_out
Domain Path: /lang
*/

if (!defined('ABSPATH')) {
    exit;
}

define('CONTENT_FREEZE_OUT_DIR', plugin_dir_path(__FILE__));

if (!class_exists("Content_Freeze_Out")) :

class Content_Freeze_Out {
    public $settings;

    function __construct() {

        // Load example settings page
        if (!class_exists("Content_Freeze_Out_Settings")) {
            require(CONTENT_FREEZE_OUT_DIR . 'settings.php');
        }

        $this->settings = new Content_Freeze_Out_Settings();

        add_action('init', array($this,'init'));

        register_activation_hook(__FILE__, array($this,'activate'));
    }

    /*
        Propagates pfunction to all blogs within our multisite setup.
        More details -
        http://shibashake.com/wordpress-theme/write-a-plugin-for-wordpress-multi-site

        If not multisite, then we just run pfunction for our single blog.
    */
    function network_propagate($pfunction, $networkwide) {
        global $wpdb;

        if (function_exists('is_multisite') && is_multisite()) {
            // check if it is a network activation - if so, run the activation function
            // for each blog id
            if ($networkwide) {
                // Get all blog ids
                $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
                foreach ($blogids as $blog_id) {
                    switch_to_blog($blog_id);
                    call_user_func($pfunction, $networkwide);
                }
                restore_current_blog();
                return;
            }
        }
        call_user_func($pfunction, $networkwide);
    }

    function activate($networkwide) {
        $this->network_propagate(array($this, '_activate'), $networkwide);
    }

    /*
        Plugin activation code here.
    */
    function _activate() {
        add_option($this->settings->settings_field, Content_Freeze_Out_Settings::$default_settings);
    }

    /*
        Load language translation files (if any) for our plugin.
    */
    function init() {
        load_plugin_textdomain('content_freeze_out', false, basename(dirname(__FILE__)) . '/lang');
    }


} // end class
endif;

// Initialize our plugin object.
global $content_freeze_out;
if (class_exists("Content_Freeze_Out") && !$content_freeze_out) {
    $content_freeze_out = new Content_Freeze_Out();
}
