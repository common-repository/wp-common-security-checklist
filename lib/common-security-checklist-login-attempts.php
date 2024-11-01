<?php

class Common_Security_Checklist_Login_Attempts {

    public $failed_login_limit = 3;
    public $lockout_duration   = 1800; //30 minutes: 60*30 = 1800
    public $transient_name     = 'csc_attempted_login';

    public function __construct( $options = [] )
    {
        if( !empty( $options['failed_login_limit'] ) )
        {
            $this->failed_login_limit = (int) $options['failed_login_limit'];
        }

        if( !empty( $options['lockout_duration'] ) )
        {
            $this->lockout_duration = (int) $options['lockout_duration'];
        }
    }


    public function check_attempted_login( $user, $username, $password ) {
        if ( get_transient( $this->transient_name ) ) {
            $datas = get_transient( $this->transient_name );

            if ( $datas['tried'] >= $this->failed_login_limit ) {
                $until = get_option( '_transient_timeout_' . $this->transient_name );
                $time = $this->compareDate( $until );

                //Display error message to the user when limit is reached
                return new WP_Error( 'too_many_tried', sprintf( __( '<strong>ERROR</strong>: You have reached authentification limit, you will be able to try again in %1$s.', 'common-security-checklist' ) , $time ) );
            }
        }

        return $user;
    }



    public function login_failed( $username ) {
        if ( get_transient( $this->transient_name ) ) {
            $datas = get_transient( $this->transient_name );
            $datas['tried']++;

            if ( $datas['tried'] <= $this->failed_login_limit )
                set_transient( $this->transient_name, $datas , $this->lockout_duration );
        } else {
            $datas = array(
                'tried'     => 1
            );
            set_transient( $this->transient_name, $datas , $this->lockout_duration );
        }
    }


    /**
     * Return difference between 2 given dates
     * @param  int      $time   Date as Unix timestamp
     * @return string           Return string
     */
    private function compareDate( $time ) {
        if ( ! $time )
            return;

        $right_now = time();

        $diff = abs( $right_now - $time );

        $second = 1;
        $minute = $second * 60;
        $hour = $minute * 60;
        $day = $hour * 24;

        if ( $diff < $minute )
            return floor( $diff / $second ) . ' secondes';

        if ( $diff < $minute * 2 )
            return "about 1 minute ago";

        if ( $diff < $hour )
            return floor( $diff / $minute ) . ' minutes';

        if ( $diff < $hour * 2 )
            return 'about 1 hour';

        return floor( $diff / $hour ) . ' hours';
    }
}