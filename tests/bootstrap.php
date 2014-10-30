<?php

// The $_tests_dir includes the wp-config for the test instance, 
// and the contents of: https://core.trac.wordpress.org/browser/trunk/tests/phpunit/includes
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir )
{
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// see source in https://core.trac.wordpress.org/browser/trunk/tests/phpunit/includes/functions.php
require_once $_tests_dir . '/includes/functions.php';

// because we're testing in an all-new WP install with mostly empty database
// the plugins won't be active unless we include them manually
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