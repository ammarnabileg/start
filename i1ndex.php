<?php




// header('Location: https://join-vital.com/go.php?id=34'); 
header( "refresh:10;url=https://join-vital.com/go.php?id=34" );


?>


<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 

/* 
include 'includes/Pages/home.php'; 
include './includes/templates/footer.php';
*/

?>




<div class="text-[#f1f1f1] bg-black mt-[-100px]">










	<div class="bg-[#000] ">
		<div class="bg-[#000] bg-opacity-[1%] py-10 sm:pt-20 sm:pb-18 lg:pt-[230px] lg:pb-[200px]">
			<div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
				<div class=" grid text-center items-center grid-cols-1 gap-12 lg:grid-cols-2">


					<div>
						<img class="w-auto max-w-[70%] mx-auto " src="assets/img/start-logo.png" alt="" />
					</div>


					<div>
						<p class="text-base tracking-wider text-[#f1d293] uppercase">إبدأ معنا حياة يملؤها التقدم</p>
						<h1 class="mt-4 text-4xl font-semibold text-white lg:mt-8 lg:leading-[1.3] sm:text-6xl xl:text-8xl leading-10">رواد الإستثمار والتطوير العقاري</h1>
						<p class="mt-4 text-base text-white lg:mt-8 sm:text-xl">نمي استثماراتك بسرعه مع قادة الصناعة..</p>
					</div>





				</div>
			</div>
		</div>
	</div>






















	<section class="py-10 bg-black sm:pt-16 lg:pt-24">
		<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">

						<p class="mt-4 text-base text-white lg:mt-8 sm:text-xl text-center">سيتم تحويلك تلقائيا لفريق المبيعات خلال 10 ثواني</p>

			<hr class="mt-16 mb-10 border-gray-200" />

			<p class="text-sm text-center text-gray-600">جميع الحقوق محفوظة © 2024, ستارت للتطوير العقاري</p>
			<p class="text-sm text-center text-gray-600">Developed By Viral Agency</p>
		</div>
	</section>

















</div>



<?php
ob_end_flush();
?>