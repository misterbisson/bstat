<?php

// don't show this panel if there are no matching sessions
if ( ! count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

// don't show this panel if there's only one term
$terms = bstat()->report()->terms_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $terms ) )
{
	return;
}

$data = bstat()->report()->report_goal_items( 'term', $terms );
?>
<h2>Terms contributing to goal</h2>
<p>
	Showing <?php echo count( $terms ); ?> top terms contributing to <?php echo number_format( count( bstat()->report()->sessions_on_goal() ) ); ?> goal completions.
</p>
<table class="stats">
	<tr>
		<th>Term</th>
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
	}//end foreach

	echo $summary_row;
	?>
</table>
