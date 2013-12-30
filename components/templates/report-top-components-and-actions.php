<?php

// don't show this panel if there's only one component:action
$components_and_actions = $this->top_components_and_actions();
if ( 2 > count( $components_and_actions ) )
{
	return;
}

// for sanity, limit this to just the top 100 component:action pairs
$components_and_actions = array_slice( $components_and_actions, 0, 100 );

$total_activity = 0;
foreach ( $components_and_actions as $component_and_action )
{
	$total_activity += $component_and_action->hits;
}

echo '<h2>Components and actions, by total activity</h2>';
echo '<p>Showing ' . count( $components_and_actions ) . ' component:action pairs with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $components_and_actions as $component_and_action )
{
	echo '<li>' . $component_and_action->component . ':' . $component_and_action->action .' (' . (int) $component_and_action->hits . ' hits)</li>';
}
echo '</ol>';