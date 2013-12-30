<?php

// don't show this panel if there's only one user
$users = $this->top_users();
if ( 2 > count( $users ) )
{
	return;
}

// for sanity, limit this to just the top 100 users
$users = array_slice( $users, 0, 100 );

$total_activity = 0;
foreach ( $users as $user )
{
	$total_activity += $user->hits;
}

echo '<h2>Users, by total activity</h2>';
echo '<p>Showing ' . count( $users ) . ' users with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $users as $user )
{
	$user_object = new WP_User( $user->user );
	if ( ! isset( $user_object->display_name ) )
	{
		$user_object->display_name = 'not logged in';
	}

	$posts = $this->get_posts( $this->posts_for_user( $user->user ), array( 'posts_per_page' => 3, 'post_type' => 'any' ) );

	// it appears WP's get_the_author() emits the author display name with no sanitization
	echo '<li>' . $user_object->display_name . ' (' . (int) $user->hits . ' hits)';
	echo '<ol>';
	foreach ( $posts as $post )
	{
		echo '<li ' . get_post_class( '', $post->ID ) . '>' . get_the_title( $post->ID ) . ' (' . (int) $post->hits . ' hits)</li>';
	}
	echo '</ol></li>';


}
echo '</ol>';