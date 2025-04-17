<?php
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set('Africa/Cairo');

if(!isset($pageTitle)){
	$pageTitle='';
}

?>

<!DOCTYPE html>
<html  x-data="data()" lang="en" class='scroll-smooth' style="max-width: -webkit-fill-available;" dir="rtl">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="google" content="notranslate"/>

		<title>Start Developments <?php if($pageTitle!="Main"){echo "| " . $pageTitle; } ?></title>
		<meta name="description" content="شركة ستارت للتطوير العقاري الرائدة والواعدة في السوق العقاري المصري، حيث تتميز رؤيتها المستقبلية واستراتيجياتها المبتكرة التي تضع العميل في قلب كل ما تقوم به."/>

		<link rel="shortcut icon" href="../assets/img/favicon.ico">
		<link href="styles.css?" rel="stylesheet"  />
		<link href="../../styles.css?" rel="stylesheet"  />
		<script src="./layout/js/tw-cdn1.js"></script>
		<script src="./layout/js/tw-cdn2.js"></script>


<meta name="description" content="شركة ستارت للتطوير العقاري الرائدة والواعدة في السوق العقاري المصري، حيث تتميز رؤيتها المستقبلية واستراتيجياتها المبتكرة التي تضع العميل في قلب كل ما تقوم به.<?php if($pageTitle!="Main"){echo "| " . $pageTitle; } ?>">
<meta property="og:title" content="Start Developments <?php if($pageTitle!="Main"){echo "| " . $pageTitle; } ?>">
<meta property="og:description" content="شركة ستارت للتطوير العقاري الرائدة والواعدة في السوق العقاري المصري، حيث تتميز رؤيتها المستقبلية واستراتيجياتها المبتكرة التي تضع العميل في قلب كل ما تقوم به.<?php if($pageTitle!="Main"){echo "| " . $pageTitle; } ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Start Developments">
<meta property="og:image" content="https://start.com.eg/assets/img/sales.png">
  <meta name="robots" content="noindex, nofollow">
<meta name="bingbot" content="nocache">

		
		
		<link rel="icon" type="image/ico" href="https://start.com.eg/assets/img/icon.png">
		<link rel="apple-touch-icon-precomposed" sizes="144x144" href="https://start.com.eg/assets/img/start-logo.png">
		<link rel="apple-touch-icon-precomposed" sizes="114x114" href="https://start.com.eg/assets/img/start-logo.png">
		<link rel="apple-touch-icon-precomposed" sizes="72x72" href="https://start.com.eg/assets/img/start-logo.png">
		<link rel="apple-touch-icon-precomposed" href="https://start.com.eg/assets/img/start-logo.png">


		<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.6.5/flowbite.min.css" rel="stylesheet" />

		<link rel="stylesheet" href ='./layout/css/fontawesome.min.css'/> 
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"/>

		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@100..900&display=swap" rel="stylesheet">
		<style>
			.alexandria {
				font-family: "Alexandria", sans-serif;
				font-optical-sizing: auto;
				font-weight: <weight>;
				font-style: normal;
			}
		</style>
		<link rel="stylesheet" href ='./layout/css/style.css'/>
		<link rel="stylesheet" href ='./layout/css/App.css'/>
		<link rel="stylesheet" href ='./layout/css/main.css?'/>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

		<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer ></script>
		<script src="./assets/js/init-alpine.js"></script>

		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<link type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/south-street/jquery-ui.css" rel="stylesheet">
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
		<script>
			tailwind.config = {
				darkMode: 'class',
				theme: {
					extend: {
						colors: {
							clifford: '#da373d',
						}
					}
				}
			}
		</script>







		<?php      
		function full_path()
		{
			$s = &$_SERVER;
			$ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
			$sp = strtolower($s['SERVER_PROTOCOL']);
			$protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
			$port = $s['SERVER_PORT'];
			$port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
			$host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
			$host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
			$uri = $protocol . '://' . $host . $s['REQUEST_URI'];
			$segments = explode('?', $uri, 2);
			$url = $segments[0];
			return $url;
		}
		$this_url=full_path();
		$_SESSION["this_url"]=$this_url;
		$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

		if(isset($_GET['d'])){

			if ($_GET['d']==1){
				$_SESSION["isDark"]='dark';
			}else{
				$_SESSION["isDark"]='';
			}

		}

		?>

		<link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
		<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

	</head>



	<body class='text-[#f1f1f1] bg-black alexandria dark:text-gray-300 <?php if (isset($_SESSION['users_id']) || isset($_COOKIE['users_id']) ) { 	if($_SESSION['users_mood']==0) echo 'dark'; }else{ echo $_SESSION["isDark"]; } ?> ' >















		<div>
			<div class="rounded-full bottom-[20px] left-[20px]  md:bottom-[30px] md:left-[30px]" style="opacity: 1;transition: opacity 0.5s ease 0s;box-sizing: border-box;text-align: right;position: fixed !important;z-index: 69 !important;">
				<style>
					@keyframes tilt-shaking {
						0% { transform: rotate(0deg); }
						25% { transform: rotate(5deg); }
						50% { transform: rotate(0eg); }
						75% { transform: rotate(-5deg); }
						100% { transform: rotate(0deg); }
					}
				</style>
				<a href="https://wa.me/+201552521511?text=قمت بزيارة موقعكم ولدي بعض الأسئلة تحتاج إلى الإجابة">
					<img style="animation: tilt-shaking 1.2s linear infinite;" class="max-w-[60px] md:max-w-[74px]"  src="assets/img/start-help.png">
				</a>
			</div>
			<div class="rounded-full " style="bottom: 35px;right: 35px;opacity: 1;transition: opacity 0.5s ease 0s;box-sizing: border-box;text-align: right;position: fixed !important;z-index: 69 !important;">
				<a href="#">
					<div class="p-4 rounded-full hover:bg-[#9d9d9d78]  ease-in-out">
						<i class="fa-solid fa-angles-up dark:text-gray-200"></i>
					</div>
				</a>
			</div>
		</div>


		<div class="min-h-[80px] bg-[#000]"></div>








		<?php
		//Errors, Success Messages
		if(isset($_SESSION['MSG_success'])){    ?>
		<div class="max-w-lg mx-auto mt-10"><div class="mb-5 flex items-center justify-center"><span class="flex items-center w-full py-2 px-3 text-sm font-medium text-white bg-[#6effa05c] rounded-lg dark:text-white"><i class="fa-solid fa-check mx-2"></i> <?php echo $_SESSION['MSG_success']; ?> </span></div></div>

		<?php   }if(isset($_SESSION['MSG_error'])){    ?>
		<div class="max-w-lg mx-auto mt-10"><div class="mb-5 flex items-center justify-center"><span class="flex items-center w-full py-2 px-3 text-sm font-medium text-white bg-[#ff6e6e5c] rounded-lg dark:text-white"><i class="fa-solid fa-xmark mx-2"></i> <?php echo $_SESSION['MSG_error']; ?> </span></div></div>
		<?php   } 

		unset($_SESSION['MSG_success']);      
		unset($_SESSION['MSG_error']);      
		?>









		<header class="z-[100] left-0 top-0 z-9999 w-full bg-[#000] transition-all duration-300 ease-in-out fixed shadow border-b border-[#242424] ">
			<div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
				<!-- lg+ -->
				<nav class="flex items-center justify-between h-20">
					<div class="flex-shrink-0">
						<a href="/" title="" class="flex">
							<img class="w-auto h-16 " src="assets/img/start-logo.png" alt="" />
						</a>
					</div>

					<button type="button" onclick="NavView()" class="inline-flex p-2 text-[#fff] transition-all duration-200 rounded-md lg:hidden focus:bg-gray-100 hover:bg-gray-100 hover:text-black focus:text-black">
						<!-- Menu open: "hidden", Menu closed: "block" -->
						<svg class="block w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
						</svg>

						<!-- Menu open: "block", Menu closed: "hidden" -->
						<svg class="hidden w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>

					<div  class="hidden lg:flex lg:items-center ltr:lg:ml-auto rtl:lg:mr-auto  lg:gap-10">
						<a href="/" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600">الرئيسية </a>
						<a href="about.php" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600">من نحن </a>
						<a href="services.php" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600">خدماتنا </a>
						<a href="FAQ.php" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600"> الأسئلة الشائعة </a>
						<a href="projects.php?page=1" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600"> مشروعاتنا </a>
						<a href="blog.php?page=1" title="" class="text-base font-medium text-white transition-all duration-200 hover:text-[#f1d293] focus:text-blue-600"> المقالات </a>
					</div>

					<a href="contact.php" title="" class="items-center justify-center hidden px-4 py-3 ltr:ml-10 rtl:mr-10 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">إتصل بنا الآن</a>
				</nav>

				<!-- xs to lg -->
				<div id="main-sidebar" style="display: none;" >
					<div onclick="NavView()" class="z-0 cursor-alias fixed bottom-0 h-full w-full"></div>
					<nav class="z-11 relative pt-4 pb-6 border-0 border-t-1 border-gray-200 lg:hidden">
						<div class="flow-root">
							<div class="flex flex-col px-6 -my-2 space-y-1">
								<a href="/" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600">	الرئيسية </a>
								<a href="services.php" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> خدماتنا </a>
								<a href="about.php" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> من نحن </a>
								<a href="FAQ.php" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> الأسئلة الشائعة </a>
								<a href="about.php" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> من نحن </a>
								<a href="projects.php?page=1" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> مشروعاتنا </a>
								<a href="blog.php?page=1" title="" class="inline-flex py-2 text-base font-medium text-white transition-all duration-200 hover:text-blue-600 focus:text-blue-600"> المقالات </a>

							</div>
						</div>

						<div class="px-6 mt-6">
							<a href="contact.php" title="" class="inline-flex justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md tems-center hover:bg-white focus:bg-[#f1f1f1]" role="button"> إتصل بنا الآن </a>
						</div>
					</nav>
				</div>

			</div>
		</header>
