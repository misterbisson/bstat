<?php

/**
 * Class GO_bStat_WPCore
 */
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

		bstat()->db()->insert( $this->footstep( $data ) );
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

		bstat()->db()->insert( $this->footstep( $data ) );
	}//end user_sign_in

	/**
	 * prepare all required data for writing to bStat
	 *
	 * @param $data data collected via the hooks
	 *
	 * @return object $footstep data to be recorded in bStat
	 */
	public function footstep( $data )
	{
		// set the timezone to UTC for the later strtotime() call,
		// preserve the old timezone so we can set it back when done
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		$footstep = (object) array(
			'post'      => 1,
			'blog'      => bstat()->get_blog(),
			'user'      => $data['user_id'],
			'x1'        => bstat()->get_variation( 'x1' ),
			'x2'        => bstat()->get_variation( 'x2' ),
			'x3'        => bstat()->get_variation( 'x3' ),
			'x4'        => bstat()->get_variation( 'x4' ),
			'x5'        => bstat()->get_variation( 'x5' ),
			'x6'        => bstat()->get_variation( 'x6' ),
			'x7'        => bstat()->get_variation( 'x7' ),
			'component' => 'wpcore',
			'action'    => $data['action'],
			'timestamp' => time(),
			'session'   => bstat()->get_session(),
			'info'      => implode( '|', $data['info'] ),
		);

		date_default_timezone_set( $old_tz );

		return $footstep;
	}//end footstep
}//end class

/**
 * @return GO_bStat_WPCore
 */
function bstat_wpcore()
{
	global $bstat_wpcore;

	if ( ! $bstat_wpcore )
	{
		$bstat_wpcore = new GO_bStat_WPCore;
	}

	return $bstat_wpcore;
} // end bstat_wpcore