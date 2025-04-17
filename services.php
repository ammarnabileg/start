<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 

?>














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
 <button type="button" class="flex items-center justify-center w-20 h-20 text-white transition-all duration-200 rounded-full bg-gradient-to-r from-[#000] to-[#f1d293] hover:opacity-90">
  <svg class="w-6 h-6 lg:w-8 lg:h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
   <path d="M8 6.82v10.36c0 .79.87 1.27 1.54.84l8.14-5.18c.62-.39.62-1.29 0-1.69L9.54 5.98C8.87 5.55 8 6.03 8 6.82z"></path>
  </svg>
 </button>
   </div>
  </div>

 </div>
   </div>
  </div>
 </section>

-->
































<section class="py-10 bg-gray-50 sm:py-16 lg:py-24">
    <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-2xl mx-auto text-center">
            <h2 class="text-3xl font-bold leading-tight text-gray-900 sm:text-4xl lg:text-5xl">خدماتنا</h2>
            <p class="max-w-xl mx-auto mt-4 text-base leading-relaxed text-gray-500"></p>
        </div>

        <div class="max-w-5xl mx-auto mt-12 sm:mt-16">
            <div class="mt-6 overflow-hidden bg-white rounded-xl">
                <div class="px-6 py-12 sm:p-12">
                    <h3 class="text-3xl font-semibold text-center text-gray-900">..</h3>

					
					
                </div>
            </div>
        </div>
    </div>
</section>

	
	
	
	
	



<?php
include 'includes/templates/footer.php'; 

ob_end_flush();
?>