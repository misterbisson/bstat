<?php

// don't show this panel if there's only one post
$posts = bstat()->report()->get_posts( bstat()->report()->top_posts(), array( 'posts_per_page' => -1, 'post_type' => 'any' ) );
if ( 2 > count( $posts ) )
{
	return;
}

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
	$recent = array_slice( $recent, 0, bstat()->options()->report->max_items );

	$total_activity = 0;
	foreach ( $recent as $post )
	{
		$total_activity += $post->hits;
	}

	echo '<h2>Recent posts</h2>';
	echo '<p>Showing ' . count( $recent ) . ' posts with ' . $total_activity . ' total actions.</p>';
	echo '<ol>';
	foreach ( $recent as $post )
	{
		printf(
			'<li %1$s><a href="%2$s">%3$s</a> (%4$s hits)</li>',
			get_post_class( '', $post->ID ),
			bstat()->report()->report_url( array( 'post' => $post->ID, ) ),
			get_the_title( $post->ID ),
			(int) $post->hits
		);
	}
	echo '</ol>';
}

if ( count( $evergreen ) )
{
	$evergreen = array_slice( $evergreen, 0, bstat()->options()->report->max_items );

	$total_activity = 0;
	foreach ( $evergreen as $post )
	{
		$total_activity += $post->hits;
	}

	echo '<h2>Evergreen posts</h2>';
	echo '<p>Showing ' . count( $evergreen ) . ' posts with ' . $total_activity . ' total actions.</p>';
	echo '<ol>';
	foreach ( $evergreen as $post )
	{
		printf(
			'<li %1$s><a href="%2$s">%3$s</a> (%4$s hits)</li>',
			get_post_class( '', $post->ID ),
			bstat()->report()->report_url( array( 'post' => $post->ID, ) ),
			get_the_title( $post->ID ),
			(int) $post->hits
		);
	}
	echo '</ol>';
}