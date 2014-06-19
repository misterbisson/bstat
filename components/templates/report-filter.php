<?php
$filters = bstat()->report()->filter;
unset( $filters['timestamp'] );

// don't show this panel if there's only one filter
if ( ! count( $filters ) )
{
	return;
}
?>
<div id="bstat-filters">
	<h2>Showing filtered activity <span>[<a href="<?php echo esc_url( bstat()->report()->report_url( array(), FALSE ) ); ?>">Reset filters</a>]</span></h2>
	<ol>
		<?php
		foreach ( $filters as $k => $v )
		{
			printf(
				'<li class="bstat-filter"><span class="bstat-component">%1$s</span><span class="bstat-action">%2$s</span></li>',
				$k,
				$v
			);
		}//end foreach
		?>
	</ol>
</div>
