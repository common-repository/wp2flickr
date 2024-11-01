<?php
	require_once('config.php');
	require_once('phpFlickr-3.1/phpFlickr.php');
	require_once('functions.php');

	//http://dev.kanngard.net/Permalinks/ID_20050507183447.html  	
	function w2f_selfURL() { 
		$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : ""; 
		$protocol = w2f_strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s; 
		$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]); 
		return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI']; 
	} 
	
	function w2f_strleft($s1, $s2) { 
		return substr($s1, 0, strpos($s1, $s2)); 
	}

	function w2f_setToken(){
		if (!empty($_GET['token'])) {
			delete_option('w2f_token');
			delete_option('w2f_user');
			add_option('w2f_token',$_GET['token']);	
			add_option('w2f_user',$_GET['user']);
			echo 'Token updated<br /><br />';
		} else {
			echo 'Error updating token';
		}
	}
	
	function w2f_testUpload(){
		$flickr=new  w2f_phpFlickr(w2f_flickrAPI,w2f_flickrAPI_secret,false);
		$flickr->setToken(get_option('w2f_token'));
		$flickr_id=$flickr->sync_upload(dirname(__FILE__)."/DSC_1518.jpg", "test", "Hello, goodbye", "tags", 0,0,0 ); 
	}
?>