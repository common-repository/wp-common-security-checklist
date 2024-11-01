<?php 

class Common_Security_Checklist_Ssl{

	public static function add_force_admin_ssl()
	{
		$constant = "#WP Common Security Checklist\ndefine('FORCE_SSL_ADMIN', true);\n#END Common Security Checklist\n";
		$home_path = get_home_path();

		//Check if constant already exists on wp-config
		if ( self::check_if_admin_ssl_is_enabled() || !file_exists( $home_path.'/wp-config.php' ) ) {
			return;
		}

		$file = new SplFileObject($home_path.'/wp-config.php', 'r');
		$ouput = '';
		while (!$file->eof()) {
		    $line = $file->fgets();
		    if( preg_match("/^define\('WP_DEBUG'/", $line) ){
		       $line = $line."\n".$constant;
		    }
		    $output .= $line;
		}
		$file = null;

		$file = new SplFileObject($home_path.'/wp-config.php', 'w+');
		$file->fwrite($output); 
	}

	public static function remove_force_admin_ssl()
	{
		$home_path = get_home_path();
		
		if ( !file_exists( $home_path.'/wp-config.php' ) ) {
			return;
		}

		$file = new SplFileObject($home_path.'/wp-config.php', 'r');
		$ouput = '';
		while (!$file->eof()) {
		    $line = $file->fgets();
		    if( preg_match("/^define\('FORCE_SSL_ADMIN'/", $line) ){

		       $file->seek($file->key() + 1);
		      	if ( preg_match('/^\#END Common Security Checklist/', $file->current() ) ) 
		      	{
		      		$file->seek($file->key() - 1);
		      		$line = "";
		      	}
		       
		    }

		    @$output .= $line;	    
		}
		$file = null;

		$file = new SplFileObject($home_path.'/wp-config.php', 'w+');
		$file->fwrite($output);       
	}

	public static function check_if_admin_ssl_is_enabled()
	{
		$home_path = get_home_path();

		if ( !file_exists( $home_path.'/wp-config.php' ) ) {
			return;
		}

		//Check if constant already exists on wp-config
		$content = file_get_contents($home_path.'/wp-config.php');
		if ( preg_match("/define\('FORCE_SSL_ADMIN'/", $content) ) {
		    return true;
		}else{
			return false;
		}
	}

}