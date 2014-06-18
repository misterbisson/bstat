<div id="bstat-viewer">
	<h2>bStat Viewer</h2>

	<?php
	// goal controls
	include __DIR__ . '/report-goal.php';

	// top components and actions
	include __DIR__ . '/report-top-components-and-actions.php';

	if ( $this->get_goal() )
	{
		// a timeseries graph of all activity on goal, broken out by component:action
		include __DIR__ . '/report-goal-timeseries.php';

		// a scatter plot of goal events by day and time
		include __DIR__ . '/report-goal-scatterplot.php';

		$sessions_on_goal = bstat()->report()->sessions_on_goal();

		// don't show this panel if there are no matching sessions
		if ( count( $sessions_on_goal ) )
		{
			// goal posts
			$this->report_goal_template( 'post', $sessions_on_goal );

			// top authors by activity on their posts
			$this->report_goal_template( 'author', $sessions_on_goal );

			// top taxonomy terms
			$this->report_goal_template( 'term', $sessions_on_goal );

			// top users
			$this->report_goal_template( 'user', $sessions_on_goal );
		}//end if
	}//end if
	else
	{
		// a timeseries graph of all activity, broken out by component:action
		include __DIR__ . '/report-timeseries.php';

		// filter controls
		include __DIR__ . '/report-filter.php';

		// information for single component:action pairs
		include __DIR__ . '/report-action-info.php';

		// top users
		include __DIR__ . '/report-top-users.php';

		// active sessions
		include __DIR__ . '/report-top-sessions.php';
	}//end else
	?>
</div>
