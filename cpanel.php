<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 




?>


<script>

	<!-- tailwind.config.js -->
	module.exports = {
		plugins: [require('@tailwindcss/forms'),]
	};
</script>



<!-- component -->
<div class="pt-5">
	<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>

	<div x-data="{ sidebarOpen: false }" class="flex bg-gray-100">
		<div :class="sidebarOpen ? 'block' : 'hidden'" @click="sidebarOpen = false" class="fixed inset-0 z-20 transition-opacity bg-black opacity-50 lg:hidden"></div>




		<div :class="sidebarOpen ? 'translate-x-0 ease-out' : '-translate-x-full ease-in'" class="fixed inset-y-0 left-0 z-30 w-64 overflow-y-auto transition duration-300 transform bg-gray-900 lg:translate-x-0 lg:static lg:inset-0 ">
			<div class="flex items-center justify-center mt-8">
				<div class="flex items-center">
					<span class="mx-2 text-2xl font-semibold text-white">لوحة التحكم</span>
				</div>
			</div>

			<?php 
			if ($_SESSION['login'] == true ) {
			?>

			<nav class="mt-10">


				<a class="flex items-center px-6 py-2 mt-4 text-gray-100 bg-gray-700 bg-opacity-25" href="cpanel.php">
					<i class="fa-solid fa-house text-xl"></i>
					<span class="mx-3">الرئيسية</span>
				</a>


				<hr class="my-2 border-[#6b7280]">


				<?php
				if (in_array(1, $unique_permissions) || in_array(2, $unique_permissions) || in_array(3, $unique_permissions)) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=ViewProjects">
					<i class="fa-solid fa-city text-xl"></i>
					<span class="mx-3">المشروعات</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(4, $unique_permissions)) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=NewProject">
					<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						 stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							 d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z">
						</path>
					</svg>
					<span class="mx-3">إضافة مشروع جديد</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(5, $unique_permissions)||in_array(6, $unique_permissions)||in_array(7, $unique_permissions)) {
				?>

				<hr class="my-2 border-[#6b7280]">


				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=Blog">
					<i class="fa-regular fa-newspaper text-xl"></i>
					<span class="mx-3">المقالات</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(8, $unique_permissions)) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=NewArticle">
					<i class="fa-regular fa-pen-to-square text-xl"></i>
					<span class="mx-3">إضافة مقالة جديدة</span>
				</a>


				<hr class="my-2 border-[#6b7280]">
				<?php
				}
				?>


				<?php
				if (in_array(9, $unique_permissions) || in_array(10, $unique_permissions)) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=ViewLeads&type=active&page=1">
					<i class="fa-regular fa-user text-xl"></i>
					<span class="mx-3">العملاء الجدد</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(11, $unique_permissions)) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=Contactus&page=1">
					<i class="fa-solid fa-headset text-xl"></i>
					<span class="mx-3">نموذج التواصل</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(12, $unique_permissions)||in_array(13, $unique_permissions)||in_array(14, $unique_permissions)) {
				?>
				<hr class="my-2 border-[#6b7280]">

				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=ViewStaff&dir=users&page=1">
					<i class="fa-solid fa-user-tie text-xl"></i>
					<span class="mx-3">الموظفين</span>
				</a>
				<?php
				}
				?>


				<?php
				if (in_array(16, $unique_permissions) || in_array(17, $unique_permissions) ||in_array(18, $unique_permissions) || in_array(19, $unique_permissions) ) {
				?>
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=StaffAccess&dir=Roles&page=1">
					<i class="fa-solid fa-user-gear text-xl"></i>
					<span class="mx-3">صلاحيات الموظفين</span>
				</a>
				<?php
				}
				?>

				<?php
				if (in_array(20, $unique_permissions) || in_array(21, $unique_permissions) ||in_array(22, $unique_permissions) || in_array(23, $unique_permissions) ) {
				?>
				<hr class="my-2 border-[#6b7280]">

				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=ViewEvents">
					<i class="fa-regular fa-calendar-days text-xl"></i>
					<span class="mx-3">إدارة الأحداث</span>
				</a>
				<?php
				}
				?>
				
				<?php
				if (in_array(24, $unique_permissions) && !in_array(20, $unique_permissions)) {
				?>
				<hr class="my-2 border-[#6b7280]">

				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=NewEventInvitation">
					<i class="fa-regular fa-calendar-days text-xl"></i>
					<span class="mx-3">إنشاء دعوة</span>
				</a>
				<?php
				}
				?>		
				
				<hr class="my-2 border-[#6b7280]">
				<a class="flex items-center px-6 py-2 mt-4 text-gray-500 hover:bg-gray-700 hover:bg-opacity-25 hover:text-gray-100"
				 href="cpanel.php?p=Logout">
					<i class="fa-solid fa-arrow-right-from-bracket text-xl"></i>
					<span class="mx-3">تسجيل خروج</span>
				</a>

			</nav>


			<?php
			}
			?>
		</div>
		<div class="flex flex-col flex-1 overflow-hidden">
			<header class="flex items-center justify-between px-6 py-4 bg-white border-b-4 border-black lg:hidden block">
				<div class="flex items-center">
					<button @click="sidebarOpen = true" class="flex gap-3 text-gray-500 focus:outline-none lg:hidden">
						<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 6H20M4 12H20M4 18H11" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								 stroke-linejoin="round"></path>
						</svg> قائمة لوحة التحكم

					</button>

				</div>


			</header>























			<?php



			$tap=$_GET['p'];

			if($tap=='ViewProjects'){
				include 'authentication.php'; 
				include 'cpanel/ViewProjects.php'; 

			}
			elseif($tap=='NewProject'){
				include 'authentication.php'; 
				include 'cpanel/NewProject.php'; 
			}
			elseif($tap=='Blog'){
				include 'authentication.php'; 
				include 'cpanel/blog.php'; 
			}
			elseif($tap=='NewArticle'){
				include 'authentication.php'; 
				include 'cpanel/NewArticle.php'; 
			}
			elseif($tap=='ViewLeads'){
				include 'authentication.php'; 
				include 'cpanel/ViewLeads.php'; 
			}
			elseif($tap=='Contactus'){
				include 'authentication.php'; 
				include 'cpanel/Contactus.php'; 
			}
			elseif($tap=='UpdateProject'){
				include 'authentication.php'; 
				include 'cpanel/UpdateProject.php'; 
			}
			elseif($tap=='UpdateBlogpost'){
				include 'authentication.php'; 
				include 'cpanel/UpdateBlogpost.php'; 
			}
			elseif($tap=='ViewStaff'){
				include 'authentication.php'; 
				include 'cpanel/ViewStaff.php'; 
			}
			elseif($tap=='StaffAccess'){
				include 'authentication.php'; 
				include 'cpanel/StaffAccess.php'; 
			}
			elseif($tap=='NewEvent'){
				include 'authentication.php'; 
				include 'cpanel/NewEvent.php'; 
			}
			elseif($tap=='ViewEvents'){
				include 'authentication.php'; 
				include 'cpanel/ViewEvents.php'; 
			}
			elseif($tap=='NewEventInvitation'){
				include 'authentication.php'; 
				include 'cpanel/NewEventInvitation.php'; 
			}
			elseif($tap=='Login'){
				include 'cpanel/Login.php'; 
			}
			elseif($tap=='Logout'){
				include 'cpanel/Logout.php'; 
			}
			else{
				include 'authentication.php'; 
				include 'cpanel/main.php'; 
			}



			?>			













		</div>
	</div>
</div>



<?php
include 'includes/templates/footer.php'; 

ob_end_flush();
?>