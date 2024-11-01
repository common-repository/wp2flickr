<?php
	session_start();
    require_once("phpFlickr-3.1/phpFlickr.php");
    require_once("config.php");
    

    $f = new w2f_phpFlickr(w2f_flickrAPI,w2f_flickrAPI_secret,false);
    
    $callbak2wp=$_SESSION['setTokenLink'];
    $_SESSION['phpFlickr_auth_redirect'] = $callbak2wp;
    
	$f->auth("write",$callbak2wp);
?>