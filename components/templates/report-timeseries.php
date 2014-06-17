<?php

// get the top five actions within this time period
$components = array_slice( bstat()->report()->top_components_and_actions(), 0, 29 );

// get an array with timeseries for each of those actions
$filters = array();
foreach ( $components as $component )
{
	$filters[ $component->component .':' . $component->action ] = array_merge( bstat()->report()->filter, array( 'component' => $component->component, 'action' => $component->action ) );
}
$components = bstat()->report()->multi_timeseries( bstat()->options()->report->quantize_time, FALSE, FALSE, $filters );

// colors stolen from Rickshaw's 'munin' scheme, https://github.com/shutterstock/rickshaw/blob/master/src/js/Rickshaw.Fixtures.Color.js
// though I guess that was stolen fron Munin
$colors = array(
	'#00cc00',
	'#0066b3',
	'#ff8000',
	'#ffcc00',
	'#330099',
	'#990099',
	'#ccff00',
	'#ff0000',
	'#808080',
	'#008f00',
	'#00487d',
	'#b35a00',
	'#b38f00',
	'#6b006b',
	'#8fb300',
	'#b30000',
	'#bebebe',
	'#80ff80',
	'#80c9ff',
	'#ffc080',
	'#ffe680',
	'#aa80ff',
	'#ee00cc',
	'#ff8080',
	'#666600',
	'#ffbfff',
	'#00ffcc',
	'#cc6699',
	'#999900',
);

?>
<div id="bstat-timeseries-container">
	<div id="bstat-timeseries-container-chart"></div>
	<div id="bstat-timeseries-container-legend"></div>
</div>

<script>

var bstat_timeseries = [
	<?php
	foreach ( $components as $k => $v )
	{
		?>
		{
			name: "<?php echo $k; ?>",
			data: <?php echo json_encode( bstat()->graphing()->array_to_series( $v ) ); ?>,
			color: "<?php echo current( $colors ); ?>",
		},
		<?php
		next( $colors );
	}
	?>
];
</script>
