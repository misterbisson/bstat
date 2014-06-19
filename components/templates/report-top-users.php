<?php

// don't show this panel if there are no users to report
$users = bstat()->report()->top_users();
if ( ! count( $users ) )
{
	return;
}

// for sanity, limit this to just the top few users
$users = array_slice( $users, 0, bstat()->options()->report->max_items );

$total_activity = 0;
foreach ( $users as $user )
{
	$total_activity += $user->hits;
}

echo '<h2>Users</h2>';
echo '<p>Showing ' . count( $users ) . ' users with ' . number_format( $total_activity ) . ' total actions.</p>';
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
		number_format( (int) $user->hits )
	);

	echo '<ol>';
	foreach ( $posts as $post )
	{
		printf(
			'<li %1$s><a href="%2$s">%3$s</a> (%4$s hits)</li>',
			get_post_class( '', $post->ID ),
			bstat()->report()->report_url( array( 'post' => $post->ID, ) ),
			get_the_title( $post->ID ),
			number_format( (int) $post->hits )
		);
	}

	echo '</ol></li>';
}//end foreach
echo '</ol>';
