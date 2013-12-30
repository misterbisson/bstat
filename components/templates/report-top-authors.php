<?php

// don't show this panel if there's only one author
$authors = bstat()->report()->top_authors();
if ( 2 > count( $authors ) )
{
	return;
}

// for sanity, limit this to just the top 100 authors
$authors = array_slice( $authors, 0, 100 );

$total_activity = 0;
foreach ( $authors as $author )
{
	$total_activity += $author->hits;
}

echo '<h2>Authors</h2>';
echo '<p>Showing ' . count( $authors ) . ' authors with ' . $total_activity . ' total actions.</p>';
echo '<ol>';
foreach ( $authors as $author )
{
	$user = new WP_User( $author->post_author );
	if ( ! isset( $user->display_name ) )
	{
		continue;
	}

	$posts = bstat()->report()->get_posts( bstat()->report()->top_posts(), array( 'author' => $author->post_author, 'posts_per_page' => 3, 'post_type' => 'any' ) );

	// it appears WP's get_the_author() emits the author display name with no sanitization
	printf(
		'<li>%1$s (%2$s hits)</li>',
		$user->display_name,
		(int) $author->hits
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