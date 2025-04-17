<?php
error_reporting(E_ALL ^ E_NOTICE);
ob_start(); // Output Buffering Start
session_start();


if(isset($_SESSION['users_id'])){

	$users_id=$_SESSION['users_id'];

	$result = $mysqli->query("SELECT * FROM users where users_id  = '$users_id' ") or die($$mysqli->error);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$users_name = $row["users_name"];
			$users_access = $row["users_access"];

		}
	}																					

	$user_roles = explode(',', $users_access); 

	$result = $mysqli->query("SELECT roles_id, roles_name, roles_permissions FROM roles") or die($mysqli->error);
	$roles_permissions = [];
	while ($row = $result->fetch_assoc()) {
		$roles_permissions[$row['roles_id']] = [
			'name' => $row['roles_name'],
			'permissions' => explode(',', $row['roles_permissions'])
		];
	}

	$unique_permissions = [];

	foreach ($user_roles as $role_id) {
		$role_id = trim($role_id); 
		if (isset($roles_permissions[$role_id])) {
			$unique_permissions = array_merge($unique_permissions, $roles_permissions[$role_id]['permissions']);
		} 
	}

	$unique_permissions = array_unique($unique_permissions);








}
?>