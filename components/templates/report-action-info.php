<?php
$filter = bstat()->report()->filter;

// don't show this panel if there isn't a component:action filter set
if ( ! isset( $filter['component'], $filter['action'] ) )
{
	return;
}

$infos = bstat()->report()->component_and_action_info( array( 'component' => $filter['component'], 'action' => $filter['action'] ) );

// for sanity, limit this to just the top few component:action pairs
$infos = array_slice( $infos, 0, bstat()->options()->report->max_items );

$total_activity = 0;
foreach ( $infos as $info )
{
	$total_activity += $info->hits;
}

echo '<h2>Additional info for ' . $filter['component'] . ' ' . $filter['action'] . '</h2>';
echo '<p>Showing ' . count( $infos ) . ' info with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $infos as $info )
{
	printf(
		'<li>%1$s (%2$s hits)</li>',
		$info->info,
		$info->hits
	);
}
echo '</ol>';