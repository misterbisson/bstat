<?php
class bStat
{

	private $admin   = FALSE;
	public  $db      = FALSE;
	public  $version = 1;

	public function __construct()
	{
	} // END __construct

	public function init()
	{
		wp_register_script( 'bstat', plugins_url( plugin_basename( __DIR__ ) ) . '/js/bstat.js', array( 'jquery' ), $this->version, TRUE );

		if( ! is_admin() )
		{
			wp_enqueue_script( 'bstat' );
			wp_localize_script( 'bstat' , 'bstat' , $this->options );
		}
	} // END init

	// a singleton for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-bstat-admin.php';
			$this->admin = new bStat_Admin();
		}

		return $this->admin;
	} // END admin

	// a singleton for the db object
	public function db()
	{
		if ( ! $this->db )
		{
			require_once __DIR__ . '/class-bstat-db-wpdb.php';
			$this->db = new bStat_Db_Wpdb();
		}

		return $this->db;
	} // END db
}

function bstat()
{
	global $bstat;

	if ( ! $bstat )
	{
		$bstat = new bStat();
	}

	return $bstat;
} // end bstat