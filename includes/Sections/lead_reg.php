<?php
ob_start(); // Output Buffering Start
session_start();
	
//conect
include '../../connect.php';
include '../../Sessions.php';




if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (isset($_POST['lead_reg']))
	{

		$lead_name = $_POST['lead_name'];
		$country_code = $_POST['country_code'];
		$phone = $_POST['phone'];
		$this_url = $_POST['this_url'];

		$mysqli->query("INSERT INTO leads  
						(     
						leads_name,leads_c_code,leads_phone,leads_location
						) 
						VALUES
						(
						'$lead_name','$country_code','$phone','$this_url'
						)") or die($mysqli->error);
		$_SESSION['MSG_success']="تم تسجيل بياناتك بنجاح، وسيتم التواصل معك خلال 48 ساعة";
        header('Location:'.$this_url);   exit;         

	}

}







ob_end_flush();

?>