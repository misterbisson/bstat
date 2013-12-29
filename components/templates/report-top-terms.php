<?php
echo '<h2>Taxonomy terms, by total activity</h2>';
print_r( $this->top_terms() );
foreach ( array_slice( $this->top_terms(), 0, 3 ) as $term )
{
	print_r( $this->top_posts_for_term( $term, array( 'posts_per_page' => 1 ) ) );
}
