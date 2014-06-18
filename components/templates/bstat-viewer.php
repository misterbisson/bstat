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
			$ajax_url = admin_url( 'admin-ajax.php?action=bstat_report_goal_items&goal=' . $_GET['goal'] );
			?>
			<div class="tabs">
				<ul>
					<li><a href="<?php echo esc_url( "{$ajax_url}&type=post" ); ?>">Posts</a></li>
					<li><a href="<?php echo esc_url( "{$ajax_url}&type=author" ); ?>">Authors</a></li>
					<li><a href="<?php echo esc_url( "{$ajax_url}&type=term" ); ?>">Terms</a></li>
					<li><a href="<?php echo esc_url( "{$ajax_url}&type=user" ); ?>">Users</a></li>
				</ul>
				<?php /* goal data is loaded in via ajax */ ?>
			</div>
			<?php
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
