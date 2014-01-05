<?php
$filters = bstat()->report()->filter;
unset( $filters['timestamp'] );

// don't show this panel if there's only one filter
if ( ! count( $filters ) )
{
	return;
}


echo '<h2>Showing filtered activity</h2>';
echo '<p><a href="' . bstat()->report()->report_url( array(), FALSE ) . '">Reset filters</a></p>';
echo '<ol>';
foreach ( $filters as $k => $v )
{
	printf(
		'<li>%1$s = %2$s</li>',
		$k,
		$v
	);
}
echo '</ol>';