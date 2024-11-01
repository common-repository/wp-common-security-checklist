<?php

class Common_Security_Checklist_Rewrite{

    public static function check_rewrite()
    {
        $home_path = get_home_path();
        $htaccess_file = $home_path.'.htaccess';

        if ( file_exists($htaccess_file) )
        {
            return true;
        }else{
            return false;
        }

    }

    public static function protect_sensitive_files( $rules ) {
        $new_rules = sprintf('
        #WP Common Security Checklist
        ##This denies all web access to your wp-config file, error_logs, php.ini, and htaccess/htpasswds. 
        <FilesMatch "^.*(error_log|wp-config\.php|php.ini|\.[hH][tT][aApP].*)$">
            Order deny,allow
            Deny from all
        </FilesMatch>
        # END Common Security Checklist
        ');
        return $rules . $new_rules;
    }

    public static function disable_php_execution_directories(  ) {
        $rules = "#WP Common Security Checklist \n <Files *.php> \n deny from all \n </Files> \n#End WP Common security Checklist";
        $home_path = get_home_path();
        $uploads_path = wp_upload_dir();
        
        if ( !file_exists( $home_path.'/wp-includes/.htaccess' ) ) {
            $file = $home_path.'/wp-includes/.htaccess';
            file_put_contents(
                $file,
                $rules
            );
        }elseif ( file_exists( $home_path.'/wp-includes/.htaccess' ) ) {

            $file = $home_path.'/wp-includes/.htaccess';
            $file_contents = file_get_contents($file);

            $search = self::get_content_between_markers($file_contents);

            // strlen is equal 1 when not exusts content betwenn markers (new line only) like "#WP Common Security Checklist\n#End WP Common security Checklist"
            if ( empty( $search ) || strlen($search) == 1 ) {
                file_put_contents(
                    $file,
                    str_replace($search,$rules,$file_contents)
                );
            }

        }

        if ( !file_exists( $uploads_path['basedir'].'.htaccess' ) ) {
            $file = $uploads_path['basedir'].'/.htaccess';
            file_put_contents(
                $file,
                $rules
            );
        }elseif ( file_exists( $uploads_path['basedir'].'.htaccess' ) ) {

            $file = $uploads_path['basedir'].'/.htaccess';
            $file_contents = file_get_contents($file);

            $search = self::get_content_between_markers($file_contents);

            // strlen is equal 1 when not exusts content betwenn markers (new line only) like "#WP Common Security Checklist\n#End WP Common security Checklist"
            if ( empty( $search ) || strlen($search) == 1 ) {
                file_put_contents(
                    $file,
                    str_replace($search,$rules,$file_contents)
                );
            }

        }
    }

    public static function undo_disable_php_execution_directories()
    {        
        $home_path = get_home_path();
        $uploads_path = wp_upload_dir();

        //Undo changes on wp-includes/.htaccess
        if ( file_exists( $home_path.'/wp-includes/.htaccess' ) ) {

            $file = $home_path.'/wp-includes/.htaccess';
            $file_contents = file_get_contents($file);

            $search = self::get_content_between_markers($file_contents);

            if ( strlen( $search ) > 1 ) {
                file_put_contents(
                    $file,
                    str_replace($search,"\n",$file_contents)
                );
            }            
        }

        //Undo changes on /wp-content/uploads/.htaccess
        if ( file_exists( $uploads_path['basedir'].'/.htaccess' ) ) {

            $file = $uploads_path['basedir'].'/.htaccess';
            $file_contents = file_get_contents($file);

            $search = self::get_content_between_markers($file_contents);

            if ( strlen( $search ) > 1 ) {
                file_put_contents(
                    $file,
                    str_replace($search,"\n",$file_contents)
                );
            }
        }
    }

    protected static function get_content_between_markers( $file_contents = '')
    {
        if ( empty($file_contents) ) {
            return;
        }

        $startsAt = strpos($file_contents, "#WP Common Security Checklist") + strlen("#WP Common Security Checklist");
        $endsAt = strpos($file_contents, "#End WP Common security Checklist", $startsAt);
        $result = substr($file_contents, $startsAt, $endsAt - $startsAt);

        if (!empty($result) || strlen($result) > 0) {
            return $result;
        }else{
            return '';
        }
    }

}

?>