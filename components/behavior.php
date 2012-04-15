<?php
class bStat_Behaviors
{
	function __construct()
	{
		if( is_admin() )
			return;

		add_action( 'init' , array( $this , 'init' ));
		$this->path_web = plugins_url( plugin_basename( dirname( __FILE__ )));
	}

	function init()
	{
		// register and queue javascripts
		wp_register_script( 'bstat', $this->path_web . '/js/bstat.js', array('jquery'), '20080503' , TRUE );
		wp_enqueue_script( 'bstat' );

		// get options or set defaults
		if( ! isset( $this->options ))
			$this->options = array(
				'api_endpoint' => admin_url( 'admin-ajax.php' ),
				'highlight' => TRUE,
			);

		// put script vars on the page
		wp_localize_script( 'bstat' , 'bstat' , $this->options );

		// enqueue the search word highlighting script?
		if( $this->options['highlight'] )
		{
			// jQuery text highlighting plugin http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html
			wp_register_script( 'highlight', $this->path_web . '/js/jquery.highlight-3.js', array('jquery'), '1' , TRUE );
			wp_enqueue_script( 'highlight' );
		}

	}
}

function bstat_get_search_engine( $ref )
{
	// a lot of inspiration and code for this function was taken from
	// Search Hilite by Ryan Boren and Matt Mullenweg
	global $wp_query;
	if( empty( $ref ))
		return FALSE;

	$referer = urldecode( $ref );
	if (preg_match('|^https?://(www)?\.?google.*|i', $referer))
		return 'google';

	if (preg_match('|^https?://(www)?\.?bing.*|i', $referer))
		return 'bing';

	if (preg_match('|^https?://search\.yahoo.*|i', $referer))
		return 'yahoo';

	if ( strpos( ' '. parse_url( $referer , PHP_URL_HOST ) , parse_url( home_url() , PHP_URL_HOST ) ))
		return 'internal';

	return FALSE;
}

function bstat_get_search_terms( $ref )
{
	// a lot of inspiration and code for this function was taken from
	// Search Hilite by Ryan Boren and Matt Mullenweg

	if( !$engine = bstat_get_search_engine( $ref ))
		return FALSE;

	$referer = parse_url( $ref );
	parse_str( $referer['query'], $query_vars );

	$query_array = array();
	switch ($engine) {
	case 'google':
	case 'bing':
		if( $query_vars['q'] )
			$query_array = explode(' ', urldecode( $query_vars['q'] ));
		break;

	case 'yahoo':
		if( $query_vars['p'] )
			$query_array = explode(' ', urldecode( $query_vars['p'] ));
		break;

	case 'internal':
		if( $query_vars['s'] )
			$query_array = explode(' ', urldecode( $query_vars['s'] ));

		// also need to handle the case where a search matches the /search/ pattern
		break;
	}

	$query_array = array_filter( array_map( 'bstat_trimquotes' , $query_array ));

	return $query_array;
}

function bstat_trimquotes( $in )
{
	return( trim( trim( $in ), "'\"" ));
}

