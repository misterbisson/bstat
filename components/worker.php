<?php
// get prereqs
require('../../../wp-config.php');
global $wpdb, $bsuite, $blog_id;

// send headers
//@header( 'Content-Type: application/json; charset='. get_option('blog_charset') );
@header( 'Content-Type: text/javascript; charset='. get_option('blog_charset') );
nocache_headers();

// get or start a session
if( $_COOKIE['bsuite_session'] )
	$session = $_COOKIE['bsuite_session'];
else
	$session = md5( uniqid( rand(), true ));

// set or update the cookie to expire 30 minutes from now
setcookie ( 'bsuite_session', $session, time()+1800, '/' );

// create an array of 'extra' details
$in_extra = array(  'ip' => $_SERVER["REMOTE_ADDR"], 'br' => $_REQUEST['br'],  'bb' => $_REQUEST['bb'],  'bl' => $_REQUEST['bl'],  'bc' => $_REQUEST['bc'],  'ba' => urlencode( $_SERVER['HTTP_USER_AGENT'] ) );

// insert the hit
$wpdb->insert( $bsuite->hits_incoming, array( 'in_type' => '0', 'in_session' => $session, 'in_blog' => absint( $blog_id ), 'in_to' => $_SERVER['HTTP_REFERER'] , 'in_from' => $_REQUEST['pr'], 'in_extra' => serialize( $in_extra )));

// output useful data
if( get_option('bsuite_swhl') && ( $searchterms = $bsuite->get_search_terms( $_REQUEST['pr'] ))){
	// output a json object to highlight search terms
	echo "var bsuite_json = {terms:['". implode("','", array_map('htmlentities',$searchterms) ) ."']};";
	echo "jQuery(function(){bsuite_highlight(bsuite_json);});";

/*
	foreach( $wpdb->get_col( $bsuite->searchsmart_query( implode( $searchterms, ' ' ))) as $post)
		$related_posts[] = '<a href="'. get_permalink( $post ) .'" title="Permalink to related post: '. get_the_title( $post ) .'">'.  get_the_title( $post ) .'</a>';
	if( count( $related_posts ))
		echo 'bsuite_related_posts('. json_encode( $related_posts ) .");\n";
*/
}

//phpinfo();
/*
print_r($wpdb->queries);
print_r( array( 'count_queries' => $wpdb->num_queries , 'count_seconds' => timer_stop(1) ));
*/
