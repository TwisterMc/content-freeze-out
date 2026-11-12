=== Content Freeze Out ===
Contributors: twistermc, wholegraindigital, MatheusFD, adamtomat, tferry
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.0
Stable tag: 0.3.2
Tags: content freeze, content lock, code lock, maintenance, migration
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
The Content Freeze plugin allows an admin to 'lock' all content on the site to prevent the site being edited.

== Description ==

Need to stop your clients and users from editing a site while you do maintenance or a server migration?

The content freeze plugin solves the problem of telling the client "Please don't edit anything on the site today because your changes may be lost" and then finding out after the event that they spent the whole day making changes to the site. You want to keep your clients happy, and restricting access during maintenance and migrations helps avoid confusion and inconvenience for all involved.

The content freeze plugin allows an admin to 'lock' the site to prevent anything from being edited. Users can still visit the site, but no one can make changes behind the scenes without your permission.

It is very simple to use. When installed, an admin can switch the content freeze on. At that point, only the admin who activated the content freeze can log in and all other users (including other admins) are locked out temporarily. When they try to log in they will see a message telling them that the site is currently frozen and they therefore cannot log in at this time.
We plan to add some more features in the near future, including scheduling and also optimising it for use on multisite networks. Contributions welcome.

== Installation ==

The content freeze plugin is really plug and play. Just upload the plugin and activate.
1. Upload the contents of the package to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
To start/stop a content freeze, go to Settings > Content Freeze and click the Freeze Content / Unfreeze Content button.

== Configuration ==

=== Custom login message ===

When a freeze is active, a message is shown on the WordPress login screen. You can customize this in the Settings > Content Freeze.

=== wp-config.php constant ===

You can force-enable a content freeze by adding the following constant to your `wp-config.php`.

    define( 'CONTENT_FREEZE_OUT', true );

When the constant is active, the freeze cannot be lifted from the admin UI — you must remove or set the constant to `false` in `wp-config.php` to unfreeze the site. The settings page will note when the freeze is active via this constant.

== Frequently Asked Questions ==

= Does it work on multisite installations? =

Yes it does, but you have to activate the freeze on each individual site within your network. Once activated, you need to visit the wp-admin URL to access any individual sub-sites that are not locked (wp-login.php URLs will not work during the freeze).

= Does it guarantee that no changes can be made to my site during a content freeze? =

Not 100%. The only person that could make any changes to the site from inside the CMS is the person who is in control of the content freeze. It is generally safe to assume that this person will respect the content freeze and not make any changes. It is also possible for people to leave comments on your site, but we will soon add a comment lock to prevent this.


== Changelog ==

= 0.3.2 =

* Fix: preserve the plugin folder when installing updates from GitHub.

= 0.2.1 =

* Feature: admin bar notice (snowflake icon, red highlight) shown when a content freeze is active
* Feature: login form is hidden during a freeze; a "Site administrator? Click here" toggle reveals it for admins
* Feature: styled "ice box" notification on the login screen for the freeze message
* Feature: Spanish (es_ES) translations added
* Fix: corrected `allowed_id` logic so the permitted user is preserved when a freeze is enabled

= 0.2.0 =

* Feature: custom lock screen message configurable under Settings > Content Freeze
* Feature: `CONTENT_FREEZE_OUT` constant — define in `wp-config.php` to force-enable a freeze
* Security: replaced invalid `is_admin()` direct-file-access guard with standard `ABSPATH` check
* Security: added `exit` after `wp_redirect()` to prevent redirect bypass; gated logout to skip AJAX and REST requests
* Security: sanitize `allowed_id` as integer in settings sanitization callback
* Security: escaped `$location` with `esc_js()`/`esc_url()` in JS redirect helper
* Security: removed admin's real name from default login lock message
* Security: moved HTML out of translated string in admin notice; URL now escaped separately via `esc_url()`
* Fix: changed `'administrator'` role slug to `'manage_options'` capability in `add_options_page()`
* Fix: unified capability check to `manage_options` (was `update_plugins`)
* Fix: corrected `load_plugin_textdomain()` argument order (deprecated second arg set to `false`)
* Fix: replaced `switch_to_blog()` with `restore_current_blog()` after multisite loop
* Fix: moved default option setup from `admin_init` to activation hook to avoid per-request DB query
* Fix: added missing text domain to `esc_attr_e()` calls
* Improvement: removed unused `javascript_redirect()` and `print_example()` methods
* Improvement: updated PHP4-style `var` property declarations to `public`
* Plugin renamed to Content Freeze Out; author updated to TwisterMc & Wholegrain Digital

= 0.1.4 =

* Fixed: error when no options have been stored yet. Props: @adamtomat

= 0.1.3 =

* Enhancement: ability to freeze all the network

= 0.1.2 =

* Fixed: redirect issue when user gets logged out

= 0.1.1 =

* Fixed: Content Freeze didn't save setting when WP debug mode was active

= 0.1 =

* Initial version
