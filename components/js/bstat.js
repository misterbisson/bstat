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
			type: "POST",
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
		type: "POST",
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

})(jQuery);