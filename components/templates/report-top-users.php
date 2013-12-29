<?php
echo '<h2>Users, by total activity</h2>';
print_r( $this->top_users() );
foreach ( array_slice( $this->top_users(), 0, 3 ) as $user )
{
	print_r( $this->get_posts( $this->posts_for_user( $user->user ), array( 'posts_per_page' => 1 ) ) );
}
