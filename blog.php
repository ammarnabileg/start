<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'المقالات';
include 'init.php'; 


?>

















<!--
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



























<section class="pt-10 bg-gray-50 sm:pt-16 lg:pt-24" >
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="max-w-2xl mx-auto text-center">
			<p class="text-base tracking-wider text-[#f1d293] uppercase">المقالات</p>
			<h2 class="text-3xl font-bold leading-tight text-black sm:text-4xl lg:text-5xl lg:leading-tight">أحدث التطورات العقارية</h2>
		</div>



		<div class="grid max-w-md grid-cols-1 gap-6 mx-auto mt-8 lg:mt-16 lg:grid-cols-3 lg:max-w-full mb-16">










			<?php
			$pagenum=$_GET['page'];
			if(is_int($pagenum)==false){echo 'dsds';}
			$totalinpage=10;
			$finalcount=$pagenum*$totalinpage;
			$startount=$finalcount-($totalinpage-1);

			if(isset($pagenum)){





				$result = $mysqli->query("SELECT * FROM blog_posts ") or die($$mysqli->error);
				$x=0;
				while ($row = $result->fetch_assoc()): 
				$x=$x+1;echo 1;
				if($x>=$startount && $x<=$finalcount){

			?>

			<div class="overflow-hidden rounded-3xl border border-solid border-black bg-white px-4 py-5 [box-:rgb(0,_0,_0)_9px_9px] ">
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
				<a href="blog.php?page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
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
				<a href="blog.php?page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php } ?>


				<?php if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php }

				if($pagenum<$n){ ?>
				<a href="blog.php?page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php } ?>


				<?php if($pagenum<$n){ ?>
				<a href="blog.php?page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-:rgb(0,_0,_0)_3px_3px]">
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