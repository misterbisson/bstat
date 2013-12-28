<?php
class bStat_Report extends bStat
{
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

	public function admin_menu()
	{
		echo '<h2>bStat Viewer</h2>';

echo '<pre>';

/*
Active posts (last 24-36 hours). All activity, or by $component:$action
Optionally, limit to posts published in that timespan
*/
echo '<h2>Posts, by total activity</h2>';
$top_posts = $this->db()->select( FALSE, FALSE, 'post,hits', 1000 );

/*
Posts that led to most second pageviews, or other $component:$action
Optionally, limit to posts published in that timespan
*/
echo '<h2>Posts that led to most second pageviews, by total activity</h2>';
$sessions_raw = $this->db()->select( FALSE, FALSE, 'sessions', 1000 );
$sessions = $posts = $posts_raw = array();
foreach ( $sessions_raw as $session )
{
	$sessions[ $session ] = wp_list_pluck( $this->db()->select( 'session', $session, 'all', 100 ), 'post' );

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
	$posts[] = (object) array(
		'post' => $k,
		'hits' => $v + 1,
	);
}
print_r( $posts );

/*
Top authors (last 24-36 hours)
Optionally, limit to posts published in that timespan
*/
echo '<h2>Authors, by total activity</h2>';
global $wpdb;
$authors = $wpdb->get_results( 'SELECT post_author, COUNT(1) AS hits FROM ' . $wpdb->posts . ' WHERE ID IN(' . implode( ',', array_map( 'absint', wp_list_pluck( $top_posts, 'post' ) ) ) . ') GROUP BY post_author ORDER BY hits DESC' );
print_r( $authors );


/*
Top taxonomy terms (last 24-36 hours)
Optionally, limit to posts published in that timespan
*/
echo '<h2>Taxonomy terms, by total activity</h2>';
global $wpdb;
$facets_query = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, COUNT(c.term_taxonomy_id) AS `count`
	FROM $wpdb->term_relationships c
	INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
	INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
	WHERE c.object_id IN (" . implode( ',', array_map( 'absint', wp_list_pluck( $top_posts, 'post' ) ) ) . ")
	GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
	/* generated in bStat_Report::some_function_or_something() */";
$terms = $wpdb->get_results( $facets_query );
print_r( $terms );


/*
Top $components and $actions (last 24-36 hours)
Optionally, limit to posts published in that timespan
*/
echo '<h2>Components and actions, by total activity</h2>';
print_r( $this->db()->select( FALSE, FALSE, 'components_and_actions,hits', 100 ) );

/*
Top users (last 24-36 hours)
Optionally filter by role
*/
echo '<h2>Users, by total activity</h2>';
print_r( $this->db()->select( FALSE, FALSE, 'user,hits', 100 ) );

/*
Filter by:
Blog
Group
*/
echo '<h2>Groups, by total activity</h2>';
print_r( $this->db()->select( FALSE, FALSE, 'group,hits', 100 ) );

echo '<h2>Blogs, by total activity</h2>';
print_r( $this->db()->select( FALSE, FALSE, 'blog,hits', 100, array( 'blog' => FALSE ) ) );

echo '</pre>';
	}

}