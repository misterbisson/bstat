<?php
$filters = bstat()->report()->filter;

// don't show this panel if there's only one filter
if ( 2 > count( $filters ) )
{
	return;
}

unset( $filters['timestamp'] );

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