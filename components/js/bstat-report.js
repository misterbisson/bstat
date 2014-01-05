(function($){

/*
Disabled because palette.color() returns black for every iteration in this context
Preserved because I'd love to figure out why
	var palette = new Rickshaw.Color.Palette();

	for (var i = 0; i < bstat_timeseries.length; i++) {
	    bstat_timeseries.color = palette.color();
	}
*/


	var graph = new Rickshaw.Graph( {
		element: document.getElementById("bstat-timeseries-container-chart"),
		width: $('#wpbody-content').width() - 20,
		height: ( $('#wpbody-content').width() - 20 ) / 3.5,
		renderer: 'bar',
		series: bstat_timeseries,
	} );

	var x_axis = new Rickshaw.Graph.Axis.Time( { graph: graph } );

	graph.render();

	var legend = document.querySelector('#bstat-timeseries-container-legend');
	var Hover = Rickshaw.Class.create(Rickshaw.Graph.HoverDetail, {

		render: function(args) {

			legend.innerHTML = args.formattedXValue;

			args.detail.sort(function(a, b) { return a.order - b.order }).forEach( function(d) {

				var line = document.createElement('div');
				line.className = 'line';

				var swatch = document.createElement('div');
				swatch.className = 'swatch';
				swatch.style.backgroundColor = d.series.color;

				var label = document.createElement('div');
				label.className = 'label';
				label.innerHTML = d.name + ": " + d.formattedYValue;

				line.appendChild(swatch);
				line.appendChild(label);

				legend.appendChild(line);

				var dot = document.createElement('div');
				dot.className = 'dot';
				dot.style.top = graph.y(d.value.y0 + d.value.y) + 'px';
				dot.style.borderColor = d.series.color;

				this.element.appendChild(dot);

				dot.className = 'dot active';

				this.show();

			}, this );
	        }
	});

	var hover = new Hover( { graph: graph } );


})(jQuery);
