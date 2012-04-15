<?php
/*
Plugin Name: bStat
Plugin URI: http://maisonbisson.com/bsuite/
Description: Stats!
Version: 5.0
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/
/*
// get options
$bsoptions = get_option('bstat-options');

// insert default options if the options array is empty
if( empty( $bsoptions ))
{
	$bsoptions = array( 
		'open-graph' => 1,
		'featured-comments' => 1,
		'twitter-api' => 1,
		'twitter-comments' => 1,
		'twitter-app_id' => '',
		'facebook-api' => 1,
		'facebook-add_button' => 1,
		'facebook-comments' => 0,
		'facebook-admins' => '',
		'facebook-app_id' => '',
		'facebook-secret' => '',
	);

	update_option( 'bstat-options' , $bsoptions );
}

// the admin menu
if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';
*/

// Better describe your content to social sites
require_once( dirname( __FILE__ ) .'/components/daemon.php' );
$bstat_daemon = new bStat_Daemon;
require_once( dirname( __FILE__ ) .'/components/behavior.php' );
$bstat_behaviors = new bStat_Behaviors;
