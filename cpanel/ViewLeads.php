

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





$active_leads=0;
$inactive_leads=0;
$result = $mysqli->query("SELECT * FROM leads") or die($$mysqli->error);
while ($row = $result->fetch_assoc()){
	if($row["leads_type"]==1){$active_leads+=1;}
	if($row["leads_type"]==0){$inactive_leads+=1;}
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

















<?php


$lead_type=$_GET['type'];

if($lead_type=="active"){
?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">العملاء الجدد</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewLeads&type=active&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full">
								<svg class="w-8 h-8 text-white" viewBox="0 0 28 30" fill="none"
									 xmlns="http://www.w3.org/2000/svg">
									<path
										  d="M18.2 9.08889C18.2 11.5373 16.3196 13.5222 14 13.5222C11.6804 13.5222 9.79999 11.5373 9.79999 9.08889C9.79999 6.64043 11.6804 4.65556 14 4.65556C16.3196 4.65556 18.2 6.64043 18.2 9.08889Z"
										  fill="currentColor"></path>
									<path
										  d="M25.2 12.0444C25.2 13.6768 23.9464 15 22.4 15C20.8536 15 19.6 13.6768 19.6 12.0444C19.6 10.4121 20.8536 9.08889 22.4 9.08889C23.9464 9.08889 25.2 10.4121 25.2 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M19.6 22.3889C19.6 19.1243 17.0927 16.4778 14 16.4778C10.9072 16.4778 8.39999 19.1243 8.39999 22.3889V26.8222H19.6V22.3889Z"
										  fill="currentColor"></path>
									<path
										  d="M8.39999 12.0444C8.39999 13.6768 7.14639 15 5.59999 15C4.05359 15 2.79999 13.6768 2.79999 12.0444C2.79999 10.4121 4.05359 9.08889 5.59999 9.08889C7.14639 9.08889 8.39999 10.4121 8.39999 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M22.4 26.8222V22.3889C22.4 20.8312 22.0195 19.3671 21.351 18.0949C21.6863 18.0039 22.0378 17.9556 22.4 17.9556C24.7197 17.9556 26.6 19.9404 26.6 22.3889V26.8222H22.4Z"
										  fill="currentColor"></path>
									<path
										  d="M6.64896 18.0949C5.98058 19.3671 5.59999 20.8312 5.59999 22.3889V26.8222H1.39999V22.3889C1.39999 19.9404 3.2804 17.9556 5.59999 17.9556C5.96219 17.9556 6.31367 18.0039 6.64896 18.0949Z"
										  fill="currentColor"></path>
								</svg>
							</div>

							<div class="mx-5">
								<h4 class="text-2xl font-semibold text-gray-700"><?php echo $active_leads; ?></h4>
								<div class="text-gray-500">عميل</div>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewLeads&type=inactive&page=1">

						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-gray-600 bg-opacity-75 rounded-full">
								<svg class="w-8 h-8 text-white" viewBox="0 0 28 30" fill="none"
									 xmlns="http://www.w3.org/2000/svg">
									<path
										  d="M18.2 9.08889C18.2 11.5373 16.3196 13.5222 14 13.5222C11.6804 13.5222 9.79999 11.5373 9.79999 9.08889C9.79999 6.64043 11.6804 4.65556 14 4.65556C16.3196 4.65556 18.2 6.64043 18.2 9.08889Z"
										  fill="currentColor"></path>
									<path
										  d="M25.2 12.0444C25.2 13.6768 23.9464 15 22.4 15C20.8536 15 19.6 13.6768 19.6 12.0444C19.6 10.4121 20.8536 9.08889 22.4 9.08889C23.9464 9.08889 25.2 10.4121 25.2 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M19.6 22.3889C19.6 19.1243 17.0927 16.4778 14 16.4778C10.9072 16.4778 8.39999 19.1243 8.39999 22.3889V26.8222H19.6V22.3889Z"
										  fill="currentColor"></path>
									<path
										  d="M8.39999 12.0444C8.39999 13.6768 7.14639 15 5.59999 15C4.05359 15 2.79999 13.6768 2.79999 12.0444C2.79999 10.4121 4.05359 9.08889 5.59999 9.08889C7.14639 9.08889 8.39999 10.4121 8.39999 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M22.4 26.8222V22.3889C22.4 20.8312 22.0195 19.3671 21.351 18.0949C21.6863 18.0039 22.0378 17.9556 22.4 17.9556C24.7197 17.9556 26.6 19.9404 26.6 22.3889V26.8222H22.4Z"
										  fill="currentColor"></path>
									<path
										  d="M6.64896 18.0949C5.98058 19.3671 5.59999 20.8312 5.59999 22.3889V26.8222H1.39999V22.3889C1.39999 19.9404 3.2804 17.9556 5.59999 17.9556C5.96219 17.9556 6.31367 18.0039 6.64896 18.0949Z"
										  fill="currentColor"></path>
								</svg>
							</div>

							<div class="mx-5">
								<h4 class="text-2xl font-semibold text-gray-700"><?php echo $inactive_leads; ?></h4>
								<div class="text-gray-500">الأرشيف</div>
							</div>
						</div>
					</a>
				</div>



			</div>
		</div>

		<div class="mt-8">

		</div>

		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div
					 class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full">
						<thead>
							<tr>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الاسم</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									رقم الهاتف</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									رابط التتبع</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50"></th>
							</tr>
						</thead>

						<tbody class="bg-white">













							<?php
	$pagenum=$_GET['page'];
	if(!isset($pagenum)){
		header("Location: cpanel.php?p=ViewLeads&page=1");
		die();
	}
	$totalinpage=10;
	$finalcount=$pagenum*$totalinpage;
	$startount=$finalcount-($totalinpage-1);

	if(isset($pagenum)){





		$result = $mysqli->query("SELECT * FROM leads where leads_type=1 order by leads_id desc") or die($$mysqli->error);
		$x=0;
		while ($row = $result->fetch_assoc()): 
		$x=$x+1;
		if($x>=$startount && $x<=$finalcount){

							?>







							<tr>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="flex gap-2 items-center">
										<div class="flex justify-center items-center bg-[#f1d293] rounded-full w-10 h-10">
											<i class="fa-solid fa-face-smile text-black"></i>
										</div>

										<div class="ml-4">
											<div class="font-medium leading-5 text-gray-900">
												<?php echo $row["leads_name"]; ?>
											</div>
										</div>
									</div>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="num-pad flex items-center gap-4 text-gray-900 ">
										<span class=" leading-5 text-gray-900 phonecrm ">
											<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>
										</span>
										<a class="btn btn-social-icon text-xl" href="https://wa.me/<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>" target="_blank">
											<i class="fa-brands fa-whatsapp"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="tel:<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>">
											<i class="fa fa-phone"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="sms:<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>">
											<i class="fa-regular fa-comment-dots"></i>
										</a>
									</div>
								</td>



								<td
									class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-no-wrap border-b border-gray-200">
									<a href="<?php echo $row["leads_location"]; ?>" ><?php echo $row["leads_location"]; ?></a></td>


								<td class="px-6 py-4 text-sm leading-5 tracking-wider text-gray-500 whitespace-no-wrap border-b border-gray-200">
<?php if(in_array(10, $unique_permissions)) { ?>
									<a href="cpanel.php?p=ViewLeads&act=del&id=<?php echo $row["leads_id"]; ?>" ><i class="fa-solid fa-user-slash"></i> إضافة الأرشيف </a>
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
				<a href="cpanel.php?p=ViewLeads&type=active&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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

<?php

}elseif($lead_type=="inactive"){

?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">أرشيف العملاء</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewLeads&type=active&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full">
								<svg class="w-8 h-8 text-white" viewBox="0 0 28 30" fill="none"
									 xmlns="http://www.w3.org/2000/svg">
									<path
										  d="M18.2 9.08889C18.2 11.5373 16.3196 13.5222 14 13.5222C11.6804 13.5222 9.79999 11.5373 9.79999 9.08889C9.79999 6.64043 11.6804 4.65556 14 4.65556C16.3196 4.65556 18.2 6.64043 18.2 9.08889Z"
										  fill="currentColor"></path>
									<path
										  d="M25.2 12.0444C25.2 13.6768 23.9464 15 22.4 15C20.8536 15 19.6 13.6768 19.6 12.0444C19.6 10.4121 20.8536 9.08889 22.4 9.08889C23.9464 9.08889 25.2 10.4121 25.2 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M19.6 22.3889C19.6 19.1243 17.0927 16.4778 14 16.4778C10.9072 16.4778 8.39999 19.1243 8.39999 22.3889V26.8222H19.6V22.3889Z"
										  fill="currentColor"></path>
									<path
										  d="M8.39999 12.0444C8.39999 13.6768 7.14639 15 5.59999 15C4.05359 15 2.79999 13.6768 2.79999 12.0444C2.79999 10.4121 4.05359 9.08889 5.59999 9.08889C7.14639 9.08889 8.39999 10.4121 8.39999 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M22.4 26.8222V22.3889C22.4 20.8312 22.0195 19.3671 21.351 18.0949C21.6863 18.0039 22.0378 17.9556 22.4 17.9556C24.7197 17.9556 26.6 19.9404 26.6 22.3889V26.8222H22.4Z"
										  fill="currentColor"></path>
									<path
										  d="M6.64896 18.0949C5.98058 19.3671 5.59999 20.8312 5.59999 22.3889V26.8222H1.39999V22.3889C1.39999 19.9404 3.2804 17.9556 5.59999 17.9556C5.96219 17.9556 6.31367 18.0039 6.64896 18.0949Z"
										  fill="currentColor"></path>
								</svg>
							</div>

							<div class="mx-5">
								<h4 class="text-2xl font-semibold text-gray-700"><?php echo $active_leads; ?></h4>
								<div class="text-gray-500">عميل</div>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewLeads&type=inactive&page=1">

						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-gray-600 bg-opacity-75 rounded-full">
								<svg class="w-8 h-8 text-white" viewBox="0 0 28 30" fill="none"
									 xmlns="http://www.w3.org/2000/svg">
									<path
										  d="M18.2 9.08889C18.2 11.5373 16.3196 13.5222 14 13.5222C11.6804 13.5222 9.79999 11.5373 9.79999 9.08889C9.79999 6.64043 11.6804 4.65556 14 4.65556C16.3196 4.65556 18.2 6.64043 18.2 9.08889Z"
										  fill="currentColor"></path>
									<path
										  d="M25.2 12.0444C25.2 13.6768 23.9464 15 22.4 15C20.8536 15 19.6 13.6768 19.6 12.0444C19.6 10.4121 20.8536 9.08889 22.4 9.08889C23.9464 9.08889 25.2 10.4121 25.2 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M19.6 22.3889C19.6 19.1243 17.0927 16.4778 14 16.4778C10.9072 16.4778 8.39999 19.1243 8.39999 22.3889V26.8222H19.6V22.3889Z"
										  fill="currentColor"></path>
									<path
										  d="M8.39999 12.0444C8.39999 13.6768 7.14639 15 5.59999 15C4.05359 15 2.79999 13.6768 2.79999 12.0444C2.79999 10.4121 4.05359 9.08889 5.59999 9.08889C7.14639 9.08889 8.39999 10.4121 8.39999 12.0444Z"
										  fill="currentColor"></path>
									<path
										  d="M22.4 26.8222V22.3889C22.4 20.8312 22.0195 19.3671 21.351 18.0949C21.6863 18.0039 22.0378 17.9556 22.4 17.9556C24.7197 17.9556 26.6 19.9404 26.6 22.3889V26.8222H22.4Z"
										  fill="currentColor"></path>
									<path
										  d="M6.64896 18.0949C5.98058 19.3671 5.59999 20.8312 5.59999 22.3889V26.8222H1.39999V22.3889C1.39999 19.9404 3.2804 17.9556 5.59999 17.9556C5.96219 17.9556 6.31367 18.0039 6.64896 18.0949Z"
										  fill="currentColor"></path>
								</svg>
							</div>

							<div class="mx-5">
								<h4 class="text-2xl font-semibold text-gray-700"><?php echo $inactive_leads; ?></h4>
								<div class="text-gray-500">الأرشيف</div>
							</div>
						</div>
					</a>
				</div>




			</div>
		</div>

		<div class="mt-8">

		</div>

		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div
					 class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full">
						<thead>
							<tr>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الاسم</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									رقم الهاتف</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									رابط التتبع</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50"></th>
							</tr>
						</thead>

						<tbody class="bg-white">













							<?php
	$pagenum=$_GET['page'];
	if(!isset($pagenum)){
		header("Location: cpanel.php?p=ViewLeads&page=1");
		die();
	}
	$totalinpage=10;
	$finalcount=$pagenum*$totalinpage;
	$startount=$finalcount-($totalinpage-1);

	if(isset($pagenum)){





		$result = $mysqli->query("SELECT * FROM leads where leads_type=0 order by leads_id desc") or die($$mysqli->error);
		$x=0;
		while ($row = $result->fetch_assoc()): 
		$x=$x+1;
		if($x>=$startount && $x<=$finalcount){

							?>







							<tr>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="flex gap-2 items-center">
										<div class="flex justify-center items-center bg-[#f1d293] rounded-full w-10 h-10">
											<i class="fa-solid fa-face-smile text-black"></i>
										</div>

										<div class="ml-4">
											<div class="font-medium leading-5 text-gray-900">
												<?php echo $row["leads_name"]; ?>
											</div>
										</div>
									</div>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="num-pad flex items-center gap-4 text-gray-900 ">
										<span class=" leading-5 text-gray-900 phonecrm ">
											<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>
										</span>
										<a class="btn btn-social-icon text-xl" href="https://wa.me/<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>" target="_blank">
											<i class="fa-brands fa-whatsapp"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="tel:<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>">
											<i class="fa fa-phone"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="sms:<?php echo $row["leads_c_code"]; ?><?php echo $row["leads_phone"]; ?>">
											<i class="fa-regular fa-comment-dots"></i>
										</a>
									</div>
								</td>



								<td
									class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-no-wrap border-b border-gray-200">
									<a href="<?php echo $row["leads_location"]; ?>" ><?php echo $row["leads_location"]; ?></a></td>


								<td class="px-6 py-4 text-sm leading-5 tracking-wider text-gray-500 whitespace-no-wrap border-b border-gray-200">

<?php if(in_array(10, $unique_permissions)) { ?>
									<a href="cpanel.php?p=ViewLeads&act=rec&id=<?php echo $row["leads_id"]; ?>" ><i class="fa-solid fa-user-check"></i> إستخراج من الأرشيف </a>
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
				<a href="cpanel.php?p=ViewLeads&type=inactive&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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
				<a href="cpanel.php?p=ViewLeads&type=inactive&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php   } ?>


				<?php   if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php   }

	if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewLeads&type=inactive&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php   } ?>


				<?php   if($pagenum<$n){ ?>
				<a href="cpanel.php?p=ViewLeads&type=inactive&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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


<?php
}
?>

















