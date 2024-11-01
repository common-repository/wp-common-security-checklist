<?php 

class Common_Security_Checklist_Toolkit
{
	public static function basename() {

		return plugin_basename( __FILE__ );

	}

	public static function path() {			
		return trailingslashit( dirname( __FILE__ ) );

	}

	public static function use_trailing_slashes() {

		return ( '/' === substr( get_option( 'permalink_structure' ), -1, 1 ) );

	}

	public static function user_trailingslashit( $string ) {

		return self::use_trailing_slashes()
			? trailingslashit( $string )
			: untrailingslashit( $string );

	}

    public static function admin_notices($type, $text) {

    	switch ($type) {
    		case 'error':
    			$class = 'notice notice-error';
    			break;
    		case 'warning':
    			$class = 'notice notice-warning';
    			break;
    		case 'success':
    			$class = 'notice notice-success';
    			break;
    		case 'info':
    			$class = 'notice notice-info';
    			break;
    		default:
    			$class = 'notice';
    			break;
    	}

		echo sprintf('<div class="%s is-dismissible"><p>%s</p></div>', $class, __( $text, 'common-security-checklist'));

	}

	public static function csc_load_textdomain() {
	    load_plugin_textdomain( 'common-security-checklist', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public static function file_search($folder, $pattern_array) {
	    $return = array();
	    $iti = new RecursiveDirectoryIterator($folder);
	    foreach(new RecursiveIteratorIterator($iti) as $file){

        	$info = pathinfo(basename($file));

        	if ( $info['extension'] == 'php' ) {
        		$return[] = $file;
        	}            
	    }
	    return $return;
	}


	/*
	 * Backup of wp-config file
	 * */
	public static function makeBackup()
    {
        $upload_dir = wp_upload_dir();
        $dirname = $upload_dir['basedir'].'/wp-common-security-checklist';

        if ( file_exists($dirname) )
        {
            $iti = new RecursiveDirectoryIterator($dirname);
            foreach(new RecursiveIteratorIterator($iti) as $file)
            {
                $info = pathinfo(basename($file));

                if ( $info['extension'] == 'php' ) {
                    @unlink($file);
                }
            }
        }

        if ( ! file_exists( $dirname ) && wp_is_writable($upload_dir['basedir']) )
        {
            wp_mkdir_p( $dirname );
            @copy( get_home_path() . 'wp-config.php',  $dirname.'/wp-config-backup-'.date('y-m-d').'.php');
        }elseif( wp_is_writable($upload_dir['basedir']) )
        {
            @copy( get_home_path() . 'wp-config.php',  $dirname.'/wp-config-backup-'.date('y-m-d').'.php');
        }
    }

    public static function checkRecentBackup($type = 'wp-config' )
    {

        $return = array();

        $upload_dir = wp_upload_dir();
        $dirname = $upload_dir['basedir'].'/wp-common-security-checklist';
        if ( !file_exists( $dirname ) )
        {
            $return[] = "<i class=\"fa fa-check-circle color-red\" aria-hidden=\"true\"></i>" . __('You don\'t have recent backup files', 'common-security-checklist');

            return $return;
        }

        $iti = new RecursiveDirectoryIterator($dirname);
        foreach(new RecursiveIteratorIterator($iti) as $file){

            $info = pathinfo(basename($file));

            $time = ( time() - (86400 * 5) ); // five days
            $stats = stat($file);

            if ( $type == 'wp-config' && $info['extension'] == 'php' )
            {
                if( ( $stats[9] > $time ) )
                {
                    $return[] = "<i class=\"fa fa-check-circle color-green\" aria-hidden=\"true\"></i>" . __('Your most recent backup has less than 5 days ', 'common-security-checklist') ;

                }else
                {
                    $return[] = "<i class=\"fa fa-check-circle color-red\" aria-hidden=\"true\"></i>" . __('Your most recent backup has more than 5 days ', 'common-security-checklist');
                }
            }

        }

        return $return;
    }

    
    public static function create_zip($file ,$destination = '',$overwrite = false) {
        
        if(file_exists($destination) && !$overwrite) 
        { 
            return false; 
        }

        $zip = new ZipArchive();
        if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
            return false;
        }
        
        $zip->addFile($file, basename($file) );
        
        
        $zip->close();
        
        return file_exists($destination);
    }
	
}

 ?>