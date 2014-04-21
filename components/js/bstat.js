(function($){

	// don't bother if the endpoint isn't defined
	if ( ! ('endpoint' in bstat) ) {
		return;
	}

	var bstat_t = {};

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

	$( document ).on( 'click', 'a', bstat_t.link_click );

	// @TODO: add tracking for clicks within forms
	// ...also maybe track progress through the form


	// parseUri 1.2.2
	// (c) Steven Levithan <stevenlevithan.com>
	// MIT License
	// stolen from http://blog.stevenlevithan.com/archives/parseuri
	// fun and easy to test at http://stevenlevithan.com/demo/parseuri/js/
	bstat_t.parse_uri = function (str) {
		var	o	  = bstat_t.parse_uri.options,
			m   = o.parser[o.strictMode ? "strict" : "loose"].exec(str),
			uri = {},
			i   = 14;

		while (i--) uri[o.key[i]] = m[i] || "";

		uri[o.q.name] = {};
		uri[o.key[12]].replace(o.q.parser, function ($0, $1, $2) {
			if ($1) uri[o.q.name][$1] = $2;
		});

		return uri;
	};
	bstat_t.parse_uri.options = {
		strictMode: false,
		key: ["source","protocol","authority","userInfo","user","password","host","port","relative","path","directory","file","query","anchor"],
		q:   {
			name:   "queryKey",
			parser: /(?:^|&)([^&=]*)=?([^&]*)/g
		},
		parser: {
			strict: /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*)(?::([^:@]*))?)?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
			loose:  /^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/)?((?:(([^:@]*)(?::([^:@]*))?)?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/
		}
	};

	// stolen from http://phpjs.org/functions/urldecode/
	bstat_t.urldecode = function (str) {
		// From: http://phpjs.org/functions
		// +	 original by: Philip Peterson
		// +	 improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +		input by: AJ
		// +	 improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +	 improved by: Brett Zamir (http://brett-zamir.me)
		// +		input by: travc
		// +		input by: Brett Zamir (http://brett-zamir.me)
		// +	 bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +	 improved by: Lars Fischer
		// +		input by: Ratheous
		// +	 improved by: Orlando
		// +	 reimplemented by: Brett Zamir (http://brett-zamir.me)
		// +		bugfixed by: Rob
		// +		input by: e-mike
		// +	 improved by: Brett Zamir (http://brett-zamir.me)
		// +		input by: lovio
		// +	 improved by: Brett Zamir (http://brett-zamir.me)
		// %		  note 1: info on what encoding functions to use from: http://xkr.us/articles/javascript/encode-compare/
		// %		  note 2: Please be aware that this function expects to decode from UTF-8 encoded strings, as found on
		// %		  note 2: pages served as UTF-8
		// *	   example 1: urldecode('Kevin+van+Zonneveld%21');
		// *	   returns 1: 'Kevin van Zonneveld!'
		// *	   example 2: urldecode('http%3A%2F%2Fkevin.vanzonneveld.net%2F');
		// *	   returns 2: 'http://kevin.vanzonneveld.net/'
		// *	   example 3: urldecode('http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a');
		// *	   returns 3: 'http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a'
		// *	   example 4: urldecode('%E5%A5%BD%3_4');
		// *	   returns 4: '\u597d%3_4'
		return decodeURIComponent((str + '').replace(/%(?![\da-f]{2})/gi, function () {
			// PHP tolerates poorly formed escape sequences
			return '%25';
		}).replace(/\+/g, '%20'));
	}

	// parse the referrer URL
	var referrer_url = bstat_t.parse_uri( document.referrer );

	// capture the referring domain (unless it was an internal referral)
	if (
		undefined != referrer_url.host && // not an empty referrer
		'' != referrer_url.host && // not an empty referrer
		undefined == bstat_t.get_search_engine( parsed_url.host ) && // don't bother with search engines, they're logged elsewhere
		document.location.domain != referrer_url.host // ignore self-referrers
	)
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
				"bstat[action]" : "r_host",
				"bstat[info]" : referrer_url.host,
			}
		});
	}

	bstat_t.get_search_engine = function ( domain ) {

		if ( domain.match( /^(www)?\.?google.*/i ) )
		{
			return 'google';
		}

		if ( domain.match( /^(www)?\.?bing.*/i ) )
		{
			return 'bing';
		}

		if ( domain.match( /^search\.yahoo.*/i ) )
		{
			return 'yahoo';
		}

		if ( document.domain == domain )
		{
			return 'internal';
		}

	}

	bstat_t.get_search_string = function ( parsed_url ) {

		var engine = bstat_t.get_search_engine( parsed_url.host );
		var search_string;

		if( undefined == engine )
		{
			return;
		}

		switch ( engine ) {
			case 'google':
			case 'bing':
				if ( undefined != parsed_url.queryKey.q )
				{
					search_string = parsed_url.queryKey.q;
				}
				break;

			case 'yahoo':
				if ( undefined != parsed_url.queryKey.p )
				{
					search_string = parsed_url.queryKey.p;
				}
				break;

			case 'internal':
				if ( undefined != parsed_url.queryKey.s )
				{
					search_string = parsed_url.queryKey.s;
				}
				else if ( parsed_url.path.match( /^\/search\// ) )
				{
					search_string = parsed_url.path.replace( /^\/search\// , '' ).replace( /\// , '' );
				}

				break;
		}

		return bstat_t.urldecode( search_string );
	}

	// capture the search query from recognized search engines
	bstat_t.search_string = bstat_t.get_search_string( referrer_url );
	if(
		undefined != bstat_t.search_string &&
		'' != bstat_t.search_string
	)
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
				"bstat[action]" : "r_search",
				"bstat[info]" : bstat_t.search_string,
			}
		});
	}

	// parse this URL, then look for UTM codes in it
	var this_url = bstat_t.parse_uri( document.location );

	// capture the UTM campaign code
	if ( undefined != this_url.queryKey.utm_campaign )
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
				"bstat[info]" : this_url.queryKey.utm_campaign,
			}
		});
	}

	// capture the UTM medium code
	if ( undefined != this_url.queryKey.utm_medium )
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
				"bstat[info]" : this_url.queryKey.utm_medium,
			}
		});
	}

	// capture the UTM source code
	if ( undefined != this_url.queryKey.utm_source )
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
				"bstat[info]" : this_url.queryKey.utm_source,
			}
		});
	}
})(jQuery);
