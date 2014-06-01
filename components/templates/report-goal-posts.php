<?php

// don't show this panel if there are no posts for the sessions on goal
$posts = bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $posts ) )
{
	return;
}

// for sanity, limit this to just the top few posts
$posts = array_slice( $posts, 0, bstat()->options()->report->max_items * 10 );

foreach ( $posts as $post )
{
	$post->sessions = count( bstat()->report()->sessions_for( 'post', $post->post ) );
	$post->sessions_on_goal = count(
		bstat()->report()->sessions_for(
			'sessions',bstat()->report()->sessions_on_goal(),
			array_merge(
				bstat()->report()->filter,
				array( 'post' => $post->post )
			)
		)
	);
}

$sum_sessions = array_sum( wp_list_pluck( $posts, 'sessions' ) );
$sum_sessions_on_goal = array_sum( wp_list_pluck( $posts, 'sessions_on_goal' ) );
$avg_cvr = $sum_sessions_on_goal / $sum_sessions;

echo '<h2>Posts contributing to goal</h2>';
echo '<p>Showing ' . count( $posts ) . ' top posts contributing to ' . number_format( count( bstat()->report()->sessions_on_goal() ) ) . ' goal completions.</p>';
echo '<table>
	<tr>
		<td>Post</td>
		<td>All sessions</td>
		<td>Sessions on goal</td>
		<td>CVR</td>
		<td>Expected sessions on goal</td>
		<td>Difference: goal - expected</td>
		<td>Multiple: goal / expected</td>
	</tr>
';

foreach ( $posts as $post )
{

	$post->sessions_on_goal_expected = $avg_cvr * $post->sessions;

	printf(
		'<tr>
			<td><a href="%1$s">%2$s</a></td>
			<td>%3$s</td>
			<td>%4$s</td>
			<td>%5$s</td>
			<td>%6$s</td>
			<td>%7$s</td>
			<td>%8$s</td>
		</tr>',
		bstat()->report()->report_url( array( 'post' => $post->post, ) ),
		$post->post,
		(int) $post->sessions,
		(int) $post->sessions_on_goal,
		number_format( ( $post->sessions_on_goal / $post->sessions ) * 100 , 2 ) . '%',
		number_format( $post->sessions_on_goal_expected, 2 ),
		number_format( $post->sessions_on_goal - $post->sessions_on_goal_expected, 2 ),
		number_format( $post->sessions_on_goal / $post->sessions_on_goal_expected, 2 )
	);
}

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
	'Totals:',
	(int) $sum_sessions,
	(int) $sum_sessions_on_goal,
	number_format( ( $sum_sessions_on_goal / $sum_sessions ) * 100 , 2 ) . '%',
	'&nbsp;',
	'&nbsp;',
	'&nbsp;'
);
echo '</table>';