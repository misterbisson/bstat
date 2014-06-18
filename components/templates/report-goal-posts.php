<?php

// don't show this panel if there are no matching sessions
if ( ! count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

// don't show this panel if there are no posts for the sessions on goal
$posts = bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $posts ) )
{
	return;
}

$data = bstat()->report()->report_goal_items( 'post', $posts );
?>
<h2>Posts contributing to goal</h2>
<p>
	Showing <?php echo count( $posts ); ?> top posts contributing to <?php echo number_format( count( bstat()->report()->sessions_on_goal() ) ); ?> goal completions.
</p>
<table class="stats">
	<tr>
		<th>Post</th>
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
	}//end foreach

	echo $summary_row;
	?>
</table>
