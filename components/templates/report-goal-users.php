<?php

// don't show this panel if there'r no matching sessions
if ( ! count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

echo '<pre>';
print_r( bstat()->report()->users_for_session( bstat()->report()->sessions_on_goal() ) );
echo '</pre>';
die;

// don't show this panel if there are no users to report
$users = bstat()->report()->users_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $users ) )
{
	return;
}

// for sanity, limit this to just the top few users
$users = array_slice( $users, 0, bstat()->options()->report->max_items );

echo '<h2>Users</h2>';
echo '<p>Showing ' . count( $users ) . ' users with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $users as $user )
{
	$user_object = new WP_User( $user->user );
	if ( ! isset( $user_object->display_name ) )
	{
		$user_object->display_name = 'not logged in';
	}

	$posts = bstat()->report()->get_posts( bstat()->report()->posts_for_user( $user->user ), array( 'posts_per_page' => 3, 'post_type' => 'any' ) );

	// it appears WP's get_the_author() emits the author display name with no sanitization
	printf(
		'<li><a href="%1$s">%2$s</a> (%3$s hits)',
		bstat()->report()->report_url( array( 'user' => $user_object->ID, ) ),
		$user_object->display_name,
		(int) $user->hits
	);

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


}
echo '</ol>';

$sum_sessions = array_sum( wp_list_pluck( $users, 'sessions' ) );
$sum_sessions_on_goal = array_sum( wp_list_pluck( $users, 'sessions_on_goal' ) );
$avg_cvr = $sum_sessions_on_goal / $sum_sessions;

// for sanity, limit this to just the top few posts
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
	number_format( $sum_sessions ),
	number_format( $sum_sessions_on_goal ),
	number_format( ( $sum_sessions_on_goal / $sum_sessions ) * 100 , 2 ) . '%',
	'&nbsp;',
	'&nbsp;',
	'&nbsp;'
);
echo '</table>';