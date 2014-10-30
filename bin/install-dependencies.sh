#!/usr/bin/env bash

#
# Get any plugins that the tested plugin depends on.
# Add/update the ${plugins[@]} array below to identify the dependencies.
#
# The plugin will be downloaded to the correct place, but activating it is another step.
#


WP_CORE_DIR=/tmp/wordpress

set -ex

# Setup our array of dependencies

# -A tells bash this is an associative array
# found in http://www.linuxjournal.com/content/bash-associative-arrays
declare -A plugins

# plugin_dir_name => git https URL format
# https URL seems more compatible with Travis environment per http://stackoverflow.com/questions/15674064/github-submodule-access-rights-travis-ci

plugins[go-ui]="https://github.com/GigaOM/go-ui.git"
plugins[go-graphing]="https://github.com/GigaOM/go-graphing.git"
plugins[go-timepicker]="https://github.com/GigaOM/go-timepicker.git"

download_plugins()
{
	# bash keys are accessed using an exclamation point
	# found in http://stackoverflow.com/questions/3112687/how-to-iterate-over-associative-array-in-bash
	for i in "${!plugins[@]}"
	do
		# example: git clone https://github.com/GigaOM/go-ui.git $WP_CORE_DIR/wp-content/plugins/go-ui
		git clone ${plugins[$i]} $WP_CORE_DIR/wp-content/plugins/$i

		# update submodules, if any
		cd $WP_CORE_DIR/wp-content/plugins/$i
		git submodule update --init --recursive
	done
}

download_plugins
