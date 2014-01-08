(function($){

	// don't bother if the endpoint isn't defined
	if ( ! ('endpoint' in bstat) ) {
		return;
	}

	var bstat_t = {};

	// Track normal link click events a
	bstat_t.link_click = function( event ) {

		// info is: nearest ID of the element that link is in, nearest ID of the widget that link is in, the link text
		var the_info = $(event.target).closest( '[id]' ).attr('id') + "|" + $(event.target).closest( '.widget' ).attr( 'id' ) + "|" + ( $(event.target).text() || $(event.target).children( 'img:first' ).attr( 'alt' ) );

		// post it
		$.ajax({
			type : "POST",
			url : bstat.endpoint,
			dataType : 'jsonp',
			data : {
				"bstat[post]" : bstat.post,
				"bstat[blog]" : bstat.blog,
				"bstat[signature]" : bstat.signature,
				"bstat[component]" : "bstat",
				"bstat[action]" : "clklink",
				"bstat[info]" : the_info
			}
		});
	}

	// @TODO: add tracking for clicks within forms
	// ...also maybe track progress through the form

	// track the page view
	// @TODO: should I make a wrapper for the ajax call?
	$.ajax({
		type : "POST",
		url : bstat.endpoint,
		dataType : 'jsonp',
		data : {
			"bstat[post]" : bstat.post,
			"bstat[blog]" : bstat.blog,
			"bstat[signature]" : bstat.signature,
			"bstat[component]" : "bstat",
			"bstat[action]" : "pageview"
		}
	});

	$( document ).on( 'click', 'a', bstat_t.link_click );

	// get arguments from the query vars
	// this is totally stolen from a StackOverflow comment
	// http://stackoverflow.com/questions/901115/how-can-i-get-query-string-values-in-javascript#comment7448147_3855394
	bstat_t.queryargs = (function (a) {
		var i,
			p,
			b = {};
		if (a === "") { return {}; }
		for (i = 0; i < a.length; i += 1) {
			p = a[i].split('=');
			if (p.length === 2) {
				b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
			}
		}
		return b;
	}(window.location.search.substr(1).split('&')));

	// capture the UTM campaign code
	if ( undefined != bstat_t.queryargs['utm_campaign'] )
	{
		$.ajax({
			type : "POST",
			url : bstat.endpoint,
			dataType : 'jsonp',
			data : {
				"bstat[post]" : bstat.post,
				"bstat[blog]" : bstat.blog,
				"bstat[signature]" : bstat.signature,
				"bstat[component]" : "bstat",
				"bstat[action]" : "u_campgn",
				"bstat[info]" : bstat_t.queryargs['utm_campaign']
			}
		});
	}

	// capture the UTM medium code
	if ( undefined == bstat_t.queryargs['utm_medium'] )
	{
		$.ajax({
			type : "POST",
			url : bstat.endpoint,
			dataType : 'jsonp',
			data : {
				"bstat[post]" : bstat.post,
				"bstat[blog]" : bstat.blog,
				"bstat[signature]" : bstat.signature,
				"bstat[component]" : "bstat",
				"bstat[action]" : "u_medium",
				"bstat[info]" : bstat_t.queryargs['utm_medium']
			}
		});
	}

	// capture the UTM source code
	if ( undefined == bstat_t.queryargs['utm_source'] )
	{
		$.ajax({
			type : "POST",
			url : bstat.endpoint,
			dataType : 'jsonp',
			data : {
				"bstat[post]" : bstat.post,
				"bstat[blog]" : bstat.blog,
				"bstat[signature]" : bstat.signature,
				"bstat[component]" : "bstat",
				"bstat[action]" : "u_source",
				"bstat[info]" : bstat_t.queryargs['utm_source']
			}
		});
	}

})(jQuery);