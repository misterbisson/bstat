<?php

$fetch = "{$type}s_for_session";

// don't show this panel if there are no posts for the sessions on goal
$items = bstat()->report()->$fetch( $sessions_on_goal );
if ( ! count( $items ) )
{
	?>
	<div id="goal-<?php echo esc_attr( $type ); ?>s">
		<h2><?php echo esc_html( ucwords( $type ) ); ?>s contributing to goal</h2>
		<p>
			There were no <?php echo esc_html( $type ); ?>s that contributed to the goal.
		</p>
	</div>
	<?php
}//end if

$data = bstat()->report()->report_goal_items( $type, $items );
?>
<div id="goal-<?php echo esc_attr( $type ); ?>s">
	<h2><?php echo esc_html( ucwords( $type ) ); ?>s contributing to goal</h2>
	<p>
		Showing <?php echo count( $items ); ?> top <?php echo esc_html( $type ); ?>s contributing to <?php echo number_format( count( $sessions_on_goal ) ); ?> goal completions.
	</p>
	<table class="stats">
		<tr>
			<th>User</th>
			<th>All sessions</th>
			<th>Sessions on goal</th>
			<th>CVR</th>
			<th>Expected sessions on goal</th>
			<th>Difference: goal - expected</th>
			<th>Multiple: goal / expected</th>
		</tr>
		<?php
		$summary_row = sprintf(
			'<tr class="stat-summary">
				<th>%1$s</th>
				<th>%2$s</th>
				<th>%3$s</th>
				<th>%4$s</th>
				<th>%5$s</th>
				<th>%6$s</th>
				<th>%7$s</th>
			</tr>',
			'Totals:',
			number_format( $data['sum_sessions'] ),
			number_format( $data['sum_sessions_on_goal'] ),
			number_format( $data['avg_cvr'], 2 ) . '%',
			'&nbsp;',
			'&nbsp;',
			'&nbsp;'
		);

		echo $summary_row;

		foreach ( $data['items'] as $item )
		{
			switch ( $type )
			{
				case 'author':
					printf(
						'<tr>
							<td>%1$s</td>
							<td>%2$s</td>
							<td>%3$s</td>
							<td>%4$s</td>
							<td>%5$s</td>
							<td>%6$s</td>
							<td>%7$s</td>
						</tr>',
						$item['display_name'],
						number_format( $item['sessions'] ),
						number_format( $item['sessions_on_goal'] ),
						number_format( $item['cvr'], 2 ) . '%',
						number_format( $item['sessions_on_goal_expected'], 2 ),
						number_format( $item['difference'], 2 ),
						number_format( $item['multiple'], 2 )
					);
					break;
				case 'post':
					printf(
						'<tr>
							<td><a href="%1$s">%2$s</a> <a href="%3$s">#</a></td>
							<td>%4$s</td>
							<td>%5$s</td>
							<td>%6$s</td>
							<td>%7$s</td>
							<td>%8$s</td>
							<td>%9$s</td>
						</tr>',
						$item['report_url'],
						$item['title'],
						$item['permalink'],
						number_format( $item['sessions'] ),
						number_format( $item['sessions_on_goal'] ),
						number_format( $item['cvr'], 2 ) . '%',
						number_format( $item['sessions_on_goal_expected'], 2 ),
						number_format( $item['difference'], 2 ),
						number_format( $item['multiple'], 2 )
					);
					break;
				case 'term':
					printf(
						'<tr>
							<td>%1$s</td>
							<td>%2$s</td>
							<td>%3$s</td>
							<td>%4$s</td>
							<td>%5$s</td>
							<td>%6$s</td>
							<td>%7$s</td>
						</tr>',
						$item['taxonomy'] . ':' . $item['slug'],
						number_format( $item['sessions'] ),
						number_format( $item['sessions_on_goal'] ),
						number_format( $item['cvr'], 2 ) . '%',
						number_format( $item['sessions_on_goal_expected'], 2 ),
						number_format( $item['difference'], 2 ),
						number_format( $item['multiple'], 2 )
					);
					break;
				case 'user':
					printf(
						'<tr class="stat-row">
							<td><a href="%1$s">%2$s</a> <a href="%3$s">#</a></td>
							<td>%4$s</td>
							<td>%5$s</td>
							<td>%6$s</td>
							<td>%7$s</td>
							<td>%8$s</td>
							<td>%9$s</td>
						</tr>',
						$item['report_url'],
						$item['display_name'],
						$item['edit_url'],
						number_format( $item['sessions'] ),
						number_format( $item['sessions_on_goal'] ),
						number_format( $item['cvr'], 2 ) . '%',
						number_format( $item['sessions_on_goal_expected'], 2 ),
						number_format( $item['difference'], 2 ),
						number_format( $item['multiple'], 2 )
					);
					break;
			}//end switch
		}//end foreach

		echo $summary_row;
		?>
	</table>
</div>
