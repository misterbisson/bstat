<?php

class bStat_Daemon
{
	function __construct()
	{
		global $wpdb;

		$this->hits_incoming = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_incoming';
		$this->hits_terms = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_terms';
		$this->hits_targets = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_targets';
		$this->hits_searchphrases = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_searchphrases';
//		$this->hits_searchwords = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_searchwords';
		$this->hits_sessions = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_sessions';
		$this->hits_shistory = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_shistory';
		$this->hits_pop = ( empty( $wpdb->base_prefix ) ? $wpdb->prefix : $wpdb->base_prefix ) .'bsuite4_hits_pop';

		// get a wpdb object to work with, can be the default or a custom instance
		$this->get_db();

		// cron
		add_filter( 'cron_schedules' , array( $this , 'cron_reccurences' ));
		add_filter( 'bsuite_interval' , array( $this , 'migrator' ));

		// track hits via admin ajax
		add_action( 'wp_ajax_bstat_incoming' , array( $this , 'incoming' )); 
		add_action( 'wp_ajax_nopriv_bstat_incoming' , array( $this , 'incoming' )); 

		// activation and menu hooks
		register_activation_hook( __FILE__ , array( $this, 'activate' ));
	}

	function get_db()
	{
		if( ! empty( $this->db ))
			return $this->db;

		global $wpdb;

		if( defined( 'BSTAT_DB_NAME' ))
			$this->db = new wpdb( BSTAT_DB_USER, BSTAT_DB_PASSWORD, BSTAT_DB_NAME, BSTAT_DB_HOST );
		else
			$this->db = $wpdb;

		return $this->db;
	}

	function incoming()
	{
		// get prereqs
		global $wpdb;

		// send headers
		@header( 'Content-Type: text/javascript; charset='. get_option( 'blog_charset' ));
		nocache_headers();

		// get or start a session
		if( $_COOKIE['bsuite_session'] )
			$session = $_COOKIE['bsuite_session'];
		else
			$session = md5( uniqid( rand(), TRUE ));

		// set or update the cookie to expire 30 minutes from now
		setcookie ( 'bsuite_session' , $session , time()+1800 , '/' );

		// insert the hit
		$this->db->insert( $this->hits_incoming, array( 
			'in_type' => '0', 
			'in_session' => $session, 
			'in_blog' => get_current_blog_id(), 
			'in_to' => $_SERVER['HTTP_REFERER'] , 
			'in_from' => $_REQUEST['pr'], 
			'in_extra' => serialize( array(
				'ip' => $_SERVER["REMOTE_ADDR"],
				'br' => $_REQUEST['br'],
				'bb' => $_REQUEST['bb'],
				'bl' => $_REQUEST['bl'],
				'bc' => $_REQUEST['bc'],
				'ba' => urlencode( $_SERVER['HTTP_USER_AGENT'] )
			)),
		));

		// output useful data
		if( $searchterms = $this->get_search_terms( $_REQUEST['pr'] ))
		{
			// output a json object to highlight search terms
			echo "var bsuite_json = {terms:['". implode("','", array_map('htmlentities',$searchterms) ) ."']};";
			echo "jQuery(function(){bsuite_highlight(bsuite_json);});";

/*
			foreach( $wpdb->get_col( $this->searchsmart_query( implode( $searchterms, ' ' ))) as $post)
				$related_posts[] = '<a href="'. get_permalink( $post ) .'" title="Permalink to related post: '. get_the_title( $post ) .'">'.  get_the_title( $post ) .'</a>';
			if( count( $related_posts ))
				echo 'bsuite_related_posts('. json_encode( $related_posts ) .");\n";
*/
		}

//phpinfo();
/*
print_r($wpdb->queries);
print_r( array( 'count_queries' => $wpdb->num_queries , 'count_seconds' => timer_stop(1) ));
*/
	}

	function get_term( $id )
	{
		if ( !$name = wp_cache_get( $id, 'bstat_terms' ))
		{
			$name = $this->db->get_var("SELECT name FROM $this->hits_terms WHERE ". $this->db->prepare( "term_id = %s", (int) $id ));
			wp_cache_add( $id, $name, 'bstat_terms', 0 );
		}
		return $name;
	}

	function is_term( $term )
	{
		$cache_key = md5( substr( $term, 0, 255 ) );
		if ( !$term_id = wp_cache_get( $cache_key, 'bstat_termids' ))
		{
			$term_id = (int) $this->db->get_var("SELECT term_id FROM $this->hits_terms WHERE ". $this->db->prepare( "name = %s", substr( $term, 0, 255 )));
			wp_cache_add( $cache_key, $term_id, 'bstat_termids', 0 );
		}
		return $term_id;
	}

	function insert_term( $term )
	{
		if ( !$term_id = $this->is_term( $term ))
		{
			if ( false === $this->db->insert( $this->hits_terms, array( 'name' => $term )))
			{
				new WP_Error('db_insert_error', __('Could not insert term into the database'), $this->db->last_error);
				return FALSE;
			}
			$term_id = (int) $this->db->insert_id;
		}
		return $term_id;
	}

	function is_session( $session_cookie )
	{
		if ( !$sess_id = wp_cache_get( $session_cookie, 'bstat_sessioncookies' ))
		{
			$sess_id = (int) $this->db->get_var("SELECT sess_id FROM $this->hits_sessions WHERE ". $this->db->prepare( "sess_cookie = %s", $session_cookie ));
			wp_cache_add( $session_cookie, $sess_id, 'bstat_sessioncookies', 10800 );
		}
		return $sess_id;
	}

	function insert_session( $session )
	{
		$s = array();
		if ( !$session_id = $this->is_session( $session->in_session ))
		{
			$this->session_new = TRUE;

			$s['sess_cookie'] = $session->in_session;
			$s['sess_date'] = $session->in_time;

			$se = unserialize( $session->in_extra );
			$s['sess_ip'] = $se['ip'];
			$s['sess_br'] = $se['br'];
			$s['sess_bb'] = $se['bb'];
			$s['sess_bl'] = $se['bl'];
			$s['sess_ba'] = urldecode( $se['ba'] );
			// could use INET_ATON and INET_NTOA to reduce storage requirements for the IP address,
			// but it's not human readable when browsing the table

			if ( false === $this->db->insert( $this->hits_sessions, $s ))
			{
				new WP_Error( 'db_insert_error', __( 'Could not insert session into the database' ), $this->db->last_error);
				return FALSE;
			}
			$session_id = (int) $this->db->insert_id;

			wp_cache_add( $session->in_session, $session_id, 'bstat_sessioncookies', 10800 );
		}
		return $session_id;
	}

	function migrator( $debug = FALSE )
	{

		$options = get_option( 'bsuite_load_max' );

		// check system load before continuing
		if( $this->get_loadavg() < $options['load-max'] )
			return FALSE;

if( $debug )
	echo "<h2>Start</h2>";

		if( !$this->get_lock( 'migrator' ))
			return FALSE;

		// also use the options table
		if ( get_option( 'bsuite_doing_migration') > time() )
			return FALSE;

		update_option( 'bsuite_doing_migration', time() + 250 );
		$status = get_option ( 'bsuite_doing_migration_status' );

if( $debug )
	echo "<h2>Get Locks</h2>";

		$getcount =  1 < get_option( 'bsuite_migration_count' ) ? absint( get_option( 'bsuite_migration_count' )) : 100;
		$since = date('Y-m-d H:i:s', strtotime('-1 minutes'));

		$res = $this->db->get_results( "SELECT * 
			FROM $this->hits_incoming
			WHERE in_time < '$since'
			ORDER BY in_time ASC
			LIMIT $getcount" );

		$status['count_incoming'] = count( $res );
		update_option( 'bsuite_doing_migration_status', $status );

if( $debug )
	echo "<h2>Got {$status['count_incoming']} records from incoming table</h2>";

		foreach( $res as $hit ){
			$object_id = $object_type = $session_id = 0;

if( $debug )
	echo "<h2>Bamn! 1</h2>";

			if( !strlen( $hit->in_to ))
				$hit->in_to = get_option( 'siteurl' ) .'/';

			if( $hit->in_session )
				$session_id = $this->insert_session( $hit );

if( $debug )
	echo "<h2>Bamn! 2</h2>";

			$hit->in_blog = absint( $hit->in_blog );
			$switch_blog = FALSE;
			if( function_exists( 'switch_to_blog' ) && get_current_blog_id() <> $hit->in_blog )
			{
				$switch_blog = TRUE;
				switch_to_blog( $hit->in_blog );
			}

if( $debug )
	echo "<h2>Bamn! 3 $hit->in_to</h2>";

			$object_id = url_to_postid( $hit->in_to );

			// determine the target
			if( ( 1 > $object_id ) || (('posts' <> get_option( 'show_on_front' )) && $object_id == get_option( 'page_on_front' )) )
			{
				$object_id = $this->insert_term( $hit->in_to );
				$object_type = 1;
			}
			$targets[] = "($hit->in_blog, $object_id, $object_type, 1, '$hit->in_time')";

if( $debug )
	echo "<h2>Bamn! 4</h2>";

			// look for search words
			if( ( $referers = implode( $this->get_search_terms( $hit->in_from ), ' ') ) && ( 0 < strlen( $referers )))
			{
				$term_id = $this->insert_term( $referers );
				$searchwords[] = "($hit->in_blog, $object_id, $object_type, $term_id, 1)";
			}

			if( $session_id )
			{
				if( $referers )
					$shistory[] = "($session_id, $hit->in_blog, $term_id, 2)";

				if( $this->session_new )
				{
					$in_from = $this->insert_term( $hit->in_from );
					if( $referers )
						$shistory[] = "($session_id, $hit->in_blog, $in_from, 3)";
				}

				$shistory[] = "($session_id, $hit->in_blog, $object_id, $object_type)";
			}

if( $debug )
	echo "<h2>Bamn! 5</h2>";

			if( $switch_blog && function_exists( 'restore_current_blog' ) )
				restore_current_blog( $hit->in_blog );
		}

		$status['count_targets'] = count( $targets );
		$status['count_searchwords'] = count( $searchwords );
		$status['count_shistory'] = count( $shistory );
		update_option( 'bsuite_doing_migration_status', $status );

if( $debug )
	echo "<h2>Found {$status['count_targets']} URL targets, {$status['count_searchwords']} search phrases, and {$status['count_shistory']} sessions</h2>";

		if( count( $targets ) && !$status['did_targets'] )
		{
			if ( false === $this->db->query( "INSERT INTO $this->hits_targets (object_blog, object_id, object_type, hit_count, hit_date) VALUES ". implode( $targets, ',' ) ." ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;" ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_target into the database'), $this->db->last_error);

			$status['did_targets'] = 1 ;
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $searchwords ) && !$status['did_searchwords'] )
		{
			if ( false === $this->db->query( "INSERT INTO $this->hits_searchphrases (object_blog, object_id, object_type, term_id, hit_count) VALUES ". implode( $searchwords, ',' ) ." ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;" ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_searchword into the database'), $this->db->last_error);

			$status['did_searchwords'] = 1;
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $shistory ) && !$status['did_shistory'] )
		{
			if ( false === $this->db->query( "INSERT INTO $this->hits_shistory (sess_id, object_blog, object_id, object_type) VALUES ". implode( $shistory, ',' ) .';' ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_session_history into the database'), $this->db->last_error);

			$status['did_shistory'] = count( $shistory );
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $res ))
		{
			if ( false === $this->db->query( "DELETE FROM $this->hits_incoming WHERE in_time < '$since' ORDER BY in_time ASC LIMIT ". count( $res ) .';'))
				return new WP_Error('db_insert_error', __('Could not clean up the incoming stats table'), $this->db->last_error);
			if( $getcount > count( $res ))
				$this->db->query( "OPTIMIZE TABLE $this->hits_incoming;");
		}

if( $debug )
	echo "<h2>Deleted records from incoming table</h2>";

		if ( get_option( 'bsuite_doing_migration_popr') < time() && $this->get_lock( 'popr' ))
		{
			if ( get_option( 'bsuite_doing_migration_popd') < time() && $this->get_lock( 'popd' ) )
			{
				$this->db->query( "TRUNCATE $this->hits_pop" );
				$this->db->query( "INSERT INTO $this->hits_pop (blog_id, post_id, date_start, hits_total)
					SELECT object_blog AS blog_id, object_id AS post_id, MIN(hit_date) AS date_start, SUM(hit_count) AS hits_total
					FROM $this->hits_targets
					WHERE object_type = 0
					AND hit_date >= DATE_SUB( NOW(), INTERVAL 45 DAY )
					GROUP BY object_id" );
				update_option( 'bsuite_doing_migration_popd', time() + 64800 );
			}
			$this->db->query( "UPDATE $this->hits_pop p
				LEFT JOIN (
					SELECT object_blog, object_id, COUNT(*) AS hit_count
					FROM (
						SELECT sess_id, sess_date
						FROM (
							SELECT sess_id, sess_date
							FROM $this->hits_sessions
							ORDER BY sess_id DESC
							LIMIT 12500
						) a
						WHERE sess_date >= DATE_SUB( NOW(), INTERVAL 1 DAY )
					) s
					LEFT JOIN $this->hits_shistory h ON h.sess_id = s.sess_id
					WHERE h.object_type = 0
					GROUP BY object_blog, object_id
				) h ON ( h.object_id = p.post_id AND h.object_blog = p.blog_id )
				SET hits_recent = h.hit_count" );
			update_option( 'bsuite_doing_migration_popr', time() + 1500 );
		}

/*
		$posts = $this->db->get_results("SELECT object_id, AVG(hit_count) AS hit_avg
				FROM $this->hits_targets
				WHERE hit_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY)
				AND object_type = 0
				GROUP BY object_id
				ORDER BY object_id ASC", ARRAY_A);
		$avg = array();
		foreach($posts as $post)
			$avg[$post['object_id']] = $post['hit_avg'];

		$posts = $this->db->get_results("SELECT object_id, hit_count * (86400/TIME_TO_SEC(TIME(NOW()))) AS hit_now
				FROM $this->hits_targets
				WHERE hit_date = CURDATE()
				AND object_type = 0
				ORDER BY object_id ASC", ARRAY_A);
		$now = array();
		foreach($posts as $post)
			$now[$post['object_id']] = $post['hit_now'];

		$diff = array();
		foreach($posts as $post)
			$diff[$post['object_id']] = intval(($now[$post['object_id']] - $avg[$post['object_id']]) * 1000 );

		$win = count(array_filter($diff, create_function('$a', 'if($a > 0) return(TRUE);')));
		$lose = count($diff) - $win;

		$sort = array_flip($diff);
		ksort($sort);

		if(!empty($sort)){
			foreach(array_slice(array_reverse($sort), 0, $detail_lines) as $object_id){
				echo '<li><a href="'. get_permalink($object_id) .'">'. get_the_title($object_id) .'</a><br><small>Up: '. number_format($diff[$object_id] / 1000, 0) .' Avg: '. number_format($avg[$object_id], 0) .' Today: '. number_format($now[$object_id], 0) ."</small></li>\n";
			}
		}
*/

//print_r($this->db->queries);

if( $debug )
	echo "<h2>Done</h2>";

		update_option( 'bsuite_doing_migration', 0 );
		update_option( 'bsuite_doing_migration_status', array() );
		return TRUE;
	}

	function get_search_engine( $ref )
	{
		// a lot of inspiration and code for this function was taken from
		// Search Hilite by Ryan Boren and Matt Mullenweg
		global $wp_query;
		if( empty( $ref ))
			return FALSE;

		$referer = urldecode( $ref );
		if (preg_match('|^https?://(www)?\.?google.*|i', $referer))
			return 'google';

		if (preg_match('|^https?://(www)?\.?bing.*|i', $referer))
			return 'bing';

		if (preg_match('|^https?://search\.yahoo.*|i', $referer))
			return 'yahoo';

		$home = parse_url( get_settings( 'siteurl' ));
		$ref = parse_url( $referer );
		if ( strpos( ' '. $ref['host'] , $home['host'] ))
			return 'internal';

		return FALSE;
	}

	function get_search_terms( $ref )
	{
		// a lot of inspiration and code for this function was taken from
		// Search Hilite by Ryan Boren and Matt Mullenweg
//		if( !$engine = $this->get_search_engine( $ref ))
//			return FALSE;

		$engine = $this->get_search_engine( $ref );

		$referer = parse_url( $ref );
		parse_str( $referer['query'], $query_vars );

		$query_array = array();
		switch ($engine) {
		case 'google':
		case 'bing':
			if( $query_vars['q'] )
				$query_array = explode(' ', urldecode( $query_vars['q'] ));
			break;

		case 'yahoo':
			if( $query_vars['p'] )
				$query_array = explode(' ', urldecode( $query_vars['p'] ));
			break;

		case 'internal':
			if( $query_vars['s'] )
				$query_array = explode(' ', urldecode( $query_vars['s'] ));

			// also need to handle the case where a search matches the /search/ pattern
			break;
		}

		$query_array = array_filter( array_map( array(&$this, 'trimquotes') , $query_array ));

		return $query_array;
	}

	//
	// cron utility functions
	//
	function cron_reccurences( $schedules )
	{
		$schedules['bsuite_interval'] = array('interval' => ( get_option( 'bsuite_migration_interval' ) ? get_option( 'bsuite_migration_interval' ) : 90 ), 'display' => __( 'bSuite interval. Set in bSuite options page.' ));
		return $schedules;
	}

	function cron_register()
	{
		// take a look at Glenn Slaven's tutorial on WP's psudo-cron:
		// http://blog.slaven.net.au/archives/2007/02/01/timing-is-everything
		wp_clear_scheduled_hook('bsuite_interval');
		wp_schedule_event( time() + 120, 'bsuite_interval', 'bsuite_interval' );
	}
	// end cron functions



	function get_lock( $lock )
	{

		if( !$lock = preg_replace( '/[^a-z|0-9|_]/i', '', str_replace( ' ', '_', $lock )))
			return FALSE;

		// use a named mysql lock to prevent simultaneous execution
		// locks automatically drop when the connection is dropped
		// http://dev.mysql.com/doc/refman/5.0/en/miscellaneous-functions.html#function_get-lock
		if( 0 == $this->db->get_var( 'SELECT GET_LOCK("'. $this->db->prefix . 'bsuitelock_'. $lock .'", ".001")' ))
			return FALSE;
		return TRUE;
	}

	function release_lock( $lock )
	{

		if( !$lock = preg_replace( '/[^a-z|0-9|_]/i', '', str_replace( ' ', '_', $lock )))
			return FALSE;

		if( 0 == $this->db->get_var( 'SELECT RELEASE_LOCK("'. $this->db->prefix . 'bsuitelock_'. $lock .'", ".001")' ))
			return FALSE;

		return TRUE;
	}

	//
	// loadaverage related functions
	//
	function get_loadavg()
	{
		static $result = FALSE;
		if( $result )
			return $result;

		if( function_exists( 'sys_getloadavg' ))
			$load_avg = sys_getloadavg();
		else
			$load_avg = $this->sys_getloadavg();

		return round( $load_avg[0] , 2 );
	}

	function sys_getloadavg()
	{
		// the following code taken from tom pittlik's comment at
		// http://php.net/manual/en/function.sys-getloadavg.php
		$str = substr( strrchr( shell_exec( 'uptime' ),':' ),1 );
		$avs = array_map( 'trim', explode( ',', $str ));
		return $avs;
	}
	// end load average related functions

	// administrivia
	function activate()
	{
		update_option( 'bsuite_doing_migration' , time() + 300 );

		$this->createtables();
		$this->cron_register();
	}

	function createtables()
	{

		$charset_collate = '';
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') )
		{
			if ( ! empty($this->db->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $this->db->charset";
			if ( ! empty($this->db->collate) )
				$charset_collate .= " COLLATE $this->db->collate";
		}

		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

		dbDelta("
			CREATE TABLE $this->hits_incoming (
				in_time timestamp NOT NULL default CURRENT_TIMESTAMP,
				in_type tinyint(4) NOT NULL default '0',
				in_session varchar(32) default '',
				in_blog bigint(20) NOT NULL,
				in_to text NOT NULL,
				in_from text,
				in_extra text
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_terms (
				term_id bigint(20) NOT NULL auto_increment,
				name varchar(255) NOT NULL default '',
				status varchar(40) NOT NULL,
				PRIMARY KEY  (term_id),
				UNIQUE KEY name_uniq (name),
				KEY name (name(8)),
				KEY status (status(1))
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_targets (
				object_blog bigint(20) NOT NULL,
				object_id bigint(20) unsigned NOT NULL default '0',
				object_type smallint(6) NOT NULL,
				hit_count smallint(6) unsigned NOT NULL default '0',
				hit_date date NOT NULL default '0000-00-00',
				PRIMARY KEY  (object_id,object_type,hit_date)
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_searchphrases (
				object_blog bigint(20) NOT NULL,
				object_id bigint(20) unsigned NOT NULL default '0',
				object_type smallint(6) NOT NULL,
				term_id bigint(20) unsigned NOT NULL default '0',
				hit_count smallint(6) unsigned NOT NULL default '0',
				PRIMARY KEY  (object_id,object_type,term_id),
				KEY term_id (term_id)
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_sessions (
				sess_id bigint(20) NOT NULL auto_increment,
				sess_cookie varchar(32) NOT NULL default '',
				sess_date datetime default NULL,
				sess_ip varchar(16) NOT NULL default '',
				sess_bl varchar(8) default '',
				sess_bb varchar(24) default '',
				sess_br varchar(24) default '',
				sess_ba varchar(200) default '',
				PRIMARY KEY  (sess_id),
				UNIQUE KEY sess_cookie_uniq (sess_cookie),
				KEY sess_cookie (sess_cookie(2))
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_shistory (
				sess_id bigint(20) NOT NULL auto_increment,
				object_blog bigint(20) NOT NULL,
				object_id bigint(20) NOT NULL,
				object_type smallint(6) NOT NULL,
				KEY sess_id (sess_id),
				KEY object_id (object_id,object_type)
			) ENGINE=MyISAM $charset_collate
			");

		dbDelta("
			CREATE TABLE $this->hits_pop (
				blog_id bigint(20) NOT NULL,
				post_id bigint(20) NOT NULL,
				date_start date NOT NULL,
				hits_total bigint(20) NOT NULL,
				hits_recent int(10) NOT NULL
			) ENGINE=MyISAM $charset_collate
			");
	}
}

// now instantiate this object
$bsuite = new bStat_Daemon;
