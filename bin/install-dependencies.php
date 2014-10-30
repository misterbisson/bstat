<?php

/*
 * Get any plugins that the tested plugin depends on.
 * Update the array in ../tests/dependencies-array.php to add or remove plugins

 * The plugin will be downloaded to the correct place, but activating it is done in ../tests/bootstrap.php
 */

function download_plugin_via_git( $path, $plugin )
{
	// example: git clone https://github.com/GigaOM/go-ui.git $WP_CORE_DIR/wp-content/plugins/go-ui
	echo passthru( "git clone {$plugin['repo']} $path" ) . "\n\n";

	// update submodules, if any
	echo passthru( "cd $path; git submodule update --init --recursive" ) . "\n\n";

	return TRUE;
}

function download_plugin( $path, $plugin )
{
	switch ( $plugin['repotype'] )
	{
		case 'git':
			return download_plugin_via_git( $path, $plugin );
			return TRUE;
		default:
			echo "Unsupported repo type {$plugin['repotype']}\n";
			return FALSE;
	}
}

function download_plugins()
{
	// this path needs to be kept in sync with the path set in install-wp-tests.sh
	// trailing slash expected
	$plugins_dir = '/tmp/wordpress/wp-content/plugins/';

	// the plugins to download
	$dependencies = require dirname( __DIR__ ). '/tests/dependencies-array.php';

	foreach ( $dependencies as $k => $dependency )
	{
		if ( ! is_dir( $plugins_dir . $k ) )
		{
			if ( download_plugin( $plugins_dir . $k, $dependency ) )
			{
				echo "Downloaded $k\n";
			}
			else
			{
				echo "FAILED to download $k\n";
			}
		}
		else
		{
			echo "DIRECTORY EXISTS, skipped $plugins_dir$k\n";
		}
	}
}

download_plugins();
