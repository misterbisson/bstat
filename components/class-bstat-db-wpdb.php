<?php
class bStat_Db_Wpdb extends bStat_Db
{
	public $wpdb = FALSE;
	public $errors = array();
	public $queries = array();

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

		// searches default to IN or =, but prepending the $for with a - will make them do a NOT IN or !=
		$not = ' ';
		if ( '-' === $for{0} )
		{
			$not = ' NOT ';
			$for = trim( $for, '-' );
		}

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
				$where = 'WHERE post' . $not . 'IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'sessions';
				}
				break;

			case 'blog':
			case 'blogs':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE blog' . $not . 'IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'sessions';
				}
				break;

			case 'user':
			case 'users':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE user' . $not . 'IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'session':
			case 'sessions':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE session' . $not . 'IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'mixedusers':
				$users = array_filter( array_map( 'absint', array_filter( $ids, 'is_numeric' ) ) );
				$sessions = array_filter( array_map( 'sanitize_title_with_dashes', array_filter( $ids, 'is_string' ) ) );
				// @TODO the NOT version of this query is not logically correct as written here. THe OR should become an AND, I think
				$where = 'WHERE 1=1 AND ( user' . $not . 'IN ("' . implode( '","', $users ) . '") OR session' . $not . 'IN ("' . implode( '","', $sessions ) . '") )';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

/*
@TODO: this will need to be refactored to support the new A/B test definition
			case 'group':
			case 'groups':
				$ids = array_filter( array_map( 'absint', $ids ) );
				$where = 'WHERE group IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;
*/

			case 'component':
			case 'components':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE component' . $not . 'IN ("' . implode( '","', $ids ) . '")';
				if ( ! $return )
				{
					$return = 'posts';
				}
				break;

			case 'action':
			case 'actions':
				$ids = array_filter( array_map( 'sanitize_title_with_dashes', $ids ) );
				$where = 'WHERE action' . $not . 'IN ("' . implode( '","', $ids ) . '")';
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
				// @TODO: this query totally won't work, note the field name. It'll need a new sanitization plan and new query
				$where = 'WHERE action' . $not . 'IN ("' . implode( '","', $ids ) . '")';
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
				$order = 'ORDER BY date DESC, time DESC';
				$convert_date_and_time_to_timestamp = TRUE;
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
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'blog':
			case 'blogs':
				$select = 'SELECT blog, COUNT(1) AS hits';
				$group = 'GROUP BY blog';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;


			case 'user':
			case 'users':
				$select = 'SELECT user, COUNT(1) AS hits';
				$group = 'GROUP BY user';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'session':
			case 'sessions':
				$select = 'SELECT session, COUNT(1) AS hits';
				$group = 'GROUP BY session';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'mixedusers':
				return array_merge(
					(array) $this->_select( $for, $ids, 'users', $return_format, $limit, $filter ),
					(array) $this->_select( $for, $ids, 'sessions', $return_format, $limit, $filter )
				);
				break;

			case 'x1':
				$select = 'SELECT `x1`, COUNT(1) AS hits';
				$group = 'GROUP BY `x1`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x2':
				$select = 'SELECT `x2`, COUNT(1) AS hits';
				$group = 'GROUP BY `x2`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x3':
				$select = 'SELECT `x3`, COUNT(1) AS hits';
				$group = 'GROUP BY `x3`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x4':
				$select = 'SELECT `x4`, COUNT(1) AS hits';
				$group = 'GROUP BY `x4`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x5':
				$select = 'SELECT `x5`, COUNT(1) AS hits';
				$group = 'GROUP BY `x5`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x6':
				$select = 'SELECT `x6`, COUNT(1) AS hits';
				$group = 'GROUP BY `x6`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'x7':
				$select = 'SELECT `x7`, COUNT(1) AS hits';
				$group = 'GROUP BY `x7`';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'component':
			case 'components':
				$select = 'SELECT component, COUNT(1) AS hits';
				$group = 'GROUP BY component';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'action':
			case 'actions':
				$select = 'SELECT action, COUNT(1) AS hits';
				$group = 'GROUP BY action';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
				break;

			case 'component_and_action':
			case 'action_and_component':
			case 'components_and_actions':
			case 'actions_and_components':
				$select = 'SELECT component, action, COUNT(1) AS hits';
				$group = 'GROUP BY component, action';
				$order = 'ORDER BY hits DESC, date DESC, time DESC';
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
				$date_where = "\n" . 'AND ( `date` >= "' . date( 'Y-m-d', $filter->timestamp->min ) . '" AND `date` <= "' . date( 'Y-m-d', $filter->timestamp->max ) . '" )';
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
		$this->queries[] = $sql = $select . "\nFROM " . $this->activity_table . "\n" . $where . $filter_where . $date_where . "\n" . $group . "\n" . $order . "\nLIMIT " . $limit ."\n";

		if ( 'col' == $return_format )
		{
			return $this->wpdb()->get_col( $sql );
		}

		if ( isset( $convert_date_and_time_to_timestamp ) )
		{
			return $this->date_and_time_to_timestamp( $this->wpdb()->get_results( $sql ) );
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
			// The DB connector can have custom options in the bstat options
			// The class name of the connector is the key to check for those options
			if ( is_object( bstat()->options()->{__CLASS__} ) )
			{
				// typically a custom WPDB object is only useful to isolate DB load from normal WP operations
				$this->wpdb = new wpdb(
					bstat()->options()->{__CLASS__}->db_user,
					bstat()->options()->{__CLASS__}->db_password,
					bstat()->options()->{__CLASS__}->db_name,
					bstat()->options()->{__CLASS__}->db_host
				);
			}
			else
			{
				global $wpdb;
				$this->wpdb = $wpdb;
			}
		}

		return $this->wpdb;
	}

	private function date_and_time_to_timestamp( $input )
	{
		// set the timezone to UTC for the later strtotime() call,
		// preserve the old timezone so we can set it back when done
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		foreach ( $input as $k => $v )
		{
			$input[ $k ]->timestamp = strtotime( $input[ $k ]->date . ' ' . $input[ $k ]->time );
			unset( $input[ $k ]->date, $input[ $k ]->time );
		}

		date_default_timezone_set( $old_tz );

		return $input;
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
				`x1` char(1) DEFAULT NULL,
				`x2` char(1) DEFAULT NULL,
				`x3` char(1) DEFAULT NULL,
				`x4` char(1) DEFAULT NULL,
				`x5` char(1) DEFAULT NULL,
				`x6` char(1) DEFAULT NULL,
				`x7` char(1) DEFAULT NULL,
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