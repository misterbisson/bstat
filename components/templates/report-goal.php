<?php

// for sanity, limit this to just the top component:action pairs
$components_and_actions = bstat()->report()->top_components_and_actions();
$components_and_actions = array_slice( $components_and_actions, 0, bstat()->options()->report->max_items );

$current_goal = $_GET['goal'] ? preg_replace( '/[0-9]+:(.+):[0-9]+/', '$1', $_GET['goal'] ) : NULL;

?>
<div id="bstat-goal">
	<label>Goal <span>&#8594;</span></label>
	<span class="goal"><?php echo esc_html( $current_goal ?: 'none' ); ?></span>
	<a class="button set"><?php echo $current_goal ? 'Change' : 'Set'; ?></a>
	<ul>
		<?php
		foreach ( $components_and_actions as $component_and_action )
		{
			$url = bstat()->report()->goal_url( array(
				'blog' => bstat()->get_blog(),
				'component' => $component_and_action->component,
				'action' => $component_and_action->action,
				'frequency' => 1,
			) );

			printf(
				'<li><a href="%1$s">%2$s</a>:<a href="%1$s">%3$s</a></li>',
				esc_url( $url ),
				$component_and_action->component,
				$component_and_action->action
			);
		}//end foreach
		?>
	</ul>
</div>
