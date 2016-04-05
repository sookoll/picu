<?php
    /* Last updated with phpFlickr 1.4
     *
     * If you need your app to always login with the same user (to see your private
     * photos or photosets, for example), you can use this file to login and get a
     * token assigned so that you can hard code the token to be used.  To use this
     * use the phpFlickr::setToken() function whenever you create an instance of 
     * the class.
     */

	session_start();
	
    require_once("phpFlickr.php");
    $f = new phpFlickr("6672672ae17dbebc0d3c02215cec9f95", "dd130d4f40526553");
    
    if(empty($_GET['frob'])) {
		//change this to the permissions you will need
		$f->auth("write");
	} else {
		$t = $f->auth_getToken($_GET['frob']);
		var_dump($t);
		//echo "Copy this token into your code: " . $_SESSION['phpFlickr_auth_token'];
	}
    
    
    
?>