<?php

// don't show this panel if there's only one author
$terms = $this->top_terms();
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


echo '<h2>Terms, by emergent activity</h2>';
echo '<p>Showing ' . count( $terms ) . ' terms.</p>';
echo '<ol>';
foreach ( $terms as $term )
{
	$posts = $this->top_posts_for_term( $term, array( 'posts_per_page' => 3, 'post_type' => 'any' ) );

	echo '<li>' . $term->taxonomy . ':' . $term->slug .' (' . (int) $term->hits . ' hits)';
	echo '<ol>';
	foreach ( $posts as $post )
	{
		echo '<li ' . get_post_class( '', $post->ID ) . '>' . get_the_title( $post->ID ) . ' (' . (int) $post->hits . ' hits)</li>';
	}
	echo '</ol></li>';


}
echo '</ol>';