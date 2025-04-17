<?php 



$pageTitle = 'Login';

if (isset($_SESSION['users_id']) || isset($_COOKIE["users_id"]) ) {
	header('Location: cpanel.php');
}

// Check If User Coming From HTTP Post Request
if (isset($_POST['login']))
{

	$correct=0;
	$users_id = filter_var($_POST['user_id'], FILTER_SANITIZE_STRING);
	$users_id = trim($users_id, " ");

	$mypassword = $_POST['password'];  


	$login_user_email=$users_id;
	$result = $mysqli->query("SELECT * FROM users where users_email='$login_user_email' and users_password='$mypassword'") or die($$mysqli->error);
	while ($row = $result->fetch_assoc()){ 
		if ($result->num_rows > 0) {
			$_SESSION['login']=true;
			$_SESSION['users_name']=$row['users_name'];
			$_SESSION['users_email']=$row['users_email'];
			$_SESSION['users_ccode']=$row['users_ccode'].$row['users_phone'];
			$_SESSION['users_id']=$row['users_id'];
			$correct=1;
			header("location: cpanel.php");
			exit;
		}
	} 

	if($correct==0){
		$login_user_id=$users_id;
		$result = $mysqli->query("SELECT * FROM users where users_id='$login_user_id' and users_password='$mypassword'") or die($$mysqli->error);
		while ($row = $result->fetch_assoc()){ 
			if ($result->num_rows > 0) {
				$_SESSION['login']=true;
				$_SESSION['users_name']=$row['users_name'];
				$_SESSION['users_email']=$row['users_email'];
				$_SESSION['users_ccode']=$row['users_ccode'].$row['users_phone'];
				$_SESSION['users_id']=$row['users_id'];
				$correct=1;
				header("location: cpanel.php");
				exit;
			}
		}

	}

	if($correct==0){ 
		$_SESSION['MSG_error']="خطأ في دخول البريد الإلكتروني أو كلمة المرور!"; 
		header("location: cpanel.php?p=Login");
		exit;
	}

}

?> 





<section class="bg-gray-50 dark:bg-gray-900">
	<div class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">

		<div class="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
			<div class="p-6 space-y-4 md:space-y-6 sm:p-8">
				<h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
					دخول الحساب
				</h1>
				<form class="space-y-4 md:space-y-6" action="cpanel.php?p=Login" method="POST">
					<div>
						<label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">البريد الإلكتروني أو كود المستخدم</label>
						<input type="text" name="user_id" id="email" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="name@company.com" required="">
					</div>
					<div>
						<label for="password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">كلمة المرور</label>
						<input type="password" name="password" id="password" placeholder="••••••••" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required="">
					</div>
					<input type="submit" class="cursor-pointer inline-flex items-center justify-center px-6 py-4 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-[#22e203] hover:text-white focus:bg-[#f1f1f1]" value="دخول" name="login">

				</form>
			</div>
		</div>
	</div>
</section>
