if ( 'undefined' == typeof bstat ) {
	var bstat = {};
}//end if

(function($){
	'use strict';

	bstat.report = {};

	bstat.report.init = function() {
		this.$parset = $( '#bstat-parset' );

		var graph = new Rickshaw.Graph( {
			element: $( '#bstat-timeseries-container-chart' ).get( 0 ),
			width: $( '#wpbody-content' ).width() - 20,
			height: ( $( '#wpbody-content' ).width() - 20 ) / 3.5,
			renderer: 'bar',
			series: bstat_timeseries,
		} );

		var x_axis = new Rickshaw.Graph.Axis.Time( { graph: graph } );

		graph.render();

		var $legend = $( '#bstat-timeseries-container-legend' );
		var Hover = Rickshaw.Class.create( Rickshaw.Graph.HoverDetail, {
			render: function( args ) {

				$legend.html( args.formattedXValue );

				args.detail.sort(function(a, b) { return a.order - b.order }).forEach( function(d) {
					var $line = $( '<div class="line" />');
					var $swatch = $( '<div class="swatch"/> ');
					$swatch.css( 'background-color', d.series.color );

					var $label = $( '<div class="label">' + d.name + ": " + d.formattedYValue + '</div>' );

					$line.append( $swatch ).append( $label );

					$legend.append( $line );

					var $dot = $( '<div class="dot active" />' );
					$dot.css( {
						'top': graph.y(d.value.y0 + d.value.y) + 'px',
						'border-color': d.series.color
					})

					$( this.element ).append( $dot );

					this.show();

				}, this );
			}
		} );

		var hover = new Hover( { graph: graph } );

		if ( ! this.$parset.length ) {
			return;
		}//end if

		this.init_parset();
	};

	bstat.report.init_parset = function() {
		this.chart = d3.parsets().dimensions( [ 'Action -1', 'Action -2', 'Action -3', 'Action -4', 'Action -5', 'Action -6', 'Action -7', 'Action -8', 'Action -9', 'Action -10' ] );
		this.chart.tension( 0.25 );
		this.chart.width( this.$parset.width() * 0.75 );
		this.chart.height( 850 );
		this.chart.tooltip( function( d ) {
			var count = d.count;
			var path = [];

			while ( d.parent ) {
				if ( d.name ) {
					path.push( d.name );
				}//end if

				d = d.parent;
			}//end while

			var comma = d3.format( ',f' );
			var percent = d3.format( '%' );

			var html = '';
			html += '<div class="count">Hits: ' + comma( count ) + ' (' + percent( count / d.count ) + ')</div>';
			html += '<ol reversed><li class="step">' + path.join( '</li><li class="step">' ) + '</li></ol>';

			html.replace( '(  )', '' );

			return html;
		} );
		d3.json( $( '.flow-tab' ).data( 'url' ), function( error, json ) {
			data = [];

			var data = {};

			var current_session = '';
			var step = 0;

			for ( var i in json ) {
				var item = json[ i ];

				if ( current_session !== $.trim( item.session ) ) {
					step = 0;
					current_session = $.trim( item.session );
					data[ current_session ] = {};
					step++;
					continue;
				}//end if

				data[ current_session ][ 'Action -' + step ] = bstat.report.translate_action( item.action ) + '( ' + item.info + ' )';
				step++;
			}//end for

			var massaged_data = [];
			for ( i in data ) {
				massaged_data.push( data[ i ] );
			}//end for

			$( '#bstat-parset' ).html( '' );

			bstat.report.svg = d3.select( '#bstat-parset' ).append( 'svg' )
				.attr( 'width', bstat.report.chart.width() )
				.attr( 'height', bstat.report.chart.height() );

			bstat.report.svg.datum( massaged_data ).call( bstat.report.chart );
		});
	};

	bstat.report.translate_action = function ( action ) {
		switch ( action ) {
			case 'pageview': return 'page view';
			case 'clklink': return 'link';
			case 'u_medium': return 'utm medium';
			case 'userauth': return 'login';
			case 'u_source': return 'utm source';
			case 'r_search': return 'search';
			case 'r_host': return 'referring host';
			case 'u_campgn': return 'utm campaign';
			default: return action;
		}//end switch
	};

	$( function() {
		bstat.report.init();

		$( '#bstat-viewer .tabs' ).tabs( {
			beforeLoad: function( event, ui ) {
				if ( ui.tab.data( 'loaded' ) ) {
					event.preventDefault();
					return;
				}//end if

				ui.ajaxSettings.cache = false;
				ui.panel.html( '<i class="fa fa-spinner fa-spin" />' );
				ui.jqXHR.success( function() {
					ui.tab.data( 'loaded', true );
				});
				ui.jqXHR.error( function() {
					ui.panel.html( 'There was a problem loading this data. Please try reloading the page.' );
				});
			}
		} );

		$( document ).on( 'click', '#bstat-goal .set', function( e ) {
			e.preventDefault();

			var $el = $( this );
			var $container = $( '#bstat-goal' );

			$container.toggleClass( 'show-goals' );
			$container.find( 'ul' ).slideToggle( 'fast' );
		});
	});
})(jQuery);
