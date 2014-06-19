<?php
$filter = bstat()->report()->filter;

// don't show this panel if there isn't a component:action filter set
if ( ! isset( $filter['component'], $filter['action'] ) )
{
	return;
}

$infos = bstat()->report()->component_and_action_info( array( 'component' => $filter['component'], 'action' => $filter['action'] ) );

// for sanity, limit this to just the top few component:action pairs
$infos = array_slice( $infos, 0, bstat()->options()->report->max_items );

$total_activity = 0;
foreach ( $infos as $info )
{
	$total_activity += $info->hits;
}
?>
<div id="bstat-additional-info">
	<h2>Additional info for <?php echo esc_html( $filter['component'] ); ?> <?php echo esc_html( $filter['action'] ); ?></h2>
	<p>Showing <?php echo count( $infos ); ?> info with <?php echo number_format( $total_activity ); ?> total actions.</p>
	<ol>
		<?php
		foreach ( $infos as $info )
		{
			printf(
				'<li>%1$s (%2$s hits)</li>',
				$info->info,
				number_format( $info->hits )
			);
		}
		?>
	</ol>
</div>
