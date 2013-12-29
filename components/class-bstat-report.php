<?php
class bStat_Report extends bStat
{
	public $filter = array();

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		$this->rickshaw();
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
				'min' => strtotime( 'midnight yesterday' ),
			),
		);

		date_default_timezone_set( $old_tz );

		return array_merge( $filter, (array) $add_filter );
	}

	public function set_filter( $filter = FALSE )
	{

		// defaults
		if ( ! $filter )
		{
			$filter = $this->default_filter();
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

	public function get_posts( $top_posts_list, $query_args = array() )
	{
		if ( ! $get_posts = wp_cache_get( $this->cache_key( 'get_posts ' . md5( serialize( $top_posts_list ) . serialize( $query_args ) ) ), $this->id_base ) )
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
				$post_hits[ $line->post ] = $line->hits;
			}

			foreach ( $get_posts as $k => $v )
			{
				$get_posts[ $k ]->hits = $post_hits[ $v->ID ];
			}

			wp_cache_set( $this->cache_key( 'get_posts ' . md5( serialize( $top_posts_list ) . serialize( $query_args ) ) ), $get_posts, $this->id_base, $this->cache_ttl() );
		}

		return $get_posts;
	}

	public function timeseries( $quantize_minutes = 1, $filter = FALSE )
	{
		// minutes are a positive integer, equal to or larger than 1
		$quantize_minutes = absint( $quantize_minutes );
		$quantize_minutes = max( $quantize_minutes, 1 );
		$seconds = $quantize_minutes * 60;

		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $timeseries = wp_cache_get( $this->cache_key( 'timeseries ' . $seconds, $filter ), $this->id_base ) )
		{
			$timeseries_raw = $this->db()->select( FALSE, FALSE, 'all', 10000, $filter );

			$timeseries = array();
			foreach ( $timeseries_raw as $item )
			{
				$quantized_time = $seconds * (int) ( $item->timestamp / $seconds );

				if ( ! isset( $timeseries[ $quantized_time ] ) )
				{
					$timeseries[ $quantized_time ] = 1;
				}
				else
				{
					$timeseries[ $quantized_time ] ++;
				}

			}

			ksort( $timeseries ); 

			// determine the min and max timestamps
			// these can be provided in the query filters
			// or derived from the result data if not specified
			$keys = array_keys( $timeseries );
			if ( isset( $filter['timestamp']['min'] ) )
			{
				$min = $seconds * (int) ( $filter['timestamp']['min'] / $seconds );
			}
			else
			{
				$min = reset( $keys );
			}

			if ( isset( $filter['timestamp']['max'] ) )
			{
				$max = $seconds * (int) ( $filter['timestamp']['max'] / $seconds );
			}
			else
			{
				$max = end( $keys );
			}

			// get an array of all the quantized timeslots, including those with no activity
			$keys = array_fill_keys( range( $min, $max, $seconds ), 0 );

			// fill out the result array so all the timeslots are represented
			$timeseries = array_replace( $keys, $timeseries );

			wp_cache_set( $this->cache_key( 'timeseries ' . $seconds, $filter ), $timeseries, $this->id_base, $this->cache_ttl() );
		}

		// tips for using the output:
		// the array key is a quantized timestamp, pass it into date( $format, $quantized_time ) and get a human readable date.
		// the value is the count of activity hits for that quantized time segment.
		return $timeseries;
	}

	public function top_posts( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_posts = wp_cache_get( $this->cache_key( 'top_posts', $filter ), $this->id_base ) )
		{
			$top_posts = $this->db()->select( FALSE, FALSE, 'post,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_posts', $filter ), $top_posts, $this->id_base, $this->cache_ttl() );
		}

		return $top_posts;
	}

	public function top_sessions( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_sessions = wp_cache_get( $this->cache_key( 'top_sessions', $filter ), $this->id_base ) )
		{
			$top_sessions = $this->db()->select( FALSE, FALSE, 'sessions', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_sessions', $filter ), $top_sessions, $this->id_base, $this->cache_ttl() );
		}

		return $top_sessions;
	}

	public function posts_for_session( $session, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $posts_for_session = wp_cache_get( $this->cache_key( 'posts_for_session ' . $session, $filter ), $this->id_base ) )
		{
			$posts_for_session = $this->db()->select( 'session', $session, 'post,hits', 250, $filter );
			wp_cache_set( $this->cache_key( 'posts_for_session ' . $session, $filter ), $posts_for_session, $this->id_base, $this->cache_ttl() );
		}

		return $posts_for_session;
	}

	public function posts_for_user( $user, $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $posts_for_user = wp_cache_get( $this->cache_key( 'posts_for_user ' . $user, $filter ), $this->id_base ) )
		{
			$posts_for_user = $this->db()->select( 'user', $user, 'post,hits', 250, $filter );
			wp_cache_set( $this->cache_key( 'posts_for_user ' . $user, $filter ), $posts_for_user, $this->id_base, $this->cache_ttl() );
		}

		return $posts_for_user;
	}

	public function top_tentpole_posts( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_tentpole_posts = wp_cache_get( $this->cache_key( 'top_tentpole_posts', $filter ), $this->id_base ) )
		{
			$top_tentpole_posts = $posts_raw = $sessions = array();

			$sessions_raw = $this->top_sessions( $filter );
			foreach ( $sessions_raw as $session )
			{
				$sessions[ $session ] = wp_list_pluck( $this->posts_for_session( $session, $filter ), 'post' );

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

			wp_cache_set( $this->cache_key( 'top_tentpole_posts', $filter ), $top_tentpole_posts, $this->id_base, $this->cache_ttl() );
		}

		return $top_tentpole_posts;
	}

	public function top_authors( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_authors = wp_cache_get( $this->cache_key( 'top_authors', $filter ), $this->id_base ) )
		{
			global $wpdb;

			$sql = 'SELECT post_author, COUNT(1) AS hits
				FROM ' . $wpdb->posts . '
				WHERE ID IN(' . implode( ',', array_map( 'absint', wp_list_pluck( $this->top_posts( $filter ), 'post' ) ) ) . ')
				GROUP BY post_author
				ORDER BY hits DESC
				/* generated in bStat_Report::top_authors() */';

			$top_authors = $wpdb->get_results( $sql );

			wp_cache_set( $this->cache_key( 'top_authors', $filter ), $top_authors, $this->id_base, $this->cache_ttl() );
		}

		return $top_authors;
	}

	public function top_terms( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_terms = wp_cache_get( $this->cache_key( 'top_terms', $filter ), $this->id_base ) )
		{
			global $wpdb;
			$sql = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, a.count, COUNT(c.term_taxonomy_id) AS `hits`
				FROM $wpdb->term_relationships c
				INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
				INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
				WHERE c.object_id IN (" . implode( ',', array_map( 'absint', wp_list_pluck( $this->top_posts( $filter ), 'post' ) ) ) . ")
				GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
				/* generated in bStat_Report::top_terms() */";

			$top_terms = $wpdb->get_results( $sql );

			wp_cache_set( $this->cache_key( 'top_terms', $filter ), $top_terms, $this->id_base, $this->cache_ttl() );
		}

		return $top_terms;
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

	public function top_components_and_actions( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_components_and_actions = wp_cache_get( $this->cache_key( 'top_components_and_actions', $filter ), $this->id_base ) )
		{
			$top_components_and_actions = $this->db()->select( FALSE, FALSE, 'components_and_actions,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_components_and_actions', $filter ), $top_components_and_actions, $this->id_base, $this->cache_ttl() );
		}

		return $top_components_and_actions;
	}

	public function top_users( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_users = wp_cache_get( $this->cache_key( 'top_users', $filter ), $this->id_base ) )
		{
			$top_users = $this->db()->select( FALSE, FALSE, 'user,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_users', $filter ), $top_users, $this->id_base, $this->cache_ttl() );
		}

		return $top_users;
	}

	public function top_groups( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		if ( ! $top_groups = wp_cache_get( $this->cache_key( 'top_groups', $filter ), $this->id_base ) )
		{
			$top_groups = $this->db()->select( FALSE, FALSE, 'group,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_groups', $filter ), $top_groups, $this->id_base, $this->cache_ttl() );
		}

		return $top_groups;
	}

	public function top_blogs( $filter = FALSE )
	{
		if ( ! $filter )
		{
			$filter = $this->filter;
		}

		$filter['blog'] = FALSE;

		if ( ! $top_blogs = wp_cache_get( $this->cache_key( 'top_blogs', $filter ), $this->id_base ) )
		{
			$top_blogs = $this->db()->select( FALSE, FALSE, 'blog,hits', 1000, $filter );
			wp_cache_set( $this->cache_key( 'top_blogs', $filter ), $top_blogs, $this->id_base, $this->cache_ttl() );
		}

		return $top_blogs;
	}

	public function admin_menu()
	{
		$this->set_filter();

		echo '<h2>bStat Viewer</h2><pre>';

$components = array_slice( $this->top_components_and_actions(), 0, 5 );
foreach ( $components as $component )
{
//	print_r( $this->timeseries( 15, $this->default_filter( array( 'component' => $component->component, 'action' => $component->action ) ) ) );
}
//print_r( $components );
?>
<style>
#chart_container {
        display: inline-block;
        font-family: Arial, Helvetica, sans-serif;
}
#chart {
        float: left;
}
#legend {
        float: left;
        margin-left: 15px;
}
#y_axis {
        float: left;
        width: 40px;
}
</style>


<div id="chart_container">
        <div id="y_axis"></div>
        <div id="chart"></div>
        <div id="legend"></div>
</div>

<script>

var palette = new Rickshaw.Color.Palette();

var graph = new Rickshaw.Graph( {
        element: document.querySelector("#chart"),
        width: 540,
        height: 240,
        renderer: 'stack',
        series: [
<?php
foreach ( $components as $component )
{
?>
                {
                        name: "<?php echo $component->component .':' . $component->action; ?>",
                        data: <?php echo json_encode( $this->rickshaw()->array_to_series( $this->timeseries( 15, array( 
                        	'component' => $component->component,
                        	'action' => $component->action,
                        	'timestamp' => array(
                        		'min' => 15 * 60 * (int) ( ( time() - 115200 ) / 1515 * 60 ),
                        		'max' => 15 * 60 * (int) ( time() / 1515 * 60 ),
                        	),
                        ) ) ) ); ?>,
                        color: palette.color()
                },
<?php
}
?>
        ]
} );

var x_axis = new Rickshaw.Graph.Axis.Time( { graph: graph } );

var y_axis = new Rickshaw.Graph.Axis.Y( {
        graph: graph,
        orientation: 'left',
        tickFormat: Rickshaw.Fixtures.Number.formatKMBT,
        element: document.getElementById('y_axis'),
} );

var legend = new Rickshaw.Graph.Legend( {
        element: document.querySelector('#legend'),
        graph: graph
} );

graph.render();

</script>
<?php
		echo '<pre>';
//print_r( json_encode( $this->rickshaw()->array_to_series( $this->timeseries( 1 ) ) ) );

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
		foreach ( array_slice( $this->top_authors(), 0, 3 ) as $author )
		{
			print_r( $this->get_posts( $this->top_posts(), array( 'author' => $author->post_author, 'posts_per_page' => 1 ) ) );
		}

		/*
		Top taxonomy terms (last 24-36 hours)
		Optionally, limit to posts published in that timespan
		*/
		echo '<h2>Taxonomy terms, by total activity</h2>';
		print_r( $this->top_terms() );
		foreach ( array_slice( $this->top_terms(), 0, 3 ) as $term )
		{
			print_r( $this->top_posts_for_term( $term, array( 'posts_per_page' => 1 ) ) );
		}


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
		foreach ( array_slice( $this->top_users(), 0, 3 ) as $user )
		{
			print_r( $this->get_posts( $this->posts_for_user( $user->user ), array( 'posts_per_page' => 1 ) ) );
		}

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