<?php

// for sanity, limit this to just the top 100 component:action pairs
$components_and_actions = bstat()->report()->top_components_and_actions();
$components_and_actions = array_slice( $components_and_actions, 0, bstat()->options()->report->max_items );

echo '<h2>Set goal</h2>';
echo '<ol>';
foreach ( $components_and_actions as $component_and_action )
{
	printf(
		'<li><a href="%1$s">%2$s</a>:<a href="%3$s">%4$s</a></li>',
		bstat()->report()->goal_url( array( 'component' => $component_and_action->component, 'action' => $component_and_action->action, 'frequency' => 1 ) ),
		$component_and_action->component,
		bstat()->report()->goal_url( array( 'component' => $component_and_action->component, 'action' => $component_and_action->action, 'frequency' => 1 ) ),
		$component_and_action->action
	);
}
echo '</ol>';