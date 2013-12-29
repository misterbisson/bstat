<?php

$posts = bstat()->report()->get_posts( bstat()->report()->top_posts(), array( 'posts_per_page' => 100, 'post_type' => 'any' ) );

// set the timezone to UTC for the later strtotime() call,
// preserve the old timezone so we can set it back when done
$old_tz = date_default_timezone_get();
date_default_timezone_set( 'UTC' );

$mendoza_line = strtotime( 'midnight yesterday' );
$recent = $evergreen = array();

foreach ( $posts as $post )
{
	if( strtotime( $post->post_date_gmt ) < $mendoza_line )
	{
		$evergreen[] = $post;
	}
	else
	{
		$recent[] = $post;
	}
}
date_default_timezone_set( $old_tz );

if ( count( $recent ) )
{
	$total_activity = 0;
	foreach ( $recent as $post )
	{
		$total_activity += $post->hits;
	}

	echo '<h2>Recent posts, by activity</h2>';
	echo '<p>Showing ' . count( $recent ) . ' posts with ' . $total_activity . ' total actions. Only showing activity on top 100 total posts.</p>';
	echo '<ol>';
	foreach ( $recent as $post )
	{
		echo '<li ' . get_post_class( '', $post->ID ) . '>' . get_the_title( $post->ID ) . ' (' . (int) $post->hits . ' hits)</li>';
	}
	echo '</ol>';
}

if ( count( $evergreen ) )
{
	$total_activity = 0;
	foreach ( $evergreen as $post )
	{
		$total_activity += $post->hits;
	}

	echo '<h2>Evergreen posts, by activity</h2>';
	echo '<p>Showing ' . count( $evergreen ) . ' posts with ' . $total_activity . ' total actions. Only showing activity on top 100 total posts.</p>';
	echo '<ol>';
	foreach ( $evergreen as $post )
	{
		echo '<li ' . get_post_class( '', $post->ID ) . '>' . get_the_title( $post->ID ) . ' (' . (int) $post->hits . ' hits)</li>';
	}
	echo '</ol>';
}