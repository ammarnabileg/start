
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">المشروعات</h3>



		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div
					 class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full">
						<thead>
							<tr>
								<th
									class="text-right px-6 py-3 text-xs font-medium leading-4 tracking-wider text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									اسم المشروع</th>
								<th
									class="text-right px-6 py-3 text-xs font-medium leading-4 tracking-wider text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الحالة</th>
								<th
									class="text-right px-6 py-3 text-xs font-medium leading-4 tracking-wider text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									مباع؟</th>
								<th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
							</tr>
						</thead>

						<tbody class="bg-white">





							<?php
							$pagenum=$_GET['page'];
							if(!isset($pagenum)){
								header("Location: cpanel.php?p=ViewProjects&page=1");
								die();
							}
							$totalinpage=10;
							$finalcount=$pagenum*$totalinpage;
							$startount=$finalcount-($totalinpage-1);

							if(isset($pagenum)){





								$result = $mysqli->query("SELECT * FROM projects") or die($$mysqli->error);
								$x=0;
								while ($row = $result->fetch_assoc()): 
								$x=$x+1;
								if($x>=$startount && $x<=$finalcount){

							?>





							<tr>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="leading-5 text-gray-900"><?php echo $row["projects_name"]; ?></div>
									<div class="text-sm leading-5 text-gray-500 mt-3"><a href="project.php?id=<?php echo $row["projects_id"]; ?>"><i class="fa-solid fa-eye"></i> مشاهدة</a></div>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php if ($row["projects_status"]==1){ ?>
									<span class="inline-flex px-2 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">مفعل</span>
									<?php }elseif ($row["projects_status"]==0){ ?>
									<span class="inline-flex px-2 text-xs font-semibold leading-5 text-red-800 bg-red-100 rounded-full">غير مفعل</span>
									<?php } ?>
								</td>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php if ($row["projects_sold"]==1){ ?>
									<span class="inline-flex px-2 text-xs font-semibold leading-5 text-red-800 bg-red-100 rounded-full"> مُباع</span>
									
									<?php }elseif ($row["projects_sold"]==0){ ?>
									<span class="inline-flex px-2 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">متاح</span>
									
									<?php } ?>
								</td>

								<td class=" flex flex-col space-y-4 px-6 py-4 text-sm font-medium leading-5 text-right whitespace-no-wrap border-b border-gray-200">
									
									<?php if(in_array(2, $unique_permissions)) { ?>	
									<a href="cpanel.php?p=UpdateProject&id=<?php echo $row["projects_id"]; ?>" class="p-3 bg-indigo-100 text-center rounded"><span class="text-indigo-600 hover:text-indigo-900"><i class="fa-solid fa-pen-to-square"></i> تعديل</span></a>
									<?php } ?>
									
									<?php if(in_array(3, $unique_permissions)) { ?>
									<a href="actions.php?t=DeleteProject&id=<?php echo $row["projects_id"]; ?>" class="p-3 bg-red-100 text-center rounded"><span class="text-red-600 hover:text-red-800"><i class="fa-solid fa-trash"></i> حذف</span></a>
									<?php } ?>
									
									
								</td>
							</tr>







							<?php

								}
								endwhile; 

							}				                               



							?>















						</tbody>
					</table>
				</div>
			</div>
		</div>









		<div class="flex justify-center mt-[50px]">
			<div class=" inline-flex justify-center gap-2">

				<?php 
				$n=ceil($x/$totalinpage);
				if($pagenum>1){ ?>
				<a href="cpanel.php?p=ViewProjects&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">السابقة</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
					</svg>
				</a>

				<?php   }else{ ?>

				<a href="#" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-[#d3d3d3] text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">السابقة</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
					</svg>
				</a>
				<?php   }

				if($pagenum>1){ ?>
				<a href="cpanel.php?p=ViewProjects&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php   } ?>


				<?php   if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php   }

				if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewProjects&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php   } ?>


				<?php   if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewProjects&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">التالية</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
					</svg>
				</a>
				<?php   }else{ ?>
				<a href="#" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-[#d3d3d3] text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
					<span class="sr-only">التالية</span>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
					</svg>
				</a>
				<?php   } ?>

			</div>
		</div>


	</div>
</main>