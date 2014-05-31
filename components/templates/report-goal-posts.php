<?php

// don't show this panel if there are no posts for the sessions on goal
$posts = bstat()->report()->posts_for_session( bstat()->report()->sessions_on_goal() );
if ( ! count( $posts ) )
{
	return;
}

// for sanity, limit this to just the top few posts
$posts = array_slice( $posts, 0, bstat()->options()->report->max_items );

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
$sum_all = $sum_sessions + ( $sum_sessions * count( $posts ) );

echo '<h2>Posts contributing to goal</h2>';
echo '<p>Showing ' . count( $posts ) . ' top posts contributing to ' . count( bstat()->report()->sessions_on_goal() ) . ' goal completions.</p>';
echo '<table>';

foreach ( $posts as $post )
{

	// it appears WP's get_the_author() emits the author display name with no sanitization
	printf(
		'<tr>
			<td><a href="%1$s">%2$s</a></td>
			<td>%3$s</td>
			<td>%4$s</td>
			<td>%5$s</td>
		</tr>',
		bstat()->report()->report_url( array( 'post' => $post->post, ) ),
		$post->post,
		(int) $post->sessions,
		(int) $post->sessions_on_goal,
		number_format( ( (int) $post->sessions_on_goal / (int) $post->sessions ) * 100 , 2 ) . '%'
	);
}
echo '</table>';
die;
