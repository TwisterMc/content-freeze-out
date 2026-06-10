<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("Content_Freeze_Out_Settings")) :

class Content_Freeze_Out_Settings {

	public $page_id, $settings_field, $options;
	public static $default_settings = array(
		'allowed_id'     => 0,
		'content_frozen' => 0,
		'lock_message'   => '',
	);

	function __construct() {
		$this->page_id = 'content_freeze_out';
		$this->settings_field = 'content_freeze_out_options';
		$this->options = get_option($this->settings_field);

		if ($this->options === false) {
			$this->options = self::$default_settings;
		} else {
			$this->options = wp_parse_args($this->options, self::$default_settings);
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

		if (! (bool) $this->get_field_value('content_frozen')) {
			return false;
		}

		return (int) $this->get_field_value('allowed_id') > 0;
	}

	function init() {
		if ($this->is_frozen()) {
			add_filter('login_message', array($this, 'login_warning_message'));
			add_action('login_enqueue_scripts', array($this, 'login_hide_form_styles'));
			add_action('login_footer', array($this, 'login_toggle_script'));
			add_action('admin_bar_menu', array($this, 'admin_bar_frozen_notice'), 100);
			add_action('wp_head', array($this, 'admin_bar_frozen_styles'));
			add_action('admin_head', array($this, 'admin_bar_frozen_styles'));
		}
	}

	function admin_bar_frozen_notice( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'content-freeze-out',
			'title' => '&#10052; ' . esc_html__( 'Content Freeze Active', 'content_freeze_out' ),
			'href'  => admin_url( 'options-general.php?page=content_freeze_out' ),
			'meta'  => array(
				'title' => esc_attr__( 'Content freeze is active — click to manage', 'content_freeze_out' ),
				'class' => 'cfo-frozen',
			),
		) );
	}

	function admin_bar_frozen_styles() {
		echo '<style>#wpadminbar li.cfo-frozen > .ab-item { background: #c0392b !important; color: #fff !important; }</style>';
	}

	function admin_init() {
		register_setting($this->settings_field, $this->settings_field, array($this, 'sanitize_theme_options'));

		if ($this->is_frozen() && isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) !== 'content_freeze_out') {
			add_action('admin_notices', array($this, 'content_frozen_notice'));
		}

		if ($this->is_frozen() && (int) $this->get_field_value('allowed_id') !== (int) get_current_user_id()) {
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
			$custom_message = __( "The site is currently in a content freeze.", 'content_freeze_out' );
		}
		$notice = '<div id="login_error" class="frozen-ice-box">'
			. esc_html($custom_message)
			. '</div>'
			. '<p class="cfo-admin-link-wrap"><button type="button" id="cfo-show-login" class="button" aria-controls="loginform" aria-expanded="false">'
			. esc_html__( 'Site administrator? Click here.', 'content_freeze_out' )
			. '</button></p>';
		return $notice . $message;
	}

	function login_hide_form_styles() {
		$css = '
			#loginform, #nav { display: none; }
			.cfo-admin-link-wrap { text-align: center; margin-top: 12px; }
			
			.frozen-ice-box {
				text-align: center;
				font-size: 1.2em;
				font-weight: bold;
				padding: 16px;
				border-radius: 8px;
				position: relative;
				overflow: hidden;
				background-color: #eaf6ff;
				border: 2px solid #bce2ff;
				box-shadow:
					inset 0 0 12px rgba(255, 255, 255, 0.8),
					inset 3px 3px 0px rgba(255, 255, 255, 0.7),
					0 4px 10px rgba(0, 0, 0, 0.05);
			}
		';
		wp_add_inline_style( 'login', $css );
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
				this.setAttribute('aria-expanded', 'true');
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

		add_options_page(__('Content Freeze Out', 'content_freeze_out'), __('Content Freeze Out', 'content_freeze_out'), 'manage_options', $this->page_id, array($this,'render'));
	}

	/*
		Sanitize our plugin settings array as needed.
	*/
	function sanitize_theme_options($options) {
		$options = is_array($options) ? $options : array();
		$options = wp_parse_args($options, self::$default_settings);

		if (isset($options['allowed_id'])) {
			$options['allowed_id'] = absint($options['allowed_id']);
		}
		if (isset($options['lock_message'])) {
			$options['lock_message'] = sanitize_text_field(wp_unslash($options['lock_message']));
		}

		// Determine freeze state from stable action tokens.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset($_POST['cfo_action']) ? sanitize_key(wp_unslash($_POST['cfo_action'])) : '';
		$current = (bool) $this->get_field_value('content_frozen');
		if ($action === 'freeze_content') {
			$options['content_frozen'] = 1;
			$options['allowed_id'] = (int) get_current_user_id();
		} elseif ($action === 'unfreeze_content') {
			$options['content_frozen'] = 0;
			$options['allowed_id'] = 0;
		} else {
			$options['content_frozen'] = (int) $current;
			if ($options['content_frozen'] === 1) {
				$options['allowed_id'] = (int) $this->get_field_value('allowed_id');
			} else {
				$options['allowed_id'] = 0;
			}
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
		return $this->get_field_name($id);
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
		$config_override = defined('CONTENT_FREEZE_OUT') && CONTENT_FREEZE_OUT;
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Content Freeze Out', 'content_freeze_out'); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields($this->settings_field); ?>

				<?php if ($this->is_frozen()): ?>
					<p><?php echo wp_kses( __( 'Content Freeze is currently <strong>enabled</strong>.', 'content_freeze_out' ), array( 'strong' => array() ) ); ?>
					<?php if ($config_override): ?> <em><?php echo wp_kses( __( '(active via <code>CONTENT_FREEZE_OUT</code> constant in wp-config)', 'content_freeze_out' ), array( 'code' => array() ) ); ?></em><?php endif; ?></p>
				<?php else: ?>
					<p><?php echo wp_kses( __( 'Content Freeze is currently <strong>disabled</strong>.', 'content_freeze_out' ), array( 'strong' => array() ) ); ?></p>
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $this->get_field_id('lock_message') ); ?>"><?php esc_html_e('Lock screen message:', 'content_freeze_out'); ?></label>
						</th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->get_field_name('lock_message') ); ?>" id="<?php echo esc_attr( $this->get_field_id('lock_message') ); ?>" value="<?php echo esc_attr($this->get_field_value('lock_message')); ?>" class="regular-text" placeholder="<?php esc_attr_e( "Content is currently frozen. You can't log in now.", 'content_freeze_out' ); ?>" />
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button" name="cfo_action" value="save_message"><?php esc_html_e('Save Message', 'content_freeze_out'); ?></button>
					<?php if ($this->is_frozen()): ?>
						<?php if (!$config_override): ?>
							<button type="submit" class="button button-primary" name="cfo_action" value="unfreeze_content"><?php esc_html_e('Unfreeze Content', 'content_freeze_out'); ?></button>
						<?php else: ?>
							<em><?php echo wp_kses( __( 'To unfreeze, remove the <code>CONTENT_FREEZE_OUT</code> constant from wp-config.php.', 'content_freeze_out' ), array( 'code' => array() ) ); ?></em>
						<?php endif; ?>
					<?php else: ?>
						<button type="submit" class="button button-primary" name="cfo_action" value="freeze_content"><?php esc_html_e('Freeze Content', 'content_freeze_out'); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

} // end class
endif;
