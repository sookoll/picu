<?php
    /* Last updated with phpFlickr 1.4
     *
     * If you need your app to always login with the same user (to see your private
     * photos or photosets, for example), you can use this file to login and get a
     * token assigned so that you can hard code the token to be used.  To use this
     * use the phpFlickr::setToken() function whenever you create an instance of 
     * the class.
     *
     * @sookoll (2016-04-05): Point this file as auth callback, fixed redirect loop,
     *                        Copy token from var_dump output
     */

    session_start();

    require_once("phpFlickr.php");
    $f = new phpFlickr("<api_key>", "<secret>");

    if(empty($_GET['frob'])) {
        //change this to the permissions you will need
        $f->auth("write");
    } else {
        $t = $f->auth_getToken($_GET['frob']);
        var_dump($t);
    }
