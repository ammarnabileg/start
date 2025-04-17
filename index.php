<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 

?>











<section class="relative w-full h-screen flex items-center justify-center text-white text-center">
    <div class="absolute inset-0 w-full h-full">

   <video autoplay muted loop playsinline class="w-full h-full object-cover">
            <source src="assets/img/header-start.mp4" type="video/mp4">
        </video>
        <!---
        <img src="assets/img/bg-11.png" 
             alt="start" 
             class="absolute inset-0 w-full h-full object-cover " 
             onerror="this.classList.remove('hidden')">
		--->
		
    </div>

    <!-- طبقة شفافة -->
    <div class="absolute inset-0 bg-black/50"></div>

    <!-- النص -->
    <div class="relative z-10 animate-fade-in">
        <h1 class="text-4xl sm:text-5xl md:text-7xl font-bold uppercase text-gray-100">
            Start <br> Development
        </h1>
        <p dir="ltr" class="text-2xl md:text-3xl mt-4 text-gray-300">
            To Infinity...
        </p>
				<a href="#about" >
				<!-- <img class="mx-auto max-w-[70px] mt-[100px]" src="assets/img/mouse-scroll.gif"> -->
				<i class=" mt-[100px] text-4xl text-gray-900 fa-solid fa-chevron-down" ></i>
			</a>
    </div>
</section>


<?php /*

<section class="bg-white py-24 sm:py-24 lg:py-36 ">
	<div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
		<div class="text-center items-center grid-cols-1 gap-12 lg:grid-cols-2">
			<div>
				<p class="text-base tracking-wider text-yellow-500 uppercase">إبدأ معنا حياة يملؤها التقدم</p>
				<h1 class="mt-4 text-4xl font-semibold text-[#000] lg:mt-8 lg:leading-[1.3] sm:text-6xl xl:text-8xl leading-10">
					رواد الإستثمار والتطوير العقاري
				</h1>
				<p class="mt-4 text-base text-gray-600 lg:mt-8 sm:text-xl">نمي استثماراتك بسرعه مع قادة الصناعة..</p>
			</div>
			<a href="#about" >
				<!-- <img class="mx-auto max-w-[70px] mt-[100px]" src="assets/img/mouse-scroll.gif"> -->
				<i class=" mt-[100px] text-4xl text-gray-600 fa-solid fa-chevron-down" ></i>
			</a>
		</div>
	</div>
</section>
*/ ?>








<!--


<section class="relative  sm:py-16 lg:py-24">
 <div class="absolute inset-0">
  <img class="object-cover w-full h-full" src="assets/img/map.png" alt="" />
 </div>
 <div class="absolute inset-0 bg-[#0000008a]"></div>


 <div class="py-10 bg-gray-50 sm:py-16 lg:py-24 max-w-2xl mx-auto">
  <div class="max-w-6xl px-4 mx-auto sm:px-6 lg:px-8">
   <div class="relative">
 <div class="aspect-w-4 aspect-h-3">
  <img class="object-cover w-full h-full rounded-3xl" src="assets/img/main-video.jpg" alt="" />
 </div>

 <div class="absolute inset-0 flex items-center justify-center">
  <div class="flex items-center justify-center rounded-full w-28 h-28 bg-white/20">
   <button type="button" class="flex items-center justify-center w-20 h-20 text-white transition-all duration-200 rounded-full bg-gradient-to-r from-[#000] to-[#f1d293] hover:opacity-90 " onclick="openModal('modelConfirm')">
 <svg class="w-6 h-6 lg:w-8 lg:h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
  <path d="M8 6.82v10.36c0 .79.87 1.27 1.54.84l8.14-5.18c.62-.39.62-1.29 0-1.69L9.54 5.98C8.87 5.55 8 6.03 8 6.82z"></path>
 </svg>
   </button>
  </div>
 </div>

   </div>
  </div>
 </div>



 <div id="modelConfirm" onclick="closeModal('modelConfirm')" class="fixed hidden z-50 inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full px-4 ">
  <div class="relative top-40 mx-auto shadow-xl rounded-md bg-white max-w-3xl">

   <div class="flex justify-end p-2">
 <button onclick="closeModal('modelConfirm')" type="button"
   class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
   <path fill-rule="evenodd"
   d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
   clip-rule="evenodd"></path>
  </svg>
 </button>
   </div>

   <div class="p-6 pt-0 text-center">

 <iframe width="100%" height="350" src="https://www.youtube.com/embed/Y5U4AaNs4_0" title="خطوات ستارت العقارية بمدينة بدر" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

   </div>

  </div>
 </div>

 <script type="text/javascript">
  window.openModal = function(modalId) {
   document.getElementById(modalId).style.display = 'block'
   document.getElementsByTagName('body')[0].classList.add('overflow-y-hidden')
  }

  window.closeModal = function(modalId) {
   document.getElementById(modalId).style.display = 'none'
   document.getElementsByTagName('body')[0].classList.remove('overflow-y-hidden')
  }

  // Close all modals when press ESC
  document.onkeydown = function(event) {
   event = event || window.event;
   if (event.keyCode === 27) {
 document.getElementsByTagName('body')[0].classList.remove('overflow-y-hidden')
 let modals = document.getElementsByClassName('modal');
 Array.prototype.slice.call(modals).forEach(i => {
  i.style.display = 'none'
 })
   }
  };
 </script>
</section>



-->









<section id="about" class="py-24 sm:py-24 lg:py-36 bg-white">
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="grid items-center grid-cols-1 gap-y-12 lg:grid-cols-2 lg:gap-x-24">
			<style>

				@-webkit-keyframes action {
					0% { transform: translateY(0); }
					100% { transform: translateY(-10px); }
				}

				@keyframes action {
					0% { transform: translateY(0); }
					100% { transform: translateY(-10px); }
				}


			</style>
			<div class="action" style="-webkit-animation: action 1s infinite  alternate;
									   animation: action 1s infinite  alternate;">

				<img class=" w-full max-w-md mx-auto" draggable="false" src="https://start.com.eg/assets/img/logo-b.png" alt="" />
			</div>

			<div class="text-center ltr:lg:text-left rtl:lg:text-right">
				<h2 class="text-xl font-bold leading-tight text-yellow-500 sm:text-2xl lg:text-3xl">خيارك الأول في مدينة بدر</h2>
				<p class="mt-6 text-base text-gray-900">
					نحن شركة ستارت للتطوير العقاري نعد من أفضل الشركات صعودا في السوق العقاري ونؤمن بأن قوتنا تستمد من تميزنا وعندما يتعلق الأمر بالتصميم والتنفيذ في مجال العقارات نعمل باجتهاد مما يتوافق مع المواصفات المصرية قمنا بتصميم وتنفيذ وتسليم الكثير من المشروعات في وقت قياسي كما شهد عملائنا على كفائتنا وخبرتنا واحترامنا في التعامل معهم وتلبية رغباتهم وتسليمهم قبل الميعاد المتفق عليه بجودة وكفائة عالية لدينا الآن خمسة وعشرون مشروعا بين تنفيذ واشراف وتشطيبات مستمرين في البناء والتطوير ونطمح الى أن نصبح أفضل شركة تطوير عقاري في مصر
					...
				</p>

				<a href="about.php" title="" class="mt-5 inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">  عن الشركة</a>
			</div>

		</div>
	</div>
</section>







<section class="bg-yellow-50 py-24 sm:py-24 lg:py-36">
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="max-w-2xl mx-auto text-center">
			<p class="text-base tracking-wider text-yellow-500 uppercase">مشروعاتنا</p>
			<h2 class="text-3xl font-bold leading-tight text-black sm:text-4xl lg:text-5xl lg:leading-tight">آخر أعمالنا</h2>
		</div>




		<div class="grid max-w-md grid-cols-1 gap-6 mx-auto mt-8 lg:mt-16 lg:grid-cols-3 lg:max-w-full mb-16">



			<?php


			$result = $mysqli->query("SELECT * FROM projects LIMIT 3") or die($$mysqli->error);
			while ($row = $result->fetch_assoc()): 

			?>


			<div class="overflow-hidden rounded-3xl border border-solid border-black bg-white  [box-shadow:rgb(0,_0,_0)_9px_9px]  ">

				<a href="project.php?id=<?php echo $row["projects_id"]; ?>" title="" class="block aspect-w-4 aspect-h-3">
					<img class="object-cover w-full h-full" src="uploads/img/<?php echo $row["projects_thumbnail"]; ?>" alt="" />
				</a>
				<p class="mt-5 text-2xl font-semibold px-4 mb-4">
					<a href="project.php?id=<?php echo $row["projects_id"]; ?>" title="" class="text-black"><?php echo $row["projects_name"]; ?></a>
				</p>
			</div>

			<?php

			endwhile; 

			?>


		</div>




		<div class="flex justify-center">
			<a href="projects.php?page=1" title="" class="mt-5 inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">  عرض المزيد</a>
		</div>

	</div>
</section>




<section class="py-24 sm:py-24 lg:py-36 bg-white">
	<div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
		<div class="max-w-xl mx-auto text-center">

			<h2 class="text-3xl font-bold leading-tight text-yellow-500 sm:text-4xl lg:text-5xl">نحن نبني الثقة</h2>
			<p class="mt-4 text-base leading-relaxed text-gray-900">في ستارت، نحن ملتزمون ببناء الثقة من خلال التميز في تقديم الحلول العقارية ونفخر بكوننا مصدرك الإستشاري الموثوق.</p>
		</div>

		<div class="grid max-w-md grid-cols-1 gap-6 mx-auto mt-8 lg:mt-16 lg:grid-cols-2 lg:max-w-full justify-center max-w-lg px-10 mx-auto mt-8 space-y-8 lg:max-w-2xl sm:px-0 sm:space-y-0 sm:flex-row sm:mt-16 sm:gap-x-6 lg:gap-x-12 sm:items-center">


			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">باقة من الخبراء</p>
					<p class="ml-4 leading-snug text-gray-600">فريق متكامل من الخبراء يضم نخبة من المهندسين والمعماريين، يعمل بتناغم لتحقيق أعلى جودة.</p>
				</div>
			</div>

			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">تصاميم مبتكرة تلهم</p>
					<p class="ml-4 leading-snug text-gray-600">تصاميم تجمع بين الحداثة والأصالة، مع تشطيبات متقنة تُبرز التفاصيل.</p>
				</div>
			</div>

			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">خدمة استثنائية بعد البيع</p>
					<p class="ml-4 leading-snug text-gray-600">دعم مستمر ومتابعة دقيقة لضمان رضا العملاء وحل أي تحديات بسرعة.</p>
				</div>
			</div>

			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">عروض حصرية لا تُنافس</p>
					<p class="ml-4 leading-snug text-gray-600">أسعار جذابة دون التنازل عن الجودة والتميز في كل مشروع.</p>
				</div>
			</div>

			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">مواقع استراتيجية ترفع القيمة</p>
					<p class="ml-4 leading-snug text-gray-600">نختار بعناية مواقع مشروعاتنا لتقديم استثمارات عالية القيمة.</p>
				</div>
			</div>

			<div class="flex items-center lg:flex-1 gap-3">
				<i class="fa-regular text-5xl fa-star flex-shrink-0 text-yellow-500 "></i>
				<div>
					<p class="ml-4 text-lg font-semibold leading-snug text-gray-900">تشطيبات فائقة الجودة</p>
					<p class="ml-4 leading-snug text-gray-600">نحرص على أدق التفاصيل في التشطيب، لتسليم وحدات جاهزة بأعلى معايير.</p>
				</div>
			</div>


		</div>

	</div>
</section>


<?php
/*

		<div class="flex justify-center mt-10 sm:mt-16">
			<iframe  src="https://www.facebook.com/plugins/video.php?height=314&href=https%3A%2F%2Fwww.facebook.com%2FStartDevEg%2Fvideos%2F1125711762274949%2F&show_text=false&width=560&t=0" width="560" height="314" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowFullScreen="true"></iframe>
		</div>

		<div class="mt-5 sm:mt-16">
			<img class="w-full max-w-3xl mx-auto -mb-16" src="assets/img/start-mall.png"/>
		</div>

*/ ?>




<section class="max-w-7xl mx-auto px-4 pt-24 sm:pt-24 lg:pt-36">
    <div class=" flex flex-col lg:flex-row items-center gap-8 ">
    <div class="w-full lg:w-1/2 ">
        <h5 class="text-gray-100 uppercase tracking-widest text-sm">آخر مشاريعنا</h5>
        <h2 class="text-3xl font-bold text-[#9C784A] mt-2">Start Mall</h2>
        <div class="text-gray-200 mt-4 leading-relaxed">
			<p>مشروع ستارت مول هو مشروع تجاري إداري طبي مملوك لشركة ستارت العقارية&nbsp;</p><p><span style="color:hsl(0,0%,0%);">مساحة المشروع 1449م&nbsp;</span></p><p>يتكون المشروع من&nbsp;</p><ul><li>جراش&nbsp;</li><li>- دور أرضي منخفض&nbsp;</li><li>- دور أرضي مرتفع نشاط تجاري&nbsp;</li><li>- دور أول نشاط تجاري&nbsp;</li><li>- دور ثاني طبي&nbsp;</li><li>- دور ثالث اداري&nbsp;</li><li>تم التعاقد على المشروع في 10 / 02 / 2024</li><li>حالة المشروع الان قيد الانشاء&nbsp;</li><li>التسليم 2026&nbsp;</li><li>&nbsp;</li><li>&nbsp;</li></ul> 
        </div>
        
        <!-- زر التفاصيل -->
        <div class="mt-6">
            <a href="https://start.com.eg/project.php?id=3" class="inline-flex items-center px-6 py-3 bg-[#9C784A] text-white text-sm font-semibold rounded-full shadow-md transition-all hover:bg-[#8B6A3E]">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                عرض المزيد
            </a>
        </div>
    </div>    
    <div class="w-full lg:w-1/2">
        <div class="relative w-full max-w-3xl shadow-lg rounded-lg overflow-hidden">
            <iframe src="https://www.facebook.com/plugins/video.php?height=314&href=https%3A%2F%2Fwww.facebook.com%2FStartDevEg%2Fvideos%2F1125711762274949%2F&show_text=false&width=560&t=0" 
                    width="560" height="314" 
                    class="w-full min-h-64 sm:min-h-[314px] border-2 border-gray-300 rounded-lg"
                    style="border:none;overflow:hidden" 
                    scrolling="no" frameborder="0" allowfullscreen="true"
                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">
            </iframe>
        </div>
    </div>
    </div>

    <!-- الصورة -->
    <div class="mt-10 sm:mt-16 relative">
        <img class="w-full max-w-3xl mx-auto -mb-10 sm:-mb-16 shadow-xl " 
             src="assets/img/start-mall.png" 
             alt="Start Mall">
    </div>

</section>






<section class="bg-white py-24 sm:py-24 lg:py-36  text-gray-900" >
	<div id="hero-help" class="max-w-screen-lg container mx-auto bg-yellow-50 py-24 text-center rounded-lg px-8 ">
		<div class="">
			<h3 class="text-3xl md:text-5xl font-semibold">هل تحتاج إلى مساعدة؟</h3>
			<p class="mt-8 text-lg md:text-xl font-light max-w-[600px] mx-auto">
				هل لديك المزيد من الاستفسارات أو تحتاج الى استشارة أحد موظفينا من الاستشاريين العقاريين المتخصصين في الاستثمار العقاري؟
			</p>
			<form action="includes/Sections/lead_reg.php" method="POST" class="flex flex-col mt-6 gap-y-3">
				<div>
					<label for="name" class="sr-only">الاسم</label>
					<input type="text" name="lead_name" id="name" placeholder="أكتب اسمك هنا" class="block w-full p-4 text-black placeholder-gray-500 transition-all duration-200 bg-white border border-gray-200 rounded-md focus:outline-none focus:border-blue-600 caret-blue-600">
				</div>

				<div class="relative flex">
					<div class="absolute inset-y-0 left-0 flex items-center selectbox">
						<select required="required" id="country_code" name="country_code" class=" min-h-[58px] max-w-[100px]  sm:max-w-[135px] h-full rounded-md block w-full rounded border-0 py-2 px-3.5 pl-3  shadow-sm placeholder-[#000] bg-[#86868617] dark:text-[#000] text-[#000] ">
							<option value="Null" disable="">إختر الدولة</option>
							<option data-countrycode="EG" value="20" selected="">Egypt (+20)</option>
							<option data-countrycode="KW" value="965">Kuwait (+965)</option>
							<option data-countrycode="SA" value="966">Saudi Arabia (+966)</option>
							<option data-countrycode="AE" value="971">United Arab Emirates (+971)</option>
							<option data-countrycode="IL" value="972">Palestine (+972)</option>
							<option data-countrycode="DZ" value="213">Algeria (+213)</option>
							<option data-countrycode="AD" value="376">Andorra (+376)</option>
							<option data-countrycode="AO" value="244">Angola (+244)</option>
							<option data-countrycode="AI" value="1264">Anguilla (+1264)</option>
							<option data-countrycode="AG" value="1268">Antigua &amp; Barbuda (+1268)</option>
							<option data-countrycode="AR" value="54">Argentina (+54)</option>
							<option data-countrycode="AM" value="374">Armenia (+374)</option>
							<option data-countrycode="AW" value="297">Aruba (+297)</option>
							<option data-countrycode="AU" value="61">Australia (+61)</option>
							<option data-countrycode="AT" value="43">Austria (+43)</option>
							<option data-countrycode="AZ" value="994">Azerbaijan (+994)</option>
							<option data-countrycode="BS" value="1242">Bahamas (+1242)</option>
							<option data-countrycode="BH" value="973">Bahrain (+973)</option>
							<option data-countrycode="BD" value="880">Bangladesh (+880)</option>
							<option data-countrycode="BB" value="1246">Barbados (+1246)</option>
							<option data-countrycode="BY" value="375">Belarus (+375)</option>
							<option data-countrycode="BE" value="32">Belgium (+32)</option>
							<option data-countrycode="BZ" value="501">Belize (+501)</option>
							<option data-countrycode="BJ" value="229">Benin (+229)</option>
							<option data-countrycode="BM" value="1441">Bermuda (+1441)</option>
							<option data-countrycode="BT" value="975">Bhutan (+975)</option>
							<option data-countrycode="BO" value="591">Bolivia (+591)</option>
							<option data-countrycode="BA" value="387">Bosnia Herzegovina (+387)</option>
							<option data-countrycode="BW" value="267">Botswana (+267)</option>
							<option data-countrycode="BR" value="55">Brazil (+55)</option>
							<option data-countrycode="BN" value="673">Brunei (+673)</option>
							<option data-countrycode="BG" value="359">Bulgaria (+359)</option>
							<option data-countrycode="BF" value="226">Burkina Faso (+226)</option>
							<option data-countrycode="BI" value="257">Burundi (+257)</option>
							<option data-countrycode="KH" value="855">Cambodia (+855)</option>
							<option data-countrycode="CM" value="237">Cameroon (+237)</option>
							<option data-countrycode="CA" value="1">Canada (+1)</option>
							<option data-countrycode="CV" value="238">Cape Verde Islands (+238)</option>
							<option data-countrycode="KY" value="1345">Cayman Islands (+1345)</option>
							<option data-countrycode="CF" value="236">Central African Republic (+236)</option>
							<option data-countrycode="CL" value="56">Chile (+56)</option>
							<option data-countrycode="CN" value="86">China (+86)</option>
							<option data-countrycode="CO" value="57">Colombia (+57)</option>
							<option data-countrycode="KM" value="269">Comoros (+269)</option>
							<option data-countrycode="CG" value="242">Congo (+242)</option>
							<option data-countrycode="CK" value="682">Cook Islands (+682)</option>
							<option data-countrycode="CR" value="506">Costa Rica (+506)</option>
							<option data-countrycode="HR" value="385">Croatia (+385)</option>
							<option data-countrycode="CU" value="53">Cuba (+53)</option>
							<option data-countrycode="CY" value="90392">Cyprus North (+90392)</option>
							<option data-countrycode="CY" value="357">Cyprus South (+357)</option>
							<option data-countrycode="CZ" value="42">Czech Republic (+42)</option>
							<option data-countrycode="DK" value="45">Denmark (+45)</option>
							<option data-countrycode="DJ" value="253">Djibouti (+253)</option>
							<option data-countrycode="DM" value="1809">Dominica (+1809)</option>
							<option data-countrycode="DO" value="1809">Dominican Republic (+1809)</option>
							<option data-countrycode="EC" value="593">Ecuador (+593)</option>
							<option data-countrycode="SV" value="503">El Salvador (+503)</option>
							<option data-countrycode="GQ" value="240">Equatorial Guinea (+240)</option>
							<option data-countrycode="ER" value="291">Eritrea (+291)</option>
							<option data-countrycode="EE" value="372">Estonia (+372)</option>
							<option data-countrycode="ET" value="251">Ethiopia (+251)</option>
							<option data-countrycode="FK" value="500">Falkland Islands (+500)</option>
							<option data-countrycode="FO" value="298">Faroe Islands (+298)</option>
							<option data-countrycode="FJ" value="679">Fiji (+679)</option>
							<option data-countrycode="FI" value="358">Finland (+358)</option>
							<option data-countrycode="FR" value="33">France (+33)</option>
							<option data-countrycode="GF" value="594">French Guiana (+594)</option>
							<option data-countrycode="PF" value="689">French Polynesia (+689)</option>
							<option data-countrycode="GA" value="241">Gabon (+241)</option>
							<option data-countrycode="GM" value="220">Gambia (+220)</option>
							<option data-countrycode="GE" value="7880">Georgia (+7880)</option>
							<option data-countrycode="DE" value="49">Germany (+49)</option>
							<option data-countrycode="GH" value="233">Ghana (+233)</option>
							<option data-countrycode="GI" value="350">Gibraltar (+350)</option>
							<option data-countrycode="GR" value="30">Greece (+30)</option>
							<option data-countrycode="GL" value="299">Greenland (+299)</option>
							<option data-countrycode="GD" value="1473">Grenada (+1473)</option>
							<option data-countrycode="GP" value="590">Guadeloupe (+590)</option>
							<option data-countrycode="GU" value="671">Guam (+671)</option>
							<option data-countrycode="GT" value="502">Guatemala (+502)</option>
							<option data-countrycode="GN" value="224">Guinea (+224)</option>
							<option data-countrycode="GW" value="245">Guinea - Bissau (+245)</option>
							<option data-countrycode="GY" value="592">Guyana (+592)</option>
							<option data-countrycode="HT" value="509">Haiti (+509)</option>
							<option data-countrycode="HN" value="504">Honduras (+504)</option>
							<option data-countrycode="HK" value="852">Hong Kong (+852)</option>
							<option data-countrycode="HU" value="36">Hungary (+36)</option>
							<option data-countrycode="IS" value="354">Iceland (+354)</option>
							<option data-countrycode="IN" value="91">India (+91)</option>
							<option data-countrycode="ID" value="62">Indonesia (+62)</option>
							<option data-countrycode="IR" value="98">Iran (+98)</option>
							<option data-countrycode="IQ" value="964">Iraq (+964)</option>
							<option data-countrycode="IE" value="353">Ireland (+353)</option>
							<option data-countrycode="IT" value="39">Italy (+39)</option>
							<option data-countrycode="JM" value="1876">Jamaica (+1876)</option>
							<option data-countrycode="JP" value="81">Japan (+81)</option>
							<option data-countrycode="JO" value="962">Jordan (+962)</option>
							<option data-countrycode="KZ" value="7">Kazakhstan (+7)</option>
							<option data-countrycode="KE" value="254">Kenya (+254)</option>
							<option data-countrycode="KI" value="686">Kiribati (+686)</option>
							<option data-countrycode="KP" value="850">Korea North (+850)</option>
							<option data-countrycode="KR" value="82">Korea South (+82)</option>
							<option data-countrycode="KG" value="996">Kyrgyzstan (+996)</option>
							<option data-countrycode="LA" value="856">Laos (+856)</option>
							<option data-countrycode="LV" value="371">Latvia (+371)</option>
							<option data-countrycode="LB" value="961">Lebanon (+961)</option>
							<option data-countrycode="LS" value="266">Lesotho (+266)</option>
							<option data-countrycode="LR" value="231">Liberia (+231)</option>
							<option data-countrycode="LY" value="218">Libya (+218)</option>
							<option data-countrycode="LI" value="417">Liechtenstein (+417)</option>
							<option data-countrycode="LT" value="370">Lithuania (+370)</option>
							<option data-countrycode="LU" value="352">Luxembourg (+352)</option>
							<option data-countrycode="MO" value="853">Macao (+853)</option>
							<option data-countrycode="MK" value="389">Macedonia (+389)</option>
							<option data-countrycode="MG" value="261">Madagascar (+261)</option>
							<option data-countrycode="MW" value="265">Malawi (+265)</option>
							<option data-countrycode="MY" value="60">Malaysia (+60)</option>
							<option data-countrycode="MV" value="960">Maldives (+960)</option>
							<option data-countrycode="ML" value="223">Mali (+223)</option>
							<option data-countrycode="MT" value="356">Malta (+356)</option>
							<option data-countrycode="MH" value="692">Marshall Islands (+692)</option>
							<option data-countrycode="MQ" value="596">Martinique (+596)</option>
							<option data-countrycode="MR" value="222">Mauritania (+222)</option>
							<option data-countrycode="YT" value="269">Mayotte (+269)</option>
							<option data-countrycode="MX" value="52">Mexico (+52)</option>
							<option data-countrycode="FM" value="691">Micronesia (+691)</option>
							<option data-countrycode="MD" value="373">Moldova (+373)</option>
							<option data-countrycode="MC" value="377">Monaco (+377)</option>
							<option data-countrycode="MN" value="976">Mongolia (+976)</option>
							<option data-countrycode="MS" value="1664">Montserrat (+1664)</option>
							<option data-countrycode="MA" value="212">Morocco (+212)</option>
							<option data-countrycode="MZ" value="258">Mozambique (+258)</option>
							<option data-countrycode="MN" value="95">Myanmar (+95)</option>
							<option data-countrycode="NA" value="264">Namibia (+264)</option>
							<option data-countrycode="NR" value="674">Nauru (+674)</option>
							<option data-countrycode="NP" value="977">Nepal (+977)</option>
							<option data-countrycode="NL" value="31">Netherlands (+31)</option>
							<option data-countrycode="NC" value="687">New Caledonia (+687)</option>
							<option data-countrycode="NZ" value="64">New Zealand (+64)</option>
							<option data-countrycode="NI" value="505">Nicaragua (+505)</option>
							<option data-countrycode="NE" value="227">Niger (+227)</option>
							<option data-countrycode="NG" value="234">Nigeria (+234)</option>
							<option data-countrycode="NU" value="683">Niue (+683)</option>
							<option data-countrycode="NF" value="672">Norfolk Islands (+672)</option>
							<option data-countrycode="NP" value="670">Northern Marianas (+670)</option>
							<option data-countrycode="NO" value="47">Norway (+47)</option>
							<option data-countrycode="OM" value="968">Oman (+968)</option>
							<option data-countrycode="PW" value="680">Palau (+680)</option>
							<option data-countrycode="PA" value="507">Panama (+507)</option>
							<option data-countrycode="PG" value="675">Papua New Guinea (+675)</option>
							<option data-countrycode="PY" value="595">Paraguay (+595)</option>
							<option data-countrycode="PE" value="51">Peru (+51)</option>
							<option data-countrycode="PH" value="63">Philippines (+63)</option>
							<option data-countrycode="PL" value="48">Poland (+48)</option>
							<option data-countrycode="PT" value="351">Portugal (+351)</option>
							<option data-countrycode="PR" value="1787">Puerto Rico (+1787)</option>
							<option data-countrycode="QA" value="974">Qatar (+974)</option>
							<option data-countrycode="RE" value="262">Reunion (+262)</option>
							<option data-countrycode="RO" value="40">Romania (+40)</option>
							<option data-countrycode="RU" value="7">Russia (+7)</option>
							<option data-countrycode="RW" value="250">Rwanda (+250)</option>
							<option data-countrycode="SM" value="378">San Marino (+378)</option>
							<option data-countrycode="ST" value="239">Sao Tome &amp; Principe (+239)</option>
							<option data-countrycode="SN" value="221">Senegal (+221)</option>
							<option data-countrycode="CS" value="381">Serbia (+381)</option>
							<option data-countrycode="SC" value="248">Seychelles (+248)</option>
							<option data-countrycode="SL" value="232">Sierra Leone (+232)</option>
							<option data-countrycode="SG" value="65">Singapore (+65)</option>
							<option data-countrycode="SK" value="421">Slovak Republic (+421)</option>
							<option data-countrycode="SI" value="386">Slovenia (+386)</option>
							<option data-countrycode="SB" value="677">Solomon Islands (+677)</option>
							<option data-countrycode="SO" value="252">Somalia (+252)</option>
							<option data-countrycode="ZA" value="27">South Africa (+27)</option>
							<option data-countrycode="ES" value="34">Spain (+34)</option>
							<option data-countrycode="LK" value="94">Sri Lanka (+94)</option>
							<option data-countrycode="SH" value="290">St. Helena (+290)</option>
							<option data-countrycode="KN" value="1869">St. Kitts (+1869)</option>
							<option data-countrycode="SC" value="1758">St. Lucia (+1758)</option>
							<option data-countrycode="SD" value="249">Sudan (+249)</option>
							<option data-countrycode="SR" value="597">Suriname (+597)</option>
							<option data-countrycode="SZ" value="268">Swaziland (+268)</option>
							<option data-countrycode="SE" value="46">Sweden (+46)</option>
							<option data-countrycode="CH" value="41">Switzerland (+41)</option>
							<option data-countrycode="SI" value="963">Syria (+963)</option>
							<option data-countrycode="TW" value="886">Taiwan (+886)</option>
							<option data-countrycode="TJ" value="7">Tajikstan (+7)</option>
							<option data-countrycode="TH" value="66">Thailand (+66)</option>
							<option data-countrycode="TG" value="228">Togo (+228)</option>
							<option data-countrycode="TO" value="676">Tonga (+676)</option>
							<option data-countrycode="TT" value="1868">Trinidad &amp; Tobago (+1868)</option>
							<option data-countrycode="TN" value="216">Tunisia (+216)</option>
							<option data-countrycode="TR" value="90">Turkey (+90)</option>
							<option data-countrycode="TM" value="7">Turkmenistan (+7)</option>
							<option data-countrycode="TM" value="993">Turkmenistan (+993)</option>
							<option data-countrycode="TC" value="1649">Turks &amp; Caicos Islands (+1649)</option>
							<option data-countrycode="TV" value="688">Tuvalu (+688)</option>
							<option data-countrycode="UG" value="256">Uganda (+256)</option>
							<option data-countrycode="GB" value="44">UK (+44)</option>
							<option data-countrycode="UA" value="380">Ukraine (+380)</option>
							<option data-countrycode="UY" value="598">Uruguay (+598)</option>
							<option data-countrycode="US" value="1">USA (+1)</option>
							<option data-countrycode="UZ" value="7">Uzbekistan (+7)</option>
							<option data-countrycode="VU" value="678">Vanuatu (+678)</option>
							<option data-countrycode="VA" value="379">Vatican City (+379)</option>
							<option data-countrycode="VE" value="58">Venezuela (+58)</option>
							<option data-countrycode="VN" value="84">Vietnam (+84)</option>
							<option data-countrycode="VG" value="84">Virgin Islands - British (+1284)</option>
							<option data-countrycode="VI" value="84">Virgin Islands - US (+1340)</option>
							<option data-countrycode="WF" value="681">Wallis &amp; Futuna (+681)</option>
							<option data-countrycode="YE" value="969">Yemen (North)(+969)</option>
							<option data-countrycode="YE" value="967">Yemen (South)(+967)</option>
							<option data-countrycode="ZM" value="260">Zambia (+260)</option>
							<option data-countrycode="ZW" value="263">Zimbabwe (+263)</option>

						</select>
					</div>
					<input required="required" type="number" name="phone" autocomplete="tel" class="text-[#000] block w-full rounded border border-gray-200 py-2 px-3.5 pl-24 sm:pl-32  min-h-[58px] placeholder-gray-500 shadow-sm bg-[#fff] dark:text-[#9e9e9e]" placeholder="رقم هاتفك">
					<input required="required" type="text" name="this_url" autocomplete="tel" class="hidden text-[#000] block w-full rounded border-0 py-2 px-3.5 pl-32  min-h-[50px] placeholder-gray-500 shadow-sm bg-[#fff] dark:text-[#9e9e9e]" value="<?php echo $actual_link; ?>">
				</div>
				<input type="submit" class="cursor-pointer inline-flex items-center justify-center px-6 py-4 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-[#22e203] hover:text-white focus:bg-[#f1f1f1]" value="طلب إستشارة مجانية" name="lead_reg">
			</form>

			<div class="flex gap-1 sm:gap-4 mx-auto mt-10">

				<a href="https://wa.me/201552521511" target="_blank" class="justify-center w-full inline-flex px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none flex items-center"><i class="fa-brands fa-whatsapp w-5 h-5 mr-2"></i>واتساب</a>

				<a href="tel:+201552521511" class="justify-center w-full inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none flex items-center"><i class="fa-solid fa-phone w-5 h-5 mr-2"></i>اتصال</a>

			</div>


		</div>
	</div>
</section>















<section class="pt-10 bg-gray-50 sm:pt-16 lg:pt-24" >
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="max-w-2xl mx-auto text-center">
			<p class="text-base tracking-wider text-[#f1d293] uppercase">المقالات</p>
			<h2 class="text-3xl font-bold leading-tight text-black sm:text-4xl lg:text-5xl lg:leading-tight">أحدث التطورات العقارية</h2>
		</div>



		<div class="grid max-w-md grid-cols-1 gap-6 mx-auto mt-8 lg:mt-16 lg:grid-cols-3 lg:max-w-full ">


			<?php
			$result = $mysqli->query("SELECT * FROM blog_posts LIMIT 3 ") or die($$mysqli->error);
			while ($row = $result->fetch_assoc()): 
			?>

			<div class="overflow-hidden rounded-3xl border border-solid border-black bg-white px-4 py-5 [box-shadow:rgb(0,_0,_0)_9px_9px]  ">
				<div class="p-5">
					<div class="relative">
						<a href="blog_post.php?id=<?php echo $row["blog_posts_id"]; ?>" title="<?php echo $row["blog_posts_title"]; ?>" class="block aspect-w-4 aspect-h-3">
							<img class="object-cover w-full h-full" src="<?php echo $row["blog_posts_img"]; ?>" alt="" />
						</a>
					</div>
					<p class="mt-5 text-2xl font-semibold">
						<a href="blog_post.php?id=<?php echo $row["blog_posts_id"]; ?>" title="<?php echo $row["blog_posts_title"]; ?>" class="text-black"> <?php echo $row["blog_posts_title"]; ?> </a>
					</p>
					<p class="mt-4 text-base text-gray-600"><?php echo substr($row["blog_posts_text"],0,50); ?>...</p>
					<a href="blog_post.php?id=<?php echo $row["blog_posts_id"]; ?>" title="<?php echo $row["blog_posts_title"]; ?>" class="inline-flex items-center justify-center pb-0.5 mt-5 text-base font-semibold text-blue-600 transition-all duration-200 border-b-2 border-transparent hover:border-blue-600 focus:border-blue-600">
						أكمل القراءة
						<i class="mx-2 w-5 h-5 fa-solid fa-chevron-left"></i>
					</a>
				</div>
			</div>


			<?php
			endwhile; 
			?>



		</div>
		<div class="flex justify-center mt-10">
			<div class=" inline-flex justify-center gap-2">

				<a href="blog.php?page=1" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
					<span>عرض المزيد</span>

				</a>
			</div>
		</div>



	</div>
</section>
















<?php
include 'includes/templates/footer.php'; 

ob_end_flush();
?>