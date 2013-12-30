<?php

// don't show this panel if there's only one author
$terms = bstat()->report()->top_terms();
if ( ! count( $terms ) )
{
	return;
}

function bstat_sort_emergent_terms( $a, $b )
{
	if ( $a->hits_per_post_score == $b->hits_per_post_score )
	{
		return 0;
	}
	return ( $a->hits_per_post_score < $b->hits_per_post_score ) ? 1 : -1;
}

usort( $terms, 'bstat_sort_emergent_terms' );

// for sanity, limit this to just the top 100 authors
$terms = array_slice( $terms, 0, 100 );


echo '<h2>Terms</h2>';
echo '<p>Showing ' . count( $terms ) . ' terms.</p>';
echo '<ol>';
foreach ( $terms as $term )
{
	$posts = bstat()->report()->top_posts_for_term( $term, array( 'posts_per_page' => 3, 'post_type' => 'any' ) );

	printf(
		'<li>%1$s (%2$s hits)</li>',
		$term->taxonomy . ':' . $term->slug,
		(int) $term->hits
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