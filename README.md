# Content Freeze Out

Content Freeze Out is a WordPress plugin that lets an administrator temporarily freeze content changes during maintenance windows, launches, and migrations.

When a freeze is enabled, only the administrator who activated the freeze can access the admin area. Other users are logged out and blocked from logging in until the freeze is lifted. During a freeze:

- The login form is hidden and replaced with the freeze message; a "Site administrator? Click here" button reveals the form for admins.
- A snowflake notice appears in the admin bar (highlighted in red) as a persistent reminder.
- An admin notice is shown on all admin pages (except the settings page) while the freeze is active.

## Requirements

- WordPress 6+
- Tested up to WordPress 7.0
- PHP 7.4+

## Installation

1\. Copy this plugin folder into your WordPress installation under `wp-content/plugins/content-freeze-out`.
2\. Activate Content Freeze Out in Plugins.
3\. Go to Settings > Content Freeze.
4\. Click Freeze Content to start a freeze, or Unfreeze Content to end it.

## Custom Login Message

When a freeze is active, a message is displayed on the WordPress login screen to inform users they cannot log in. You can customize this message in the settings. If left blank, the default message is used: *"The site is currently in a content freeze."*

## wp-config.php Constant

You can force-enable a content freeze by adding this constant to `wp-config.php`:

```php
define( 'CONTENT_FREEZE_OUT', true );
```

When set, the freeze is active regardless of the admin UI setting and cannot be lifted from the settings page — remove or set the constant to `false` to unfreeze.

## Notes

- Multisite is supported, but not tested.
- During a freeze, the user who enabled it remains the only allowed admin user.

## Fork

I modified the [Content Freeze plugin](https://wordpress.org/plugins/content-freeze/) to add some new features and will see if we can merge code at some point.

## License

This project is licensed under the GNU General Public License v2.0 or later.
See [LICENSE](~LICENSE~).
