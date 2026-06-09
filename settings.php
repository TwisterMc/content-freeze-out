<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("Content_Freeze_Out_Settings")) :

class Content_Freeze_Out_Settings {

	public static $default_settings = array(
		'allowed_id' => 0,
		'content_frozen' => 0,
		'lock_message' => '',
	);

	public $pagehook, $page_id, $settings_field, $options;

	function __construct() {
		$this->page_id = 'content_freeze_out';
		// This is the get_options slug used in the database to store our plugin option values.
		$this->settings_field = 'content_freeze_out_options';
		$this->options = get_option($this->settings_field);

		if ($this->options === false) {
			$this->options = [];
		}

		if (is_admin()) {
			add_action('admin_init', array($this,'admin_init'), 20);
			add_action('admin_menu', array($this, 'admin_menu'), 20);
		}

		add_action('init', array($this,'init'), 20);
	}

	protected function is_frozen() {
		if (defined('CONTENT_FREEZE_OUT') && CONTENT_FREEZE_OUT) {
			return true;
		}
		return (bool) $this->get_field_value('content_frozen');
	}

	function init() {
		if ($this->is_frozen()) {
			add_filter('login_message', array($this, 'login_warning_message'));
			add_action('login_enqueue_scripts', array($this, 'login_hide_form_styles'));
			add_action('login_footer', array($this, 'login_toggle_script'));
		}
	}

	function admin_init() {
		register_setting($this->settings_field, $this->settings_field, array($this, 'sanitize_theme_options'));

		if ($this->is_frozen() && isset($_GET['page']) && $_GET['page'] != 'content_freeze_out') {
			add_action('admin_notices', array($this, 'content_frozen_notice'));
		}

		if ($this->is_frozen() && $this->get_field_value('allowed_id') != get_current_user_id()) {
			if ( ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
				wp_logout();
				wp_redirect( wp_login_url() );
				exit;
			}
		}
	}

	function login_warning_message($message) {
		$custom_message = $this->get_field_value('lock_message');
		if (empty($custom_message)) {
			$custom_message = __( "Content is currently frozen. You can't log in now.", 'content_freeze_out' );
		}
		return '<div id="login_error" style="text-align:center; font-size:1.2em; font-weight:bold; padding: 16px;">'
			. esc_html($custom_message)
			. '</div>'
			. '<p style="text-align:center; margin-top:12px;"><a href="#" id="cfo-show-login" style="font-size:0.85em; color:#72777c;">'
			. esc_html__( 'Site administrator? Click here.', 'content_freeze_out' )
			. '</a></p>';
	}

	function login_hide_form_styles() {
		wp_add_inline_style( 'login', '#loginform, #nav { display: none; }' );
	}

	function login_toggle_script() {
		?>
		<script>
		(function() {
			var link = document.getElementById('cfo-show-login');
			if (!link) return;
			link.addEventListener('click', function(e) {
				e.preventDefault();
				var form = document.getElementById('loginform');
				var nav  = document.getElementById('nav');
				if (form) {
					form.style.display = 'block';
					// Re-enable any inputs that WordPress disabled while the form was hidden.
					var inputs = form.querySelectorAll('input, button');
					for (var i = 0; i < inputs.length; i++) {
						inputs[i].removeAttribute('disabled');
					}
				}
				if (nav) nav.style.display = 'block';
				this.parentNode.style.display = 'none';
			});
		})();
		</script>
		<?php
	}

	function content_frozen_notice() {
		$settings_url = esc_url( admin_url( 'options-general.php?page=content_freeze_out' ) );
		?>
	    <div class="update-nag" style="display: block;">
	        <p style="margin: 0;">
	        	<?php
	        	printf(
	        		/* translators: %s: URL to the Content Freeze Out settings page */
	        		__( '<strong>Warning!</strong> Content is currently frozen for other admins. <a href="%s">Click here to change the settings</a>.', 'content_freeze_out' ),
	        		$settings_url
	        	);
	        	?>
	        </p>
	    </div>
	    <?php
	}

	function admin_menu() {
		if (! current_user_can('manage_options'))
			return;

		// Add a new submenu to the standard Settings panel
		$this->pagehook = $page = add_options_page(__('Content Freeze Out', 'content_freeze_out'), __('Content Freeze Out', 'content_freeze_out'), 'manage_options', $this->page_id, array($this,'render'));

		// Executed on-load. Add all metaboxes.
		add_action('load-' . $this->pagehook, array($this, 'metaboxes'));

		// Include js, css, or header *only* for our settings page
		add_action("admin_print_scripts-$page", array($this, 'js_includes'));

		// add_action("admin_print_styles-$page", array($this, 'css_includes'));
		add_action("admin_head-$page", array($this, 'admin_head'));
	}

	function admin_head() {
?>
		<style>
			.settings_page_content_freeze_out label { display:inline-block; width: 150px; }
		</style>
<?php
	}

	function js_includes() {
		// Needed to allow metabox layout and close functionality.
		wp_enqueue_script('postbox');
	}

	/*
		Sanitize our plugin settings array as needed.
	*/
	function sanitize_theme_options($options) {
		if (isset($options['allowed_id'])) {
			$options['allowed_id'] = absint($options['allowed_id']);
		}
		if (isset($options['content_frozen'])) {
			$options['content_frozen'] = absint($options['content_frozen']);
		}
		if (isset($options['lock_message'])) {
			$options['lock_message'] = sanitize_text_field(wp_unslash($options['lock_message']));
		}
		return $options;
	}

	/*
		Settings access functions.
	*/
	protected function get_field_name($name) {

		return sprintf('%s[%s]', $this->settings_field, $name);
	}

	protected function get_field_id($id) {

		return sprintf('%s[%s]', $this->settings_field, $id);
	}

	protected function get_field_value($key) {
		if (!array_key_exists($key, $this->options)) {
			return null;
		}

		return $this->options[$key];
	}

	/*
		Render settings page.
	*/
	function render() {
		global $wp_meta_boxes;

		$title = __('Content Freeze Out', 'content_freeze_out');
		?>
		<div class="wrap">
			<h2><?php echo esc_html($title); ?></h2>

			<form method="post" action="options.php">
				<input type="hidden" name="<?php echo $this->get_field_name('allowed_id'); ?>" id="<?php echo $this->get_field_id('allowed_id'); ?>" value="<?php echo get_current_user_id() ?>" />
                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 99%;">
                    <?php
						// Render metaboxes
                        settings_fields($this->settings_field);
                        do_meta_boxes($this->pagehook, 'main', null);
                      	if (isset($wp_meta_boxes[$this->pagehook]['column2']))
 							do_meta_boxes($this->pagehook, 'column2', null);
                    ?>
                    </div>
                </div>
			</form>
		</div>

        <!-- Needed to allow metabox layout and close functionality. -->
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready(function ($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
			});
			//]]>
		</script>
	<?php }


	function metaboxes() {

		add_meta_box('content-freeze-out-settings-box', __('Settings', 'content_freeze_out'), array($this, 'settings_box'), $this->pagehook, 'main', 'high');
	}

	function settings_box() {
		$config_override = defined('CONTENT_FREEZE_OUT') && CONTENT_FREEZE_OUT;
		if ($this->is_frozen()) {
?>
			<input type="hidden" name="<?php echo $this->get_field_name('content_frozen'); ?>" id="<?php echo $this->get_field_id('content_frozen'); ?>" value="0" />
			<p>You have <b>frozen</b> the content for other admins<?php if ($config_override): ?> <em>(active via <code>CONTENT_FREEZE_OUT</code> constant in wp-config)</em><?php endif; ?></p>
			<p>
				<label for="<?php echo $this->get_field_id('lock_message'); ?>"><?php _e('Lock screen message:', 'content_freeze_out'); ?></label><br />
				<input type="text" name="<?php echo $this->get_field_name('lock_message'); ?>" id="<?php echo $this->get_field_id('lock_message'); ?>" value="<?php echo esc_attr($this->get_field_value('lock_message')); ?>" class="regular-text" placeholder="<?php esc_attr_e('Leave blank for default message', 'content_freeze_out'); ?>" />
			</p>
			<?php if (!$config_override): ?>
			<p>
				<input type="submit" class="button button-primary" name="save_options" value="<?php esc_attr_e('Unfreeze Content', 'content_freeze_out'); ?>" />
			</p>
			<?php else: ?>
			<p><em><?php _e('To unfreeze, remove the <code>CONTENT_FREEZE_OUT</code> constant from wp-config.php.', 'content_freeze_out'); ?></em></p>
			<?php endif; ?>
<?php
		} else {
?>
			<input type="hidden" name="<?php echo $this->get_field_name('content_frozen'); ?>" id="<?php echo $this->get_field_id('content_frozen'); ?>" value="1" />
			<p>Content is currently <b>not frozen</b></p>
			<p>
				<label for="<?php echo $this->get_field_id('lock_message'); ?>"><?php _e('Lock screen message:', 'content_freeze_out'); ?></label><br />
				<input type="text" name="<?php echo $this->get_field_name('lock_message'); ?>" id="<?php echo $this->get_field_id('lock_message'); ?>" value="<?php echo esc_attr($this->get_field_value('lock_message')); ?>" class="regular-text" placeholder="<?php esc_attr_e('Leave blank for default message', 'content_freeze_out'); ?>" />
			</p>
			<p>
				<input type="submit" class="button button-primary" name="save_options" value="<?php esc_attr_e('Freeze Content', 'content_freeze_out'); ?>" />
			</p>
<?php
		}
	}

} // end class
endif;
