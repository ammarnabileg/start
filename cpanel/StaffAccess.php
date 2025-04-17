<?php


$page_dir=$_GET['dir'];

if($page_dir=="Roles"){
?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">إدارة الأدوار</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=Roles&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع الأدوار</h4>
							</div>
						</div>
					</a>
				</div>

<?php if(in_array(19, $unique_permissions)) { ?>


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=NewRole">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة دور جديد</h4>
							</div>
						</div>
					</a>
				</div>
<?php } ?>



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
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">اسم الدور</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50"></th>
							</tr>
						</thead>

						<tbody class="bg-white">













							<?php
	$pagenum=$_GET['page'];
	if(!isset($pagenum)){
		header("Location: cpanel.php?p=StaffAccess&dir=Roles&page=1");
		die();
	}
	$totalinpage=10;
	$finalcount=$pagenum*$totalinpage;
	$startount=$finalcount-($totalinpage-1);

	if(isset($pagenum)){





		$result = $mysqli->query("SELECT * FROM roles") or die($$mysqli->error);
		$x=0;
		while ($row = $result->fetch_assoc()): 
		$x=$x+1;
		if($x>=$startount && $x<=$finalcount){

							?>







							<tr>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="font-medium leading-5 text-gray-900">
										<?php echo $row["roles_name"]; ?>
									</div>
								</td>



								<td class="px-6 py-4 text-sm leading-5 tracking-wider text-gray-500 whitespace-no-wrap border-b border-gray-200">
									
<?php if(in_array(17, $unique_permissions)) { ?>

									<a href="cpanel.php?p=StaffAccess&dir=edit_role&id=<?php echo $row["roles_id"]; ?>" ><i class="fa-solid fa-user-slash"></i> تعديل </a>
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
				<a href="cpanel.php?p=StaffAccess&dir=Roles&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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
				<a href="cpanel.php?p=StaffAccess&dir=Roles&page=<?php echo ($pagenum-1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum-1); ?></a>
				<?php   } ?>


				<?php   if($pagenum>=1){ ?>
				<p class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-black text-gray-100 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo $pagenum; ?></p>
				<?php   }

	if($pagenum<$n){ ?>
				<a href="cpanel.php?p=StaffAccess&dir=Roles&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]"><?php echo ($pagenum+1); ?></a>
				<?php   } ?>


				<?php   if($pagenum<$n){ ?>
				<a href="cpanel.php?p=StaffAccess&dir=Roles&page=<?php echo ($pagenum+1); ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">
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

}elseif($page_dir=="NewRole"){

?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">إضافة دور جديد</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=Roles&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع الأدوار</h4>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=NewRole">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة دور جديد</h4>
							</div>
						</div>
					</a>
				</div>


			</div>
		</div>

		<div class="mt-8">

		</div>

		<div class="flex flex-col mt-8">




































			<div class="flex flex-col mt-8">
				<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">




					<div class="bg-white inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">

						<form action="actions.php" method="POST" enctype="multipart/form-data">

							<div class="relative grid px-4 py-4 m-0 overflow-hidden text-center text-white bg-[#cbcbcb] place-items-center rounded-t-xl bg-clip-border shadow-gray-900/20 mb-[50px]">
								<div class="h-20 p-6 text-white">
									<i class="fa-solid fa-user-plus text-3xl"></i>
								</div>
							</div>

							<div class="p-6">
								<div class="block overflow-visible">
									<div class="relative block w-full !overflow-y-visible bg-transparent">
										<div role="tabpanel" class="w-full p-0 font-sans text-base antialiased font-light leading-relaxed text-gray-700 h-max" data-value="card">

											<div class="flex flex-col gap-4">


												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">اسم الدور</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="text" name="rolename" placeholder="اسم الدور" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">الصلاحيات</p>
													<div class="relative w-full min-w-[200px]">

														<select name="permissions[]" multiple class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50 h-[150px] ">


															<option value="1">عرض المشروعات</option>
															<option value="2">تعديل المشروعات</option>
															<option value="3">حذف المشروعات</option>
															<option value="4">إضافة المشروعات</option>
															<option value="5">عرض المقالات</option>
															<option value="6">تعديل المقالات</option>
															<option value="7">حذف المقالات</option>
															<option value="8">إضافة المقالات</option>
															<option value="9">عرض العملاء الجدد</option>
															<option value="10">أرشفة وإلغاء أرشفة العملاء الجدد</option>
															<option value="11">عرض نماذج التواصل</option>
															<option value="12">عرض الموظفين</option>
															<option value="13">تعديل الموظفين</option>
															<option value="14">حذف الموظفين</option>
															<option value="15">إضافة الموظفين</option>
															<option value="16">عرض الصلاحيات</option>
															<option value="17">تعديل الصلاحيات</option>
															<option value="18">حذف الصلاحيات</option>
															<option value="19">إضافة الصلاحيات</option>
															<option value="20">عرض الأحداث</option>
															<option value="21">إضافة الأحداث</option>
															<option value="22">حذف الأحداث</option>
															<option value="23">تعديل الأحداث</option>
															<option value="24">إنشاء الدعوات</option>
															<option value="25">تعديل الدعوات</option>
															<option value="26">إدارة أنواع التذاكر</option>

														</select>

													</div>
												</div>




											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">


								<input class="w-full select-none rounded-lg bg-gray-900 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none" name="addnewrole" type="submit" value="إضافة الدور">


							</div>

						</form>


					</div>
				</div>
			</div>


















		</div>






	</div>
</main>


<?php

}elseif($page_dir=="edit_role"){


	$role_id=$_GET['id'];

	$page_access=0;
	$result = $mysqli->query("SELECT * FROM roles where roles_id  = '$role_id' ") or die($$mysqli->error);
	if ($result->num_rows > 0) {
		$page_access=1;

		while($row = $result->fetch_assoc()) {
			$roles_name = $row["roles_name"];
			$roles_permissions = $row["roles_permissions"];
			$storedPermissions = explode(',', $row['roles_permissions']);
		}
	}

	if($page_access==0){
		header("Location: cpanel.php?p=StaffAccess&dir=Roles&page=1");
		die();
	}



?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">تعديل الدور (<?php echo $roles_name; ?>)</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=Roles&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع الأدوار</h4>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=StaffAccess&dir=NewRole">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة دور جديد</h4>
							</div>
						</div>
					</a>
				</div>



			</div>
		</div>

		<div class="mt-8">

		</div>

		<div class="flex flex-col mt-8">












			<div class="flex flex-col mt-8">
				<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">




					<div class="bg-white inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">

						<form action="actions.php" method="POST" enctype="multipart/form-data">

							<div class="relative grid px-4 py-4 m-0 overflow-hidden text-center text-white bg-[#cbcbcb] place-items-center rounded-t-xl bg-clip-border shadow-gray-900/20 mb-[50px]">
								<div class="h-20 p-6 text-white">
									<i class="fa-solid fa-user-plus text-3xl"></i>
								</div>
							</div>

							<div class="p-6">
								<div class="block overflow-visible">
									<div class="relative block w-full !overflow-y-visible bg-transparent">
										<div role="tabpanel" class="w-full p-0 font-sans text-base antialiased font-light leading-relaxed text-gray-700 h-max" data-value="card">

											<div class="flex flex-col gap-4">


												<div class="hidden">
													<div class=" h-0 w-0 min-w-[0]">
														<input type="text" name="roleid" value="<?php echo $role_id; ?>" >
													</div>
												</div>
												
												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">اسم الدور</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="text" name="rolename" value="<?php echo $roles_name; ?>" placeholder="اسم الدور" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">الصلاحيات</p>
													<div class="relative w-full min-w-[200px]">

														<select name="permissions[]" multiple class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50 h-[150px] ">


    <option value="1" <?php if(in_array(1, $storedPermissions)) echo 'selected'; ?>>عرض المشروعات</option>
    <option value="2" <?php if(in_array(2, $storedPermissions)) echo 'selected'; ?>>تعديل المشروعات</option>
    <option value="3" <?php if(in_array(3, $storedPermissions)) echo 'selected'; ?>>حذف المشروعات</option>
    <option value="4" <?php if(in_array(4, $storedPermissions)) echo 'selected'; ?>>إضافة المشروعات</option>
    <option value="5" <?php if(in_array(5, $storedPermissions)) echo 'selected'; ?>>عرض المقالات</option>
    <option value="6" <?php if(in_array(6, $storedPermissions)) echo 'selected'; ?>>تعديل المقالات</option>
    <option value="7" <?php if(in_array(7, $storedPermissions)) echo 'selected'; ?>>حذف المقالات</option>
    <option value="8" <?php if(in_array(8, $storedPermissions)) echo 'selected'; ?>>إضافة المقالات</option>
    <option value="9" <?php if(in_array(9, $storedPermissions)) echo 'selected'; ?>>عرض العملاء الجدد</option>
    <option value="10" <?php if(in_array(10, $storedPermissions)) echo 'selected'; ?>>أرشفة وإلغاء أرشفة العملاء الجدد</option>
    <option value="11" <?php if(in_array(11, $storedPermissions)) echo 'selected'; ?>>عرض نماذج التواصل</option>
    <option value="12" <?php if(in_array(12, $storedPermissions)) echo 'selected'; ?>>عرض الموظفين</option>
    <option value="13" <?php if(in_array(13, $storedPermissions)) echo 'selected'; ?>>تعديل الموظفين</option>
    <option value="14" <?php if(in_array(14, $storedPermissions)) echo 'selected'; ?>>حذف الموظفين</option>
    <option value="15" <?php if(in_array(15, $storedPermissions)) echo 'selected'; ?>>إضافة الموظفين</option>
    <option value="16" <?php if(in_array(16, $storedPermissions)) echo 'selected'; ?>>عرض الصلاحيات</option>
    <option value="17" <?php if(in_array(17, $storedPermissions)) echo 'selected'; ?>>تعديل الصلاحيات</option>
    <option value="18" <?php if(in_array(18, $storedPermissions)) echo 'selected'; ?>>حذف الصلاحيات</option>
    <option value="19" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>إضافة الصلاحيات</option>
    <option value="20" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>عرض الأحداث</option>
    <option value="21" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>إضافة الأحداث</option>
    <option value="22" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>حذف الأحداث</option>
    <option value="23" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>تعديل الأحداث</option>
    <option value="24" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>إنشاء الدعوات</option>
    <option value="25" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>تعديل الدعوات</option>
    <option value="26" <?php if(in_array(19, $storedPermissions)) echo 'selected'; ?>>إدارة أنواع التذاكر</option>

														</select>

													</div>
												</div>




											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">


								<input class="w-full select-none rounded-lg bg-gray-900 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none" name="updaterole" type="submit" value="تحديث الدور">


							</div>

						</form>
















					</div>
				</div>

<?php if($role_id!=1){ ?>
<?php if(in_array(18, $unique_permissions)) { ?>

				<a href="actions.php?t=Deleterole&id=<?php echo $role_id; ?>" class="mt-[100px] w-full select-none rounded-lg bg-red-500 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none"> حذف الدور </a>

<?php }} ?>


			</div>


















		</div>






	</div>
</main>


<?php
}
?>
























