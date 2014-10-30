<?php

/*
 * The following array specifies what other plugins are required for this plugin
 * Any necessary configuration of the dependent plugins should be done in the bootstrap.php
 */

return array(
	'go-ui' => array(
		'include' => 'go-ui/go-ui.php',
		'repo' => 'https://github.com/GigaOM/go-ui.git',
		'repotype' => 'git',
	),
	'go-graphing' => array(
		'include' => 'go-graphing/go-graphing.php',
		'repo' => 'https://github.com/GigaOM/go-graphing.git',
		'repotype' => 'git',
	),
	'go-timepicker' => array(
		'include' => 'go-timepicker/go-timepicker.php',
		'repo' => 'https://github.com/GigaOM/go-timepicker.git',
		'repotype' => 'git',
	),
);