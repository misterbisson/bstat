<?php

class bStat
{

	function bStat()
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
		
		$this->loadavg = $this->get_loadavg();

		// establish web path to this plugin's directory
		$this->path_web = plugins_url( basename( dirname( __FILE__ )));

		$this->is_quickview = FALSE;

		// register and queue javascripts
		wp_register_script( 'bsuite', $this->path_web . '/js/bsuite.js', array('jquery'), '20080503' , TRUE );
		wp_enqueue_script( 'bsuite' );

		// jQuery text highlighting plugin http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html
		wp_register_script( 'highlight', $this->path_web . '/js/jquery.highlight-1.js', array('jquery'), '1' , TRUE );
		wp_enqueue_script( 'highlight' );

		// is this wpmu?
		if( function_exists( 'is_site_admin' ))
			$this->is_mu = TRUE;
		else
			$this->is_mu = FALSE;

		// default CSS
		if( get_option( 'bsuite_insert_css' )){
			add_action('wp_head', 'wp_print_styles', 9);
			wp_register_style( 'bsuite-default', $this->path_web .'/css/default.css' );
			wp_enqueue_style( 'bsuite-default' );
		}

		// bstat
		add_action('get_footer', array(&$this, 'bstat_js'));

		// cron
		add_filter('cron_schedules', array(&$this, 'cron_reccurences'));
		if( $this->loadavg < get_option( 'bsuite_load_max' )){ // only do cron if load is low-ish
			add_filter('bsuite_interval', array(&$this, 'bstat_migrator'));
		}

		add_action('widgets_init', array(&$this, 'widgets_register'));


		// activation and menu hooks
		register_activation_hook(__FILE__, array(&$this, 'activate'));
		add_action('admin_menu', array(&$this, 'admin_menu_hook'));
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('init', array(&$this, 'init'));
		// end register WordPress hooks


	}

	function admin_init(){

		register_setting( 'bsuite-options', 'bsuite_insert_related', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_insert_sharelinks', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_searchsmart', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_swhl', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_who_can_edit' );
		register_setting( 'bsuite-options', 'bsuite_managefocus_month', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_managefocus_author', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_insert_css', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_migration_interval', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_migration_count', 'absint' );
		register_setting( 'bsuite-options', 'bsuite_load_max', 'absint' );
	}


	function admin_menu_hook() {
		// add the options page
		add_options_page('bSuite Settings', 'bSuite', 'manage_options', plugin_basename( dirname( __FILE__ )) .'/ui_options.php' );

		// the bstat reports are handled in a seperate file
		add_submenu_page('index.php', 'bSuite bStat Reports', 'bStat Reports', 'edit_posts', plugin_basename( dirname( __FILE__ )) .'/ui_stats.php' );
	}


	//
	// Stats Related
	//
	function bstat_js() {
		if( !$this->didstats ){
?>
<script type="text/javascript">
var bsuite_api_location='<?php echo $this->path_web . '/worker.php' ?>';
</script>
<noscript><img src="<?php echo $this->path_web . '/worker.php' ?>" width="1" height="1" alt="stat counter" /></noscript>
<?php
		}
	}

	function bstat_get_term( $id ) {
		global $wpdb;

		if ( !$name = wp_cache_get( $id, 'bstat_terms' )) {
			$name = $wpdb->get_var("SELECT name FROM $this->hits_terms WHERE ". $wpdb->prepare( "term_id = %s", (int) $id ));
			wp_cache_add( $id, $name, 'bstat_terms', 0 );
		}
		return( $name );
	}

	function bstat_is_term( $term ) {
		global $wpdb;

		$cache_key = md5( substr( $term, 0, 255 ) );
		if ( !$term_id = wp_cache_get( $cache_key, 'bstat_termids' )) {
			$term_id = (int) $wpdb->get_var("SELECT term_id FROM $this->hits_terms WHERE ". $wpdb->prepare( "name = %s", substr( $term, 0, 255 )));
			wp_cache_add( $cache_key, $term_id, 'bstat_termids', 0 );
		}
		return( $term_id );
	}

	function bstat_insert_term( $term ) {
		global $wpdb;

		if ( !$term_id = $this->bstat_is_term( $term )) {
			if ( false === $wpdb->insert( $this->hits_terms, array( 'name' => $term ))){
				new WP_Error('db_insert_error', __('Could not insert term into the database'), $wpdb->last_error);
				return( 1 );
			}
			$term_id = (int) $wpdb->insert_id;
		}
		return( $term_id );
	}

	function bstat_is_session( $session_cookie ) {
		global $wpdb;

		if ( !$sess_id = wp_cache_get( $session_cookie, 'bstat_sessioncookies' )) {
			$sess_id = (int) $wpdb->get_var("SELECT sess_id FROM $this->hits_sessions WHERE ". $wpdb->prepare( "sess_cookie = %s", $session_cookie ));
			wp_cache_add( $session_cookie, $sess_id, 'bstat_sessioncookies', 10800 );
		}
		return($sess_id);
	}

	function bstat_insert_session( $session ) {
		global $wpdb;

		$s = array();
		if ( !$session_id = $this->bstat_is_session( $session->in_session )) {
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

			if ( false === $wpdb->insert( $this->hits_sessions, $s )){
				new WP_Error('db_insert_error', __('Could not insert session into the database'), $wpdb->last_error);
				return( FALSE );
			}
			$session_id = (int) $wpdb->insert_id;

			wp_cache_add($session->in_session, $session_id, 'bstat_sessioncookies', 10800 );
		}
		return( $session_id );
	}

	function bstat_migrator( $debug = FALSE )
	{
		if( $debug )
			echo "<h2>Start</h2>";

		global $wpdb, $blog_id;

		if( !$this->get_lock( 'migrator' ))
			return( TRUE );

		// also use the options table
		if ( get_option( 'bsuite_doing_migration') > time() )
			return( TRUE );

		update_option( 'bsuite_doing_migration', time() + 250 );
		$status = get_option ( 'bsuite_doing_migration_status' );

		if( $debug )
			echo "<h2>Get Locks</h2>";

		$getcount =  1 < get_option( 'bsuite_migration_count' ) ? absint( get_option( 'bsuite_migration_count' )) : 100;
		$since = date('Y-m-d H:i:s', strtotime('-1 minutes'));

		$res = $wpdb->get_results( "SELECT * 
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
				$session_id = $this->bstat_insert_session( $hit );

if( $debug )
	echo "<h2>Bamn! 2</h2>";

			$hit->in_blog = absint( $hit->in_blog );
			$switch_blog = FALSE;
			if( function_exists( 'switch_to_blog' ) && absint( $blog_id ) <> $hit->in_blog )
			{
				$switch_blog = TRUE;
				switch_to_blog( $hit->in_blog );
			}

if( $debug )
	echo "<h2>Bamn! 3 $hit->in_to</h2>";

			$object_id = url_to_postid( $hit->in_to );

			// determine the target
			if( ( 1 > $object_id ) || (('posts' <> get_option( 'show_on_front' )) && $object_id == get_option( 'page_on_front' )) ){
				$object_id = $this->bstat_insert_term( $hit->in_to );
				$object_type = 1;
			}
			$targets[] = "($hit->in_blog, $object_id, $object_type, 1, '$hit->in_time')";

if( $debug )
	echo "<h2>Bamn! 4</h2>";

			// look for search words
			if( ( $referers = implode( $this->get_search_terms( $hit->in_from ), ' ') ) && ( 0 < strlen( $referers ))) {
				$term_id = $this->bstat_insert_term( $referers );
				$searchwords[] = "($hit->in_blog, $object_id, $object_type, $term_id, 1)";
			}

			if( $session_id ){
				if( $referers )
					$shistory[] = "($session_id, $hit->in_blog, $term_id, 2)";

				if( $this->session_new ){
					$in_from = $this->bstat_insert_term( $hit->in_from );
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

		if( count( $targets ) && !$status['did_targets'] ){
			if ( false === $wpdb->query( "INSERT INTO $this->hits_targets (object_blog, object_id, object_type, hit_count, hit_date) VALUES ". implode( $targets, ',' ) ." ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;" ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_target into the database'), $wpdb->last_error);

			$status['did_targets'] = 1 ;
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $searchwords ) && !$status['did_searchwords'] ){
			if ( false === $wpdb->query( "INSERT INTO $this->hits_searchphrases (object_blog, object_id, object_type, term_id, hit_count) VALUES ". implode( $searchwords, ',' ) ." ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;" ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_searchword into the database'), $wpdb->last_error);

			$status['did_searchwords'] = 1;
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $shistory ) && !$status['did_shistory'] ){
			if ( false === $wpdb->query( "INSERT INTO $this->hits_shistory (sess_id, object_blog, object_id, object_type) VALUES ". implode( $shistory, ',' ) .';' ))
				return new WP_Error('db_insert_error', __('Could not insert bsuite_hits_session_history into the database'), $wpdb->last_error);

			$status['did_shistory'] = count( $shistory );
			update_option( 'bsuite_doing_migration_status', $status );
		}

		if( count( $res )){
			if ( false === $wpdb->query( "DELETE FROM $this->hits_incoming WHERE in_time < '$since' ORDER BY in_time ASC LIMIT ". count( $res ) .';'))
				return new WP_Error('db_insert_error', __('Could not clean up the incoming stats table'), $wpdb->last_error);
			if( $getcount > count( $res ))
				$wpdb->query( "OPTIMIZE TABLE $this->hits_incoming;");
		}

		if( $debug )
			echo "<h2>Deleted records from incoming table</h2>";

		if ( get_option( 'bsuite_doing_migration_popr') < time() && $this->get_lock( 'popr' )){
			if ( get_option( 'bsuite_doing_migration_popd') < time() && $this->get_lock( 'popd' ) ){
				$wpdb->query( "TRUNCATE $this->hits_pop" );
				$wpdb->query( "INSERT INTO $this->hits_pop (blog_id, post_id, date_start, hits_total)
					SELECT object_blog AS blog_id, object_id AS post_id, MIN(hit_date) AS date_start, SUM(hit_count) AS hits_total
					FROM $this->hits_targets
					WHERE object_type = 0
					AND hit_date >= DATE_SUB( NOW(), INTERVAL 45 DAY )
					GROUP BY object_id" );
				update_option( 'bsuite_doing_migration_popd', time() + 64800 );
			}
			$wpdb->query( "UPDATE $this->hits_pop p
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
		$posts = $wpdb->get_results("SELECT object_id, AVG(hit_count) AS hit_avg
				FROM $this->hits_targets
				WHERE hit_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY)
				AND object_type = 0
				GROUP BY object_id
				ORDER BY object_id ASC", ARRAY_A);
		$avg = array();
		foreach($posts as $post)
			$avg[$post['object_id']] = $post['hit_avg'];

		$posts = $wpdb->get_results("SELECT object_id, hit_count * (86400/TIME_TO_SEC(TIME(NOW()))) AS hit_now
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

//print_r($wpdb->queries);

		if( $debug )
			echo "<h2>Done</h2>";

		update_option( 'bsuite_doing_migration', 0 );
		update_option( 'bsuite_doing_migration_status', array() );
		return(TRUE);
	}

	function get_search_engine( $ref ) {
		// a lot of inspiration and code for this function was taken from
		// Search Hilite by Ryan Boren and Matt Mullenweg
		global $wp_query;
		if( empty( $ref ))
			return false;

		$referer = urldecode( $ref );
		if (preg_match('|^http://(www)?\.?google.*|i', $referer))
			return('google');

		if (preg_match('|^http://search\.yahoo.*|i', $referer))
			return('yahoo');

		if (preg_match('|^http://search\.live.*|i', $referer))
			return('windowslive');

		if (preg_match('|^http://search\.msn.*|i', $referer))
			return('msn');

		if (preg_match('|^http://search\.lycos.*|i', $referer))
			return('lycos');

		$home = parse_url( get_settings( 'siteurl' ));
		$ref = parse_url( $referer );
		if ( strpos( ' '. $ref['host'] , $home['host'] ))
			return('internal');

		return(FALSE);
	}

	function get_search_terms( $ref ) {
		// a lot of inspiration and code for this function was taken from
		// Search Hilite by Ryan Boren and Matt Mullenweg
//		if( !$engine = $this->get_search_engine( $ref ))
//			return(FALSE);

$engine = $this->get_search_engine( $ref );

		$referer = parse_url( $ref );
		parse_str( $referer['query'], $query_vars );

		$query_array = array();
		switch ($engine) {
		case 'google':
			if( $query_vars['q'] )
				$query_array = explode(' ', urldecode( $query_vars['q'] ));
			break;

		case 'yahoo':
			if( $query_vars['p'] )
				$query_array = explode(' ', urldecode( $query_vars['p'] ));
			break;

		case 'windowslive':
			if( $query_vars['q'] )
				$query_array = explode(' ', urldecode( $query_vars['q'] ));
			break;

		case 'msn':
			if( $query_vars['q'] )
				$query_array = explode(' ', urldecode( $query_vars['q'] ));
			break;

		case 'lycos':
			if( $query_vars['query'] )
				$query_array = explode(' ', urldecode( $query_vars['query'] ));
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

	function post_hits( $args = '' ) {
		global $wpdb, $blog_id;

		$defaults = array(
			'return' => 'formatted',
			'days' => 0,
			'template' => '<li><a href="%%link%%">%%title%%</a>&nbsp;(%%hits%%)</li>'
		);
		$args = wp_parse_args( $args, $defaults );

		$post_id = (int) $args['post_id'] > 1 ? 'AND object_id = '. (int) $args['post_id'] : '';

		$date = '';
		if($args['days'] > 1)
			$date  = "AND hit_date > '". date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d") - $args['days'], date("Y"))) ."'";

		// here's the query, but let's try to get the data from cache first
		$request = "SELECT
			FORMAT(SUM(hit_count), 0) AS hits, 
			FORMAT(AVG(hit_count), 0) AS average
			FROM $this->hits_targets
			WHERE 1=1
			AND object_blog = ". absint( $blog_id ) ."
			$post_id
			AND object_type = 0
			$date
			";

		if ( !$result = wp_cache_get( (int) $args['post_id'] .'_'. (int) $args['days'], 'bstat_post_hits' ) ) {
			$result = $wpdb->get_results($request, ARRAY_A);
			wp_cache_add( (int) $args['post_id'] .'_'. (int) $args['days'], $result, 'bstat_post_hits', 1800 );
		}

		if(empty($result))
			return(NULL);

		if($args['return'] == 'array')
			return($result);

		if($args['return'] == 'formatted'){
			$list = str_replace(array('%%avg%%','%%hits%%'), array($result[0]['average'], $result[0]['hits']), $args['template']);
			return($list);
		}
	}

	function pop_posts( $args = '' ) {
		global $wpdb, $bsuite, $blog_id;

		if( !$this->get_lock( 'pop_posts' ))
			return( FALSE );

		$args = wp_parse_args( $args, array(
			'count' => 15,
			'return' => 'formatted',
			'show_icon' => 0,
			'show_title' => 1,
			'show_counts' => 1,
			'icon_size' => 's',
		));

		$date = 'AND hit_date = DATE(NOW())';
		if($args['days'] > 1)
			$date  = "AND hit_date > '". date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d") - $args['days'], date("Y"))) ."'";

		$limit = 'LIMIT '. ( absint( $args['count'] ) * 2 );

		$request = "SELECT object_id, SUM(hit_count) AS hit_count
			FROM $this->hits_targets
			WHERE 1=1
			AND object_blog = ". absint( $blog_id ) ."
			AND object_type = 0
			$date
			GROUP BY object_id
			ORDER BY hit_count DESC
			$limit";
		$result = $wpdb->get_results($request, ARRAY_A);

		if(empty($result))
			return(NULL);

		if($args['return'] == 'array')
			return($result);

		if($args['return'] == 'formatted'){
			$list = '';
			foreach($result as $post){
				$list .='<li>'. ( $args['show_icon'] ? '<a href="'. get_permalink( $post['object_id'] ) .'" class="bsuite_post_icon_link" title="'. attribute_escape( get_the_title( $post['object_id'] )).'">'. $this->icon_get_h( $post['object_id'], $args['icon_size'] ) .'</a>' : '' ) . ( $args['show_title'] ? '<a href="'. get_permalink( $post['object_id'] ) .'" title="'. attribute_escape( get_the_title( $post['object_id'] )).'">'. get_the_title( $post['object_id'] ) . '</a>' : '' ) . ( $args['show_counts'] ? '&nbsp;('. $post['hit_count'] .')' : '' ) .'</li>';
			}
			return($list);
		}
	}

	function pop_refs( $args = '' ) {
		global $wpdb, $bsuite, $blog_id;

		if( !$this->get_lock( 'pop_refs' ))
			return( FALSE );

		$defaults = array(
			'count' => 15,
			'return' => 'formatted',
			'template' => '<li>%%title%%&nbsp;(%%hits%%)</li>'
		);
		$args = wp_parse_args( $args, $defaults );

		$limit = 'LIMIT '. (int) $args['count'];

		$request = "SELECT COUNT(*) AS hit_count, name
			FROM (
				SELECT object_blog, object_id
				FROM $this->hits_shistory
				WHERE object_blog = ". absint( $blog_id ) ."
				AND object_type = 2
				ORDER BY sess_id DESC
				LIMIT 1000
			) a
			LEFT JOIN $this->hits_terms t ON a.object_id = t.term_id
			WHERE t.status = ''
			GROUP BY object_blog, object_id
			ORDER BY hit_count DESC
			$limit";

		$result = $wpdb->get_results($request, ARRAY_A);

		if(empty($result))
			return(NULL);

		if($args['return'] == 'array')
			return($result);

		if($args['return'] == 'formatted'){
			$list = '';
			foreach($result as $row){
				$list .= str_replace(array('%%title%%','%%hits%%'), array($row['name'], $row['hit_count']), $args['template']);
			}
			return($list);
		}
	}
	// end stats functions




	//
	// cron utility functions
	//
	function cron_reccurences( $schedules ) {
		$schedules['bsuite_interval'] = array('interval' => ( get_option( 'bsuite_migration_interval' ) ? get_option( 'bsuite_migration_interval' ) : 90 ), 'display' => __( 'bSuite interval. Set in bSuite options page.' ));
		return( $schedules );
	}

	function cron_register() {
		// take a look at Glenn Slaven's tutorial on WP's psudo-cron:
		// http://blog.slaven.net.au/archives/2007/02/01/timing-is-everything
		wp_clear_scheduled_hook('bsuite_interval');
		wp_schedule_event( time() + 120, 'bsuite_interval', 'bsuite_interval' );
	}
	// end cron functions



	function get_lock( $lock ){
		global $wpdb;

		if( !$lock = preg_replace( '/[^a-z|0-9|_]/i', '', str_replace( ' ', '_', $lock )))
			return( FALSE );

		// use a named mysql lock to prevent simultaneous execution
		// locks automatically drop when the connection is dropped
		// http://dev.mysql.com/doc/refman/5.0/en/miscellaneous-functions.html#function_get-lock
		if( 0 == $wpdb->get_var( 'SELECT GET_LOCK("'. $wpdb->prefix . 'bsuitelock_'. $lock .'", ".001")' ))
			return( FALSE );
		return( TRUE );
	}

	function release_lock( $lock ){
		global $wpdb;

		if( !$lock = preg_replace( '/[^a-z|0-9|_]/i', '', str_replace( ' ', '_', $lock )))
			return( FALSE );

		if( 0 == $wpdb->get_var( 'SELECT RELEASE_LOCK("'. $wpdb->prefix . 'bsuitelock_'. $lock .'", ".001")' ))
			return( FALSE );
		return( TRUE );
	}

	//
	// loadaverage related functions
	//
	function get_loadavg(){
		static $result = FALSE;
		if($result)
			return($result);

		if(function_exists('sys_getloadavg')){
			$load_avg = sys_getloadavg();
		}else{
			$load_avg = &$this->sys_getloadavg();
		}
		return( round( $load_avg[0], 2 ));
	}

	function sys_getloadavg(){
		// the following code taken from tom pittlik's comment at
		// http://php.net/manual/en/function.sys-getloadavg.php
		$str = substr( strrchr( shell_exec( 'uptime' ),':' ),1 );
		$avs = array_map( 'trim', explode( ',', $str ));
		return( $avs );
	}
	// end load average related functions


	// A short but powerfull recursive function
	// that works also if the dirs contain hidden files
	//
	// taken from http://us.php.net/manual/en/function.unlink.php
	//
	// contributions from:
	// ayor at ayor dot biz (20-Dec-2007 09:02)
	// ggarciaa at gmail dot com (04-July-2007 01:57)
	// stefano at takys dot it (28-Dec-2005 11:57)
	//
	// $dir = the target directory
	// $DeleteMe = if true delete also $dir, if false leave it alone
	function unlink_recursive($dir, $DeleteMe = FALSE) {
		if(!$dh = @opendir($dir)) return;
		while (false !== ($obj = readdir($dh))) {
			if($obj=='.' || $obj=='..') continue;
			if (!@unlink($dir.'/'.$obj)) $this->unlink_recursive($dir.'/'.$obj, true);
		}

		closedir($dh);
		if ($DeleteMe){
			@rmdir($dir);
		}
	}

	// timers
	function timer_start( $name = 1 ) {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$this->time_start[ $name ] = $mtime[1] + $mtime[0];
		return true;
	}

	function timer_stop( $name = 1 ) {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$time_end = $mtime[1] + $mtime[0];
		$time_total = $time_end - $this->time_start[ $name ];
		return $time_total;
	}
	// end timers


	function trimquotes( $in ) {
		return( trim( trim( $in ), "'\"" ));
	}

	// set a cookie
	function cookie($name, $value = NULL) {
		if($value === NULL){
			if($_GET[$name]) return $_GET[$name];
			elseif($_POST[$name]) return $_POST[$name];
			elseif($_SESSION[$name]) return $_SESSION[$name];
			elseif($_COOKIE[$name]) return $_COOKIE[$name];
			else return false;
		}else{
			setcookie($name, $value, time()+60*60*24*30, '/', '.scriblio.net');
			return($value);
		}
	}
	// end 

	// widgets
	function widget_popular_posts( $args, $widget_args = 1 ) {
		global $post, $wpdb;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );

		extract($args, EXTR_SKIP);
		$options = get_option('bstat_pop_posts');

		$opt = array( 
			'show_icon' => $options[ $number ]['show_icon'],
			'show_title' => $options[ $number ]['show_title'],
			'show_counts' => $options[ $number ]['show_counts'],
		);
		$title = empty($options[ $number ]['title']) ? __('Popular Posts', 'bsuite') : $options[ $number ]['title'];
		if ( !$opt['count'] = (int) $options[ $number ]['number'] )
			$opt['count'] = 5;
		else if ( $opt['count'] < 1 )
			$opt['count'] = 1;
		else if ( $opt['count'] > 15 )
			$opt['count'] = 15;

		if ( !$opt['days'] = (int) $options[ $number ]['days'] )
			$opt['days'] = 7;
		else if ( $opt['days'] < 1 )
			$opt['days'] = 1;
		else if ( $opt['days'] > 30 )
			$opt['days'] = 30;

		$opt['icon_size'] = 's';
		if( !$opt['show_icon'] && !$opt['show_title'] )
			$opt['show_title'] = $opt['show_counts'] = 1;

		if ( !$pop_posts = wp_cache_get( 'bstat-pop-posts-'. $number , 'widget' ) ) {
			$pop_posts = $this->pop_posts( $opt );
			wp_cache_set( 'bstat-pop-posts-'. $number , $pop_posts, 'widget', 3600 );
		}

		if ( !empty($pop_posts) ) {
?>
			<?php echo $before_widget; ?>
				<?php echo $before_title . $title . $after_title; ?>
				<ul><?php
				echo $pop_posts;
				?></ul>
			<?php echo $after_widget; ?>
<?php
		}
	}

	function widget_popular_posts_delete_cache() {
		if ( !$options = get_option('bstat_pop_posts') )
			$options = array();
		foreach ( array_keys($options) as $o )
			wp_cache_delete( 'bstat-pop-posts-'. $o, 'widget' );
	}


	function widget_popular_posts_control($widget_args) {
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );

		$options = get_option('bstat_pop_posts');
		if ( !is_array($options) )
			$options = array();

		if ( !$updated && !empty($_POST['sidebar']) ) {
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();
			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id ) {
				if ( array(&$this, 'widget_popular_posts') == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "bstat-pop-posts-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
				}
			}

			foreach ( (array) $_POST['bstat-pop-posts'] as $widget_number => $widget_var ) {
				if ( !isset($widget_var['number']) && isset($options[$widget_number]) ) // user clicked cancel
					continue;

				$options[$widget_number]['title'] = strip_tags(stripslashes($widget_var['title']));
				$options[$widget_number]['number'] = (int) $widget_var['number'];
				$options[$widget_number]['days'] = (int) $widget_var['days'];
				$options[$widget_number]['show_title'] = (int) $widget_var['show_title'];
				$options[$widget_number]['show_counts'] = (int) $widget_var['show_counts'];
				$options[$widget_number]['show_icon'] = (int) $widget_var['show_icon'];
				$options[$widget_number]['icon_size'] = $widget_var['icon_size'] ? 's' : 0;
			}

			update_option('bstat_pop_posts', $options);
			$this->widget_popular_posts_delete_cache();
			$updated = true;
		}

		if ( -1 == $number ) {
			$title = __( 'Popular Posts', 'bsuite' );
			$posts = 5;
			$days = 7;
			$show_icon = '';
			$show_title = 'checked="checked"';
			$show_counts = 'checked="checked"';

			// we reset the widget number via JS
			$number = '%i%';
		} else {
			$title = attribute_escape( $options[$number]['title'] );
			if ( !$posts = (int) $options[$number]['number'] )
				$posts = 5;
			if ( !$days = (int) $options[$number]['days'] )
				$days = 7;
			$show_icon = $options[$number]['show_icon'] ? 'checked="checked"' : '';
			$show_title = $options[$number]['show_title'] ? 'checked="checked"' : '';
			$show_counts = $options[$number]['show_counts'] ? 'checked="checked"' : '';
		}

?>
		<p><label for="bstat-pop-posts-title-<?php echo $number; ?>"><?php _e('Title:'); ?> <input style="width: 250px;" id="bstat-pop-posts-title-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" /></label></p>

		<p><label for="bstat-pop-posts-number-<?php echo $number; ?>"><?php _e('Number of posts to show:'); ?> <input style="width: 25px; text-align: center;" id="bstat-pop-posts-number-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][number]" type="text" value="<?php echo $posts; ?>" /></label> <?php _e('(at most 15)'); ?></p>

		<p><label for="bstat-pop-posts-days-<?php echo $number; ?>"><?php _e('In past x days (1 = today only):'); ?> <input style="width: 25px; text-align: center;" id="bstat-pop-posts-days-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][days]" type="text" value="<?php echo $days; ?>" /></label> <?php _e('(at most 30)'); ?></p>

		<p><?php _e('Show:'); ?>
			<label for="bstat-pop-posts-show_icon-<?php echo $number; ?>"><?php _e('icon:'); ?> <input class="checkbox" type="checkbox" value="1" <?php echo $show_icon; ?> id="bstat-pop-posts-show_icon-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][show_icon]" /></label> 
			<label for="bstat-pop-posts-show_title-<?php echo $number; ?>"><?php _e('title:'); ?> <input class="checkbox" type="checkbox" value="1" <?php echo $show_title; ?> id="bstat-pop-posts-show_title-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][show_title]" /></label> 
			<label for="bstat-pop-posts-show_counts-<?php echo $number; ?>"><?php _e('counts:'); ?> <input class="checkbox" type="checkbox" value="1" <?php echo $show_counts; ?> id="bstat-pop-posts-show_counts-<?php echo $number; ?>" name="bstat-pop-posts[<?php echo $number; ?>][show_counts]" /></label>
		</p>

		<input type="hidden" id="bstat-pop-posts-submit" name="bstat-pop-posts[<?php echo $number; ?>][submit]" value="1" />
<?php
	}

	function widget_popular_posts_register() {
		if ( !$options = get_option('bstat_pop_posts') )
			$options = array();
		$widget_ops = array('classname' => 'bstat-pop-posts', 'description' => __('Your site&#8217;s most popular posts and pages', 'bsuite'));
		$control_ops = array('width' => 320, 'height' => 90, 'id_base' => 'bstat-pop-posts');
		$name = 'bSuite<br /> '. __( 'Popular Posts', 'bsuite' );

		$id = false;
		foreach ( array_keys($options) as $o ) {
			// Old widgets can have null values for some reason
			if ( !isset($options[$o]['title']))
				continue;
			$id = "bstat-pop-posts-$o"; // Never never never translate an id
			wp_register_sidebar_widget($id, $name, array(&$this, 'widget_popular_posts'), $widget_ops, array( 'number' => $o ));
			wp_register_widget_control($id, $name, array(&$this, 'widget_popular_posts_control'), $control_ops, array( 'number' => $o ));
		}

		// If there are none, we register the widget's existance with a generic template
		if ( !$id ) {
			wp_register_sidebar_widget( 'bstat-pop-posts-1', $name, array(&$this, 'widget_popular_posts'), $widget_ops, array( 'number' => -1 ) );
			wp_register_widget_control( 'bstat-pop-posts-1', $name, array(&$this, 'widget_popular_posts_control'), $control_ops, array( 'number' => -1 ) );
		}
	}

	function widget_popular_refs($args) {
		global $post, $wpdb;

		extract($args, EXTR_SKIP);
		$options = get_option('bstat_pop_refs');
		$title = empty($options['title']) ? __('Recent Search Terms') : $options['title'];
		if ( !$number = (int) $options['number'] )
			$number = 5;
		else if ( $number < 1 )
			$number = 1;
		else if ( $number > 15 )
			$number = 15;

		if ( !$days = (int) $options['days'] )
			$days = 7;
		else if ( $days < 1 )
			$days = 1;
		else if ( $days > 30 )
			$days = 30;

		if ( !$pop_refs = wp_cache_get( 'bstat_pop_refs', 'widget' ) ) {
			$pop_refs = $this->pop_refs("count=$number&days=$days");
			wp_cache_add( 'bstat_pop_refs', $pop_refs, 'widget', 3600 );
		}

		if ( !empty($pop_refs) ) {
?>
			<?php echo $before_widget; ?>
				<?php echo $before_title . $title . $after_title; ?>
				<ul id="bstat-pop-refs"><?php
				echo $pop_refs;
				?></ul>
			<?php echo $after_widget; ?>
<?php
		}
	}

	function widget_popular_refs_delete_cache() {
		wp_cache_delete( 'bstat_pop_refs', 'widget' );
	}

	function widget_popular_refs_control() {
		$options = $newoptions = get_option('bstat_pop_refs');
		if ( $_POST['bstat-pop-refs-submit'] ) {
			$newoptions['title'] = strip_tags(stripslashes($_POST['bstat-pop-refs-title']));
			$newoptions['number'] = (int) $_POST['bstat-pop-refs-number'];
			$newoptions['days'] = (int) $_POST['bstat-pop-refs-days'];
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('bstat_pop_refs', $options);
			$this->widget_popular_refs_delete_cache();
		}
		$title = attribute_escape($options['title']);
		if ( !$number = (int) $options['number'] )
			$number = 5;
		if ( !$days = (int) $options['days'] )
			$days = 7;
	?>
				<p><label for="bstat-pop-refs-title"><?php _e('Title:'); ?> <input style="width: 250px;" id="bstat-pop-refs-title" name="bstat-pop-refs-title" type="text" value="<?php echo $title; ?>" /></label></p>
				<p><label for="bstat-pop-refs-number"><?php _e('Number of refs to show:'); ?> <input style="width: 25px; text-align: center;" id="bstat-pop-refs-number" name="bstat-pop-refs-number" type="text" value="<?php echo $number; ?>" /></label> <?php _e('(at most 15)'); ?></p>
				<input type="hidden" id="bstat-pop-refs-submit" name="bstat-pop-refs-submit" value="1" />
	<?php
	}

	function widgets_register(){
		$this->widget_popular_posts_register();

		wp_register_sidebar_widget('bstat-pop-refs', __('bStat Refs'), array(&$this, 'widget_popular_refs'), 'bstat-pop-refs');
		wp_register_widget_control('bstat-pop-refs', __('bStat Refs'), array(&$this, 'widget_popular_refs_control'), 'width=320&height=90');
	}
	// end widgets



	// administrivia
	function activate() {

		update_option('bsuite_doing_migration', time() + 7200 );

		$this->createtables();
		$this->cron_register();

		global $bsuite_search;
		$bsuite_search->create_table;

		// set some defaults for the plugin
		if(!get_option('bsuite_insert_related'))
			update_option('bsuite_insert_related', TRUE);

		if(!get_option('bsuite_insert_sharelinks'))
			update_option('bsuite_insert_sharelinks', FALSE);

		if(!get_option('bsuite_searchsmart'))
			update_option('bsuite_searchsmart', TRUE);

		if(!get_option('bsuite_swhl'))
			update_option('bsuite_swhl', TRUE);

		if(!get_option('bsuite_insert_css'))
			update_option('bsuite_insert_css', TRUE);

		if(!get_option('bsuite_migration_interval'))
			update_option('bsuite_migration_interval', 90);

		if(!get_option('bsuite_migration_count'))
			update_option('bsuite_migration_count', 100);

		if(!get_option('bsuite_load_max'))
			update_option('bsuite_load_max', 4);


		// allow authors to edit their own pages by default
		if(!get_option('bsuite_who_can_edit'))
			update_option('bsuite_who_can_edit', 'authors');

		if(!get_option('bsuite_managefocus_month'))
			update_option('bsuite_managefocus_month', FALSE);
		if(!get_option('bsuite_managefocus_author'))
			update_option('bsuite_managefocus_author', FALSE);


		// set some defaults for the widgets
		if(!get_option('bsuite_related_posts'))
			update_option('bsuite_related_posts', array('title' => 'Related Posts', 'number' => 7));

		if(!get_option('bstat_pop_refs'))
			update_option('bstat_pop_refs', array('title' => 'Popular Searches', 'number' => 5));
	}

	function createtables() {
		global $wpdb;

		$charset_collate = '';
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
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
$bsuite = & new bSuite;

function bstat_hits($template = '%%hits%% hits, about %%avg%% daily', $post_id = NULL, $todayonly = 0, $return = NULL) {
	global $bsuite;
	if(!empty($return))
		return($bsuite->post_hits(array('post_id' => $post_id,'days' => $todayonly, 'template' => $template )));
	echo $bsuite->post_hits(array('post_id' => $post_id,'days' => $todayonly, 'template' => $template ));
}


// deprecated functions
function bstat_pulse($post_id = 0, $maxwidth = 400, $disptext = 1, $dispcredit = 1, $accurate = 4) {
	// this one isn't so much deprecated as, well, 
	// the code sucks and I haven't re-written it yet

	global $wpdb, $bstat;

	$post_id = (int) $post_id;

	$for_post_id = $post_id > 1 ? 'AND post_id = '. $post_id : '';

	// here's the query, but let's try to get the data from cache first
	$request = "SELECT
		SUM(hit_count) AS hits, 
		hit_date
		FROM $bstat->hits_table
		WHERE 1=1
		$for_post_id
		GROUP BY hit_date
		";

	if ( !$result = wp_cache_get( $post_id, 'bstat_post_pulse' ) ) {
		$result = $wpdb->get_results($request, ARRAY_A);
		wp_cache_add( $post_id, $result, 'bstat_post_pulse', 3600 );
	}

	if(empty($result))
		return(NULL);

	$tot = count($result);

	if(count($result)>0){
		$point = null;
		$point[] = 0;
		foreach($result as $row){
			$point[] = $row['hits'];
		}
		$sum = array_sum($point);
		$max = max($point);
		$avg = round($sum / $tot);

		if($accurate == 4){
			$graphaccurate = get_option('bstat_graphaccurate');
		}else{
			$graphaccurate = $accurate;
		}

		$minwidth = ($maxwidth / 8.1);
		if($graphaccurate) $minwidth = ($maxwidth / 4.1);

		while(count($point) <= $minwidth){
			$newpoint = null;
			for ($i = 0; $i < count($point); $i++) {
				if($i > 0){
					if(!$graphaccurate) $newpoint[] = ((($point[$i-1] * 2) + $point[$i]) / 3);
					$newpoint[] = (($point[$i-1] + $point[$i]) / 2);
					if(!$graphaccurate) $newpoint[] = (($point[$i-1] + ($point[$i-1] * 2)) / 3);
				}
				$newpoint[] = $point[$i];
			}
			$point = $newpoint;
		}


		$tot = count($point);
		$width = round($maxwidth / $tot);
		if($width > 3)
			$width = 4;

		if($width < 1)
			$width = 1;

		if(($width  * $tot) > $maxwidth)
			$skipstart = (($width  * $tot) - $maxwidth) / $width;

		$i = 1;
		$hit_chart = "";
		foreach($point as $row){
			if((!$skipstart) || ($i > $skipstart)){
				$hit_chart .= "<img src='" . get_settings('siteurl') .'/'. PLUGINDIR .'/'. plugin_basename(dirname(__FILE__))  . "/img/spacer.gif' width='$width' height='" . round((($row) / $max) * 100) . "' alt='graph element.' />";
				}
			$i++;
		}

		$pre = "<div id=\"bstat_pulse\">";
		$post = "</div>";
		$disptext = ($disptext == 1) ? (number_format($sum) .' total reads, averaging '. number_format($avg) .' daily') : ("");
		$dispcredit = ($dispcredit == 1) ? ("<small><a href='http://maisonbisson.com/blog/search/bsuite' title='a pretty good WordPress plugin'>stats powered by bSuite bStat</a></small>") : ("");
		$disptext = (($disptext) || ($dispcredit)) ? ("\n<p>$disptext\n<br />$dispcredit</p>") : ("");

		echo($pre . $hit_chart . "\n" . $disptext . $post);
	}
}



// php4 compatibility, argh
if(!function_exists('str_ireplace')){
	function str_ireplace($a, $b, $c){
		return str_replace($a, $b, $c);
	}
}