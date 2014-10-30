<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir )
{
	$_tests_dir = '/tmp/wordpress-tests-lib';
}
require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin()
{
	$directory_of_this_plugin = dirname( dirname( __FILE__ ) );

	// the plugins we need active in order to test this plugin
	$dependencies = array(
		'go-ui/go-ui.php',
		'go-graphing/go-graphing.php',
		'go-timepicker/go-timepicker.php',
	);

	foreach ( $dependencies as $dependency )
	{
		if ( is_dir( dirname( $directory_of_this_plugin ) . dirname( $dependency ) ) )
		{
			require dirname( $directory_of_this_plugin ) . $dependency;
			echo "Loaded $dependency\n";
		}
		elseif ( is_dir( WP_PLUGIN_DIR .'/' . dirname( $dependency ) ) )
		{
			require WP_PLUGIN_DIR .'/' . $dependency;
			echo "Loaded $dependency\n";
		}
		else
		{
			echo "COULD NOT LOAD $dependency\n";
		}
	}

	// Load our plugin
	require $directory_of_this_plugin . '/bstat.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

