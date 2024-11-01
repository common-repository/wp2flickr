<?php
/*
	Plugin Name: wp2flickr
	Plugin URI: http://wp2flickr.com/
	Description: upload photos from posts to flickr (standard media or YAPB)
	Version: 0.15
	Author: Fran Sim&oacute;
	Author URI: http://fransimo.info/
*/

/*
 * Configuration
 */

require_once('config.php');
require_once('phpFlickr-3.1/phpFlickr.php');
require_once('functions.php');

function w2f_polyglot_filter($text) {	
	$is_p=function_exists("polyglot_filter");
	if ($is_p) {
		return polyglot_filter($text);
	} else {
		return $text;
	}
}

/*
 * Publish
 */

add_action('publish_post', 'w2f_publish',100); 
//http://www.stephanmiller.com/tag-support-for-the-onlywire-autosubmit-plugin/
function w2f_publish($post_id){
	if(w2f_check_record($post_id)) { return $post_id; } //publish hook is called whereever is a new public or a save of alredey published
	if(''==get_option('w2f_token')) { return $post_id; }  //if token is not set exit

	w2f_log_check(); 
	
	$post=get_post($post_id);
	$permalink = get_permalink($post_id);
	$tags = get_the_tags($post_id); $taglist='';
	
	if (!empty($tags)){
		foreach($tags as $tag){
			$taglist .= '"'.$tag->name.'" ';
		}
	}
	if (!strpos($taglist,"not2flickr")){
		$imageFilePath='';
		if (class_exists('YapbImage')) {
			if (!is_null($image = YapbImage::getInstanceFromDb($post_id))) {
				$imageFilePath=$image->systemFilePath($image->uri);
				$image_id=$image->id;
				$content=$post->post_content;
			}
		}
		
		if ($imageFilePath=='') {
			$uploads = wp_upload_dir();
			$content = preg_replace("/<img[^>]+\>/i", " ", $post->post_content);  //remove all <img> tags from content to use as description in flickr.			
			$iurl=w2f_url_image($post->post_content);

			if (!empty($iurl)) {
				$remove_url=str_replace(get_bloginfo( 'url' ), "", $iurl);
				$inter=w2f_string_intersect($remove_url,$uploads['basedir']);
				$file=str_replace($inter, "", $remove_url);
				
				$imageFilePath=$uploads['basedir'].$file;
				$image_id=$post_id;
				
			} else { //try to find the image in attachments... not very 
				//http://codex.wordpress.org/Function_Reference/get_children
				$images =get_children( 'post_parent='.$post->ID.'&post_type=attachment&post_mime_type=image' );
				if ( !empty($images) ) {
					$attachment=array_pop($images); // a photoblog should have only an image per post
					unset($images);
					$attachment_id=$attachment->ID;
					$file = get_post_meta( $attachment_id, '_wp_attached_file', true);
					$imageFilePath=$uploads['basedir'].'/'.$file;
					$image_id=$attachment_id;
				} else {
					// no attachments here
				}
			}
			
		}
		
		if (!$imageFilePath=='') {
			$flickr=new  w2f_phpFlickr(w2f_flickrAPI,w2f_flickrAPI_secret,false);
			$flickr->setToken(get_option('w2f_token'));
			/*if (defined("WP_PROXY_HOST")) {  
				w2f_log("Proxy on");
				$flickr->req->setProxy(WP_PROXY_HOST,WP_PROXY_PORT,WP_PROXY_USERNAME,WP_PROXY_PASSWORD);
			}*/
				
			if (get_option('w2f_flickr_URL')) { 
				$content=$permalink."<br />\n".$content; 
			}
			$content=w2f_polyglot_filter($content);
			$title=$post->post_title;
			$title=w2f_polyglot_filter($title);
			
			$flickr_id=$flickr->sync_upload($imageFilePath, 
							$title, 
							$content,
							$taglist, 
							get_option('w2f_flickr_is_public'),get_option('w2f_flickr_is_friend'),get_option('w2f_flickr_is_family')); 
							
			w2f_log($flickr_id);
			
			if ($flickr_id!="") {
							
				w2f_insert_recrod($post_id,$image_id,$flickr_id);
		
				$sets=explode(",",get_option('w2f_sets'));
				foreach ($sets as $set) {
					$flickr->photosets_addPhoto(trim($set), $flickr_id);
				}
				
				$groups_txt=get_option('w2f_groups'); // grupos por defecto
				
				$post_categories = wp_get_post_categories( $post_id );
				foreach($post_categories as $c){
					$cat = get_category( $c );
					$o='w2f_groups_by_'. $cat->category_nicename;
					$v=get_option($o);
								
					$groups_txt.=",".$v;
				}
				
				w2f_log($groups_txt);
				
				$groups=array_unique(explode(",",$groups_txt));
				foreach ($groups as $group) {
					w2f_log("Adding $flickr_id to: ". trim($group));
					$r=$flickr->groups_pools_add($flickr_id, trim($group));
					w2f_log($r);
					w2f_log("error code: ".$flickr->error_code);
					w2f_log("error message: ".$flickr->error_msg);			
				}
			} else {
				w2f_log("Error uploading $imageFilePath");
				w2f_log("error code: ".$flickr->error_code);
				w2f_log("error message: ".$flickr->error_msg);
			}
		}
	}
	return $post_id;	
}

function w2f_check_record($id){
	global $wpdb; 
	$sql = "SELECT * FROM ".w2f_table_name(). " WHERE post_id=$id limit 1";
	$sql=$wpdb->get_var($sql);
	return ($sql>0);
}

function w2f_insert_recrod($post_id,$yapb_id,$flickr_id){
	global $wpdb; 
	$sql = "INSERT INTO ". w2f_table_name(). " (post_id, yapb_id , flickr_photo_id) ".
				" VALUES (".$post_id. " , ".$yapb_id." , ". $flickr_id. 
				")";
	$wpdb->query($sql);	
}

/*
 *  Installation
 */


add_action('activate_wp2flickr/wp2flickr.php','w2f_install');
add_action('deactivate_wp2flickr/wp2flickr.php','w2f_uninstall');

function w2f_table_name() {
	global $wpdb;
	return $wpdb->prefix . "wp2flickr";
}

function w2f_table_post_name() {
	global $wpdb;
	return $wpdb->prefix . "posts";
}
 
function w2f_install() {
	global $wpdb;
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	$table_name = w2f_table_name();
	$table_name_check=$wpdb->get_var("SHOW TABLES LIKE '$table_name'");
	if($table_name_check != $table_name) {
		
		$sql=" CREATE TABLE ".$table_name."  (
					`post_id` bigint(20) NOT NULL,
  					`yapb_id` bigint(20) NOT NULL,
  					`flickr_photo_id` bigint(20) NOT NULL,
  					PRIMARY KEY  (`post_id`) 
			) ";
		dbDelta($sql);
	}
	
	$sql=" insert into ".$table_name."  ( post_id ) ".
				" select ID from ".w2f_table_post_name().
				" where post_status='publish' ".
				" and id not in ( select `post_id` from $table_name )";
	
	$wpdb->query($sql);	
	w2f_log($sql);	

	//default options
	add_option( 'w2f_flickr_is_public', 1 );
	add_option( 'w2f_flickr_is_friend', 0 );
	add_option( 'w2f_flickr_is_family', 0 );
	add_option( 'w2f_groups', '1281351@N21');
	add_option( 'w2f_flickr_URL', 1 );	
}
 
function w2f_uninstall() {
	global $wpdb;
	$table_name = w2f_table_name();
	//$wpdb->query("DROP TABLE ".$table_name);
	w2f_log_clean();
}

add_action('admin_menu', 'w2f_admin_menu');

function w2f_admin_menu(){
    if (function_exists('add_options_page')) {
        add_options_page('wp2flickr configuration', 'wp2flickr', 8, basename(__FILE__), 'w2f_options_page');
    }
}

function w2f_options_page (){
	echo '<div id="wpbody-content">';
	if ( $_GET['page'] == basename(__FILE__) ) {
		if('testUpload' == $_REQUEST['action'] ) { 
			w2f_testUpload();
		} elseif ('updateOptions' == $_REQUEST['action'] ) {
			w2f_updateOptions();
		} elseif ( !empty($_GET['setToken']) ) {
			w2f_setToken();
		} 
		//si no hay acci�n mostrar la pantalla de opciones
		$setTokenLink=w2f_selfURL();
		$_SESSION['setTokenLink'] = $setTokenLink;
		
		//1. getToken
		echo '<h2>1. Authorize wp2flickr</h2>';
		$getTokenLink="../wp-content/plugins/wp2flickr/getToken.php";
		echo "<form action=\"$getTokenLink\" method=\"post\">";
		echo "Authorize wp2flickr to write into your Flickr account ";
		echo '<input name="action" type="hidden" value="setToken" />';
		echo '<input name="save" type="submit" value="Go" tabindex="1" accesskey="T" />';  
		echo "</form>";
		
		//2. testUpload
		echo '<h2>2. Test upload</h2>';
		echo "<form action=".wp_specialchars( $_SERVER['REQUEST_URI'] )." method=\"post\">";
		echo "Upload a picture for testing (as private) ";
		echo '<input name="action" type="hidden" value="testUpload" />';
		echo '<input name="save" type="submit" value="Go" tabindex="1" accesskey="T" />';  
		echo "</form>";
		
		//3. Visibilidad
		echo '<h2>3. Options (for new posts)</h2>';
		echo "<form action=".wp_specialchars( $_SERVER['REQUEST_URI'] )." method=\"post\">";
		echo '<table>';
		echo '<tr valign="top"><td>';
		
		echo "Token";
		echo '</td><td>';
		$v=get_option('w2f_token');
		echo '<input type="text" name="form_w2f_token" size=50 value="'.$v.'"/><br />';
		echo 'This value should be set automatically in step 1 (flickr authorization) ';
		echo '</td></tr><tr valign="top"><td>';
				
		echo "Is public? ";
		echo '</td><td>';
		$v=get_option('w2f_flickr_is_public') ? 'checked' : '';
		echo '<input type="checkbox" name="form_w2f_flickr_is_public" '.$v.'/>';
		echo '</td></tr><tr valign="top"><td>';
		
		echo "Is friends? ";
		echo '</td><td>';
		$v=get_option('w2f_flickr_is_friend') ? 'checked' : '';
		echo '<input type="checkbox" name="form_w2f_flickr_is_friend" '.$v.'/>';
		echo '</td></tr><tr valign="top"><td>';
		
		echo "Is family? ";
		echo '</td><td>';
		$v=get_option('w2f_flickr_is_family') ? 'checked' : '';
		echo '<input type="checkbox" name="form_w2f_flickr_is_family"'.$v.'/>';
		echo '</td></tr><tr valign="top"><td>';
		
		//4. grupos
		echo "Groups to be add to ";
		echo '</td><td>';
		$v=get_option('w2f_groups');
		echo '<textarea  name="form_w2f_groups" type="text" class="text" rows="2" cols="50">';
		echo $v;
		echo '</textarea><br />';
		echo 'Comma separated. Only group id is valid. Sample: 1281351@N21, 35034354545@N01, 56189500@N00<br />';
		echo 'You can use <a href="http://idgettr.com/">idGettr</a> to get this IDs.';
		echo '</td></tr><tr valign="top"><td>';
		
		//5. sets
		
		echo "Sets to be add to ";
		echo '</td><td>';
		$v=get_option('w2f_sets');
		echo '<textarea  name="form_w2f_sets" type="text" class="text" rows="2" cols="50">';
		echo $v;
		echo '</textarea><br />';
		echo 'Comma separated. Only set id is valid. Sample: 72157622762652130, 72157622709336280, 72157621962594411';
		echo '</td></tr><tr valign="top"><td>';
		
		//6. URL
		echo "Add blog URL to flickr image? ";
		$v=get_option('w2f_flickr_URL') ? 'checked' : '';
		echo '</td><td>';
		echo '<input type="checkbox" name="form_w2f_flickr_URL" '.$v.'/>';
		
		echo '</td></tr>';
		
		echo '<tr><td></td></tr>';
		
		echo '<tr><td>';
		echo 'Groups to be add to depending on categories';
		echo '</td></tr>';
		$allcategories = get_categories('type=post&hide_empty=0');
		$i = 0;
		foreach ($allcategories as $cat) {
			$plugincategory = $cat->category_nicename;
			$o='w2f_groups_by_'. $cat->category_nicename;
			$v=get_option($o);
			echo '<tr>
				<td><INPUT TYPE=hidden NAME=plugincategory['. $i .'] VALUE="'. $plugincategory .'">'. $cat->cat_name .'</td>
				<td><INPUT TYPE=text NAME='. $o .' VALUE="'. $v .'" SIZE=60></td>
			</tr>';
			$i++;
		}
				
		echo '</table>';
		echo '<br />';
		echo '<input name="save" type="submit" value="Update options" tabindex="1" accesskey="T" />';  
		echo '<input name="action" type="hidden" value="updateOptions" />';
		echo "</form>";	
		
		echo '<br />';
		echo 'Join <a href="http://www.flickr.com/groups/1281351@N21/">wp2flickr users group</a> on Flickr and put 1281351@N21 into your group list!';
		

	}
	echo '</div>';
}

function w2f_updateOptions() {
	update_option( 'w2f_token', stripslashes( $_REQUEST['form_w2f_token']  ) );
	$v=($_REQUEST['form_w2f_flickr_is_public']=='on') ? 1 : 0;
	update_option( 'w2f_flickr_is_public', $v );
	$v=($_REQUEST['form_w2f_flickr_is_friend']=='on') ? 1 : 0;
	update_option( 'w2f_flickr_is_friend', $v );
	$v=($_REQUEST['form_w2f_flickr_is_family']=='on') ? 1 : 0;
	update_option( 'w2f_flickr_is_family', $v );
	update_option( 'w2f_sets', stripslashes( $_REQUEST['form_w2f_sets']  ) );
	update_option( 'w2f_groups', stripslashes( $_REQUEST['form_w2f_groups']  ) );
	$v=($_REQUEST['form_w2f_flickr_URL']=='on') ? 1 : 0;
	update_option( 'w2f_flickr_URL', $v );
	
	$allcategories = get_categories('type=post&hide_empty=0');
	foreach ($allcategories as $cat) {
		$o='w2f_groups_by_'. $cat->category_nicename;
		$v=stripslashes($_REQUEST[$o]);
		$tmp=get_option($o);
		delete_option($o);
		add_option($o, $v );
	}
			
}

function w2f_url_image($content){
    $img      = "";
    $pattern1 = '/img([^>]*)src="([^"]*)"/i';
    preg_match($pattern1, $content, $match1);
    foreach ($match1 as $i) {
        $img = $i;
    }
    $url      = "";
    $pattern2 = '#http://.+?\.(png|jpe?g)#i';
    preg_match($pattern2, $img, $match2);
    foreach ($match2 as $i) {
        if (strncmp("http://", $i, 7) == 0 && strlen($i) > 7) {
            $url = $i;
        }
    }
    return $url;
}

function w2f_string_intersect($str1,$str2) {
	$a1=explode("/",$str1);
	$a2=explode("/",$str2);
	$result = array_intersect($a1, $a2);
	$intersect=implode("/",$result);  
	return $intersect;
}

function w2f_log($message) {
	$error_log_file=dirname(__FILE__).'/log.txt';
	$fh=fopen($error_log_file , 'a');
	fwrite($fh, date("Y-m-d H:i:s") . " - " .$message . "\n");
	fclose($fh);
}

function w2f_log_clean() {
	$error_log_file=dirname(__FILE__).'/log.txt';
	unlink($error_log_file);
}

function w2f_log_check() {
	$error_log_file=dirname(__FILE__).'/log.txt';
	if (file_exists($error_log_file)) {
		$k=filesize($error_log_file);
		if ($k>50000) w2f_log_clean();
	}

}

?>