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

	public function initial_setup()
	{
		$this->createtables();
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

	private function createtables()
	{
		$charset_collate = '';
		if ( version_compare( mysql_get_server_info(), '4.1.0', '>=' ) )
		{
			if ( ! empty( $this->wpdb()->charset ) )
			{
				$charset_collate = "DEFAULT CHARACTER SET $this->wpdb()->charset";
			}
			if ( ! empty( $this->wpdb()->collate ) )
			{
				$charset_collate .= " COLLATE $this->wpdb()->collate";
			}
		}

		require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

		dbDelta( "
			CREATE TABLE $this->hits_incoming (
				in_time timestamp NOT NULL default CURRENT_TIMESTAMP,
				in_type tinyint(4) NOT NULL default '0',
				in_session varchar(32) default '',
				in_blog bigint(20) NOT NULL,
				in_to text NOT NULL,
				in_from text,
				in_extra text
			) ENGINE=MyISAM $charset_collate
		" );
	}

}