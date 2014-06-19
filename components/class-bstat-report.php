<?php
class bStat_Report
{
	public $filter = array();

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_ajax_bstat_report_goal_items', array( $this, 'goal_items_ajax' ) );
		add_action( 'wp_ajax_bstat_report_top_sessions', array( $this, 'top_sessions_ajax' ) );
		add_action( 'wp_ajax_bstat_report_top_users', array( $this, 'top_users_ajax' ) );
	}//end __construct

	public function init()
	{
		// make sure the graphing libraries have been initialized
		bstat()->graphing();

		add_action( 'admin_menu', array( $this, 'admin_menu_init' ) );
		wp_register_style( bstat()->id_base . '-report', plugins_url( 'css/bstat-report.css', __FILE__ ), array( 'rickshaw', 'd3-parsets' ), bstat()->version );
		wp_register_script( bstat()->id_base . '-report', plugins_url( 'js/bstat-report.js', __FILE__ ), array( 'rickshaw', 'd3-parsets', 'jquery-ui-tabs' ), bstat()->version, TRUE );
	} // END init

	// add the menu item to the dashboard
	public function admin_menu_init()
	{
		$this->menu_url = admin_url( 'index.php?page=' . bstat()->id_base . '-report' );

		add_submenu_page( 'index.php', 'bStat Viewer', 'bStat Viewer', 'edit_posts', bstat()->id_base . '-report', array( $this, 'admin_menu' ) );
	} // END admin_menu_init

	public function parse_goal( $goal )
	{
		$temp = array_map( 'trim', (array) explode( ':', $goal ) );

		if ( ! isset( $temp[0], $temp[1], $temp[2], $temp[3] ) )
		{
			return FALSE;
		}

		$goal = array(
			'blog' => sanitize_title_with_dashes( $temp[0] ),
			'component' => sanitize_title_with_dashes( $temp[1] ),
			'action' => sanitize_title_with_dashes( $temp[2] ),
			'frequency' => (int) $temp[3],
		);

		return $goal;
	}

	public function get_goal()
	{
		if ( empty( $_GET['goal'] ) )
		{
			return array();
		}

		return $this->parse_goal( $_GET['goal'] );
	}

	public function goal_url( $goal )
	{
		$url = admin_url( '/index.php?page=' . bstat()->id_base . '-report' );

		return add_query_arg( array( 'goal' => $goal['blog'] . ':' . $goal['component'] . ':' . $goal['action'] . ':' . $goal['frequency'] ), $url );
	}

	public function report_url( $filter = array(), $additive = TRUE )
	{
		$url = admin_url( '/index.php?page=' . bstat()->id_base . '-report' );

		if ( $additive )
		{
			$filter = array_merge( $filter, $this->filter );
			unset( $filter['timestamp'] );
		}

		return add_query_arg( $filter, $url );
	}

	public function default_filter( $add_filter = array() )
	{
		// set the timezone to UTC for the later strtotime() call,
		// preserve the old timezone so we can set it back when done
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		// only setting the oldest part of the time window for better caching
		// the newest part is filled in with the current time when the query is executed
		$filter = array(
			'timestamp' => array(
				'min' => strtotime( 'midnight last week' ),
			),
		);

		date_default_timezone_set( $old_tz );

		return array_merge( $filter, (array) $add_filter );
	}

	public function set_filter( $filter = FALSE )
	{
		// are there filter vars in the $_GET? Okay, use those
		if ( ! $filter )
		{
			$filter = array_filter( (array) bstat()->db()->sanitize_footstep( $_GET, TRUE ) );
		}

		// defaults, if we can't find a filter anywhere
		if ( ! $filter )
		{
			$filter = array_merge( $this->default_filter(), array_filter( (array) bstat()->db()->sanitize_footstep( $_GET, TRUE ) ) );
		}

		$this->filter = (array) $filter;
	}

	public function cache_key( $part, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		return $part .' '. md5( serialize( (array) $filter ) );
	}

	public function cache_ttl()
	{
		return mt_rand( 101, 503 ); // prime numbers for almost 2 minutes or a little over 8 minutes
	}

	function sort_by_hits_desc( $a, $b )
	{
		if ( $a->hits == $b->hits )
		{
			return 0;
		}
		return ( $a->hits < $b->hits ) ? 1 : -1;
	}

	function sort_by_sessions_on_goal_desc( $a, $b )
	{
		if ( $a->sessions_on_goal == $b->sessions_on_goal )
		{
			return 0;
		}
		return ( $a->sessions_on_goal < $b->sessions_on_goal ) ? 1 : -1;
	}

	public function timeseries( $quantize_minutes = 1, $for = FALSE, $ids = FALSE, $filter = FALSE )
	{
		// minutes are a positive integer, equal to or larger than 1
		$quantize_minutes = absint( $quantize_minutes );
		$quantize_minutes = max( $quantize_minutes, 1 );
		$seconds = $quantize_minutes * 60;

		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'timeseries' . $seconds . $for . md5( serialize( $ids ) ), $filter );

		if ( ! $timeseries = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			$timeseries_raw = bstat()->db()->select( FALSE, FALSE, 'all', 10000, $filter );

			$timeseries = array();
			foreach ( $timeseries_raw as $item )
			{
				$quantized_time = $seconds * (int) ( $item->timestamp / $seconds );

				if ( isset( $timeseries[ $quantized_time ] ) )
				{
					$timeseries[ $quantized_time ] ++;
				}
				else
				{
					$timeseries[ $quantized_time ] = 1;
				}

			}

			ksort( $timeseries );

			// get an array of all the quantized timeslots, including those with no activity
			$keys = array_keys( $timeseries );
			$keys = array_fill_keys( range( reset( $keys ), end( $keys ), $seconds ), 0 );

			$timeseries = array_replace( $keys, $timeseries );

			wp_cache_set( $cachekey, $timeseries, bstat()->id_base, $this->cache_ttl() );
		}

		// tips for using the output:
		// the array key is a quantized timestamp, pass it into date( $format, $quantized_time ) and get a human readable date.
		// the value is the count of activity hits for that quantized time segment.
		return $timeseries;
	}

	public function multi_timeseries( $quantize_minutes = 1, $for = FALSE, $ids = FALSE, $filters = array() )
	{
		if ( ! is_array( $filters ) )
		{
			return FALSE;
		}

		// get the data for each filter
		foreach ( $filters as $k => $v )
		{
			$filters[ $k ] = $this->timeseries( $quantize_minutes, $for, $ids, $v );
			$min = isset( $min ) ? min( $min, min( array_keys( $filters[ $k ] ) ) ) : min( array_keys( $filters[ $k ] ) );
			$max = isset( $max ) ? max( $max, max( array_keys( $filters[ $k ] )	) ) : max( array_keys( $filters[ $k ] ) );
		}

		// get a single time space that covers all the data
		$keys = array_fill_keys( range( $min, $max, $quantize_minutes * 60 ), 0 );

		// reiterate the array, conform all the returned data to a single time space
		foreach ( $filters as $k => $v )
		{
			$filters[ $k ] = array_replace( $keys, $v );
		}

		return $filters;
	}

	public function get_posts( $top_posts_list, $query_args = array() )
	{

		$cachekey = $this->cache_key( 'get_posts ' . md5( serialize( $top_posts_list ) . serialize( $query_args ) ) );

		if ( ! $get_posts = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			$get_posts = get_posts( array_merge(
				array(
					'post__in' => array_map( 'absint', wp_list_pluck( $top_posts_list, 'post' ) ),
					'orderby' => 'post__in',
				),
				$query_args
			) );

			$post_hits = array();
			foreach ( $top_posts_list as $line )
			{
				$post_hits[ $line->post ] = clone $line;
				unset( $post_hits[ $line->post ]->post );
			}

			foreach ( $get_posts as $k => $v )
			{
				$get_posts[ $k ] = (object) array_replace( (array) $get_posts[ $k ], (array) $post_hits[ $v->ID ] );
			}

			wp_cache_set( $cachekey, $get_posts, bstat()->id_base, $this->cache_ttl() );
		}

		return $get_posts;
	}

	public function top_posts( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_posts = wp_cache_get( $this->cache_key( 'top_posts', $filter ), bstat()->id_base ) )
		{
			$top_posts = bstat()->db()->select( FALSE, FALSE, 'post,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_posts', $filter ), $top_posts, bstat()->id_base, $this->cache_ttl() );
		}

		return $top_posts;
	}

	public function sessions_on_goal( $goal = NULL )
	{

		// inherit the goal if none is provided
		$goal = empty( $goal ) ? $this->get_goal() : $goal;

		// do not continue without a goal
		if ( ! $goal )
		{
			return array();
		}

		// merge the goal and environmental filters
		$filter = array_replace( $this->filter, $goal );

		if ( ! $sessions_on_goal = wp_cache_get( $this->cache_key( 'sessions_on_goal', $filter ), bstat()->id_base ) )
		{
			// get lots of sessions
			$sessions = bstat()->db()->select( $for, $ids, 'sessions,hits', 10000, $filter );

			$sessions_on_goal = array();
			$frequency = absint( $goal['frequency'] );
			foreach ( $sessions as $session )
			{
				if ( $goal['frequency'] > 0 && $session->hits >= $frequency )
				{
					$sessions_on_goal[] = $session->session;
				}
				elseif ( $goal['frequency'] < 0 && $session->hits <= $frequency )
				{
					$sessions_on_goal[] = $session->session;
				}
			}

			wp_cache_set( $this->cache_key( 'sessions_on_goal', $filter ), $sessions_on_goal, bstat()->id_base, $this->cache_ttl() );
		}

		return $sessions_on_goal;
	}

	public function top_sessions( $filter = FALSE )
	{
		return $this->sessions_for( FALSE, FALSE, $filter );
	}

	public function sessions_for( $for, $ids, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $sessions_for = wp_cache_get( $this->cache_key( 'sessions_for' . $for . md5( serialize( $ids ) ), $filter ), bstat()->id_base ) )
		{
			$sessions_for = bstat()->db()->select( $for, $ids, 'sessions,hits', 5000, $filter );
			wp_cache_set( $this->cache_key( 'sessions_for' . $for . md5( serialize( $ids ) ), $filter ), $sessions_for, bstat()->id_base, $this->cache_ttl() );
		}

		return $sessions_for;
	}

	public function posts_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'posts_for_session' . md5( serialize( $session ) ), $filter );

		if ( ! $posts_for_session = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			$posts_for_session = bstat()->db()->select( 'session', $session, 'post,hits', 250, $filter );

			foreach ( $posts_for_session as $k => $post )
			{
				$post->sessions = count( bstat()->report()->sessions_for( 'post', $post->post ) );
				$post->sessions_on_goal = count(
					bstat()->report()->sessions_for(
						'sessions',bstat()->report()->sessions_on_goal(),
						array_merge(
							bstat()->report()->filter,
							array( 'post' => $post->post )
						)
					)
				);
			}

			usort( $posts_for_session, array( $this, 'sort_by_sessions_on_goal_desc' ) );

			wp_cache_set( $cachekey, $posts_for_session, bstat()->id_base, $this->cache_ttl() );
		}

		return $posts_for_session;
	}

	public function posts_for_user( $user, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $posts_for_user = wp_cache_get( $this->cache_key( 'posts_for_user ' . $user, $filter ), bstat()->id_base ) )
		{
			$posts_for_user = bstat()->db()->select( 'user', $user, 'post,hits', 250, $filter );
			wp_cache_set( $this->cache_key( 'posts_for_user ' . $user, $filter ), $posts_for_user, bstat()->id_base, $this->cache_ttl() );
		}

		return $posts_for_user;
	}

	public function authors_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'authors_for_session' . md5( serialize( $session ) ), $filter );

		if ( ! $authors_for_session = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			$posts = $this->get_posts( $this->posts_for_session( $session, $filter ), array( 'posts_per_page' => -1, 'post_type' => 'any' ) );

			if ( ! count( $posts ) )
			{
				return FALSE;
			}

			$authors_for_session = array();
			foreach ( $posts as $post )
			{

				if ( isset( $authors_for_session[ $post->post_author ] ) )
				{
					$authors_for_session[ $post->post_author ]->hits += $post->hits;
					$authors_for_session[ $post->post_author ]->sessions += $post->sessions;
					$authors_for_session[ $post->post_author ]->sessions_on_goal += $post->sessions_on_goal;
				}
				else
				{
					$authors_for_session[ $post->post_author ] = (object) array_intersect_key( (array) $post, array(
						'hits' => FALSE,
						'sessions' => FALSE,
						'sessions_on_goal' => FALSE,
						'post_author' => FALSE,
					) );
				}
			}

			usort( $authors_for_session, array( $this, 'sort_by_sessions_on_goal_desc' ) );

			wp_cache_set( $cachekey, $authors_for_session, bstat()->id_base, $this->cache_ttl() );
		}

		return $authors_for_session;
	}

	public function top_authors( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_authors = wp_cache_get( $this->cache_key( 'top_authors', $filter ), bstat()->id_base ) )
		{
			$posts = $this->get_posts( $this->top_posts( $filter ), array( 'posts_per_page' => -1, 'post_type' => 'any' ) );

			if ( ! count( $posts ) )
			{
				return FALSE;
			}

			$top_authors = $authors = array();
			foreach ( $posts as $post )
			{

				if ( isset( $authors[ $post->post_author ] ) )
				{
					$top_authors[ $post->post_author ]->hits += $post->hits;
				}
				else
				{
					$top_authors[ $post->post_author ] = (object) array_intersect_key( (array) $post, array( 'hits' => FALSE, 'post_author' => FALSE ) );
				}
			}

			usort( $top_authors, array( $this, 'sort_by_hits_desc' ) );

			wp_cache_set( $this->cache_key( 'top_authors', $filter ), $top_authors, bstat()->id_base, $this->cache_ttl() );
		}

		return $top_authors;
	}

	public function terms_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'terms_for_session' . md5( serialize( $session ) ), $filter );

		if ( ! $terms_for_session = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			global $wpdb;
			$sql = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, a.count, COUNT(c.term_taxonomy_id) AS `count_in_set`
				FROM $wpdb->term_relationships c
				INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
				INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
				WHERE c.object_id IN (" . implode( ',', array_map( 'absint', wp_list_pluck( $this->posts_for_session( $session, $filter ), 'post' ) ) ) . ")
				GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
				/* generated in bStat_Report::top_terms() */";

			$terms_for_session = $wpdb->get_results( $sql );

			// reiterate to insert hits from recent activity
			foreach ( $terms_for_session as $k => $v )
			{
				$posts = $this->top_posts_for_term_and_session( $v, $session, array( 'posts_per_page' => -1, 'post_type' => 'any' ) );

				$terms_for_session[ $k ]->hits = array_sum( wp_list_pluck( $posts, 'hits' ) );
				$terms_for_session[ $k ]->hits_per_post_score = $terms_for_session[ $k ]->hits + (int) ( 100 * $terms_for_session[ $k ]->hits / $terms_for_session[ $k ]->count_in_set );
				$terms_for_session[ $k ]->depth_of_coverage_score = (int) ( 100 * $terms_for_session[ $k ]->count_in_set / $terms_for_session[ $k ]->count );
				$terms_for_session[ $k ]->sessions = array_sum( wp_list_pluck( $posts, 'sessions' ) );
				$terms_for_session[ $k ]->sessions_on_goal = array_sum( wp_list_pluck( $posts, 'sessions_on_goal' ) );
				$terms_for_session[ $k ]->sessions_per_post_score = (int) ( 100 * $terms_for_session[ $k ]->sessions / $terms_for_session[ $k ]->count_in_set );
				$terms_for_session[ $k ]->sessions_on_goal_per_post_score = (int) ( 100 * $terms_for_session[ $k ]->sessions_on_goal / $terms_for_session[ $k ]->count_in_set );
			}

			usort( $terms_for_session, array( $this, 'sort_by_sessions_on_goal_desc' ) );

			wp_cache_set( $cachekey, $terms_for_session, bstat()->id_base, 10 * $this->cache_ttl() );
		}

		return $terms_for_session;
	}

	public function top_terms( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_terms = wp_cache_get( $this->cache_key( 'top_terms', $filter ), bstat()->id_base ) )
		{
			global $wpdb;
			$sql = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, a.count, COUNT(c.term_taxonomy_id) AS `count_in_set`
				FROM $wpdb->term_relationships c
				INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
				INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
				WHERE c.object_id IN (" . implode( ',', array_map( 'absint', wp_list_pluck( $this->top_posts( $filter ), 'post' ) ) ) . ")
				GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
				/* generated in bStat_Report::top_terms() */";

			$top_terms = $wpdb->get_results( $sql );

			// reiterate to insert hits from recent activity
			foreach ( $top_terms as $k => $v )
			{
				$top_terms[ $k ]->hits = array_sum( wp_list_pluck( $this->top_posts_for_term( $v, array( 'posts_per_page' => -1, 'post_type' => 'any' ) ), 'hits' ) );
				$top_terms[ $k ]->hits_per_post_score = $top_terms[ $k ]->hits + (int) ( 100 * $top_terms[ $k ]->hits / $top_terms[ $k ]->count_in_set );
				$top_terms[ $k ]->depth_of_coverage_score = (int) ( 100 * $top_terms[ $k ]->count_in_set / $top_terms[ $k ]->count );
			}

			wp_cache_set( $this->cache_key( 'top_terms', $filter ), $top_terms, bstat()->id_base, 10 * $this->cache_ttl() );
		}

		return $top_terms;
	}

	public function top_posts_for_term_and_session( $term, $session, $query_args = array(), $filter = FALSE )
	{

		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		return $this->get_posts( $this->posts_for_session( $session, $filter ), array_merge(
			array(
				'tax_query' => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field' => 'id',
						'terms' => $term->term_id,
					),
				),
			),
			$query_args
		) );
	}

	public function top_posts_for_term( $term, $query_args = array(), $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		return $this->get_posts( $this->top_posts( $filter ), array_merge(
			array(
				'tax_query' => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field' => 'id',
						'terms' => $term->term_id,
					),
				),
			),
			$query_args
		) );
	}

	public function components_and_actions_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'components_and_actions_for_session' . md5( serialize( $session ) ), $filter );

		if ( ! $components_and_actions_for_session = wp_cache_get( $cachekey . 'asdf', bstat()->id_base ) )
		{
			$components_and_actions_for_session = bstat()->db()->select( 'session', $session, 'components_and_actions,hits', 1000, $filter );

			foreach ( $components_and_actions_for_session as $k => $component_and_action )
			{
				$component_and_action->sessions = count( bstat()->report()->sessions_for( FALSE, FALSE, array_replace(
					$filter,
					array(
						'component' => $component_and_action->component,
						'action' => $component_and_action->action,
					)
				) ) );
				$component_and_action->sessions_on_goal = count(
					bstat()->report()->sessions_for(
						'sessions',bstat()->report()->sessions_on_goal(),
						array_replace(
							$filter,
							array(
								'component' => $component_and_action->component,
								'action' => $component_and_action->action,
							)
						)
					)
				);
			}

			usort( $components_and_actions_for_session, array( $this, 'sort_by_sessions_on_goal_desc' ) );

			wp_cache_set( $cachekey, $components_and_actions_for_session, bstat()->id_base, $this->cache_ttl() );
		}

		return $components_and_actions_for_session;
	}

	public function top_components_and_actions( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_components_and_actions = wp_cache_get( $this->cache_key( 'top_components_and_actions', $filter ), bstat()->id_base ) )
		{
			$top_components_and_actions = bstat()->db()->select( FALSE, FALSE, 'components_and_actions,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_components_and_actions', $filter ), $top_components_and_actions, bstat()->id_base, $this->cache_ttl() );
		}

		return $top_components_and_actions;
	}

	public function component_and_action_info( $component_and_action, $filter = FALSE )
	{

		if ( is_string( $component_and_action ) )
		{
			$temp = explode( ':', $component_and_action );
			$component_and_action = array(
				'component' => trim( $temp[0] ),
				'action' => trim( $temp[1] ),
			);
		}

		if ( 2 != count( $component_and_action ) )
		{
			return FALSE;
		}

		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$filter = array_merge( array( 'component' => $component_and_action['component'], 'action' => $component_and_action['action'], ),  $filter );

		if ( ! $component_and_action_info = wp_cache_get( $this->cache_key( 'component_and_action_info', $filter ), bstat()->id_base ) )
		{
			$component_and_action_info_raw = wp_list_pluck( bstat()->db()->select( FALSE, FALSE, 'all', 1000, $filter ), 'info' );

			$component_and_action_info = array();
			foreach ( $component_and_action_info_raw as $row )
			{
				if ( empty( $row ) )
				{
					$row = 'no information provided for action';
				}

				if ( ! isset( $component_and_action_info[ $row ] ) )
				{
					$component_and_action_info[ $row ] = (object) array( 'info' => $row, 'hits' => 1 );
				}
				else
				{
					$component_and_action_info[ $row ]->hits ++;
				}
			}

			usort( $component_and_action_info, array( $this, 'sort_by_hits_desc' ) );

			wp_cache_set( $this->cache_key( 'component_and_action_info', $filter ), $component_and_action_info, bstat()->id_base, $this->cache_ttl() );
		}

		return $component_and_action_info;
	}

	public function users_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$cachekey = $this->cache_key( 'users_for_session' . md5( serialize( $session ) ), $filter );

		if ( ! $users_for_session = wp_cache_get( $cachekey, bstat()->id_base ) )
		{
			$users_for_session = bstat()->db()->select( 'session', $session, 'user,hits', 1000, $filter );

			foreach ( $users_for_session as $k => $user )
			{
				$user->sessions = count( bstat()->report()->sessions_for( 'user', $user->user ) );
				$user->sessions_on_goal = count(
					bstat()->report()->sessions_for(
						'sessions',bstat()->report()->sessions_on_goal(),
						array_merge(
							bstat()->report()->filter,
							array( 'user' => $user->user )
						)
					)
				);
			}

			usort( $users_for_session, array( $this, 'sort_by_sessions_on_goal_desc' ) );

			wp_cache_set( $cachekey, $users_for_session, bstat()->id_base, $this->cache_ttl() );
		}

		return $users_for_session;
	}

	public function top_users( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_users = wp_cache_get( $this->cache_key( 'top_users', $filter ), bstat()->id_base ) )
		{
			$top_users = bstat()->db()->select( FALSE, FALSE, 'user,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_users', $filter ), $top_users, bstat()->id_base, $this->cache_ttl() );
		}

		return $top_users;
	}

	public function top_blogs( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$filter['blog'] = FALSE;

		if ( ! $top_blogs = wp_cache_get( $this->cache_key( 'top_blogs', $filter ), bstat()->id_base ) )
		{
			$top_blogs = bstat()->db()->select( FALSE, FALSE, 'blog,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_blogs', $filter ), $top_blogs, bstat()->id_base, $this->cache_ttl() );
		}

		return $top_blogs;
	}//end top_blogs

	public function report_goal_items( $type, $items )
	{
		$data = array(
			'sum_sessions' => 0,
			'sum_sessions_on_goal' => 0,
			'avg_cvr' => 0,
			'items' => array(),
		);

		if ( ! $items )
		{
			return $data;
		}//end if

		$data['sum_sessions'] = array_sum( wp_list_pluck( $items, 'sessions' ) );
		$data['sum_sessions_on_goal'] = array_sum( wp_list_pluck( $items, 'sessions_on_goal' ) );
		$data['avg_cvr'] = $data['sum_sessions_on_goal'] / $data['sum_sessions'] * 100;

		if ( 'term' == $type )
		{
			$data['sum_matching_posts'] = array_sum( wp_list_pluck( $items, 'count_in_set' ) );
			$data['sum_posts_in_session'] = count( bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() ) );
		}//end if

		// for sanity, limit this to just the top few users
		$items = array_slice( $items, 0, bstat()->options()->report->max_items );

		foreach ( $items as $item )
		{
			$goal_function = 'report_goal_' . $type;

			$data['items'][] = $this->$goal_function( $item, $data );
		}//end foreach

		return $data;
	}//end report_goal_items

	private function report_goal_author( $item, $data )
	{
		$user = new WP_User( $item->post_author );
		if ( ! isset( $user->display_name ) )
		{
			continue;
		}//end if

		$item->sessions_on_goal_expected = $data['avg_cvr'] * $item->sessions / 100;

		$item_data = array(
			'display_name' => $user->display_name,
			'sessions' => $item->sessions,
			'sessions_on_goal' => $item->sessions_on_goal,
			'cvr' => ( $item->sessions_on_goal / $item->sessions ) * 100,
			'sessions_on_goal_expected' => $item->sessions_on_goal_expected,
			'difference' => $item->sessions_on_goal - $item->sessions_on_goal_expected,
			'multiple' => $item->sessions_on_goal / $item->sessions_on_goal_expected,
		);

		return $item_data;
	}//end report_goal_author

	private function report_goal_post( $item, $data )
	{
		$item->sessions_on_goal_expected = $data['avg_cvr'] * $item->sessions / 100;

		$item_data = array(
			'report_url' => $this->report_url( array( 'post' => $item->post ) ),
			'title' => get_the_title( $item->post ),
			'permalink' => get_permalink( $item->post ),
			'sessions' => $item->sessions,
			'sessions_on_goal' => $item->sessions_on_goal,
			'cvr' => ( $item->sessions_on_goal / $item->sessions ) * 100,
			'sessions_on_goal_expected' => $item->sessions_on_goal_expected,
			'difference' => $item->sessions_on_goal - $item->sessions_on_goal_expected,
			'multiple' => $item->sessions_on_goal / $item->sessions_on_goal_expected,
		);

		return $item_data;
	}//end report_goal_post

	private function report_goal_term( $item, $data )
	{
		// adjust the numbers to reflect the contribution an individual term has among many on each post
		$item->sessions = $item->sessions / $item->count_in_set;
		$item->sessions_on_goal = $item->sessions_on_goal / $item->count_in_set;

		$item->sessions_on_goal_expected = $data['avg_cvr'] * $item->sessions / 100;

		$item_data = array(
			'taxonomy' => $item->taxonomy,
			'slug' => $item->slug,
			'sessions' => $item->sessions,
			'sessions_on_goal' => $item->sessions_on_goal,
			'cvr' => ( $item->sessions_on_goal / $item->sessions ) * 100,
			'sessions_on_goal_expected' => $item->sessions_on_goal_expected,
			'difference' => $item->sessions_on_goal - $item->sessions_on_goal_expected,
			'multiple' => $item->sessions_on_goal / $item->sessions_on_goal_expected,
		);

		return $item_data;
	}//end report_goal_term

	private function report_goal_user( $item, $data )
	{
		$user_object = new WP_User( $item->user );
		if ( ! isset( $user_object->display_name ) )
		{
			$user_object->display_name = 'not logged in';
		}//end if

		$item->sessions_on_goal_expected = $data['avg_cvr'] * $item->sessions / 100;

		$item_data = array(
			'display_name' => $user_object->display_name,
			'report_url' => $this->report_url( array( 'user' => $user_object->ID, ) ),
			'edit_url' => get_edit_user_link( $item->user ),
			'sessions' => $item->sessions,
			'sessions_on_goal' => $item->sessions_on_goal,
			'cvr' => ( $item->sessions_on_goal / $item->sessions ) * 100,
			'sessions_on_goal_expected' => $item->sessions_on_goal_expected,
			'difference' => $item->sessions_on_goal - $item->sessions_on_goal_expected,
			'multiple' => $item->sessions_on_goal / $item->sessions_on_goal_expected,
		);

		return $item_data;
	}//end report_goal_user

	public function goal_items_ajax()
	{
		$type = empty( $_GET['type'] ) ? '' : $_GET['type'];
		$type = trim( $type );

		if ( ! in_array( $type, array( 'post', 'author', 'term', 'user' ) ) )
		{
			die( 'invalid request' );
		}//end if

		$_GET = $this->fix_ajax_args( $_GET );

		$this->set_filter();
		$sessions_on_goal = $this->sessions_on_goal();
		$this->report_goal_template( $type, $sessions_on_goal );
		die;
	}//end goal_items_ajax

	/**
	 * ajax response to a top sessions request
	 */
	public function top_sessions_ajax()
	{
		$_GET = $this->fix_ajax_args( $_GET );
		$this->set_filter();
		include __DIR__ . '/templates/report-top-sessions.php';
		die;
	}//end top_sessions_ajax

	/**
	 * ajax response to a top users request
	 */
	public function top_users_ajax()
	{
		$_GET = $this->fix_ajax_args( $_GET );
		$this->set_filter();
		include __DIR__ . '/templates/report-top-users.php';
		die;
	}//end top_users_ajax

	/**
	 * massages ajax parameters, fixing collisions and removing garbage
	 */
	public function fix_ajax_args( $args )
	{
		// let's make sure the ajax parameters aren't included in the filter setup
		unset( $args['action'], $args['type'] );

		// There is a variable name collision between WP ajax requests and the bstat "action", we had to
		// pass the bstat action with a different name. Let's fix it for the filter.
		if ( isset( $args['bstat_action'] ) )
		{
			$args['action'] = $args['bstat_action'];
			unset( $args['bstat_action'] );
		}//end if

		return $args;
	}//end fix_ajax_args

	public function report_goal_template( $type, $sessions_on_goal )
	{
		include __DIR__ . '/templates/report-goal-items.php';
	}//end report_goal_template

	public function admin_menu()
	{
		$this->set_filter();

		wp_enqueue_style( bstat()->id_base . '-report' );
		wp_enqueue_script( bstat()->id_base . '-report' );

		require_once __DIR__ . '/templates/bstat-viewer.php';
	}//end admin_menu
}//end class
