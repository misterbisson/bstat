<?php

// don't show this panel if there'r no matching sessions
if ( 1 > count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

// don't show this panel if there are no posts for the sessions on goal
$posts = bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $posts ) )
{
	return;
}

$sum_sessions = array_sum( wp_list_pluck( $posts, 'sessions' ) );
$sum_sessions_on_goal = array_sum( wp_list_pluck( $posts, 'sessions_on_goal' ) );
$avg_cvr = $sum_sessions_on_goal / $sum_sessions;

// for sanity, limit this to just the top few posts
$posts = array_slice( $posts, 0, bstat()->options()->report->max_items );

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
			<td><a href="%1$s">%2$s</a> <a href="%3$s">#</a></td>
			<td>%4$s</td>
			<td>%5$s</td>
			<td>%6$s</td>
			<td>%7$s</td>
			<td>%8$s</td>
			<td>%9$s</td>
		</tr>',
		bstat()->report()->report_url( array( 'post' => $post->post, ) ),
		get_the_title( $post->post ),
		get_permalink( $post->post ),
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