<?php

class Common_Security_Checklist_Admin_Options {
	
	protected static $instance = null;

	private $prefixName = "csc_";

	private $settings_key = 'csc_general_settings';
	private $settings_login_attempts_key = 'csc_login_attempts_settings';
	private $plugin_settings_tabs = array();

	private $plugin;

	const plugin_options_key = 'csc_plugin_options';
	
	
	public function __construct() {
		add_action( 'admin_init', [$this, 'register_general_settings'] );
		add_action( 'admin_init', [$this, 'admin_init'] );
		add_action( 'init', [$this, 'load_settings'] );		
		add_action( 'admin_init', [$this, 'load_plugin_data'] );		
		add_action( 'admin_menu', [$this, 'load_plugin_data'] );
		add_action( 'admin_menu', [$this, 'add_admin_menus'] );
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_js'] );
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_css'] );
				
	}

	public static function get_instance() {

	    if ( null == self::$instance ) {
	        self::$instance = new self;
	    }

	    return self::$instance;
	}

	public function admin_init()
	{
		add_filter( 'pre_update_option_csc_general_settings', [ $this, 'before_update_default_admin_username' ], 10, 2);
		add_filter( 'pre_update_option_csc_general_settings', [ $this, 'before_update_make_wpconfig_backup' ], 10, 3);
	}

	public function before_update_default_admin_username( $new_value, $old_value ) {
		global $wpdb;

		$current_admin_default_username = get_user_by( 'login', 'admin' );
		if ( !empty( $new_value['admin_username'] ) && !empty( $current_admin_default_username ) ) {

			$data = [ 'user_login' => $new_value['admin_username'] ];
			$where = [ 'id' => $current_admin_default_username->ID, 'user_login' => $current_admin_default_username->user_login ];

			//Check if already exists, to avoid conflicts between usernames
			if ( !get_user_by( 'login', $new_value['admin_username'] ) ) 
			{
				$updated = $wpdb->update( $wpdb->users, $data, $where );
			}else{
				add_action('admin_notices', function(){
					echo sprintf('<div class="%s is-dismissible"><p>%s</p></div>', 'notice notice-warning', __( 'This username already exists', 'common-security-checklist'));
				});
			}
		}		

	   return $new_value;
	}

	/**
	 * wp-config backup
	*/
	public function before_update_make_wpconfig_backup( $new_value, $old_value )
	{

		if ( !empty( $new_value['make_wpconfig_backup'] ) )
		{
			Common_Security_Checklist_Toolkit::makeBackup($type = 'wp-config');

			if ( !empty( $_POST['backup_to_email'] ) && extension_loaded('zip') ) {
				$upload_dir = wp_upload_dir();
				$dirname = $upload_dir['basedir'].'/wp-common-security-checklist';
				
				$backup_zip = $dirname.'/wp-config-backup-'.date('y-m-d').'.zip';

				if ( file_exists($dirname) && file_exists( $dirname.'/wp-config-backup-'.date('y-m-d').'.php' ) ) {
					
					$file = $dirname.'/wp-config-backup-'.date('y-m-d').'.php';

					//Send Email
					$to = $_POST['backup_to_email'];
					$subject = sprintf('[%s] wp-config Backup - %s', $this->plugin['Name'], date('y-m-d'));
					$body = __( sprintf('Hello! Your wp-config.php backup is on attachments <br/> From: %s', home_url()) , 'common-security-checklist');
					$headers = array('Content-Type: text/html; charset=UTF-8');
					//attachment
					$attachments = Common_Security_Checklist_Toolkit::create_zip(  $file, $backup_zip );

					if ( !empty( $attachments ) || file_exists( $backup_zip ) ) {
						@wp_mail( $to, $subject, $body, $headers, $attachments = [ $backup_zip ] );
						@unlink($backup_zip);
					}
				}
			}
		}
		return $new_value;
	}
	
	public function enqueue_admin_js() { 
	    if( is_admin() ) { 

            wp_enqueue_script(
            	$this->prefixName.'admin_bootstrap-switch_js',
				plugins_url( 'assets/js/bootstrap-switch.min.js', __FILE__ ),
				[ 'jquery' ],
				'',
				true
			);

			wp_enqueue_script(
				$this->prefixName.'admin_custom_js',
				plugins_url( 'assets/js/admin.js', __FILE__ ),
				[ 'jquery', $this->prefixName.'admin_bootstrap-switch_js' ],
				'',
				true
			);
	    }
	}

	public function enqueue_admin_css() { 
	    if( is_admin() ) {

            wp_enqueue_style( $this->prefixName.'admin_font-awesome_css', plugins_url( 'assets/css/font-awesome.min.css', __FILE__ ) );
            wp_enqueue_style( $this->prefixName.'admin_bootstrap-switch_css', plugins_url( 'assets/css/bootstrap-switch.css', __FILE__ ) );
			wp_enqueue_style( $this->prefixName.'admin_css', plugins_url( 'assets/css/admin.css', __FILE__ ) );
	    }
	}

	public function load_plugin_data()
	{

		$this->plugin = get_plugin_data( dirname( dirname(__FILE__) ).'/common-security-checklist.php' );
	}
	
	public function load_settings() {		

		$this->general_settings = (array) get_option( $this->settings_key );
        $this->settings_login_attempts = (array) get_option( $this->settings_login_attempts_key );

	}
	
	
	public function register_general_settings() {

		/**
		 * General Settings
		 */

		$this->plugin_settings_tabs[$this->settings_key] = __( 'General settings', 'common-security-checklist');
		
		register_setting( $this->settings_key, $this->settings_key);
		

		add_settings_section( 
			'section_general', 
			sprintf( __( ' %s - General settings', 'common-security-checklist') , $this->plugin['Name']), 
			array( &$this, 'section_general_desc' ), 
			$this->settings_key 
			);
		
		
		add_settings_field(
			'remove_meta_generator_tag',
			__( 'Remove meta generator Tag?', 'common-security-checklist'),
			array( &$this, 'field_remove_meta_generator_tag' ),
			$this->settings_key,
			'section_general'
		);

		
		add_settings_field(
			'new_login_slug',
			__( 'Change default login url', 'common-security-checklist'),
			array( &$this, 'field_new_login_slug' ),
			$this->settings_key,
			'section_general'
		);

		add_settings_field(
			'disable_theme_editor',
			__( 'Disable theme editor on Admin', 'common-security-checklist'),
			array( &$this, 'field_disable_theme_editor' ),
			$this->settings_key,
			'section_general'
		);

		add_settings_field(
			'admin_username',
			__( 'Change default Admin username', 'common-security-checklist'),
			array( $this, 'field_admin_user' ),
			$this->settings_key,
			'section_general'
		);

		add_settings_field(
			'make_wpconfig_backup',
			__( 'Make a wp-config.php backup', 'common-security-checklist'),
			array( $this, 'field_make_wpconfig_backup' ),
			$this->settings_key,
			'section_general'
		);

        add_settings_field(
			'protect_sensitive_files',
			__( 'Protect Sensitive Files', 'common-security-checklist'),
			array( $this, 'field_protect_sensitive_files' ),
			$this->settings_key,
			'section_general'
		);

		add_settings_field(
			'disable_php_execution_directories',
			__('Disable PHP execution in specifics directories', 'common-security-checklist'),
			array( $this, 'field_disable_php_execution_directories' ),
			$this->settings_key,
			'section_general'
		);

		add_settings_field(
		    'enable_comment_captcha',
		    __( 'Enable Captcha comment form to prevent spam', 'common-security-checklist'),
		    array( $this, 'field_enable_comment_captcha' ),
		    $this->settings_key,
		    'section_general'
		);

        /* Login Attempts */
        register_setting( $this->settings_login_attempts_key, $this->settings_login_attempts_key );
        $this->plugin_settings_tabs[$this->settings_login_attempts_key] = __( 'Login Attempts', 'common-security-checklist');
        add_settings_section(
            'section_login_attempts',
            sprintf('%s - Login Attempts', $this->plugin['Name']),
            array( &$this, 'section_login_attempts_desc' ),
            $this->settings_login_attempts_key
        );

        add_settings_field(
            'limit_login_attempts',
            __( 'Limit Login Attempts', 'common-security-checklist'),
            array( $this, 'field_limit_login_attempts' ),
            $this->settings_login_attempts_key,
            'section_login_attempts'
        );

        add_settings_field(
            'failed_login_limit',
            __( 'Failed login limit', 'common-security-checklist'),
            array( $this, 'field_failed_login_limit' ),
            $this->settings_login_attempts_key,
            'section_login_attempts'
        );

        add_settings_field(
            'enable_login_captcha',
            __( 'Enable Captcha on login form', 'common-security-checklist'),
            array( $this, 'field_enable_login_captcha' ),
            $this->settings_login_attempts_key,
            'section_login_attempts'
        );

	}
	
	
	public function section_general_desc() { 
		echo sprintf(
			'<h4>%s</h4>', 
			__( 'General settings', 'common-security-checklist')
			); 
	}

	public function section_login_attempts_desc() { 
		echo sprintf(
			'<h4>%s</h4>', 
			__( 'Login Attempts', 'common-security-checklist')
			); 
	}

	
	public function add_admin_menus() {

		add_options_page( 
			$this->plugin['Name'], 
			$this->plugin['Name'], 
			'manage_options', 
			self::plugin_options_key, 
			[ $this, 'plugin_options_page' ] 
		);

	}
	
	
	public function plugin_options_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->settings_key;
		?>
		<div class="<?=$this->prefixName?>wrap_admin_header">
			<div class="<?=$this->prefixName?>wrap_admin_logo"><img src="<?= plugin_dir_url(__FILE__) ?>/assets/img/logo.png"></div>
		</div>		

		<div class="<?=$this->prefixName?>wrap">			

			<?php $this->plugin_options_tabs(); ?>			
			<form method="post" action="options.php">				
				<?php wp_nonce_field( 'update-options' ); ?>				
				<?php settings_fields( $tab ); ?>				
				<?php do_settings_sections( $tab ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th><?= __('Do you need help?', 'common-security-checklist') ?></th>
							<td><a href="http://blog.luisfred.com.br/?utm_source=wordpress&utm_campaign=wp-common-security-checklist&utm_medium=plugin"><?= __('Yes! lend a hand!', 'common-security-checklist') ?></a></td>
						</tr>
					</tbody>	
				</table>
				<?php submit_button( __( 'Save Settings', 'common-security-checklist') ); ?>
			</form>
		</div>
		<?php
	}
	
	public function plugin_options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->settings_key;

		screen_icon();
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . self::plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';	
		}
		echo '</h2>';
	}

	
	public function field_remove_meta_generator_tag()
	{
		global $wp_version;

		?>
		<label>
			<input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[remove_meta_generator_tag]" id="remove_meta_generator_tag" value="1" <?= checked( @$this->general_settings['remove_meta_generator_tag'], 1 ) ?> >
			<p class="option_help">
				<?php if( empty( $this->general_settings['remove_meta_generator_tag'] ) ): ?>
					<i class="fa fa-times-circle color-red" aria-hidden="true"></i> <i class="color-red"><?= __( 'Remove Meta Generator Tag from your HTML theme. Example: ', 'common-security-checklist') . esc_html(sprintf('<meta name="generator" content="WordPress %s" />', $wp_version)) ?></i>
				<?php else: ?>
					<i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __( 'All right! Meta Generator Tag was disabled from HTML.', 'common-security-checklist') ?></i>
				<?php endif ?>
			</p>
		</label>
		<?php
	}

	public function field_disable_theme_editor()
	{

		?>
		<label>
			<input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[disable_theme_editor]" id="disable_theme_editor" value="1" <?= checked( @$this->general_settings['disable_theme_editor'], 1 ) ?> />

			<?php if( !defined('DISALLOW_FILE_EDIT') ): ?>
				<p class="option_help">
					<i class="fa fa-times-circle color-red" aria-hidden="true"></i> <i class="color-red"><?= __( 'Disable theme editor on Admin', 'common-security-checklist') ?> </i>
				</p>
			<?php else: ?>
				<p class="option_help">
					<i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __( 'All right! Theme editor was disabled on Admin', 'common-security-checklist') ?></i>
				</p>
			<?php endif ?>
		</label>
		<?php
	}


	
	public function field_new_login_slug()
	{
		?>
		
		<input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[hide_login_url]" id="hide_login_url" value="1" <?= checked( @$this->general_settings['hide_login_url'], 1 ) ?> />

		<input class="regular-text" type="text" name="<?php echo $this->settings_key; ?>[new_login_slug]" id="window_width" value="<?php echo !empty( $this->general_settings['new_login_slug'] ) ? $this->general_settings['new_login_slug'] : 'login' ; ?>" placeholder='' maxlength="160">

		<?php if ( !empty( $this->general_settings['hide_login_url'] ) ): ?>
			<p class="option_help">
				<span class="yellow_box">
					<i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __( 'Your default login slug <strong>was changed</strong> to: ', 'common-security-checklist') ?><?= sprintf("%s/", home_url()) ?><strong><?php echo !empty( $this->general_settings['new_login_slug'] ) ? esc_attr($this->general_settings['new_login_slug']) : 'login' ; ?></strong></i>
				</span>
			</p>
		<?php else: ?>
				<p>
					<i><?= __( 'Change your login url from wp-login.php or /wp-admin to a new url on field above. <br/> Default will be http://www.yoursite.com/login if you don\'t provide other slug on field above.', 'common-security-checklist') ?></i>
				</p>
		<?php endif ?>		
		<?php
	}

	public function field_admin_user()
	{
		if ( username_exists('admin') ) {
			$user = get_user_by( 'login', 'admin' );
			?>
			<input class="regular-text" type="text" name="<?php echo $this->settings_key; ?>[admin_username]" id="window_width" value="<?php echo $user->user_login ; ?>" placeholder='' maxlength="160">
			<p class="option_help">
				<i class="fa fa-times-circle color-red" aria-hidden="true"></i> <i class="color-red"><?= __( 'Change your admin username. This is a good security measure to help protect against brute force attack. Update and login again.', 'common-security-checklist') ?></i>
			</p>
			<?php
		}else{
			?>
			<p class="option_help">
				<i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __( 'All right! <strong>"admin"</strong> username was not found :-).', 'common-security-checklist') ?> </i>
			</p>
			<?php
		}
	}

	public function field_make_wpconfig_backup()
	{
		?>
		<label>
			<input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[make_wpconfig_backup]" id="make_wpconfig_backup" value="1" />
			<input class="regular-text" type="text" name="backup_to_email" id="" placeholder="<?= __( 'Your e-mail address', 'common-security-checklist') ?>">
			<p>
				<i><?= __( 'Make a copy of current wp-config.php file. Provide your email address on the field above, to send an email with backup file.', 'common-security-checklist') ?></i>
			</p>
			<?php
			$check = Common_Security_Checklist_Toolkit::checkRecentBackup();
			foreach($check as $check):
				?>
				<i><?= $check ?></i>
			<?php endforeach; ?>
		</label>
		<?php
	}

    public function field_protect_sensitive_files()
    {

        ?>
        <label>
            <input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[protect_sensitive_files]" id="protect_sensitive_files" value="1" <?= checked( @$this->general_settings['protect_sensitive_files'], 1 ) ?> />

            <p>
                <?php if( empty( $this->general_settings['protect_sensitive_files'] ) ): ?>
                    <i class="fa fa-times-circle color-red" aria-hidden="true"></i> <i class="color-red"><?= __( 'This denies all web access to your wp-config file, error_logs, php.ini, and htaccess/htpasswds', 'common-security-checklist') ?></i>
                <?php else: ?>
                    <i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __( 'All right! Sensitive Files are protected.', 'common-security-checklist') ?></i>
                <?php endif ?>
            </p>
        </label>
        <?php
    }

    public function field_disable_php_execution_directories()
    {

        ?>
        <label>
            <input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[disable_php_execution_directories]" id="disable_php_execution_directories" value="1" <?= checked( @$this->general_settings['disable_php_execution_directories'], 1 ) ?> />

            <p>
                <?php if( empty( $this->general_settings['disable_php_execution_directories'] ) ): ?>
                    <i class="fa fa-times-circle color-red" aria-hidden="true"></i> <i class="color-red"><?= __('Disable PHP Execution in wp-content/uploads and wp-includes Directories', 'common-security-checklist') ?></i>
                <?php else: ?>
                    <i class="fa fa-check-circle color-green" aria-hidden="true"></i> <i><?= __('wp-content/uploads and wp-includes directories are protected', 'common-security-checklist') ?></i>
                <?php endif ?>
            </p>
        </label>
        <?php
    }

    public function field_enable_comment_captcha()
    {

        ?>
        <label>
            <input class="" type="checkbox" name="<?php echo $this->settings_key; ?>[enable_comment_captcha]" id="enable_comment_captcha" value="1" <?= checked( @$this->general_settings['enable_comment_captcha'], 1 ) ?> />
            <p>
                <i><?= __('Prove that the visitor is a human being and not a spam robot. If checked, comment form asks the visitors to answer a math questions. This prevent spam on your comment section.', 'common-security-checklist') ?></i>
            </p>
        </label>
        <?php
    }

    /* Limit login Attempts */
    public function field_limit_login_attempts()
    {

        ?>
        <label>
            <input class="" type="checkbox" name="<?php echo $this->settings_login_attempts_key; ?>[limit_login_attempts]" id="limit_login_attempts" value="1" <?= checked( @$this->settings_login_attempts['limit_login_attempts'], 1 ) ?> />
            <p>
                <i><?= __('Prevent Mass WordPress Login Attacks by setting locking the system when login fail', 'common-security-checklist') ?></i>
            </p>
        </label>
        <?php
    }

    public function field_failed_login_limit()
    {

        ?>
        <label>
            <input class="" type="number" min="3" name="<?php echo $this->settings_login_attempts_key; ?>[failed_login_limit]" id="failed_login_limit" value="<?= !empty( $this->settings_login_attempts['failed_login_limit'] ) ? $this->settings_login_attempts['failed_login_limit'] : '3' ?>"  />
            <p>
                <i><?= __('Number of authentication attempts accepted.', 'common-security-checklist') ?></i>
            </p>
        </label>
        <?php
    }

    public function field_enable_login_captcha()
    {

        ?>
        <label>
            <input class="" type="checkbox" name="<?php echo $this->settings_login_attempts_key; ?>[enable_login_captcha]" id="enable_login_captcha" value="1" <?= checked( @$this->settings_login_attempts['enable_login_captcha'], 1 ) ?> />
            <p>
                <i><?= __('Prove that the visitor is a human being and not a spam robot. If checked, login form asks the visitors to answer a math questions. This prevent mass attacks on WordPress login too.', 'common-security-checklist') ?></i>
            </p>
        </label>
        <?php
    }


};