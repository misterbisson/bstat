<?php

// don't show this panel if there's only one group
$groups = bstat()->report()->top_groups();
if ( 2 > count( $groups ) )
{
	return;
}

// for sanity, limit this to just the top few groups
$groups = array_slice( $groups, 0, bstat()->options()->report->max_items );

$total_activity = 0;
foreach ( $groups as $group )
{
	$total_activity += $group->hits;
}

echo '<h2>Groups</h2>';
echo '<p>Showing ' . count( $groups ) . ' users with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $groups as $group )
{
	printf(
		'<li><a href="%1$s">%2$s</a> (%3$s hits)',
		bstat()->report()->report_url( array( 'group' => (int) $group->group, ) ),
		(int) $group->group,
		(int) $group->hits
	);
}
echo '</ol>';