<?php
/*
Plugin Name: WP Common security Checklist
Plugin URI: http://www.luisfred.com.br
Description: A simple plugin that enables you to add common security implementations on your WordPress.
Version: 1.0.8
License: GPLv2 or later
Author: Luís Fred G S
Author URI: http://www.luisfred.com.br
Text Domain: common-security-checklist
Domain Path: /languages
*/

if ( defined( 'ABSPATH' )&& ! class_exists( 'Common_Security_Checklist' ) ) 
{
    define( 'CSC_PATH', trailingslashit( dirname( __FILE__ ) ) );

    function csc_autoload ($class) {
        
        $lib_folders = [
            '',
            'admin/',
            'lib/'
        ];
        foreach ($lib_folders as $folder) {
            $class_path = CSC_PATH . $folder . preg_replace('/\_/', '-',  strtolower($class) ).'.php';
            if ( file_exists($class_path) )
            {
                require_once( $class_path );   
            }            
        }
    }
    spl_autoload_register("csc_autoload");

    /**
     * Common_Security_Checklist main class
     * 
     */

	class Common_Security_Checklist {


	    protected static $instance = null;

	    protected $plugin_general_settings = [];
	    protected $plugin_settings_login_attempts_key = [];

		public function __construct() {            

			$this->plugin_general_settings = get_option( 'csc_general_settings' );
			$this->plugin_settings_login_attempts_key = get_option( 'csc_login_attempts_settings' );


	        add_action( 'plugins_loaded', [  'Common_Security_Checklist_Toolkit', 'csc_load_textdomain' ] );

			global $wp_version;

			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	        if ( is_plugin_active( 'rename-wp-login/rename-wp-login.php' ) ) {
	            deactivate_plugins( plugin_basename( __FILE__ ) );

	            add_action( 'admin_notices', Common_Security_Checklist_Toolkit::admin_notices('warning', 'Common Security Checklist could not be activated because you already have Rename wp-login.php active. Please uninstall rename wp-login.php to use this plugin') );

	            if ( isset( $_GET['activate'] ) ) {
	                unset( $_GET['activate'] );
	            }
	            return;
	        }

            register_activation_hook( Common_Security_Checklist_Toolkit::basename(), array( $this, 'activate' ) );
        

	        //add_action( 'admin_init', [ $this, 'admin_init' ] );

	        if ( !empty( $this->plugin_general_settings['hide_login_url'] ) ) 
            {
                add_action( 'plugins_loaded', [ new Common_Security_Checklist_Login_Slug(), 'custom_plugins_loaded' ], 2 );
                add_action( 'wp_loaded', [ new Common_Security_Checklist_Login_Slug(), 'custom_wp_loaded' ] );
                add_filter( 'site_url', [ new Common_Security_Checklist_Login_Slug(), 'custom_site_url' ], 10, 4 );
                add_filter( 'wp_redirect', [ new Common_Security_Checklist_Login_Slug(), 'custom_wp_redirect' ], 10, 2 );
            }

			add_action( 'admin_bar_menu', [ $this, 'admin_bar_link' ] );

			remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

			call_user_func([ $this, 'disable_theme_editor' ]);
			call_user_func([ $this, 'disable_tag_generator' ]);

            $plugin = plugin_basename( plugin_dir_path( __FILE__ ) . basename(__FILE__));
            add_filter( "plugin_action_links_$plugin", [ $this, 'plugin_add_settings_link' ] );

            /* Rewrite */
            add_action('admin_init', [ $this, 'custom_admin_init' ]);
            add_filter('mod_rewrite_rules', [ $this, 'protect_sensitive_files' ]);

            /* Login Attemps */
            if ( !empty( $this->plugin_settings_login_attempts_key['limit_login_attempts'] ) )
            {
                $login_attempts_options = [
                    'failed_login_limit' => $this->plugin_settings_login_attempts_key['failed_login_limit']
                ];

                add_filter( 'authenticate', [ new Common_Security_Checklist_Login_Attempts( $login_attempts_options ), 'check_attempted_login' ], 30, 3 );
                add_action( 'wp_login_failed', [ new Common_Security_Checklist_Login_Attempts( $login_attempts_options ), 'login_failed' ], 10, 1 );
            }

            //Captcha
            if ( !empty( $this->plugin_settings_login_attempts_key['enable_login_captcha'] ) ) 
            {
                add_action( 'wp_authenticate_user', [ 'Common_Security_Checklist_Login_Form', 'custom_wp_authenticate' ], 10, 2 );
                add_action( 'login_form', [ 'Common_Security_Checklist_Login_Form', 'add_captcha' ] );
            }   

            //Comment Form
            if ( !empty( $this->plugin_general_settings['enable_comment_captcha'] ) ) 
            {
                add_filter( 'comment_form_default_fields',  [ new Common_Security_Checklist_Comment_Form(), 'add_captcha' ] );
                add_action( 'comment_post', [ new Common_Security_Checklist_Comment_Form(), 'save_comment_meta_data' ] );
                add_filter( 'preprocess_comment' , [ new Common_Security_Checklist_Comment_Form(), 'custom_preprocess_comment_handler' ] );
            }            

		}

        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;
        }

		public function custom_admin_init()
        {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();

            $this->disable_php_execution_directories();

            // Force SSL on Admin area
            if ( is_admin() ) {

                if ( !empty( $this->plugin_general_settings['force_ssl_admin'] ) && !is_ssl() ) {

                    Common_Security_Checklist_Ssl::add_force_admin_ssl();
                    
                }else{
                    Common_Security_Checklist_Ssl::remove_force_admin_ssl();
                }
            }    
        }



		public function activate() {

			add_option( 'csc_redirect', '1' );

			delete_option( 'csc_admin' );

		}

        /**
         * Disable the Plugin and Theme Editor
         * See: https://codex.wordpress.org/Editing_wp-config.php
         */
		private function disable_theme_editor()
		{
			if ( !empty( $this->plugin_general_settings['disable_theme_editor'] ) && !defined('DISALLOW_FILE_EDIT') ) {

				return define('DISALLOW_FILE_EDIT',true);
			}
		}

		private function disable_tag_generator()
		{
			if ( !empty( $this->plugin_general_settings['remove_meta_generator_tag'] ) ) {
				remove_action('wp_head', 'wp_generator');
			}
		}



        /* Rewrite */

        public function protect_sensitive_files( $rules ) {

            if ( !empty( $this->plugin_general_settings['protect_sensitive_files'] ) ) {

                return Common_Security_Checklist_Rewrite::protect_sensitive_files($rules);

            }else{
                return $rules;
            }
        }

        public function disable_php_execution_directories() {

            if ( !empty( $this->plugin_general_settings['disable_php_execution_directories'] ) ) 
            {
            	Common_Security_Checklist_Rewrite::disable_php_execution_directories();
            }else{
            	Common_Security_Checklist_Rewrite::undo_disable_php_execution_directories();
            }
        }


        public function plugin_add_settings_link( $links ) {
            $settings_link = '<a href="'.esc_url( admin_url( 'options-general.php?page='.Common_Security_Checklist_Admin_Options::plugin_options_key ) ).'">'. __( 'Settings', 'common-security-checklist') .'</a>';
            array_unshift( $links, $settings_link );
            return $links;
        }

        public function admin_bar_link()
        {
        	global $wp_admin_bar;

        	if ( !is_super_admin() || !is_admin_bar_showing() ) {
        		return;
        	}

        	$wp_admin_bar->add_menu([
        		'id' => 'common-security-checklist',
        		'title' => __('WP Common security Checklist', 'common-security-checklist'),
        		'href' =>  esc_url( admin_url( 'options-general.php?page='.Common_Security_Checklist_Admin_Options::plugin_options_key ) )        		
        	]);
        }

    }

	add_action( 'plugins_loaded', [ 'Common_Security_Checklist', 'get_instance' ], 1 );
    add_action( 'plugins_loaded', [ 'Common_Security_Checklist_Admin_Options', 'get_instance' ]  );
}