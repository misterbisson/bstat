<?php
class bStat_Db_Wpdb extends bStat_Db
{
	public $wpdb = FALSE;
	public $errors = array();

	public function __construct()
	{
		global $wpdb;
		$this->activity_table = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'bstat_activity';
	}

	public function insert_footstep( $footstep )
	{
$this->initial_setup();

		if ( ! $footstep = $this->sanitize_footstep( $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_insert_error', 'Could not sanitize input data' );
			return FALSE;
		}

		 $footstep->date = date( 'Y-m-d', $footstep->timestamp );
		 $footstep->time = date( 'H:i:s', $footstep->timestamp );
		 unset( $footstep->timestamp );

		if ( FALSE === $this->wpdb()->insert( $this->activity_table, (array) $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_insert_error', 'Could not insert footstep into the activity table', $this->wpdb()->last_error );
			return FALSE;
		}

		return TRUE;
	}

	// get the shared wpdb object, or create a new one
	private function wpdb()
	{
		if ( ! $this->wpdb )
		{
			// @TODO: this info needs to be fetched from go-config, rather than a constant
			if ( defined( 'BSTAT_DB_NAME' ) )
			{
				$this->wpdb = new wpdb( BSTAT_DB_USER, BSTAT_DB_PASSWORD, BSTAT_DB_NAME, BSTAT_DB_HOST );
			}
			else
			{
				global $wpdb;
				$this->wpdb = $wpdb;
			}
		}

		return $this->wpdb;
	}

	public function initial_setup()
	{
		$this->createtables();
	}

	private function createtables()
	{
		$charset_collate = '';
		if ( version_compare( mysql_get_server_info(), '4.1.0', '>=' ) )
		{
			if ( ! empty( $this->wpdb()->charset ) )
			{
				$charset_collate = 'DEFAULT CHARACTER SET ' . $this->wpdb()->charset;
			}
			if ( ! empty( $this->wpdb()->collate ) )
			{
				$charset_collate .= ' COLLATE '. $this->wpdb()->collate;
			}
		}

		require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

		dbDelta( "
			CREATE TABLE $this->activity_table (
				`post` int NOT NULL default '0',
				`blog` int NOT NULL default '0',
				`user` int NOT NULL default '0',
				`group` tinyint,
				`component` char(8) NOT NULL default '',
				`action` char(8) NOT NULL default '',
				`date` date NOT NULL default '1970-01-01',
				`time` time NOT NULL default '00:00:00',
				`session` char(32) NOT NULL default '0',
				`info` varchar(180)
			) ENGINE=MyISAM $charset_collate
		" );
	}

}