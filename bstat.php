<?php
/*
Plugin Name: bStat
Plugin URI: http://wordpress.org/plugins/bstats/
Description: Blog stats and activity stream
Version: 6.0
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

require_once __DIR__ . '/components/class-bstat.php';
bstat();

register_activation_hook( __FILE__, array( bstat(), 'initial_setup' ) );


// comment tracking is kept separate as an example of how to build other integrations
require_once __DIR__ . '/components/class-bstat-comments.php';
require_once __DIR__ . '/components/class-bstat-wpcore.php';
bstat_comments();
bstat_wpcore();
