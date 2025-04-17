<?php   
error_reporting(E_ALL ^ E_NOTICE);

session_start(); //to ensure you are using same session


session_destroy(); //destroy the session
header("location:cpanel.php?p=Login"); //to redirect back to "index.php" after logging out
exit();
?>