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
//print_r( $this->db()->select( NULL, NULL, 'posts' ) );

//print_r( $this->db()->select( 'post', 17283, 'mixedusers', FALSE, array( 'blog' => FALSE ) ) );


print_r( $this->db()->select( 'mixedusers', $this->db()->select( 'post', 17283, 'mixedusers' ), 'posts' ) );
echo '</pre>';

/*
Active posts (last 24-36 hours). All activity, or by $component:$action
Optionally, limit to posts published in that timespan

Posts that led to most second pageviews, or other $component:$action
Optionally, limit to posts published in that timespan

Top authors (last 24-36 hours)
Optionally, limit to posts published in that timespan

Top users (last 24-36 hours)
Optionally filter by role

Top taxonomy terms (last 24-36 hours)
Optionally, limit to posts published in that timespan

Top $components and $actions (last 24-36 hours)
Optionally, limit to posts published in that timespan

Filter by:
Blog
Group
*/
	}

}