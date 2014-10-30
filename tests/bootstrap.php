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

// load the plugins we need active in order to test this plugin
// this runs before we load the main plugin so, well, so the dependencies are in place first
function _manually_load_dependencies()
{
	// when testing locally, this will likely resolve to the plugins directory in your working WordPress install, not the test install elsewhere
	$local_plugin_directory = dirname( dirname( dirname( __FILE__ ) ) );

	// get the array specifying the dependencies
	$dependencies = require __DIR__ . '/dependencies-array.php';

	foreach ( $dependencies as $k => $dependency )
	{

		// first try to get the plugin from the "real" WP install
		if ( is_dir( $local_plugin_directory . $k ) )
		{
			require $local_plugin_directory . $dependency['include'];
			echo "Loaded $k\n";
		}

		// try again, but in the "test" install (this is mostly for Travis)
		elseif ( is_dir( WP_PLUGIN_DIR .'/' . $k ) )
		{
			require WP_PLUGIN_DIR .'/' . $dependency['include'];
			echo "Loaded $k\n";
		}

		// give up
		else
		{
			echo "COULD NOT LOAD $k\n";
		}
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_dependencies' );

// because we're testing in an all-new WP install with mostly empty database
// the plugins won't be active unless we include them manually
function _manually_load_plugin()
{
	// Load our plugin
	require dirname( dirname( __FILE__ ) ) . '/bstat.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';