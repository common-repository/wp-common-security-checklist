<?php 


class Common_Security_Checklist_Login_Slug
{

	public static function new_login_url( $scheme = null ) 
	{

		if ( get_option( 'permalink_structure' ) ) 
		{

			return Common_Security_Checklist_Toolkit::user_trailingslashit( home_url( '/', $scheme ) . self::new_login_slug() );

		} else {

			return home_url( '/', $scheme ) . '?' . self::new_login_slug();

		}

	}

	public static function new_login_slug() {

		$settings = get_option( 'csc_general_settings' );

		if ( $slug = $settings['new_login_slug'] ) {
			return $slug;
		}  else if ( $slug = 'login' ) {
			return $slug;
		}

	}

	public static function filter_wp_login_php( $url, $scheme = null ) {

		if ( strpos( $url, 'wp-login.php' ) !== false ) {

			if ( is_ssl() ) {

				$scheme = 'https';

			}

			$args = explode( '?', $url );

			if ( isset( $args[1] ) ) {

				parse_str( $args[1], $args );

				$url = add_query_arg( $args, self::new_login_url( $scheme ) );

			} else {

				$url = self::new_login_url( $scheme );

			}
		}

		return $url;

	}

	public function custom_plugins_loaded() {

		global $pagecurrent;

		if ( ( strpos( $_SERVER['REQUEST_URI'], 'wp-signup' )  !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-activate' ) )  !== false ) {

			wp_die( __( 'This feature is not enabled.', 'common-security-checklist' ) );

		}

		$request = parse_url( $_SERVER['REQUEST_URI'] );

		if ( ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false || untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) && ! is_admin() ) {

			wp_die( __( 'This feature is not avaliable.', 'common-security-checklist' ) );
			//


		} elseif ( untrailingslashit( $request['path'] ) === home_url( self::new_login_slug(), 'relative' ) || ( ! get_option( 'permalink_structure' ) && isset( $_GET[self::
			new_login_slug()] ) && empty( $_GET[self::new_login_slug()] ) ) ) {
			$pagecurrent = 'wp-login.php';

		}

	}

	public function custom_wp_loaded() {

		global $pagecurrent;

		if ( is_admin()
			&& ! is_user_logged_in()
			&& ! defined( 'DOING_AJAX' )
			&& $pagecurrent !== 'admin-post.php' ) {

			status_header(404);
            nocache_headers();
            
            wp_redirect(home_url());
            exit;
		}

		$request = parse_url( $_SERVER['REQUEST_URI'] );

		if ( $pagecurrent === 'wp-login.php' ) {

			global $error, $interim_login, $action, $user_login;

			@require_once ABSPATH . 'wp-login.php';

			die;

		}

	}

	public function custom_site_url( $url, $path, $scheme, $blog_id ) {

		return self::filter_wp_login_php( $url, $scheme );

	}

	public function custom_wp_redirect( $location, $status ) {

		return self::filter_wp_login_php( $location );

	}
}

 ?>