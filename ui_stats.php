<?php
/*  ui_stats.php

	This file displays the report/status screens for bSuite bStat


	Copyright 2004 - 2008  Casey Bisson

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
if( !isset( $wpdb ) || !isset( $bsuite ) )
	exit;

require_once (ABSPATH . WPINC . '/rss.php');

$bsuite->createtables();

update_site_option('bsuite_doing_migration', time() + 300 );
?>


<div class="wrap">
<h2><?php _e('Quick Stats') ?></h2>
<?php

$best_num = 10;
$detail_lines = 25;
$bstat_period = 90;
$date  = date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d") - $bstat_period, date("Y")));

?>
<table><tr valign='top'>
<td><h4>Today's Page Loads</h4><ul><?php echo $wpdb->get_var("SELECT FORMAT(SUM(hit_count), 0) FROM $bsuite->hits_targets WHERE hit_date = CURDATE() AND object_blog = ". absint( $blog_id ) ." AND object_type IN (0,1)"); ?></ul></td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

<td><h4>Avg Daily Loads</h4><ul><?php echo $wpdb->get_var("SELECT FORMAT((SUM(hit_count)/ ((TO_DAYS(CURDATE()) - TO_DAYS(MIN(hit_date))) + 1)), 0) FROM $bsuite->hits_targets WHERE hit_date > '$date' AND object_blog = ". absint( $blog_id ) ." AND object_type IN (0,1)"); ?></ul></td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

<td><h4>Today's Prediction</h4><ul><?php echo $wpdb->get_var("SELECT FORMAT(SUM(hit_count) * (86400/TIME_TO_SEC(TIME(NOW()))), 0) FROM $bsuite->hits_targets WHERE hit_date = CURDATE() AND object_blog = ". absint( $blog_id ) ." AND object_type IN (0,1)"); ?></ul></td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

<td><h4>Total Page Loads</h4><ul><?php echo $wpdb->get_var("SELECT FORMAT(SUM(hit_count), 0) FROM $bsuite->hits_targets WHERE object_type IN (0,1) AND object_blog = ". absint( $blog_id )); ?></ul></td>
</tr></table>
<?php

$now = $wpdb->get_var( 'SELECT UNIX_TIMESTAMP( CONCAT( DATE( NOW()), " ", HOUR( NOW()), ":00:00" ))' );

$sessions = $pageloads = $hours = array();
for($i = 0; $i <= 23; $i++)
	$sessions[ $now - ( $i * 60 * 60 ) ] = $pageloads[ $now - ( $i * 60 * 60 ) ] = 0;
ksort( $sessions );
ksort( $pageloads );

foreach( $pageloads as $key => $val )
	$hours[] = date('H', $key);

$dates = $wpdb->get_col( "SELECT sess_date
	FROM (
		SELECT sess_id, sess_date AS sess_timestamp, DATE(sess_date) AS sess_date, HOUR(sess_date) AS sess_hour
		FROM $bsuite->hits_sessions
		ORDER BY sess_id DESC
		LIMIT 25000
	) a
	WHERE sess_timestamp >= DATE_SUB( NOW(), INTERVAL 1 DAY )
	GROUP BY sess_date, sess_hour" );

$sessions_db = $wpdb->get_results( "SELECT COUNT(*) AS hit_count, sess_timestamp
	FROM(
		SELECT UNIX_TIMESTAMP( CONCAT( DATE(sess_date), ' ', HOUR(sess_date), ':00:00' )) AS sess_timestamp, DATE(sess_date) AS sess_date, HOUR(sess_date) AS sess_hour, sess_id
		FROM $bsuite->hits_sessions
		WHERE sess_date >= DATE_SUB( NOW(), INTERVAL 25 HOUR )
	) s
	JOIN ( SELECT s.sess_id
		FROM(
			SELECT sess_id
			FROM $bsuite->hits_sessions
			WHERE sess_date >= DATE_SUB( NOW(), INTERVAL 25 HOUR )
		) s
		JOIN $bsuite->hits_shistory h ON h.sess_id = s.sess_id
		WHERE h.object_type IN (0,1)
		AND h.object_blog = ". absint( $blog_id ) ."
		GROUP BY s.sess_id
	) a ON a.sess_id = s.sess_id
	GROUP BY sess_date, sess_hour
	" );


foreach( $sessions_db as $session )
	$sessions[$session->sess_timestamp] = $session->hit_count;

$pageloads_db = $wpdb->get_results( "SELECT COUNT(*) AS hit_count, sess_timestamp, s.sess_id
	FROM(
		SELECT UNIX_TIMESTAMP( CONCAT( DATE(sess_date), ' ', HOUR(sess_date), ':00:00' )) AS sess_timestamp, DATE(sess_date) AS sess_date, HOUR(sess_date) AS sess_hour, sess_id
			FROM $bsuite->hits_sessions
			WHERE sess_date >= DATE_SUB( NOW(), INTERVAL 25 HOUR )
	) s
	JOIN $bsuite->hits_shistory h ON h.sess_id = s.sess_id
	WHERE h.object_type IN (0,1)
	AND h.object_blog = ". absint( $blog_id ) ."
	GROUP BY sess_date, sess_hour
	" );


foreach( $pageloads_db as $pageload )
	$pageloads[$pageload->sess_timestamp] = $pageload->hit_count;

?>
<h4><?php echo number_format( array_sum( $pageloads )) ?> page loads and <?php echo number_format( array_sum( $sessions )) ?> visitors in last 24 hours:</h4>
<?php
echo '<img src="http://chart.apis.google.com/chart?chs=550x150&cht=lc&chco=0077CC&chm=B,E6F2FA,0,0,0&chls=1,0,0&chds='. min( $sessions ) .','. max( $pageloads ) .'&chd=t:'. implode( $sessions, ',' ) .'|'. implode( $pageloads, ',' ) .'&chxt=x,x,y&chxl=0:|'. implode( $hours, '|' ) .'|1:| |'. $dates[0] .'| | | | | | | | | |'. array_pop( $dates ) .'| |2:|'. min( $sessions ) .'|'. max( $pageloads ) .'" width="550" height="150" alt="Graph of pageloads and unique visitors in last 24 hours.">';


$months = $wpdb->get_col( "SELECT DATE_FORMAT( MAKEDATE( YEAR( hit_date ) ,DAYOFYEAR( hit_date ) ) , '%M' )
	FROM (
		SELECT SUM(hit_count) AS hit_count, hit_date
		FROM $bsuite->hits_targets
		GROUP BY hit_date DESC
		LIMIT 31
	) a" );

$days = $wpdb->get_col( "SELECT DAY( hit_date )
	FROM ( 
		SELECT SUM(hit_count) AS hit_count, hit_date
		FROM $bsuite->hits_targets
		GROUP BY hit_date DESC
		LIMIT 31
	) a" );

$pageloads = $wpdb->get_col( "SELECT hit_count
	FROM ( 
		SELECT SUM(hit_count) AS hit_count, hit_date
		FROM $bsuite->hits_targets
		WHERE object_blog = ". absint( $blog_id ) ."
		GROUP BY hit_date DESC
		LIMIT 31
	) a" );
?>
<h4><?php echo number_format( array_sum( $pageloads )) ?> page loads in last month:</h4>
<?php
echo '<img src="http://chart.apis.google.com/chart?chs=550x150&cht=lc&chco=0077CC&chm=B,E6F2FA,0,0,0&chls=1,0,0&chds='. min( $pageloads ) .','. max( $pageloads ) .'&chd=t:'. implode( array_reverse( $pageloads ), ',' ) .'&chxt=x,x,y&chxl=0:|'. implode( array_reverse( $days ), '|' ) .'|1:| |'. array_pop( $months ) .'| | | | | | | | | |'. $months[0] .'| |2:|'. min( $pageloads ) .'|'. max( $pageloads ) .'" width="550" height="150" alt="Graph of pageloads in last 31 days.">';
?>

<h4>&nbsp;</h4>
<strong>Compiled:</strong> <?php echo date('F j, Y, g:i a'); ?> | <strong>System Load Average:</strong> <?php echo $bsuite->get_loadavg(); ?>

<h4>&nbsp;</h4>

</div>
<div class="wrap">
<h2><?php _e('Page Loads') ?></h2>
<table width="100%"><tr valign='top'><td width="33%"><h4>Most Daily Reads</h4><ol>
<?php
$results = $wpdb->get_results("SELECT post_id, hits_total, ROUND( hits_total / ( TO_DAYS( NOW() ) - TO_DAYS( date_start ) )) AS hits_average, hits_recent
	FROM $bsuite->hits_pop
	WHERE blog_id = ". absint( $blog_id ) ." 
	ORDER BY hits_average DESC
	LIMIT $detail_lines", ARRAY_A);

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_permalink($res['post_id']) .'">'. wordwrap( get_the_title($res['post_id']), 25, "\n", TRUE ).'</a><br><small>Avg: '. number_format( $res['hits_average'] ) .' Recent: '. number_format( $res['hits_recent'] ) .'<!-- Tot: '. number_format( $res['hits_total'] ) ." --></small></li>\n";
else
	echo '<li>No Data Yet.</li>';
?>
</ol></td>

<td width="33%"><h4>Top Climbers</h4><ol>
<?php
$results = $wpdb->get_results("SELECT post_id, ROUND( hits_total / ( TO_DAYS( NOW() ) - TO_DAYS( date_start ) )) AS hits_average, hits_recent, ( hits_recent - ROUND( hits_total / ( TO_DAYS( NOW() ) - TO_DAYS( date_start ) ))) AS hits_diff
	FROM $bsuite->hits_pop
	WHERE blog_id = ". absint( $blog_id ) ." 
	HAVING hits_diff > 0
	ORDER BY hits_diff DESC
	LIMIT $detail_lines", ARRAY_A);

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_permalink($res['post_id']) .'">'. wordwrap( get_the_title($res['post_id']), 25, "\n", TRUE ).'</a><br><small>Avg: '. number_format( $res['hits_average'] ) .' Recent: '. number_format( $res['hits_recent'] ) .' Up: '. number_format( $res['hits_diff'] ) ."</small></li>\n";
else
	echo '<li>No Data Yet.</li>';
?>
</ol></td>

<td width="33%"><h4>Biggest Losers<?php if($lose) echo " ($lose)" ?></h4><ol>
<?php
$results = $wpdb->get_results("SELECT post_id, ROUND( hits_total / ( TO_DAYS( NOW() ) - TO_DAYS( date_start ) )) AS hits_average, hits_recent, ( hits_recent - ROUND( hits_total / ( TO_DAYS( NOW() ) - TO_DAYS( date_start ) ))) AS hits_diff
	FROM $bsuite->hits_pop
	WHERE blog_id = ". absint( $blog_id ) ." 
	HAVING hits_diff < 0
	ORDER BY hits_diff ASC
	LIMIT $detail_lines", ARRAY_A);

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_permalink($res['post_id']) .'">'. wordwrap( get_the_title($res['post_id']), 25, "\n", TRUE ).'</a><br><small>Avg: '. number_format( $res['hits_average'] ) .' Recent: '. number_format( $res['hits_recent'] ) .' Down: '. number_format( $res['hits_diff'] ) ."</small></li>\n";
else
	echo '<li>No Data Yet.</li>';
?>
</ol></td></tr></table>
</div>











<div class="wrap">
<h2><?php _e('Other Access') ?></h2>

<table width="100%"><tr valign='top'><td width="33%"><h4>Top non-Post URLs</h4><ol>
<?php
$results = $wpdb->get_results("SELECT hit_count, hit_avg, name
	FROM (
		SELECT object_id, SUM(hit_count) AS hit_count, AVG(hit_count) AS hit_avg
		FROM $bsuite->hits_targets
		WHERE hit_date >= DATE( DATE_SUB( NOW(), INTERVAL 5 DAY ))
		AND object_blog = ". absint( $blog_id ) ."
		AND object_type = 1
		GROUP BY object_id
		ORDER BY hit_count DESC
		LIMIT $detail_lines
	) a
	LEFT JOIN $bsuite->hits_terms t ON a.object_id = t.term_id
	GROUP BY object_id
	ORDER BY hit_count DESC");

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. sanitize_url( $res->name ).'">'. wordwrap( htmlspecialchars( urldecode( str_replace( get_settings( 'siteurl' ), '', $res->name ))), 25, "\n", TRUE ) .'</a><br><small>Avg: '. number_format( $res->hit_avg ) .' Total: '. number_format( $res->hit_count ) ."</small></li>\n";
else
	echo '<li>No Data Yet.</li>';


?>
</ol></td>

<td width="33%"><h4>Top Entry URLs</h4><ol>
<?php
$results = $wpdb->get_results("SELECT name, object_id, object_type, COUNT(*) AS hit_count, MIN( sess_date ) AS date_min
	FROM (
		SELECT a.sess_id, a.sess_date, b.object_id, b.object_type
		FROM (
			SELECT sess_id, sess_date
			FROM $bsuite->hits_sessions
			ORDER BY sess_id DESC
			LIMIT 25000
		) a
		INNER JOIN $bsuite->hits_shistory b ON a.sess_id = b.sess_id
		WHERE b.object_type IN (0, 1)
		AND object_blog = ". absint( $blog_id ) ."
		GROUP BY sess_id
		LIMIT 2500
	) c
	LEFT JOIN $bsuite->hits_terms t ON c.object_id = t.term_id
	GROUP BY t.term_id
	ORDER BY hit_count DESC
	LIMIT $detail_lines");

if( count( $results ) )
	foreach( $results as $res ){
		if( 1 == $res->object_type )
			echo '<li><a href="'. sanitize_url( $res->name ) .'">'. wordwrap( htmlspecialchars( urldecode( str_replace( get_settings( 'siteurl' ), '', $res->name ))), 25, "\n", TRUE ) .'</a><br><small>'. number_format( $res->hit_count ) .' hits since '. $res->date_min .'</small></li>';
		else
			echo '<li><a href="'. get_permalink( $res->object_id ) .'">'. wordwrap( get_the_title( $res->object_id ), 25, "\n", TRUE ) .'</a><br><small>'. number_format( $res->hit_count ) .' hits since '. $res->date_min .'</small></li>';
}else{
	echo '<li>No Data Yet.</li>';
}

?>
</ol></td>

<td width="33%"><h4>Top Exit Destinations</h4><ol>
<?php
	echo '<li>coming soon.</li>';


?>
</ol></td></tr></table>
</div>












<div class="wrap">
<h2><?php _e('Most Trafficked Categories and Tags') ?></h2>

<table width="100%"><tr valign='top'><td width="33%"><h4>Categories (past month)</h4><ol>
<?php
$results = $wpdb->get_results("SELECT tt.term_id, name, taxonomy, hit_count, (hit_count / count) AS normalized_hit_count 
	FROM (
		SELECT term_taxonomy_id, SUM(hit_count) AS hit_count
		FROM (
			SELECT object_id, SUM(hit_count) AS hit_count
			FROM $bsuite->hits_targets
			WHERE hit_date >= DATE( DATE_SUB( NOW(), INTERVAL 1 MONTH ))
			AND object_blog = ". absint( $blog_id ) ."
			AND object_type = 0
			GROUP BY object_id
			ORDER BY hit_count DESC
			LIMIT 1000
		) p
		LEFT JOIN $wpdb->term_relationships tr ON p.object_id = tr.object_id
		GROUP BY tr.term_taxonomy_id
		ORDER BY hit_count DESC
	) tr
	LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
	LEFT JOIN $wpdb->terms t ON tt.term_id = t.term_id
	WHERE taxonomy = 'category'
	ORDER BY normalized_hit_count DESC
	LIMIT 10");

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_category_link( $res->term_id ) .'">'. $res->name .'</a></li>';
else
	echo '<li>No Data Yet.</li>';


?>
</ol></td>

<td width="33%"><h4>Categories (past 24 hours)</h4><ol>
<?php
$results = $wpdb->get_results("SELECT tt.term_id, name, taxonomy, hit_count, (hit_count / count) AS normalized_hit_count 
	FROM (
		SELECT term_taxonomy_id, SUM(hit_count) AS hit_count
		FROM (
			SELECT object_id, SUM(hit_count) AS hit_count
			FROM $bsuite->hits_targets
			WHERE hit_date >= DATE( DATE_SUB( NOW(), INTERVAL 1 DAY ))
			AND object_blog = ". absint( $blog_id ) ."
			AND object_type = 0
			GROUP BY object_id
			ORDER BY hit_count DESC
			LIMIT 1000
		) p
		LEFT JOIN $wpdb->term_relationships tr ON p.object_id = tr.object_id
		GROUP BY tr.term_taxonomy_id
		ORDER BY hit_count DESC
	) tr
	LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
	LEFT JOIN $wpdb->terms t ON tt.term_id = t.term_id
	WHERE taxonomy = 'category'
	ORDER BY normalized_hit_count DESC
	LIMIT 10");

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_category_link( $res->term_id ) .'">'. $res->name .'</a></li>';
else
	echo '<li>No Data Yet.</li>';


?>
</ol></td>

<td width="33%"><h4>Tags (recently)</h4><ol>
<?php
$results = $wpdb->get_results("SELECT tt.term_id, name, taxonomy, hit_count, (hit_count / count) AS normalized_hit_count 
	FROM (
		SELECT term_taxonomy_id, SUM(hit_count) AS hit_count
		FROM (
			SELECT object_id, SUM(hit_count) AS hit_count
			FROM $bsuite->hits_targets
			WHERE hit_date >= DATE( DATE_SUB( NOW(), INTERVAL 3 DAY ))
			AND object_blog = ". absint( $blog_id ) ."
			AND object_type = 0
			GROUP BY object_id
			ORDER BY hit_count DESC
			LIMIT 1000
		) p
		LEFT JOIN $wpdb->term_relationships tr ON p.object_id = tr.object_id
		GROUP BY tr.term_taxonomy_id
		ORDER BY hit_count DESC
	) tr
	LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
	LEFT JOIN $wpdb->terms t ON tt.term_id = t.term_id
	WHERE taxonomy = 'post_tag'
	ORDER BY hit_count DESC
	LIMIT 10");

if( count( $results ) )
	foreach( $results as $res )
		echo '<li><a href="'. get_tag_link( $res->term_id ) .'">'. $res->name .'</a></li>';
else
	echo '<li>No Data Yet.</li>';


?>
</ol></td></tr></table>
</div>


















<div class="wrap">
<h2><?php _e('Referrers') ?></h2>

<table width="100%"><tr valign='top'><td width="33%"><h4>Incoming Search Terms</h4><ol>
<?php
//
// Incoming Search Terms
//

$refs = $bsuite->pop_refs("count=$detail_lines&days=$bstat_period");
if(!empty($refs))
	echo $refs;
else
	echo '<li>No Data Yet.</li>';
?>
</ol></td>

<?php
//
// Referrers from Google Blog Search
//
$rss_feed = 'http://blogsearch.google.com/blogsearch_feeds?hl=en&scoring=d&ie=utf-8&num='. $detail_lines .'&output=rss&partner=bsuite&q=link:' . trailingslashit( get_option('home') );
$more_link = apply_filters( 'dashboard_incoming_links_link', 'http://blogsearch.google.com/blogsearch?hl=en&scoring=d&partner=bsuite&q=link:' . trailingslashit( get_option('home') ) );

echo '<td width="33%"><h4>Referrers from <a href="'. htmlspecialchars( $more_link ) .'">Google Blog Search</a></h4><ol>';

$rss = @fetch_rss( $rss_feed );
if ( isset($rss->items) && 1 < count($rss->items) ) {
	$rss->items = array_slice($rss->items, 0, $detail_lines);
	foreach ($rss->items as $item ) {
?>
		<li><a href="<?php echo wp_filter_kses($item['link']); ?>"><?php echo wptexturize(wp_specialchars($item['title'])); ?></a></li>
<?php
	}
}else{
	echo '<li>No Data Yet.</li>';
}
?>
</ol></td>

<?php
//
// Referrers from Technorati
//
$rss_feed = 'http://feeds.technorati.com/cosmos/rss/?url='. trailingslashit(get_option('home')) .'&partner=bsuite';
$more_link = 'http://www.technorati.com/cosmos/search.html?url=' . urlencode(trailingslashit( get_option('home') ) ) .'&partner=bsuite';

echo '<td width="33%"><h4>Referrers from <a href="'. htmlspecialchars( $more_link ) .'">Technorati</a></h4><ol>';

$rss = @fetch_rss( $rss_feed );
if ( isset($rss->items) && 1 < count($rss->items) ) {
	$rss->items = array_slice($rss->items, 0, $detail_lines);
	foreach ($rss->items as $item ) {
?>
		<li><a href="<?php echo wp_filter_kses($item['link']); ?>"><?php echo wptexturize(wp_specialchars($item['title'])); ?></a></li>
<?php
	}
}else{
	echo '<li>No Data Yet.</li>';
}
?>
</ol></td></tr></table>
</div>