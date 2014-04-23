<?php

class GO_bStat_WPCore
{
    public function __construct()
    {
        // log new user accounts
        add_action( 'user_register', array( $this, 'user_register' ) );

        // log user sign-ins
        add_action( 'set_auth_cookie', array( $this, 'user_sign_in' ), 10, 5 );

    } // END __construct

    /**
     * track new user create actions; specific data to the action being tracked is added here
     *
     * @param $user_id (WP User ID - note: not User object)
     */
    public function user_register( $user_id )
    {
        if ( ! $user_id )
        {
            return;
        }

        $data = array(
            'action'      => 'newuser',
            'user_id'     => $user_id,
            'info'        => array(
                'referring_url' => wp_get_referer(),
            ),
        );

        $this->insert( $data );
    }//end user_register

    /**
     * track sign-in actions; specific data to the action being tracked is added here
     *
     * @param $user_id (WP User ID - note: not User object)
     */
    public function user_sign_in( $unused_auth_cookie, $unused_expire, $unused_expiration, $user_id, $unused_scheme )
    {
        $data = array(
            'action'  => 'userauth',
            'user_id' => $user_id,
            'info'    => array(
                'referring_url' => wp_get_referer(),
            ),
        );

        $this->insert( $data );
    }//end user_sign_in

    /**
     * insert common data into bStat
     *
     * @param $data data to be inserted
     */
    public function insert( $data )
    {
        if ( ! $data )
        {
            return;
        }//end if

        // derive and add standard payload (not 'info') fields
        // Get post_id to facilitate tracking via footstep()
        $data['post_id'] = go_subscriptions_bstat()->config( 'go_subscriptions_tracking_id' );

        if ( is_wp_error( $data['post_id'] ) )
        {
            return; // no footstep possible
        }

        $old_tz = date_default_timezone_get();
        date_default_timezone_set( 'UTC' );
        $date = new DateTime();
        $data['date_gmt'] = date_format( $date, 'U' );
        date_default_timezone_set( $old_tz );

        bstat()->db()->insert( $this->footstep( $data ) );
    }//end insert

    /**
     * prepare all required data for writing to bStat
     *
     * @param $data data to be inserted
     */
    public function footstep( $data )
    {
        // set the timezone to UTC for the later strtotime() call,
        // preserve the old timezone so we can set it back when done
        $old_tz = date_default_timezone_get();
        date_default_timezone_set( 'UTC' );

        $footstep = (object) array(
            'post'      => $data['post_id'],
            'blog'      => bstat()->get_blog(),
            'user'      => $data['user_id'],
            'group'     => NULL,
            'component' => 'wpcore',
            'action'    => $data['action'],
            'timestamp' => $data['date_gmt'],
            'session'   => bstat()->get_session(),
            'info'      => implode( '|', $data['info'] ),
        );

        date_default_timezone_set( $old_tz );

        return $footstep;
    }//end footstep
}//end class

function bstat_wpcore()
{
    global $bstat_wpcore;

    if ( ! $bstat_wpcore )
    {
        $bstat_wpcore = new GO_bStat_WPCore;
    }

    return $bstat_wpcore;
} // end bstat_wpcore