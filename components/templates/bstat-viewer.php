<div id="bstat-viewer">
	<h2>bStat Viewer</h2>

	<?php
	$start = isset( $_GET['timestamp']['min'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['timestamp']['min'] ) : date( 'Y-m-d', strtotime( 'midnight last week' ) );
	$end = isset( $_GET['timestamp']['max'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['timestamp']['max'] ) : NULL;

	do_action( 'go_timepicker_date_range_picker', array(
		'start' => $start,
		'start_field_name' => 'timestamp[min]',
		'end' => $end,
		'end_field_name' => 'timestamp[max]',
	) );

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
			$ajax_url = 'admin-ajax.php?action=bstat_report_goal_items&goal=' . $_GET['goal'];

			if ( isset( $_GET['component'] ) )
			{
				$ajax_url .= '&component=' . $_GET['component'];
			}//end if

			if ( isset( $_GET['action'] ) )
			{
				$ajax_url .= '&bstat_action=' . $_GET['action'];
			}//end if

			if ( isset( $_GET['timestamp']['min'] ) )
			{
				$ajax_url .= '&timestamp%5Bmin%5D=' . $_GET['timestamp']['min'];
			}//end if

			if ( isset( $_GET['timestamp']['max'] ) )
			{
				$ajax_url .= '&timestamp%5Bmax%5D=' . $_GET['timestamp']['max'];
			}//end if
			?>
			<div class="tabs">
				<ul>
					<li><a href="<?php echo esc_url( admin_url( "{$ajax_url}&type=post" ) ); ?>">Posts</a></li>
					<li><a href="<?php echo esc_url( admin_url( "{$ajax_url}&type=author" ) ); ?>">Authors</a></li>
					<li><a href="<?php echo esc_url( admin_url( "{$ajax_url}&type=term" ) ); ?>">Terms</a></li>
					<li><a href="<?php echo esc_url( admin_url( "{$ajax_url}&type=test" ) ); ?>">Tests</a></li>
					<li><a href="<?php echo esc_url( admin_url( "{$ajax_url}&type=user" ) ); ?>">Users</a></li>
					<?php
					$ajax_url = str_replace( 'bstat_report_goal_items', 'bstat_report_goal_flow', $ajax_url );
					?>
					<li><a href="#bstat-report-flow" class="flow-tab" data-url="<?php echo esc_url( admin_url( "{$ajax_url}&type=flow" ) ); ?>">Flow</a></li>
				</ul>
				<div id="bstat-report-flow">
					<h3>Actions</h3>
					<ul class="actions">
					</ul>
					<div id="bstat-parset"><i class="fa fa-spin fa-spinner"></i></div>
				</div>
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

		$ajax_url = 'admin-ajax.php?action=bstat_report_top_%s';

		if ( isset( $_GET['component'] ) )
		{
			$ajax_url .= '&component=' . $_GET['component'];
		}//end if

		if ( isset( $_GET['action'] ) )
		{
			$ajax_url .= '&bstat_action=' . $_GET['action'];
		}//end if

		if ( isset( $_GET['timestamp']['min'] ) )
		{
			$ajax_url .= '&timestamp%5Bmin%5D=' . $_GET['timestamp']['min'];
		}//end if

		if ( isset( $_GET['timestamp']['max'] ) )
		{
			$ajax_url .= '&timestamp%5Bmax%5D=' . $_GET['timestamp']['max'];
		}//end if
		?>
		<div class="tabs">
			<ul>
				<li><a href="<?php echo esc_url( admin_url( sprintf( $ajax_url, 'users' ) ) ); ?>">Users</a></li>
				<li><a href="<?php echo esc_url( admin_url( sprintf( $ajax_url, 'sessions' ) ) ); ?>">Sessions</a></li>
			</ul>
			<?php /* these tabs are loaded in via ajax */ ?>
		</div>
		<?php
	}//end else
	?>
</div>
