<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 

?>








<?php /* 
<section class="relative sm:py-16 lg:py-24">
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
		<div class="relative top-40 mx-auto rounded-md bg-white max-w-md">

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

				<iframe width="100%" height="400" src="https://www.youtube.com/embed/KIRiZxfZcbw" title="أعمالنا من أرض الواقع" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>



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
 */ ?>




<section class="relative sm:py-16 lg:py-24">
	<div class="absolute inset-0">
		<img class="object-cover w-full h-full" src="https://www.jadwa.com/themes/custom/jadwa/assets/new-imgs/our-businesses/real-estate/REIT-Saudi/our-businesses-buildings.jpg" alt="" />
	</div>
	<div class="absolute inset-0 bg-[#0000008a]"></div>


	<div class="py-10 bg-gray-50 sm:py-16 lg:py-24 max-w-2xl mx-auto">
		<div class="max-w-6xl px-4 mx-auto sm:px-6 lg:px-8">
			<div class="relative text-center text-2xl lg:text-4xl" >

مشروعاتنا الحالية
			</div>
		</div>
	</div>



	<div id="modelConfirm" onclick="closeModal('modelConfirm')" class="fixed hidden z-50 inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full px-4 ">
		<div class="relative top-40 mx-auto rounded-md bg-white max-w-md">

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

				<iframe width="100%" height="400" src="https://www.youtube.com/embed/KIRiZxfZcbw" title="أعمالنا من أرض الواقع" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>



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

































<section class="pt-10 bg-gray-50 sm:pt-16 lg:pt-24" >
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="max-w-2xl mx-auto text-center">
			<p class="text-base tracking-wider text-[#f1d293] uppercase">مشروعاتنا</p>
			<h2 class="text-3xl font-bold leading-tight text-black sm:text-4xl lg:text-5xl lg:leading-tight">آخر أعمالنا</h2>
		</div>


















		<div class="grid max-w-md grid-cols-1 gap-6 mx-auto mt-8 lg:mt-16 lg:grid-cols-3 lg:max-w-full mb-16">










			<?php
			$pagenum=$_GET['page'];
			$totalinpage=15;
			$finalcount=$pagenum*$totalinpage;
			$startount=$finalcount-($totalinpage-1);

			if(isset($pagenum)){





				$result = $mysqli->query("SELECT * FROM projects") or die($$mysqli->error);
				$x=0;
				while ($row = $result->fetch_assoc()): 
				$x=$x+1;
				if($x>=$startount && $x<=$finalcount){

			?>


			<div class="relative overflow-hidden rounded-3xl border border-solid border-black bg-white [box-:rgb(0,_0,_0)_9px_9px] ">
			<?php 
					if($row["projects_sold"]==1){ ?>
				<a href="project.php?id=<?php echo $row["projects_id"]; ?>" class="absolute inset-0 flex items-center justify-center bg-black/60 text-white text-6xl font-extrabold tracking-widest transition-opacity duration-300 opacity-100 hover:opacity-0 z-[1]">
					SOLD
				</a>
			<?php } ?>	
				<a href="project.php?id=<?php echo $row["projects_id"]; ?>" title="" class="block aspect-w-4 aspect-h-3">
					<img class="object-cover w-full h-full" src="uploads/img/<?php echo $row["projects_thumbnail"]; ?>" alt="" />
				</a>
				<p class="mt-5 text-2xl font-semibold px-4 mb-4">
					<a href="project.php?id=<?php echo $row["projects_id"]; ?>" title="" class="text-black"><?php echo $row["projects_name"]; ?></a>
				</p>
			</div>

			<?php

				}
				endwhile; 

			}				 



			?>










		</div>




		<div class="flex justify-center">
			<div class=" inline-flex justify-center gap-2">

				<?php 
				$n=ceil($x/$totalinpage);
				if($pagenum>1){ ?>
				<a href="projects.php?page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">السابقة</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
					</svg>
				</a>

				<?php }else{ ?>

				<a href="#" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-[#d3d3d3] text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">السابقة</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
					</svg>
				</a>
				<?php }

				if($pagenum>1){ ?>
				<a href="projects.php?page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php } ?>


				<?php if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php }

				if($pagenum<$n){ ?>
				<a href="projects.php?page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php } ?>


				<?php if($pagenum<$n){ ?>
				<a href="projects.php?page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">التالية</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
					</svg>
				</a>
				<?php }else{ ?>
				<a href="#" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-[#d3d3d3] text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">التالية</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
					</svg>
				</a>
				<?php } ?>

			</div>
		</div>









	</div>

</section>














<?php
include 'includes/templates/footer.php'; 

ob_end_flush();
?>