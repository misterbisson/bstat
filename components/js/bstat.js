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

	//
	// a/b testing framework
	//
	bstat.testing = {};

	// set up the event object that will hold bound functions
	bstat.testing.event = {};

	// this will hold the user's variations. It should get populated by the cookie and/or bstat.select_variations.
	bstat.testing.variations = {};

	// specified expiration for the testing cookie - denotes 'days from now'
	bstat.testing.expiration = 30;

	/**
	 * handle the bootstrapping of this object
	 */
	bstat.testing.init = function() {
		// initialize any commonly used dom elements as this.$something
		this.$body = $( document.body );

		// bind any events of note. Bound events should have their function declared as go_ab_testing.event.funcname.
		// Ideally, event functions will simply e.preventDefault(); where appropriate and call go_ab_testing functions.

		// call any setup functions

		this.get_variations();
		this.set_variations();
		this.apply_variations();

		// in addition to applying the variations to the page in the above call, we'll need to broadcast
		// the variations out to various other sources. Let's do that with a this.variation_notifiy(); call
		this.variation_notify();
	};

	/**
	 * reads the testing cookie for variations
	 */
	bstat.testing.get_variations = function() {
		// read from cookie into this.variations
		this.variations = JSON.parse( $.cookie('tests') ) || {};
		// this should probably run this.clean_variations() regardless of whether or not if found
		// variations in the cookie.
		this.clean_variations( this.variations );
	};

	/**
	 * sets the testing cookie
	 *
	 * @param object variations (optional) Variations the user will maintain on subsequent page loads
	 */
	bstat.testing.set_variations = function( variations ) {
		// this should set the cookie to whatever is passed in. If variations is NOT passed in, use what is
		// stored in this.variations.  If there isn't anything of note in there, unset the cookie.

		// store original state of $.cookie.json, in case other plugins are using it
		var original_cookie_json = $.cookie.json;
		if ( variations ) {
			$.cookie.json = true;
			$.cookie( 'tests',  variations, { expires: this.expiration } );
		}
		else {
			if ( $.isEmptyObject( this.variations ) ) {
				$.removeCookie( 'tests' );
			}
			else {
				$.cookie.json = true;
				$.cookie( 'tests', this.variations, { expires: this.expiration } );
			}
		}

		// reset it back to its original state
		$.cookie.json = original_cookie_json;
	};

	/**
	 * ensures all the appropriate variations exist in this.variations
	 */
	bstat.testing.clean_variations = function( variations ) {
		if ( $.isEmptyObject( variations ) ) {
			// nothing in cookies, so use tests obtained from server, which are stored in 'bstat.tests'
			for ( test in bstat.tests ) {
				// select one of the test variants
				var selected_variation = this.select_variation( bstat.tests[ test ] );
				// create test name; note: using specified naming convention
				var test_name = 'bstat-' + test + '-' + selected_variation.key;
				this.variations[ test_name ] = {
					'class' : selected_variation.variation,
					'time' : new Date().getTime()
				};
			}
			return;
		}

		// this should compare the contents of this.variations with the data that exists in
		// this.test (which comes from wp_localize_script). Missing tests should be selected
		// (via this.select_variation( test ) ), variations that don't exist in this.tests should be removed.
		for ( test in variations ) {
			// get the test; note: this assumes use of specified naming convention
			var selected_test = test.split('-')[1];
			if ( bstat.tests[selected_test] ) {
				// The timestamp for each variation must be checked against the date_start for each test.
				// If the cookie's variation date is before the date_start, the selected variation is expired and must be ignored.
				if ( bstat.tests[ selected_test ].date_start > variations[ test ]['time'] ) {
					delete bstat.variations[ test ];
					this.set_variations( bstat.variations );
				}
			}
			else {
				this.select_variation( bstat.tests[ selected_test ] );
			}
		}
	};

	/**
	 * select a variation from a test
	 */
	bstat.testing.select_variation = function( test ) {
		// select a variation from the provided test and return the selected one.
		var random_variation = this.getRandomPropertyWithKey( test.variations );
		return random_variation;
	};

	/**
	 * applies variations to the page
	 */
	bstat.testing.apply_variations = function() {
		// apply variations to the body class in some way
		var values = '';
		var keys = Object.keys( this.variations );
		for ( key in keys ) {
			values += ' ' + keys[ key ] + ' ' + this.variations[ keys[ key ] ]['class'];
		}
		this.$body.addClass( values );
	};

	/**
	 * advertises the implementation of a variation on the page
	 */
	bstat.testing.variation_notify = function() {
		// trigger custom events with the the variation data is probably the right plan, here.

		// For bstat, something like:
		// $( document ).trigger( 'bstat/track', data_to_send_to_bstat );
		//
		// This will, of course, require bstat to do something to handle that data, whether it be to
		// collect it into a bstat_t property and pass it along in the new bstat_t.step method that Casey
		// has proposed or if it is to make individual calls.  Casey will know more about what should be
		// done there.

		// likewise, we'll need something along those lines for google analytics:
		// $( document ).trigger( 'go-google-analytics/track', data_to_send_to_go_google_analytics );
		// with an appropriate listener in that plugin as well
	};

	/**
	 * get a random property, along with its key
	 */
	bstat.testing.getRandomPropertyWithKey = function( obj ) {
		var keys = Object.keys( obj );
		var random_key = keys[ keys.length * Math.random() << 0 ];
		return {
			'variation' : obj[ random_key ],
			'key'  : random_key
		};
	};

	// install an a/b test variation, if any
	bstat.testing.init();
})(jQuery);
