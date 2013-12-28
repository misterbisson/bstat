<?php
class bStat_Report extends bStat
{

	public $cache_ttl = 101;
	public $filter = array();

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu_init' ) );
		wp_enqueue_style( $this->id_base . '-report', plugins_url( 'css/bstat-report.css', __FILE__ ), array(), $this->version );
	} // END init

	// add the menu item to the dashboard
	public function admin_menu_init()
	{
		$this->menu_url = admin_url( 'index.php?page=' . $this->id_base . '-report' );

		add_submenu_page( 'index.php', 'bStat Viewer', 'bStat Viewer', 'edit_posts', $this->id_base . '-report', array( $this, 'admin_menu' ) );
	} // END admin_menu_init

	public function set_filter( $filter = FALSE )
	{

		// defaults
		if ( ! $filter )
		{
			// set the timezone to UTC for the later strtotime() call,
			// preserve the old timezone so we can set it back when done
			$old_tz = date_default_timezone_get();
			date_default_timezone_set( 'UTC' );

			// only setting the oldest part of the time window for better caching
			// the newest part is filled in with the current time when the query is executed
			$filter = array(
				'timestamp' => array(
					'min' => strtotime( 'midnight yesterday' ),
				),
			);

			date_default_timezone_set( $old_tz );
		}

		$this->filter = (array) $filter;
	}

	public function cache_key( $part )
	{
		return $part .' '. md5( serialize( (array) $this->filter ) );
	}

	public function top_posts()
	{
		if ( ! $top_posts = wp_cache_get( $this->cache_key( 'top_posts' ), $this->id_base ) )
		{
			$top_posts = $this->db()->select( FALSE, FALSE, 'post,hits', 1000, $this->filter );
			wp_cache_set( $this->cache_key( 'top_posts' ), $top_posts, $this->id_base, $this->cache_ttl );
		}

		return $top_posts;
	}

	public function top_sessions()
	{
		if ( ! $top_sessions = wp_cache_get( $this->cache_key( 'top_sessions' ), $this->id_base ) )
		{
			$top_sessions = $this->db()->select( FALSE, FALSE, 'sessions', 1000, $this->filter );
			wp_cache_set( $this->cache_key( 'top_sessions' ), $top_sessions, $this->id_base, $this->cache_ttl );
		}

		return $top_sessions;
	}

	public function posts_for_session( $session )
	{
		if ( ! $posts_for_session = wp_cache_get( $this->cache_key( 'posts_for_session ' . $session ), $this->id_base ) )
		{
			$posts_for_session = $this->db()->select( 'session', $session, 'all', 250, $this->filter );
			wp_cache_set( $this->cache_key( 'posts_for_session ' . $session ), $posts_for_session, $this->id_base, $this->cache_ttl );
		}

		return $posts_for_session;
	}

	public function top_tentpole_posts()
	{
		if ( ! $top_tentpole_posts = wp_cache_get( $this->cache_key( 'top_tentpole_posts' ), $this->id_base ) )
		{
			$top_tentpole_posts = $posts_raw = $sessions = array();

			$sessions_raw = $this->top_sessions();
			foreach ( $sessions_raw as $session )
			{
				$sessions[ $session ] = wp_list_pluck( $this->posts_for_session( $session ), 'post' );

				if ( 1 >= count( $sessions[ $session ] ) )
				{
					continue;
				}

				$post = end( $sessions[ $session ] );
				if ( isset( $posts_raw[ $post ] ) )
				{
					$posts_raw[ $post ]++;
				}
				else
				{
					$posts_raw[ $post ] = 0;
				}
			}
			arsort( $posts_raw );
			$posts_raw = array_filter( $posts_raw );
			foreach ( $posts_raw as $k => $v )
			{
				$top_tentpole_posts[] = (object) array(
					'post' => $k,
					'hits' => $v + 1,
				);
			}

			wp_cache_set( $this->cache_key( 'top_tentpole_posts' ), $top_tentpole_posts, $this->id_base, $this->cache_ttl );
		}

		return $top_tentpole_posts;
	}

	public function top_authors()
	{
		if ( ! $top_authors = wp_cache_get( $this->cache_key( 'top_authors' ), $this->id_base ) )
		{
			global $wpdb;

			$sql = 'SELECT post_author, COUNT(1) AS hits
				FROM ' . $wpdb->posts . '
				WHERE ID IN(' . implode( ',', array_map( 'absint', wp_list_pluck( $this->top_posts(), 'post' ) ) ) . ')
				GROUP BY post_author
				ORDER BY hits DESC
				/* generated in bStat_Report::top_authors() */';

			$top_authors = $wpdb->get_results( $sql );

			wp_cache_set( $this->cache_key( 'top_authors' ), $top_authors, $this->id_base, $this->cache_ttl );
		}

		return $top_authors;
	}

	public function top_terms()
	{
		if ( ! $top_terms = wp_cache_get( $this->cache_key( 'top_terms' ), $this->id_base ) )
		{
			global $wpdb;
			$sql = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, a.count, COUNT(c.term_taxonomy_id) AS `hits`
				FROM $wpdb->term_relationships c
				INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
				INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
				WHERE c.object_id IN (" . implode( ',', array_map( 'absint', wp_list_pluck( $this->top_posts(), 'post' ) ) ) . ")
				GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
				/* generated in bStat_Report::top_terms() */";

			$top_terms = $wpdb->get_results( $sql );

			wp_cache_set( $this->cache_key( 'top_terms' ), $top_terms, $this->id_base, $this->cache_ttl );
		}

		return $top_terms;
	}

	public function top_components_and_actions()
	{
		if ( ! $top_components_and_actions = wp_cache_get( $this->cache_key( 'top_components_and_actions' ), $this->id_base ) )
		{
			$top_components_and_actions = $this->db()->select( FALSE, FALSE, 'components_and_actions,hits', 1000, $this->filter );
			wp_cache_set( $this->cache_key( 'top_components_and_actions' ), $top_components_and_actions, $this->id_base, $this->cache_ttl );
		}

		return $top_components_and_actions;
	}

	public function top_users()
	{
		if ( ! $top_users = wp_cache_get( $this->cache_key( 'top_users' ), $this->id_base ) )
		{
			$top_users = $this->db()->select( FALSE, FALSE, 'user,hits', 1000, $this->filter );
			wp_cache_set( $this->cache_key( 'top_users' ), $top_users, $this->id_base, $this->cache_ttl );
		}

		return $top_users;
	}

	public function top_groups()
	{
		if ( ! $top_groups = wp_cache_get( $this->cache_key( 'top_groups' ), $this->id_base ) )
		{
			$top_groups = $this->db()->select( FALSE, FALSE, 'group,hits', 1000, $this->filter );
			wp_cache_set( $this->cache_key( 'top_groups' ), $top_groups, $this->id_base, $this->cache_ttl );
		}

		return $top_groups;
	}

	public function top_blogs()
	{
		$filter = (array) $this->filter;
		$filter['blog'] = FALSE;

		if ( ! $top_blogs = wp_cache_get( $this->cache_key( 'top_blogs' ), $this->id_base ) )
		{
			$top_blogs = $this->db()->select( FALSE, FALSE, 'blog,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_blogs' ), $top_blogs, $this->id_base, $this->cache_ttl );
		}

		return $top_blogs;
	}

	public function admin_menu()
	{
		$this->set_filter();

		echo '<h2>bStat Viewer</h2>';

		echo '<pre>';

		/*
		Active posts (last 24-36 hours). All activity, or by $component:$action
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Posts, by total activity</h2>';
		print_r( $this->top_posts() );

		/*
		Posts that led to most second pageviews, or other $component:$action
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Posts that led to most second pageviews, by total activity</h2>';
		print_r( $this->top_tentpole_posts() );

		/*
		Top authors (last 24-36 hours)
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Authors, by total activity</h2>';
		print_r( $this->top_authors() );

		/*
		Top taxonomy terms (last 24-36 hours)
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Taxonomy terms, by total activity</h2>';
		print_r( $this->top_terms() );

		/*
		Top $components and $actions (last 24-36 hours)
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Components and actions, by total activity</h2>';
		print_r( $this->top_components_and_actions() );

		/*
		Top users (last 24-36 hours)
		Optionally filter by role
		*/
		echo '<h2>Users, by total activity</h2>';
		print_r( $this->top_users() );

		/*
		Filter by:
		Blog
		Group
		*/
		echo '<h2>Groups, by total activity</h2>';
		print_r( $this->top_groups() );

		echo '<h2>Blogs, by total activity</h2>';
		print_r( $this->top_blogs() );

		echo '</pre>';
	}

}