<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();


date_default_timezone_set('Africa/Cairo');
$timenow=date("Y-m-d H:i:sa");

include"connect.php";





if(isset($_GET['t'])){
	// $task = $_GET['t'];
	$task_unsecured= $_GET['t'];
	$task_secured = filter_var($task_unsecured, FILTER_SANITIZE_STRING);
	$task = $task_secured;
}


/////~~~~~~~~~~~~~~~~~~~~~~~~*********[DeleteProject]~~~~[Start]*********~~~~~~~~~~~~~~~~~~~~~~~~/

if($task == "DeleteEvent"){

	$DeleteEvent_id = $_GET['id'];

	$mysqli->query("UPDATE events SET events_activity = 0 
 WHERE events_id = $DeleteEvent_id") or die($mysqli->error);
			$_SESSION['MSG_success']= "تم حذف الحدث بنجاح.";

	header('Location: '.$_SERVER['HTTP_REFERER']); 
	exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~[DeleteProject]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////


/////~~~~~~~~~~~~~~~~~~~~~~~~*********[DeleteProject]~~~~[Start]*********~~~~~~~~~~~~~~~~~~~~~~~~/

if($task == "DeleteProject"){

	$DeleteProject_id = $_GET['id'];

	$mysqli->query("DELETE FROM projects
 WHERE projects_id = $DeleteProject_id") or die($mysqli->error);

	header('Location: '.$_SERVER['HTTP_REFERER']); 
	exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~[DeleteProject]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////


/////~~~~~~~~~~~~~~~~~~~~~~~~*********[Deleteuser]~~~~[Start]*********~~~~~~~~~~~~~~~~~~~~~~~~/

if($task == "Deleteuser"){

	$DeleteUser_id = $_GET['id'];

	$mysqli->query("DELETE FROM users
 WHERE users_id = $DeleteUser_id") or die($mysqli->error);

	header('Location: '.$_SERVER['HTTP_REFERER']); 
	exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~[Deleteuser]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////


/////~~~~~~~~~~~~~~~~~~~~~~~~*********[DeleteBlog]~~~~[Start]*********~~~~~~~~~~~~~~~~~~~~~~~~/

if($task == "DeleteBlog"){

	$DeleteBlog = $_GET['id'];

	$mysqli->query("DELETE FROM blog_posts
 WHERE blog_posts_id = $DeleteBlog") or die($mysqli->error);

	header('Location: '.$_SERVER['HTTP_REFERER']); 
	exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~[DeleteBlog]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

/////~~~~~~~~~~~~~~~~~~~~~~~~*********[Deleterole]~~~~[Start]*********~~~~~~~~~~~~~~~~~~~~~~~~/

if($task == "Deleterole"){

	$id = $_GET['id'];

	$mysqli->query("DELETE FROM roles
 WHERE roles_id = $id") or die($mysqli->error);

	header('Location: '.$_SERVER['HTTP_REFERER']); 
	exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~[Deleterole]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////


if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "No Access!"; header('Location: '.$_SERVER['HTTP_REFERER']); exit; }

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

if (isset($_POST['addnewuser']))
{
	$name = $_POST['name'];
	$email = $_POST['email'];
	$users_ccode = $_POST['users_ccode'];
	$users_phone = $_POST['users_phone'];
	$rand_password = $_POST['rand_password'];
	$roles = $_POST['roles'];
 $rolesStr = implode(",", $roles); 

	$error_1=0;
	$result = $mysqli->query("SELECT * FROM users where users_email = '$email' ") or die($$mysqli->error);
	if ($result->num_rows > 0) {
		$error_1=1; 

	}else{


		$mysqli->query("INSERT INTO users 
 ( 
 users_name,users_email,users_ccode,users_phone,users_password,users_access
 ) 
 VALUES
 (
 '$name','$email','$users_ccode','$users_phone','$rand_password','$rolesStr'
 )") or die($mysqli->error);
		$_SESSION['MSG_success']='تمت إضافة الحساب بنجاح.<br> <a href="cpanel.php?p=ViewStaff&dir=users&page=1">عرض جميع المستخدمين</a>' ;
	}


	if($error_1==1){
		$_SESSION['MSG_error']='البريد الإلكتروني مستخدم من قبل!!';
	}
	header('Location: cpanel.php?p=ViewStaff&dir=new_user'); exit;

}
////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[updateuser]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

if (isset($_POST['updateuser']))
{
	$name = $_POST['name'];
	$email = $_POST['email'];
	$users_ccode = $_POST['country_code'];
	$users_phone = $_POST['users_phone'];
	$rand_password = $_POST['rand_password'];
	$roles = $_POST['roles'];
 $rolesStr = implode(",", $roles); 
	

	$result = $mysqli->query("SELECT * FROM users where users_email = '$email' ") or die($$mysqli->error);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$userss_id = $row["users_id"];
			
		}
		
		
		$mysqli->query("UPDATE users SET 
 users_name = '$name',					 
 users_email = '$email',					 
 users_ccode = '$users_ccode',					 
 users_phone = '$users_phone',					 
 users_password = '$rand_password',
 users_access = '$rolesStr'
 WHERE users_email = '$email' ") or die($mysqli->error);

		$_SESSION['MSG_success']='تم تعديل بيانات الحساب بنجاح' ;

	}


	header('Location: cpanel.php?p=ViewStaff&dir=edit_user&id='.$userss_id); exit;

}
////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[updateuser]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////



////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[contactus]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

if (isset($_POST['contactus']))
{
 $name = htmlspecialchars(trim($_POST['name']));
 $country_code = htmlspecialchars(trim($_POST['country_code']));
 $phone = htmlspecialchars(trim($_POST['phone']));
 $email = htmlspecialchars(trim($_POST['email']));
 $job = htmlspecialchars(trim($_POST['job']));
 $msg = htmlspecialchars(trim($_POST['msg']));

 if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
 $_SESSION['MSG_error'] = 'بريد إلكتروني غير صالح';
 header('Location: contact.php');
 exit;
 }
	

	
	$mysqli->query("INSERT INTO contact 
 ( 
 contact_name,contact_ccode,contact_phone,contact_email,contact_job,contact_msg
 ) 
 VALUES
 (
 '$name','$country_code','$phone','$email','$job','$msg'
 )") or die($mysqli->error);
		$_SESSION['MSG_success']='تم إرسال رسالتك بنجاح';

	header('Location: contact.php'); exit;

}
////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[updateuser]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////




////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[addnewrole]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////
if (isset($_POST['addnewrole']))
{
	$rolename = $_POST['rolename'];
	$permissions = $_POST['permissions'];
 $permissionsStr = implode(",", $permissions); // This creates a comma-separated list


	
	$mysqli->query("INSERT INTO roles 
 ( 
 roles_name,roles_permissions
 ) 
 VALUES
 (
 '$rolename','$permissionsStr'
 )") or die($mysqli->error);
		$_SESSION['MSG_success']='تم إضافة دور '.$rolename.' بنجاح';

	header('Location: cpanel.php?p=StaffAccess&dir=NewRole'); exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[addnewrole]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[updaterole]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////
if (isset($_POST['updaterole']))
{
	$roleid = $_POST['roleid'];
	$rolename = $_POST['rolename'];
	$permissions = $_POST['permissions'];
 $permissionsStr = implode(",", $permissions); // This creates a comma-separated list


	$mysqli->query("UPDATE roles SET 
 roles_name = '$rolename',					 
 roles_permissions = '$permissionsStr'	
 WHERE roles_id = $roleid ") or die($mysqli->error);

	$_SESSION['MSG_success']='تم تحديث دور '.$rolename.' بنجاح';

	header('Location: cpanel.php?p=StaffAccess&dir=edit_role&id='.$roleid); exit;
} 

////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[updaterole]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////



////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[]~~~~[Start]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////
if (isset($_POST['aaaaaaaaaaaaaaa']))
{
	$f_title = $_POST['f_title'];

	$result = $mysqli->query("SELECT * FROM branches where branch_id = '$mybranch' and branch_academyid = $myacademyid ") or die($mysqli->error);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$mybranch_name = $row["branch_name"];
		}
	}

	$mysqli->query("UPDATE groups SET 
 group_lec_num = '$group_lec_num',					 
 group_type = '$group_type'	
 WHERE group_id = $groupcode and group_academyid = $myacademyid") or die($mysqli->error);

	$mysqli->query("INSERT INTO plans 
 ( 
 plans_title,plans_group,plans_date
 ) 
 VALUES
 (
 '$f_title','$f_group','$timenow'
 )") or die($mysqli->error);

	header('Location: plans.php'); exit;
} 
////~/~/~/~/~/~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~[]~~~~[END]~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~/~/~/~/~/~////









?>