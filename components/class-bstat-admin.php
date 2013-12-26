<?php
class bStat_Admin extends bStat
{
	public function __construct()
	{
		add_action( 'wp_ajax_bstat' , array( $this , 'bstat_ajax' ) );
		add_action( 'wp_ajax_nopriv_bstat' , array( $this , 'bstat_ajax' ) );
	}

	public function admin_init()
	{
	}

	public function nonce_field()
	{
		wp_nonce_field( plugin_basename( __FILE__ ) , $this->id_base .'-nonce' );
	}

	public function verify_nonce()
	{
		return wp_verify_nonce( $_POST[ $this->id_base .'-nonce' ] , plugin_basename( __FILE__ ));
	}

	public function get_field_name( $field_name )
	{
		return $this->id_base . '[' . $field_name . ']';
	}

	public function get_field_id( $field_name )
	{
		return $this->id_base . '-' . $field_name;
	}

	public function bstat_ajax()
	{
		// send headers
		header( 'Content-Type: text/javascript; charset='. get_option('blog_charset') );
		nocache_headers();

		// ignore requests without POST data
		if ( ! is_array( $_POST[ $this->id_base ] ) )
		{
			echo 'no input data!';
			die;
		}

		// ignore requests with invalid signatures
		if ( ! $this->validate_signature( $_POST[ $this->id_base ] ) )
		{
			echo 'invalid signature!';
			die;
		}

		// format the inputted data to insert (it's sanitized in the DB class)
		$footstep = stripslashes_deep( $_POST[ $this->id_base ] );
		$this->db()->insert_footstep( array(
			'post'      => $footstep['post'],
			'blog'      => $footstep['blog'],
			'user'      => get_current_user_id(),
			'group'     => $footstep['group'],
			'component' => $footstep['component'],
			'action'    => $footstep['action'],
			'timestamp' => time(),
			'session'   => $this->get_session(),
			'info'      => $footstep['info'],
		) );
	}
}