<?php
/*  ui_options.php

	This file allows you to view/change options for bSuite


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

$bsuite->createtables();
$bsuite->cron_register();

//  apply new settings if form submitted
if($_REQUEST['Options'] == __('Rebuild bSuite search index', 'bsuite')){		
	$bsuite->command_rebuild_searchsmart();
}else if($_REQUEST['Options'] == __('Add post_excerpt to all posts', 'bsuite')){		
	$bsuite->command_rebuild_autoksum();
}else if($_REQUEST['Options'] == __('Show rewrite rules', 'bsuite')){
	echo '<div class="updated"><p><strong>' . __('The current rewrite rules (permlink settings)', 'bsuite') . ':</strong></p></div><div class="wrap" style="overflow:auto;"><pre>';
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
	print_r( $wp_rewrite->rewrite_rules() );
	echo '</pre></div>';
}else if($_REQUEST['Options'] == __('Force Stats Migration', 'bsuite')){
	update_site_option( 'bsuite_doing_migration', 0 );
	update_site_option( 'bsuite_doing_migration_popd', 0 );
	update_site_option( 'bsuite_doing_migration_popr', 0 );

	$bsuite->bstat_migrator();
	echo '<div class="updated"><p><strong>' . __('Completed stats migration', 'bsuite') . '</strong></p></div>';
}else if($_REQUEST['Options'] == __('PHP Info', 'bsuite')){
	phpinfo();
}


//  output settings/configuration form
?>
<div class="wrap">

<form method="post" action="options.php">
<?php settings_fields('bsuite-options'); ?>


<h3><?php _e('Options'); ?></h3>

<table class="form-table">

<tr>
<th scope="row" class="th-full">
<label for="bsuite_insert_related">
<input name="bsuite_insert_related" type="checkbox" id="bsuite_insert_related" value="1" <?php checked('1', get_option('bsuite_insert_related')); ?> />
<?php _e('Insert related posts links at bottom of each post', 'bsuite') ?>
</label>
</th>
</tr>
<tr>

<!--
<tr>
<th scope="row" class="th-full">
<label for="bsuite_insert_sharelinks">
<input name="bsuite_insert_sharelinks" type="checkbox" id="bsuite_insert_sharelinks" value="1" <?php checked('1', get_option('bsuite_insert_sharelinks')); ?> />
<?php _e('Insert share links at bottom of posts', 'bsuite') ?>
</label>
</th>
</tr>
<tr>
-->

<tr>
<th scope="row" class="th-full">
<label for="bsuite_searchsmart">
<input name="bsuite_searchsmart" type="checkbox" id="bsuite_searchsmart" value="1" <?php checked('1', get_option('bsuite_searchsmart')); ?> />
<?php _e('Enhance WordPress search with full text keyword indexing', 'bsuite') ?>
</label>
</th>
</tr>
<tr>

<tr>
<th scope="row" class="th-full">
<label for="bsuite_swhl">
<input name="bsuite_swhl" type="checkbox" id="bsuite_swhl" value="1" <?php checked('1', get_option('bsuite_swhl')); ?> />
<?php _e('Highlight search words for users who arrive at this site from recognized search engines', 'bsuite') ?>
</label>
</th>
</tr>
<tr>

<tr>
<th scope="row" class="th-full">
<label for="bsuite_who_can_edit">
<?php _e('Who can edit pages', 'bsuite') ?> 
<select name="bsuite_who_can_edit" id="bsuite_who_can_edit" >
<option value="anyone" <?php selected('anyone', get_settings('bsuite_who_can_edit')); ?>><?php _e('Anyone') ?></option>
<option value="registered_users" <?php selected('registered_users', get_settings('bsuite_who_can_edit')); ?>><?php _e('Registered users') ?></option>
<option value="authors" <?php selected('authors', get_settings('bsuite_who_can_edit')); ?>><?php _e('Authors and Editors') ?></option>
<option value="editors" <?php selected('editors', get_settings('bsuite_who_can_edit')); ?>><?php _e('Just Editors') ?></option>
</select>

</label>
</th>
</tr>
</table>

<?php if( !$bsuite->is_mu ) {?>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Management focus', 'bsuite') ?></th>
<td>
<label for="bsuite_managefocus_author">
<input name="bsuite_managefocus_author" type="checkbox" id="bsuite_managefocus_author" value="1" <?php checked('1', get_option('bsuite_managefocus_author')); ?> />
<?php _e('Focus default management view on current user', 'bsuite') ?>
</label> &nbsp; 
<label for="bsuite_managefocus_month">
<input name="bsuite_managefocus_month" type="checkbox" id="bsuite_managefocus_month" value="1" <?php checked('1', get_option('bsuite_managefocus_month')); ?> />
<?php _e('Focus default management view on current month', 'bsuite') ?>
</label>
</td>
</tr>
</table>

<table class="form-table">
<tr>
<th scope="row" class="th-full">
<label for="bsuite_insert_css">
<input name="bsuite_insert_css" type="checkbox" id="bsuite_insert_css" value="1" <?php checked('1', get_option('bsuite_insert_css')); ?> />
<?php _e('Insert default CSS', 'bsuite') ?>
</label>
</th>
</tr>
</table>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Cron settings', 'bsuite') ?></th>
<td>
<label for="bsuite_migration_interval"><?php _e('Seconds between migrations', 'bsuite'); ?></label>
<input name="bsuite_migration_interval" type="text" id="bsuite_migration_interval" value="<?php absint( get_site_option('bsuite_migration_interval')); ?>" size="6" />
<label for="bsuite_migration_count"><?php _e('Maximum number of items to process', 'bsuite'); ?></label>
<input name="bsuite_migration_count" type="text" id="bsuite_migration_count" value="<?php absint( get_site_option('bsuite_migration_count')); ?>" size="6" />
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Load awareness', 'bsuite') ?></th>
<td>
<label for="bsuite_load_max"><?php _e('Limit background processing when system load average is over', 'bsuite'); ?></label>
<input name="bsuite_load_max" type="text" id="bsuite_load_max" value="<?php absint( get_site_option('bsuite_load_max')); ?>" size="6" />
</td>
</tr>
</table>

<?php }else{ ?>

</table>

<?php } ?>

<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" class="button" />
</p>
</form>


<h3>&nbsp;</h3>


<h3><?php _e('Documentation') ?></h3>
<p><?php _e('More information about bSuite is available at <a href="http://maisonbisson.com/blog/bsuite/">MaisonBisson.com</a>.', 'bsuite') ?></p>



<h3><?php _e('bSuite Commands', 'bsuite') ?></h3>
<p><?php _e('bSuite will do these things automatically; these buttons are here for the impatient.', 'bsuite') ?></p>
<table class="form-table submit">
<tr>
<th scope="row" class="th-full">
<form method="post">
<input type="submit" name="Options" value="<?php _e('Rebuild bSuite search index', 'bsuite') ?>" /> &nbsp; 
<input type="submit" name="Options" value="<?php _e('Add post_excerpt to all posts', 'bsuite') ?>" /> &nbsp; 
<input type="submit" name="Options" value="<?php _e('Force Stats Migration', 'bsuite') ?>" />
</th>
</tr>
</table>

<h3><?php _e('Debugging Tools', 'bsuite') ?></h3>
<p><?php _e('Easy access to information about WordPress and PHP.', 'bsuite') ?></p>
<table class="form-table submit">
<tr>
<th scope="row" class="th-full">
<form method="post">
<input type="submit" name="Options" value="<?php _e('Show rewrite rules', 'bsuite') ?>" /> &nbsp; 
<input type="submit" name="Options" value="<?php _e('PHP Info', 'bsuite') ?>" /></form>
</th>
</tr>
</table>













</div>