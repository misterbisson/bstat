<?php

// enqueue the rickshaw js and style
bstat()->rickshaw()->enqueue();

// get the top five actions within this time period
$components = array_slice( bstat()->report()->top_components_and_actions(), 0, 5 );

// get an array with timeseries for each of those actions
$filters = array();
foreach ( $components as $component )
{
	$filters[ $component->component .':' . $component->action ] = array( 'component' => $component->component, 'action' => $component->action );
}
$components = bstat()->report()->multi_timeseries( 180, $filters );

?>
<style>
#chart_container {
	display: inline-block;
	font-family: Arial, Helvetica, sans-serif;
}
#chart {
	float: left;
}
#legend {
	float: left;
	margin-left: 15px;
}
#y_axis {
	float: left;
	width: 40px;
}
</style>


<div id="chart_container">
	<div id="y_axis"></div>
	<div id="chart"></div>
	<div id="legend"></div>
</div>

<script>
(function($){

	var palette = new Rickshaw.Color.Palette();

	var graph = new Rickshaw.Graph( {
		element: document.querySelector("#chart"),
		width: 540,
		height: 240,
		renderer: 'stack',
		series: [
			<?php
			foreach ( $components as $k => $v )
			{
			?>
				{
					name: "<?php echo $k; ?>",
					data: <?php echo json_encode( bstat()->rickshaw()->array_to_series( $v ) ); ?>,
					color: palette.color()
				},
			<?php
			}
			?>
		]
	} );

	var x_axis = new Rickshaw.Graph.Axis.Time( { graph: graph } );

	var y_axis = new Rickshaw.Graph.Axis.Y( {
		graph: graph,
		orientation: 'left',
		tickFormat: Rickshaw.Fixtures.Number.formatKMBT,
		element: document.getElementById('y_axis'),
	} );

	var legend = new Rickshaw.Graph.Legend( {
		element: document.querySelector('#legend'),
		graph: graph
	} );

	graph.render();

})(jQuery);
</script>