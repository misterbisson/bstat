<?php

// don't show this panel if there'r no matching sessions
if ( ! count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

// don't show this panel if there are no users to report
$users = bstat()->report()->users_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $users ) )
{
	return;
}

// for sanity, limit this to just the top few users
$users = array_slice( $users, 0, bstat()->options()->report->max_items );

$sum_sessions = array_sum( wp_list_pluck( $users, 'sessions' ) );
$sum_sessions_on_goal = array_sum( wp_list_pluck( $users, 'sessions_on_goal' ) );
$avg_cvr = $sum_sessions_on_goal / $sum_sessions;

// for sanity, limit this to just the top few users
$users = array_slice( $users, 0, bstat()->options()->report->max_items );

echo '<h2>Users contributing to goal</h2>';
echo '<p>Showing ' . count( $users ) . ' top users contributing to ' . number_format( count( bstat()->report()->sessions_on_goal() ) ) . ' goal completions.</p>';
echo '<table>
	<tr>
		<td>User</td>
		<td>All sessions</td>
		<td>Sessions on goal</td>
		<td>CVR</td>
		<td>Expected sessions on goal</td>
		<td>Difference: goal - expected</td>
		<td>Multiple: goal / expected</td>
	</tr>
';

foreach ( $users as $user )
{
	$user_object = new WP_User( $user->user );
	if ( ! isset( $user_object->display_name ) )
	{
		$user_object->display_name = 'not logged in';
	}

	$user->sessions_on_goal_expected = $avg_cvr * $post->sessions;

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
		bstat()->report()->report_url( array( 'user' => $user_object->ID, ) ),
		$user_object->display_name,
		get_edit_user_link( $user->user ),
		number_format( $user->sessions ),
		number_format( $user->sessions_on_goal ),
		number_format( ( $user->sessions_on_goal / $user->sessions ) * 100 , 2 ) . '%',
		number_format( $user->sessions_on_goal_expected, 2 ),
		number_format( $user->sessions_on_goal - $user->sessions_on_goal_expected, 2 ),
		number_format( $user->sessions_on_goal / $user->sessions_on_goal_expected, 2 )
	);

/*
	$posts = bstat()->report()->get_posts( bstat()->report()->posts_for_user( $user->user ), array( 'posts_per_page' => 3, 'post_type' => 'any' ) );
	echo '<ol>';
	foreach ( $posts as $post )
	{
		printf(
			'<li %1$s><a href="%2$s">%3$s</a> (%4$s hits)</li>',
			get_post_class( '', $post->ID ),
			bstat()->report()->report_url( array( 'post' => $post->ID, ) ),
			get_the_title( $post->ID ),
			(int) $post->hits
		);
	}
	echo '</ol></li>';
*/
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
	number_format( $sum_sessions ),
	number_format( $sum_sessions_on_goal ),
	number_format( ( $sum_sessions_on_goal / $sum_sessions ) * 100 , 2 ) . '%',
	'&nbsp;',
	'&nbsp;',
	'&nbsp;'
);
echo '</table>';