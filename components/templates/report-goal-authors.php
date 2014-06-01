<?php

// don't show this panel if there'r no matching sessions
if ( ! count( bstat()->report()->sessions_on_goal() ) )
{
	return;
}

// don't show this panel if there's only one author
$authors = bstat()->report()->authors_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $authors ) )
{
	return;
}

// for sanity, limit this to just the top few authors
$authors = array_slice( $authors, 0, bstat()->options()->report->max_items );

$sum_sessions = array_sum( wp_list_pluck( $authors, 'sessions' ) );
$sum_sessions_on_goal = array_sum( wp_list_pluck( $authors, 'sessions_on_goal' ) );
$avg_cvr = $sum_sessions_on_goal / $sum_sessions;

echo '<h2>Authors contributing to goal</h2>';
echo '<p>Showing ' . count( $authors ) . ' top authors contributing to ' . number_format( count( bstat()->report()->sessions_on_goal() ) ) . ' goal completions.</p>';
echo '<table>
	<tr>
		<td>Author</td>
		<td>All sessions</td>
		<td>Sessions on goal</td>
		<td>CVR</td>
		<td>Expected sessions on goal</td>
		<td>Difference: goal - expected</td>
		<td>Multiple: goal / expected</td>
	</tr>
';

foreach ( $authors as $author )
{

	$user = new WP_User( $author->post_author );
	if ( ! isset( $user->display_name ) )
	{
		continue;
	}

	$author->sessions_on_goal_expected = $avg_cvr * $author->sessions;

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
		$user->display_name,
		(int) $author->sessions,
		(int) $author->sessions_on_goal,
		number_format( ( $author->sessions_on_goal / $author->sessions ) * 100 , 2 ) . '%',
		number_format( $author->sessions_on_goal_expected, 2 ),
		number_format( $author->sessions_on_goal - $author->sessions_on_goal_expected, 2 ),
		number_format( $author->sessions_on_goal / $author->sessions_on_goal_expected, 2 )
	);

/*
	$posts = bstat()->report()->get_posts( bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() ), array( 'author' => $author->post_author, 'posts_per_page' => 3, 'post_type' => 'any' ) );
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
	(int) $sum_sessions,
	(int) $sum_sessions_on_goal,
	number_format( ( $sum_sessions_on_goal / $sum_sessions ) * 100 , 2 ) . '%',
	'&nbsp;',
	'&nbsp;',
	'&nbsp;'
);
echo '</table>';