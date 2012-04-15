<?php
/*  admin.php

	This file allows you to view/change options for bSocial

*/


add_action( 'admin_menu', 'bsocial_config_page' );
function bsocial_config_page()
{
	add_submenu_page( 'plugins.php' , __('bSocial Configuration') , __('bSocial Configuration') , 'manage_options' , 'bsocial-options' , 'bsocial_options' );
}

add_filter( 'plugin_action_links', 'bsocial_plugin_action_links', 10, 2 );
function bsocial_plugin_action_links( $links, $file )
{
	if ( $file == plugin_basename( dirname(__FILE__) .'/bsocial.php' ))
		$links[] = '<a href="plugins.php?page=bsocial-options">'. __('Settings') .'</a>';

	return $links;
}

add_action( 'admin_init' , 'bsocial_admin_init' );
function bsocial_admin_init()
{
	register_setting( 'bsocial-options', 'bsocial-options', 'bsocial_sanitize_options' );
}

function bsocial_sanitize_options( $input )
{

	// filter the values so we only store known items
	$input = wp_parse_args( (array) $input , array(
		'open-graph' => 0,
		'featured-comments' => 0,
		'twitter-api' => 0,
		'twitter-comments' => 0,
		'twitter-app_id' => '',
		'facebook-api' => 0,
		'facebook-add_button' => 0,
		'facebook-comments' => 0,
		'facebook-admins' => '',
		'facebook-app_id' => '',
		'facebook-secret' => '',
	));

	// sanitize the integer values
	foreach( array(
		'open-graph',
		'featured-comments',
		'twitter-api',
		'twitter-comments',
		'facebook-api',
		'facebook-add_button',
		'facebook-comments',
	) as $key )
		$input[ $key ] = absint( $input[ $key ] );

	// sanitize the text values
	foreach( array(
		'twitter-app_id',
		'facebook-admins',
		'facebook-app_id',
		'facebook-secret',
	) as $key )
		$input[ $key ] = wp_filter_nohtml_kses( $input[ $key ] );

	return $input;
}

function bsocial_options()
{
?>
<div class="wrap">
	<h2>bSocial Options</h2>
	<form method="post" action="options.php">
		<?php settings_fields('bsocial-options'); ?>
		<?php $options = get_option('bsocial-options'); ?>
		<table class="form-table">
			<tr valign="top"><th scope="row">Add Open Graph metadata to pages</th>
				<td><input name="bsocial-options[open-graph]" type="checkbox" value="1" <?php checked( '1' , $options['open-graph']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Activate featured comments</th>
				<td><input name="bsocial-options[featured-comments]" type="checkbox" value="1" <?php checked( '1' , $options['featured-comments']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Activate Twitter components</th>
				<td><input name="bsocial-options[twitter-api]" type="checkbox" value="1" <?php checked( '1' , $options['twitter-api']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Twitter application consumer key</th>
				<td><input type="text" name="bsocial-options[twitter-app_id]" value="<?php echo $options['twitter-app_id']; ?>" /></td>
			</tr>

			<tr valign="top"><th scope="row">Ingest tweets that link to this site as comments on the post they link to</th>
				<td><input name="bsocial-options[twitter-comments]" type="checkbox" value="1" <?php checked( '1' , $options['twitter-comments']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Activate Facebook components</th>
				<td><input name="bsocial-options[facebook-api]" type="checkbox" value="1" <?php checked( '1' , $options['facebook-api']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Add a Facebook like button to every post</th>
				<td><input name="bsocial-options[facebook-add_button]" type="checkbox" value="1" <?php checked( '1' , $options['facebook-add_button']); ?> /></td>
			</tr>

			<tr valign="top"><th scope="row">Facebook admin IDs</th>
				<td><input type="text" name="bsocial-options[facebook-admins]" value="<?php echo $options['facebook-admins']; ?>" /></td>
			</tr>
			<tr valign="top"><th scope="row">Facebook app ID/API key</th>
				<td><input type="text" name="bsocial-options[facebook-app_id]" value="<?php echo $options['facebook-app_id']; ?>" /></td>
			</tr>
			<tr valign="top"><th scope="row">Facebook secret</th>
				<td><input type="text" name="bsocial-options[facebook-secret]" value="<?php echo $options['facebook-secret']; ?>" /></td>
			</tr>

			<tr valign="top"><th scope="row">Ingest Facebook comments</th>
				<td><input name="bsocial-options[facebook-comments]" type="checkbox" value="1" <?php checked( '1' , $options['facebook-comments']); ?> /></td>
			</tr>
		</table>
		<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
<?php
}