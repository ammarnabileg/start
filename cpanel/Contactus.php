

<?php 

$action=$_GET['act'];
$selected_lead_id=$_GET['id'];

if($action=="del" &isset($_GET['id'])){
	$mysqli->query("UPDATE leads SET 
        leads_type = 0	
        WHERE leads_id   = $selected_lead_id") or die($mysqli->error);
	header("Location: cpanel.php?p=ViewLeads&type=active&page=1");
	die();


}
elseif($action=="rec"&isset($_GET['id'])){
	$mysqli->query("UPDATE leads SET 
        leads_type = 1	
        WHERE leads_id   = $selected_lead_id") or die($mysqli->error);
	header("Location: cpanel.php?p=ViewLeads&type=inactive&page=1");
	die();
}




?>


<style>

	.phonecrm{
		filter: blur(4px);
		webkit-filter: blur(4px);
		transition: 0.1s ease;

	}
	.phonecrm a{
		padding: 0.375rem 0.25rem !important;
	}
	.phonecrm:hover{
		filter: blur(0px);
		webkit-filter: blur(0px);
		transition: 0.3s ease;

	}
</style>







<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">طلبات التواصل</h3>



		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div
					 class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full">
						<thead>
							<tr>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">الاسم</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">الوظيفة</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">رقم الهاتف</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">البريد الإلكتروني</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">الرسالة</th>
							</tr>
						</thead>

						<tbody class="bg-white">













							<?php
	$pagenum=$_GET['page'];
	if(!isset($pagenum)){
		header("Location: cpanel.php?p=Contactus&page=1");
		die();
	}
	$totalinpage=10;
	$finalcount=$pagenum*$totalinpage;
	$startount=$finalcount-($totalinpage-1);

	if(isset($pagenum)){





		$result = $mysqli->query("SELECT * FROM contact") or die($$mysqli->error);
		$x=0;
		while ($row = $result->fetch_assoc()): 
		$x=$x+1;
		if($x>=$startount && $x<=$finalcount){

							?>







							<tr class="text-gray-900">
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php echo $row["contact_name"]; ?>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php echo $row["contact_job"]; ?>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="num-pad flex items-center gap-4 text-gray-900 ">
										<span class=" leading-5 text-gray-900 phonecrm ">
											<?php echo $row["contact_ccode"]; ?><?php echo $row["contact_phone"]; ?>
										</span>
										<a class="btn btn-social-icon text-xl" href="https://wa.me/<?php echo $row["contact_ccode"]; ?><?php echo $row["contact_phone"]; ?>" target="_blank">
											<i class="fa-brands fa-whatsapp"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="tel:<?php echo $row["contact_ccode"]; ?><?php echo $row["contact_phone"]; ?>">
											<i class="fa fa-phone"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="sms:<?php echo $row["contact_ccode"]; ?><?php echo $row["contact_phone"]; ?>">
											<i class="fa-regular fa-comment-dots"></i>
										</a>
									</div>
									
									
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php echo $row["contact_email"]; ?>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<?php echo $row["contact_msg"]; ?>
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
				<a href="cpanel.php?p=ViewLeads&type=active&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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
				<a href="cpanel.php?p=ViewLeads&type=active&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php   } ?>


				<?php   if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php   }

	if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewLeads&type=active&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php   } ?>


				<?php   if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewLeads&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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
















