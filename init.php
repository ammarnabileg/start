<?php 
session_start();

//conect
include 'connect.php';
include 'sessions.php';

//Routes
$tpl = 'includes/templates/'; //template Directory
$func= 'includes/functions/'; //Directory function
$css = 'layout/css/'; //css directory
$js = 'layout/js/'; // js Directory
$lang= 'includes/languages/'; //language Directory

//include the important Files
include $func.'functions.php';
include $lang.'english.php';

include $tpl.'header.php';

//include Navbar on all Pages Expect the one with $noNavbar Variables
if(!(isset($noNavbar))) {
	// include $tpl . "navbar.php";
}

