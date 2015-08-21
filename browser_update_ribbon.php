<?php
/*
Plugin Name: Browser Update Ribbon
Plugin URI: http://www.duckinformatica.it
Description: Puts a ribbon on the website if the user browser is older than expected.
Version: 1.4.0
Author: duckinformatica, whiletrue
Author URI: http://www.duckinformatica.it
Text Domain: bur
Domain Path: /languages
*/

/*
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2, 
    as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action('wp_footer', 'browser_update_ribbon_show');
add_filter('plugin_action_links', 'browser_update_ribbon_add_settings_link', 10, 2 );
add_action('admin_init', 'browser_update_ribbon_init');
add_action('admin_menu', 'browser_update_ribbon_menu');
add_action('plugins_loaded', 'browser_update_ribbon_load_plugin_textdomain' );

define( 'BROWSER_UPDATE_RIBBON_VERSION', '1.4.0' );

function browser_update_ribbon_load_plugin_textdomain() {
    load_plugin_textdomain( 'bur', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}

function browser_update_ribbon_init() {
        wp_register_script(
            'browser_update_ribbon',
            plugin_dir_url( __FILE__ ) . 'browser_update_ribbon_admin.js',
            array( 'jquery' ),
            BROWSER_UPDATE_RIBBON_VERSION,
            'all'
        );
}

function browser_update_ribbon_menu() {
	$page_hook_suffix=add_options_page('Browser Update Ribbon Options', 'Update Ribbon', 'manage_options', 'browser_update_ribbon_options', 'browser_update_ribbon_options');
  add_action('admin_print_scripts-' . $page_hook_suffix, 'browser_update_ribbon_admin_scripts');
}

function browser_update_ribbon_admin_scripts() {
        wp_enqueue_media();
        
        // Localize the script with new data
        $translation_array = array(
        	'wp_media_title' => __( 'Select or Upload an Image', 'bur' )
        );
        wp_localize_script( 'browser_update_ribbon', 'bur', $translation_array );

        wp_enqueue_script( 'browser_update_ribbon' );
}


function browser_update_ribbon_add_settings_link($links, $file) {
	static $this_plugin;
	if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);
 
	if ($file == $this_plugin){
		$settings_link = '<a href="admin.php?page=browser_update_ribbon_options">'.__("Settings", 'bur' ).'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
} 


function browser_update_ribbon_show () {
	if(is_admin()){
		return;
	}

  //GET ARRAY OF STORED VALUES
  $browser_update_ribbon_option = browser_update_ribbon_get_options_stored();
  
	
	require_once('browser.php');
	$browser = new Browser();

	$browser_name = strtolower(str_replace(' ', '_', $browser->getBrowser()));
	
	if ($browser_update_ribbon_option['debug']) {
		echo __('Detected browser', 'bur' ).': '.$browser_name.' --  '.
    __('Detected version', 'bur' ).': '.(int)$browser->getVersion().' --  '.
    __('User agent string', 'bur' ).': '.$browser->getUserAgent().'<br />';
	}
  $showhome=true;
  if ($browser_update_ribbon_option['onlyhome']){
     if (!is_front_page()) $showhome=false; 
  }
  
  //select correct ribbon image
  if ($browser_update_ribbon_option['ribbon']=='custom' &&  $browser_update_ribbon_option['custom_img']!=''){
    $img_url = $browser_update_ribbon_option['custom_img'];
  }
  else{ //default
    if($browser_update_ribbon_option['position']=='topleft' || $browser_update_ribbon_option['position']=='topright')
      $img_url = plugins_url( 'default_ribbon_top.png', __FILE__ ); 
    else //bottom left or right
      $img_url = plugins_url( 'default_ribbon_bottom.png', __FILE__ );
  }
  
  //set position
     
  if($browser_update_ribbon_option['position']=='topleft') {
    $style='position: fixed; top:0px; left:0px; ';
  }
  else if($browser_update_ribbon_option['position']=='topright') {
    $style='position: fixed; top:0px; right:0px; ';
    if($browser_update_ribbon_option['ribbon']=='default')
      $style.='-ms-transform: rotate(90deg); -webkit-transform: rotate(90deg); transform: rotate(90deg); ';
  } 
  else if($browser_update_ribbon_option['position']=='bottomleft') {
    $style='position: fixed; bottom:0px; left:0px; ';
    if ($browser_update_ribbon_option['debug']) {
       $style='position: fixed; bottom:25px; left:0px; ';
    }
  }
  else if($browser_update_ribbon_option['position']=='bottomright') {
    $style='position: fixed; bottom:0; right:0; ';
    if($browser_update_ribbon_option['ribbon']=='default')
      $style.='-ms-transform: rotate(270deg); -webkit-transform: rotate(270deg); transform: rotate(270deg); ';
  }


	if(isset($browser_update_ribbon_option['blocked_browsers'][$browser_name]) 
	and $browser_update_ribbon_option['blocked_browsers'][$browser_name] > (int)$browser->getVersion() and $showhome) {
		$target = ($browser_update_ribbon_option['link_target']=='blank') ? ' target="_blank" ' : '';
		echo '<a href="'.$browser_update_ribbon_option['link'].'" title="'.$browser_update_ribbon_option['title'].'" '.$target.'>
    <img src="'.$img_url.'" alt="'.$browser_update_ribbon_option['title'].'" title="'.$browser_update_ribbon_option['title'].'" 
			style="'.$style.'z-index: 100000; cursor: pointer; border:none; background-color:transparent;" /></a>';
	}
}




function browser_update_ribbon_options () {
  //GET ARRAY OF STORED VALUES
  $browser_update_ribbon_option = browser_update_ribbon_get_options_stored();
  
  add_action( 'admin_enqueue_scripts', 'browser_update_ribbon_enqueue_scripts');

	$option_name = 'browser_update_ribbon';

	//must check that the user has the required capability 
	if (!current_user_can('manage_options')) {
		wp_die( __('You do not have sufficient permissions to access this page.', 'bur') );
	}

	$browsers = array(
		'chrome',
		'firefox',
    'edge',
		'internet_explorer',
		'opera',
		'safari'
	);	
  
	$out = '';
	
	// See if the user has posted us some information
	if( isset($_POST[$option_name.'_title'])) {
		$option = array();

		foreach ($browsers as $item) {
			$option['blocked_browsers'][$item]  = esc_html($_POST[$option_name.'_blocked_'.$item]);
		}
		$option['title'] = esc_html($_POST[$option_name.'_title']);
		$option['link']  = esc_html($_POST[$option_name.'_link']);
		$option['link_target']  = esc_html($_POST[$option_name.'_link_target']);
    $option['position']  = esc_html($_POST[$option_name.'_position']);
    $option['ribbon']  = esc_html($_POST[$option_name.'_ribbon']);
    $option['custom_img']  = esc_html($_POST[$option_name.'_custom_img']);
    $option['custom_img_thumb']  = esc_html($_POST[$option_name.'_custom_img_thumb']);
		$option['debug'] = (isset($_POST[$option_name.'_debug']) and $_POST[$option_name.'_debug']=='on') ? true : false;
    $option['onlyhome'] = (isset($_POST[$option_name.'_onlyhome']) and $_POST[$option_name.'_onlyhome']=='on') ? true : false;
		
    if ($option['ribbon']=='') $option['ribbon']='default';
    if ($option['position']=='') $option['position']='topleft';
    if ($option['custom_img']=='') $option['custom_img_thumb']='';
    
		update_option($option_name, $option);
		// Put a settings updated message on the screen
		$out .= '<div class="updated"><p><strong>'.__('Settings saved.', 'bur' ).'</strong></p></div>';
	}
	
	//GET (EVENTUALLY UPDATED) ARRAY OF STORED VALUES
	$option = browser_update_ribbon_get_options_stored();
	
	$debug = ($option['debug']) ? 'checked="checked"' : '';
  $onlyhome = ($option['onlyhome']) ? 'checked="checked"' : '';
	$link_target_blank = ($option['link_target']=='blank') ? 'selected="selected"' : '';
  $pos_tl = ($option['position']=='topleft') ? 'selected="selected"' : '';
  $pos_tr = ($option['position']=='topright') ? 'selected="selected"' : '';
  $pos_bl = ($option['position']=='bottomleft') ? 'selected="selected"' : '';
  $pos_br = ($option['position']=='bottomright') ? 'selected="selected"' : '';
  $ribbon_default = ($option['ribbon']=='default') ? 'checked="checked"' : '';
  $ribbon_custom = ($option['ribbon']=='custom') ? 'checked="checked"' : '';
  
  if ($option['custom_img']=='') $hide_img = 'class="hidden"';
  
  // SETTINGS FORM

	$out .= '
	<style>
		#browser_update_ribbon_form h3 { cursor: default; }
		#browser_update_ribbon_form td { vertical-align:top; padding-bottom:15px; }
	</style>
	
	<div class="wrap">
	<h2>'.__( 'Browser Update Ribbon', 'bur' ).'</h2>
	<div id="poststuff" style="padding-top:10px; position:relative;">

	<div>

		<form id="browser_update_ribbon_form" name="form1" method="post" action="">

		<div class="postbox">
		<h3>'.__("General options", 'bur' ).'</h3>
		<div class="inside">
			<table>
			<tr><td style="width:130px;">'.__("Browsers control", 'bur' ).':<br /><br />
				<span class="description">'.__("To disable for some browser, set value to 1", 'bur' ).'</span>
			</td>
			<td>';
		
			$out .= '<ul>';
			
			foreach (array_keys($option['blocked_browsers']) as $name) {

				$out .= '<li class="ui-state-default" id="'.$name.'" style="width:300px;">
						<div style="float:left; width:150px;">
							<b>'.ucwords(str_replace('_', ' ', $name)).'</b>
						</div>
						<div style="float:left; width:150px;">
							'.__('Minimum version','bur').': <input type="text" name="'.$option_name.'_blocked_'.$name.'" value="'.stripslashes($option['blocked_browsers'][$name]).'" style="width:35px; margin:0; padding:0; text-align:right;" />
						</div>
					</li>';
			}

			$out .= '</ul>';

			$out .= '</td></tr>
			<tr><td>'.__("Title", 'bur' ).':</td>
			<td><input type="text" name="'.$option_name.'_title" value="'.stripslashes($option['title']).'" size="60" /><br />
				<span class="description">'.__("Text shown when the user's mouse is over the ribbon", 'bur' ).'</span>
			</td></tr>
      <tr><td>'.__("Ribbon", 'bur' ).':</td>
			<td>
      <div>
      <div style="float: left; margin-right: 15px;">
        <input type="radio" name="'.$option_name.'_ribbon" value="default" '.$ribbon_default.' /> '.__("Default", 'bur' ).'<br />
        <img src="'.plugin_dir_url( __FILE__ ).'default_ribbon_top.png" alt="'.__("Default Ribbon", 'bur' ).'" title="'.__("Default Ribbon", 'bur' ).'" style="vertical-align: middle; border: 1px solid black; height:'.get_option( 'thumbnail_size_h' ).'px;"/>
      </div>
      <div style="float: left; margin-right: 15px;">
        <input type="radio" name="'.$option_name.'_ribbon" value="custom" '.$ribbon_custom.'/> '.__("Custom", 'bur' ).'<br /> 
        <div id="custom-ribbon-image-container" '.$hide_img.'>
          <img src="'.stripslashes($option['custom_img_thumb']).'" alt="'.__("Custom Ribbon", 'bur' ).'" title="'.__("Custom Ribbon", 'bur' ).'" style="vertical-align: middle; border: 1px solid black;"/>
        </div>
        <a name="imgsel" id="imgsel" href="javascript:;" class="button">'.__("Select Image", 'bur' ).'</a><br />
        <input type="text" name="'.$option_name.'_custom_img" id="'.$option_name.'_custom_img" value="'.stripslashes($option['custom_img']).'" size="60" />
        <input type="hidden" name="'.$option_name.'_custom_img_thumb" id="'.$option_name.'_custom_img_thumb" value="'.stripslashes($option['custom_img_thumb']).'" />
      </div>
      </div>
			</td></tr>
      <tr><td>'.__("Position", 'bur' ).':</td>
			<td><select name="'.$option_name.'_position">
				<option value="topleft" '.$pos_tl.' > '.__('Top Left', 'bur' ).'</option>
				<option value="topright" '.$pos_tr.' > '.__('Top Right', 'bur' ).'</option>
        <option value="bottomleft" '.$pos_bl.' > '.__('Bottom Left', 'bur' ).'</option>
				<option value="bottomright" '.$pos_br.' > '.__('Bottom Right', 'bur' ).'</option>
				</select><br />
        <span class="description">'.__("Choose the corner to display the ribbon", 'bur' ).'</span>
			</td></tr>
			<tr><td>'.__("Link URL", 'bur' ).':</td>
			<td><input type="text" name="'.$option_name.'_link" value="'.stripslashes($option['link']).'" size="60" /><br />
				<span class="description">'.__("Link activated when the user clicks on the ribbon. The link can be a page of your website or an external url", 'bur' ).'</span>
			</td></tr>
			<tr><td>'.__("Link target", 'bur' ).':</td>
			<td><select name="'.$option_name.'_link_target">
				<option value=""> '.__('Same window', 'bur' ).'</option>
				<option value="blank" '.$link_target_blank.' > '.__('New window', 'bur' ).'</option>
				</select>
			</td></tr>
      <tr><td>'.__("Homepage Only", 'bur' ).':</td>
			<td><input type="checkbox" name="'.$option_name.'_onlyhome" '.$onlyhome.' />
					<span class="description">'.__("Enable homepage only mode (shows only in homepage)", 'bur' ).'</span>
			</td></tr>
			<tr><td>'.__("Debug mode", 'bur' ).':</td>
			<td><input type="checkbox" name="'.$option_name.'_debug" '.$debug.' />
					<span class="description">'.__("Enable debug mode (shows browser version in the footer)", 'bur' ).'</span>
			</td></tr>
			</table>
      <div style="text-align:right;">
         v.'.BROWSER_UPDATE_RIBBON_VERSION.'
      </div>
		</div>
		</div>'
		.'<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="'.__("Save Changes", 'bur' ).'" />
		</p>

		</form>

	</div>
	
	</div>
	</div>
	';
	echo $out; 
}


// PRIVATE FUNCTIONS

function browser_update_ribbon_get_options_stored () {
	//GET ARRAY OF STORED VALUES
	$option = get_option('browser_update_ribbon');
	 
	if(!is_array($option)) {
		$option = array();
	}	
	
	// MERGE DEFAULT AND STORED OPTIONS
	$option_default = browser_update_ribbon_get_options_default();
	$option = array_merge($option_default, $option);

	return $option;
}

function browser_update_ribbon_get_options_default () {
	$option = array();
	$option['title'] = __('Please update your browser', 'bur' );
	$option['link'] = 'http://www.updateyourbrowser.net/en/';
	$option['link_target'] = '';
	$option['onlyhome'] = false;
  $option['position'] = '';
  $option['ribbon'] = '';
  $option['custom_img'] = '';
  $option['custom_img_thumb'] = '';
  $option['debug'] = false;
  

	// THE NUMBER REPRESENTS THE MINUMUM ACCEPTED VERSION
	$option['blocked_browsers'] = array( 
		'chrome'=>'36',
		'firefox'=>'31',
    'edge'=>'12',
		'internet_explorer'=>'11',
		'opera'=>'23',
		'safari'=>'7'
	);
	return $option;
}


