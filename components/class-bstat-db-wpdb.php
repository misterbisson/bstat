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

	public function _insert( $footstep )
	{
		$footstep->date = date( 'Y-m-d', $footstep->timestamp );
		$footstep->time = date( 'H:i:s', $footstep->timestamp );
		unset( $footstep->timestamp );

		if ( FALSE === $this->wpdb()->insert( $this->activity_table, (array) $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_insert_error', 'Could not insert footstep into activity table', $this->wpdb()->last_error );
			return FALSE;
		}

		return TRUE;
	}

	public function _select( $for, $ids, $return, $return_format, $limit, $filter )
	{

		// starting WHERE clauses
		switch ( $for )
		{
			case NULL:
			case FALSE:
				$where = 'WHERE 1=1';
				if ( ! $return )
				{
					$return = 'all';
				}
				break;
			case 'post':
			case 'posts':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE post IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'sessions';
				}
				break;

			case 'blog':
			case 'blogs':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE blog IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'sessions';
				}
				break;

			case 'user':
			case 'users':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE user IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'session':
			case 'sessions':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE session IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'mixedusers':
				$users = array_filter( array_map( 'absint', array_filter( $ids, 'is_numeric' ) ) );
				$sessions = array_filter( array_map( 'sanitize_title_with_dashes', array_filter( $ids, 'is_string' ) ) );
				$where = 'WHERE 1=1 AND ( user IN ("' . implode( ",", $users ) . '") OR session IN ("' . implode( ",", $sessions ) . '") )';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'group':
			case 'groups':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE group IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'component':
			case 'components':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE component IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'action':
			case 'actions':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE action IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'component_and_action':
			case 'action_and_component':
			case 'components_and_actions':
			case 'actions_and_components':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE action IN ("' . implode( ",", $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			default:
				return FALSE;
		}

		// starting SELECT, GROUP BY, and ORDER BY clauses
		switch ( $return )
		{
			case 'all':
				$select = 'SELECT *';
				$group = '';
				$order = 'ORDER BY date, time DESC';
				break;

			case 'post':
			case 'posts':
				if ( isset( $filter->blog ) )
				{
					$select = 'SELECT post, COUNT(1) AS hits';
					$group = 'GROUP BY post';
				}
				else
				{
					$select = 'SELECT blog, post, COUNT(1) AS hits';
					$group = 'GROUP BY blog, post';
				}
				$order = 'ORDER BY hits DESC';
				break;

			case 'blog':
			case 'blogs':
				$select = 'SELECT blog, COUNT(1) AS hits';
				$group = 'GROUP BY blog';
				$order = 'ORDER BY hits DESC';
				break;


			case 'user':
			case 'users':
				$select = 'SELECT user, COUNT(1) AS hits';
				$group = 'GROUP BY user';
				$order = 'ORDER BY hits DESC';
				break;

			case 'session':
			case 'sessions':
				$select = 'SELECT session, COUNT(1) AS hits';
				$group = 'GROUP BY session';
				$order = 'ORDER BY hits DESC';
				break;

			case 'mixedusers':
				return array_merge( (array) $this->_select( $for, $ids, 'users', $return_format, $limit, $filter ), (array) $this->_select( $for, $ids, 'sessions', $return_format, $limit, $filter ) );
				break;

			case 'group':
			case 'groups':
				$select = 'SELECT group, COUNT(1) AS hits';
				$group = 'GROUP BY group';
				$order = 'ORDER BY hits DESC';
				break;

			case 'component':
			case 'components':
				$select = 'SELECT component, COUNT(1) AS hits';
				$group = 'GROUP BY component';
				$order = 'ORDER BY hits DESC';
				break;

			case 'action':
			case 'actions':
				$select = 'SELECT action, COUNT(1) AS hits';
				$group = 'GROUP BY action';
				$order = 'ORDER BY hits DESC';
				break;

			case 'component_and_action':
			case 'action_and_component':
			case 'components_and_actions':
			case 'actions_and_components':
				$select = 'SELECT component, action, COUNT(1) AS hits';
				$group = 'GROUP BY component, action';
				$order = 'ORDER BY hits DESC';
				break;

			default:
				return FALSE;

		}

		// filtering by date
		if ( is_object( $filter->timestamp ) )
		{
			// time only works within the same day, otherwise we get whole days of results
			if ( date( 'Y-m-d', $filter->timestamp->min ) == date( 'Y-m-d', $filter->timestamp->max ) )
			{
				$date_where = 'AND ( `date` >= "' . date( 'Y-m-d', $filter->timestamp->min ) . '" AND `time` >= "' . date( 'H:i:s', $filter->timestamp->min ) . '" AND `time` <= "' . date( 'H:i:s', $filter->timestamp->max ) . '" )';
			}
			else
			{
				$date_where = 'AND ( `date` >= "' . date( 'Y-m-d', $filter->timestamp->min ) . '" AND `date` <= "' . date( 'Y-m-d', $filter->timestamp->max ) . '" )' . "\n";
			}

			// unset this so it doesn't get in the way later
			unset( $filter->timestamp );
		}
		else
		{
			$date_where = '';
		}

		// filtering by other criteria
		if ( count( (array) $filter ) )
		{
			$filters = array();
			foreach ( (array) $filter as $field => $value )
			{
				$filters[] = $field .' = "' . $value . '"';
			}
			$filter_where = "\nAND " . implode( "\nAND ", $filters );
		}
		else
		{
			$filter_where = '';
		}

		// all the SQL together in one place
		$sql = $select . "\nFROM " . $this->activity_table . "\n" . $where . $filter_where . $date_where . $group . "\n" . $order . "\nLIMIT " . $limit ."\n";

//echo $sql;

		if ( 'col' == $return_format )
		{
			return $this->wpdb()->get_col( $sql );
		}

		return $this->wpdb()->get_results( $sql );
	}

	public function _delete( $footstep )
	{
		$footstep->date = date( 'Y-m-d', $footstep->timestamp );
		$footstep->time = date( 'H:i:s', $footstep->timestamp );
		unset( $footstep->timestamp );

		if ( FALSE === $this->wpdb()->delete( $this->activity_table, (array) $footstep ) )
		{
			$this->errors[] = new WP_Error( 'db_delete_error', 'Could not delete footstep from activity table', $this->wpdb()->last_error );
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
				`post` int unsigned NOT NULL default '0',
				`blog` int unsigned NOT NULL default '0',
				`user` int unsigned NOT NULL default '0',
				`group` tinyint unsigned DEFAULT NULL,
				`component` char(8) NOT NULL default '',
				`action` char(8) NOT NULL default '',
				`date` date NOT NULL default '1970-01-01',
				`time` time NOT NULL default '00:00:00',
				`session` char(32) NOT NULL default '0',
				`info` varchar(180) DEFAULT NULL,
				KEY `date_and_time` (`date`,`time`),
				KEY `component_and_action` (`component`(1),`action`(1)),
				KEY `session` (`session`(2)),
				KEY `blog_and_post` (`blog`,`post`)
			) ENGINE=MyISAM $charset_collate
		" );
	}

}