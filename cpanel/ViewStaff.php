<?php 

$result = $mysqli->query("SELECT * FROM users") or die($$mysqli->error);
while ($row = $result->fetch_assoc()){

}

?>





<?php


$page_dir=$_GET['dir'];

if($page_dir=="users"){
?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">إدارة المستخدمين</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=users&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع المستخدمين</h4>
							</div>
						</div>
					</a>
				</div>

<?php if(in_array(15, $unique_permissions)) { ?>


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=new_user&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة مستخدم</h4>
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
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الاسم</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الكود </th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									البريد الالكتروني</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									التواصل</th>
								<th
									class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50">
									الدور</th>
								<th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase border-b border-gray-200 bg-gray-50"></th>
							</tr>
						</thead>

						<tbody class="bg-white">













							<?php
	$pagenum=$_GET['page'];
	if(!isset($pagenum)){
		header("Location: cpanel.php?p=ViewStaff&dir=users&page=1");
		die();
	}
	$totalinpage=10;
	$finalcount=$pagenum*$totalinpage;
	$startount=$finalcount-($totalinpage-1);

	if(isset($pagenum)){





		$result = $mysqli->query("SELECT * FROM users") or die($$mysqli->error);
		$x=0;
		while ($row = $result->fetch_assoc()): 
		$x=$x+1;
		if($x>=$startount && $x<=$finalcount){

							?>







							<tr>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="font-medium leading-5 text-gray-900">
										<?php echo $row["users_name"]; ?>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="font-medium leading-5 text-gray-900">
										<?php echo $row["users_id"]; ?>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="font-medium leading-5 text-gray-900">
										<?php echo $row["users_email"]; ?>
									</div>
								</td>

								<td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
									<div class="num-pad flex items-center gap-4 text-gray-900 ">
										<span class=" leading-5 text-gray-900 phonecrm ">
											<?php echo $row["users_ccode"]; ?><?php echo $row["users_phone"]; ?>
										</span>
										<a class="btn btn-social-icon text-xl" href="https://wa.me/<?php echo $row["users_ccode"]; ?><?php echo $row["users_phone"]; ?>" target="_blank">
											<i class="fa-brands fa-whatsapp"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="tel:<?php echo $row["users_ccode"]; ?><?php echo $row["users_phone"]; ?>">
											<i class="fa fa-phone"></i>
										</a>
										<a class="btn btn-social-icon text-xl" href="sms:<?php echo $row["users_ccode"]; ?><?php echo $row["users_phone"]; ?>">
											<i class="fa-regular fa-comment-dots"></i>
										</a>
									</div>
								</td>



								<td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-no-wrap border-b border-gray-200">
									<?php
										$users_access = explode(',', $row['users_access']);

	
										$result1 = $mysqli->query("SELECT * FROM roles") or die($$mysqli->error);
										while ($row1 = $result1->fetch_assoc()) {
											$roles[$row1["roles_id"]] = $row1["roles_name"];
										}

										foreach ($users_access as $access) {
											if (isset($roles[$access])) {
												echo $roles[$access] . '<br>';
											} else {
												echo "لا أدوار";
											}
										}

									?>
								</td>


								<td class="px-6 py-4 text-sm leading-5 tracking-wider text-gray-500 whitespace-no-wrap border-b border-gray-200">

<?php if(in_array(13, $unique_permissions)) { ?>

									<a href="cpanel.php?p=ViewStaff&dir=edit_user&id=<?php echo $row["users_id"]; ?>" ><i class="fa-solid fa-user-slash"></i> تعديل </a>
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

<?php

}elseif($page_dir=="new_user"){

?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">إضافة مستخدم</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=users&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع المستخدمين</h4>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=new_user&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة مستخدم</h4>
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
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">الاسم</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="text" name="name" placeholder="اسم المستخدم" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">البريد الإلكتروني</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="email" name="email" placeholder="البريد الإلكتروني" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">رقم الموبايل</p>


													<div class="relative flex h-10 w-full min-w-[200px]">

														<div class="absolute inset-y-0 left-0 flex items-center selectbox">
															<select required="required" id="country_code" name="users_ccode" class=" min-h-[40px] max-w-[100px]  sm:max-w-[135px] h-full rounded-md block w-full rounded border-0 py-2 px-3.5 pl-3  shadow-sm placeholder-[#000] bg-[#86868617] dark:text-[#000] text-[#000] ">
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
														<input type="number" name="users_phone" placeholder="رقم الموبايل" class=" pl-24 sm:pl-32  text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">كلمة المرور الإفتراضية</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="text" name="rand_password" placeholder="أكتب كلمة مرور هنا" value="<?php echo(rand(19999999,99999999)); ?>" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												
												
												
												
					
												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">أدوار المستخدم</p>
													<div class="relative  w-full min-w-[200px]">
														<select name="roles[]" multiple="" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50 h-[150px] ">
															
<?php
	
			$result1 = $mysqli->query("SELECT * FROM roles") or die($$mysqli->error);
				while ($row1 = $result1->fetch_assoc()){
															
															?>															
															
															<option value="<?php echo $row1["roles_id"]; ?>" >
																<?php echo $row1["roles_name"]; ?>
															</option>
															
															<?php } 
															?>
															
															
														</select>
													</div>
												</div>
		
																								
												
												
												
												
												
												




											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">


								<input class="w-full select-none rounded-lg bg-gray-900 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none" name="addnewuser" type="submit" value="إضافة المستخدم">


							</div>

						</form>


					</div>
				</div>
			</div>


















		</div>






	</div>
</main>


<?php

							  }elseif($page_dir=="edit_user"){


	$usr_id=$_GET['id'];
	//cpanel.php?p=ViewStaff&dir=edit_user&id=1

	$page_access=0;
	$result = $mysqli->query("SELECT * FROM users where users_id = '$usr_id' ") or die($$mysqli->error);
	if ($result->num_rows > 0) {
		$page_access=1;

		while($row = $result->fetch_assoc()) {
			$users_name = $row["users_name"];
			$users_email = $row["users_email"];
			$users_ccode = $row["users_ccode"];
			$users_phone = $row["users_phone"];
			$users_password = $row["users_password"];
			$storedusers_access = explode(',', $row['users_access']);
		}
	}

	
	if($page_access==0){
		header("Location: cpanel.php?p=ViewStaff&dir=users&page=1");
		die();
	}



?>



<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">تعديل المستخدم (<?php echo $users_name; ?>)</h3>

		<div class="mt-4">
			<div class="flex flex-wrap -mx-6">


				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=users&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-indigo-600 bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-users text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">جميع المستخدمين</h4>
							</div>
						</div>
					</a>
				</div>



				<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
					<a href="cpanel.php?p=ViewStaff&dir=new_user&page=1">
						<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
							<div class="p-3 bg-[#46e562bf] bg-opacity-75 rounded-full w-16 h-16  items-center flex">
								<i class="fa-solid fa-user-plus text-xl mx-auto"></i>
							</div>
							<div class="mx-5">
								<h4 class="text-xl font-semibold text-gray-700">إضافة مستخدم</h4>
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
									<i class="fa-solid fa-user-pen text-3xl"></i>
								</div>
							</div>

							<div class="p-6">
								<div class="block overflow-visible">
									<div class="relative block w-full !overflow-y-visible bg-transparent">
										<div role="tabpanel" class="w-full p-0 font-sans text-base antialiased font-light leading-relaxed text-gray-700 h-max" data-value="card">

											<div class="flex flex-col gap-4">


												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">الاسم</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="text" name="name" value="<?php echo $users_name; ?>" placeholder="اسم المستخدم" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">البريد الإلكتروني</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="email" name="email" value="<?php echo $users_email; ?>" placeholder="البريد الإلكتروني" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">رقم الموبايل</p>


													<div class="relative flex h-10 w-full min-w-[200px]">

														<div class="absolute inset-y-0 left-0 flex items-center selectbox">
															<select required="required" id="country_code" name="country_code" class=" min-h-[40px] max-w-[100px]  sm:max-w-[135px] h-full rounded-md block w-full rounded border-0 py-2 px-3.5 pl-3  shadow-sm placeholder-[#000] bg-[#86868617] dark:text-[#000] text-[#000] ">
																<option                       Value=""  <?php if( $users_ccode==''){echo 'selected';}?>   >select</option>
																<option data-countrycode="EG" Value="20"  <?php if( $users_ccode=='20'){echo 'selected';}?>   >Egypt (+20)</option>
																<option data-countrycode="KW" Value="965"  <?php if( $users_ccode=='965'){echo 'selected';}?>   >Kuwait (+965)</option>
																<option data-countrycode="SA" Value="966"  <?php if( $users_ccode=='966'){echo 'selected';}?>   >Saudi Arabia (+966)</option>
																<option data-countrycode="AE" Value="971"  <?php if( $users_ccode=='971'){echo 'selected';}?>   >United Arab Emirates (+971)</option>
																<option data-countrycode="IL" Value="972"  <?php if( $users_ccode=='972'){echo 'selected';}?>   >Palestine (+972)</option>
																<option data-countrycode="DZ" Value="213"  <?php if( $users_ccode=='213'){echo 'selected';}?>   >Algeria (+213)</option>
																<option data-countrycode="AD" Value="376"  <?php if( $users_ccode=='376'){echo 'selected';}?>   >Andorra (+376)</option>
																<option data-countrycode="AO" Value="244"  <?php if( $users_ccode=='244'){echo 'selected';}?>   >Angola (+244)</option>
																<option data-countrycode="AI" Value="1264"  <?php if( $users_ccode=='1264'){echo 'selected';}?>   >Anguilla (+1264)</option>
																<option data-countrycode="AG" Value="1268"  <?php if( $users_ccode=='1268'){echo 'selected';}?>   >Antigua &amp; Barbuda (+1268)</option>
																<option data-countrycode="AR" Value="54"  <?php if( $users_ccode=='54'){echo 'selected';}?>   >Argentina (+54)</option>
																<option data-countrycode="AM" Value="374"  <?php if( $users_ccode=='374'){echo 'selected';}?>   >Armenia (+374)</option>
																<option data-countrycode="AW" Value="297"  <?php if( $users_ccode=='297'){echo 'selected';}?>   >Aruba (+297)</option>
																<option data-countrycode="AU" Value="61"  <?php if( $users_ccode=='61'){echo 'selected';}?>   >Australia (+61)</option>
																<option data-countrycode="AT" Value="43"  <?php if( $users_ccode=='43'){echo 'selected';}?>   >Austria (+43)</option>
																<option data-countrycode="AZ" Value="994"  <?php if( $users_ccode=='994'){echo 'selected';}?>   >Azerbaijan (+994)</option>
																<option data-countrycode="BS" Value="1242"  <?php if( $users_ccode=='1242'){echo 'selected';}?>   >Bahamas (+1242)</option>
																<option data-countrycode="BH" Value="973"  <?php if( $users_ccode=='973'){echo 'selected';}?>   >Bahrain (+973)</option>
																<option data-countrycode="BD" Value="880"  <?php if( $users_ccode=='880'){echo 'selected';}?>   >Bangladesh (+880)</option>
																<option data-countrycode="BB" Value="1246"  <?php if( $users_ccode=='1246'){echo 'selected';}?>   >Barbados (+1246)</option>
																<option data-countrycode="BY" Value="375"  <?php if( $users_ccode=='375'){echo 'selected';}?>   >Belarus (+375)</option>
																<option data-countrycode="BE" Value="32"  <?php if( $users_ccode=='32'){echo 'selected';}?>   >Belgium (+32)</option>
																<option data-countrycode="BZ" Value="501"  <?php if( $users_ccode=='501'){echo 'selected';}?>   >Belize (+501)</option>
																<option data-countrycode="BJ" Value="229"  <?php if( $users_ccode=='229'){echo 'selected';}?>   >Benin (+229)</option>
																<option data-countrycode="BM" Value="1441"  <?php if( $users_ccode=='1441'){echo 'selected';}?>   >Bermuda (+1441)</option>
																<option data-countrycode="BT" Value="975"  <?php if( $users_ccode=='975'){echo 'selected';}?>   >Bhutan (+975)</option>
																<option data-countrycode="BO" Value="591"  <?php if( $users_ccode=='591'){echo 'selected';}?>   >Bolivia (+591)</option>
																<option data-countrycode="BA" Value="387"  <?php if( $users_ccode=='387'){echo 'selected';}?>   >Bosnia Herzegovina (+387)</option>
																<option data-countrycode="BW" Value="267"  <?php if( $users_ccode=='267'){echo 'selected';}?>   >Botswana (+267)</option>
																<option data-countrycode="BR" Value="55"  <?php if( $users_ccode=='55'){echo 'selected';}?>   >Brazil (+55)</option>
																<option data-countrycode="BN" Value="673"  <?php if( $users_ccode=='673'){echo 'selected';}?>   >Brunei (+673)</option>
																<option data-countrycode="BG" Value="359"  <?php if( $users_ccode=='359'){echo 'selected';}?>   >Bulgaria (+359)</option>
																<option data-countrycode="BF" Value="226"  <?php if( $users_ccode=='226'){echo 'selected';}?>   >Burkina Faso (+226)</option>
																<option data-countrycode="BI" Value="257"  <?php if( $users_ccode=='257'){echo 'selected';}?>   >Burundi (+257)</option>
																<option data-countrycode="KH" Value="855"  <?php if( $users_ccode=='855'){echo 'selected';}?>   >Cambodia (+855)</option>
																<option data-countrycode="CM" Value="237"  <?php if( $users_ccode=='237'){echo 'selected';}?>   >Cameroon (+237)</option>
																<option data-countrycode="CA" Value="1"  <?php if( $users_ccode=='1'){echo 'selected';}?>   >Canada (+1)</option>
																<option data-countrycode="CV" Value="238"  <?php if( $users_ccode=='238'){echo 'selected';}?>   >Cape Verde Islands (+238)</option>
																<option data-countrycode="KY" Value="1345"  <?php if( $users_ccode=='1345'){echo 'selected';}?>   >Cayman Islands (+1345)</option>
																<option data-countrycode="CF" Value="236"  <?php if( $users_ccode=='236'){echo 'selected';}?>   >Central African Republic (+236)</option>
																<option data-countrycode="CL" Value="56"  <?php if( $users_ccode=='56'){echo 'selected';}?>   >Chile (+56)</option>
																<option data-countrycode="CN" Value="86"  <?php if( $users_ccode=='86'){echo 'selected';}?>   >China (+86)</option>
																<option data-countrycode="CO" Value="57"  <?php if( $users_ccode=='57'){echo 'selected';}?>   >Colombia (+57)</option>
																<option data-countrycode="KM" Value="269"  <?php if( $users_ccode=='269'){echo 'selected';}?>   >Comoros (+269)</option>
																<option data-countrycode="CG" Value="242"  <?php if( $users_ccode=='242'){echo 'selected';}?>   >Congo (+242)</option>
																<option data-countrycode="CK" Value="682"  <?php if( $users_ccode=='682'){echo 'selected';}?>   >Cook Islands (+682)</option>
																<option data-countrycode="CR" Value="506"  <?php if( $users_ccode=='506'){echo 'selected';}?>   >Costa Rica (+506)</option>
																<option data-countrycode="HR" Value="385"  <?php if( $users_ccode=='385'){echo 'selected';}?>   >Croatia (+385)</option>
																<option data-countrycode="CU" Value="53"  <?php if( $users_ccode=='53'){echo 'selected';}?>   >Cuba (+53)</option>
																<option data-countrycode="CY" Value="90392"  <?php if( $users_ccode=='90392'){echo 'selected';}?>   >Cyprus North (+90392)</option>
																<option data-countrycode="CY" Value="357"  <?php if( $users_ccode=='357'){echo 'selected';}?>   >Cyprus South (+357)</option>
																<option data-countrycode="CZ" Value="42"  <?php if( $users_ccode=='42'){echo 'selected';}?>   >Czech Republic (+42)</option>
																<option data-countrycode="DK" Value="45"  <?php if( $users_ccode=='45'){echo 'selected';}?>   >Denmark (+45)</option>
																<option data-countrycode="DJ" Value="253"  <?php if( $users_ccode=='253'){echo 'selected';}?>   >Djibouti (+253)</option>
																<option data-countrycode="DM" Value="1809"  <?php if( $users_ccode=='1809'){echo 'selected';}?>   >Dominica (+1809)</option>
																<option data-countrycode="DO" Value="1809"  <?php if( $users_ccode=='1809'){echo 'selected';}?>   >Dominican Republic (+1809)</option>
																<option data-countrycode="EC" Value="593"  <?php if( $users_ccode=='593'){echo 'selected';}?>   >Ecuador (+593)</option>
																<option data-countrycode="SV" Value="503"  <?php if( $users_ccode=='503'){echo 'selected';}?>   >El Salvador (+503)</option>
																<option data-countrycode="GQ" Value="240"  <?php if( $users_ccode=='240'){echo 'selected';}?>   >Equatorial Guinea (+240)</option>
																<option data-countrycode="ER" Value="291"  <?php if( $users_ccode=='291'){echo 'selected';}?>   >Eritrea (+291)</option>
																<option data-countrycode="EE" Value="372"  <?php if( $users_ccode=='372'){echo 'selected';}?>   >Estonia (+372)</option>
																<option data-countrycode="ET" Value="251"  <?php if( $users_ccode=='251'){echo 'selected';}?>   >Ethiopia (+251)</option>
																<option data-countrycode="FK" Value="500"  <?php if( $users_ccode=='500'){echo 'selected';}?>   >Falkland Islands (+500)</option>
																<option data-countrycode="FO" Value="298"  <?php if( $users_ccode=='298'){echo 'selected';}?>   >Faroe Islands (+298)</option>
																<option data-countrycode="FJ" Value="679"  <?php if( $users_ccode=='679'){echo 'selected';}?>   >Fiji (+679)</option>
																<option data-countrycode="FI" Value="358"  <?php if( $users_ccode=='358'){echo 'selected';}?>   >Finland (+358)</option>
																<option data-countrycode="FR" Value="33"  <?php if( $users_ccode=='33'){echo 'selected';}?>   >France (+33)</option>
																<option data-countrycode="GF" Value="594"  <?php if( $users_ccode=='594'){echo 'selected';}?>   >French Guiana (+594)</option>
																<option data-countrycode="PF" Value="689"  <?php if( $users_ccode=='689'){echo 'selected';}?>   >French Polynesia (+689)</option>
																<option data-countrycode="GA" Value="241"  <?php if( $users_ccode=='241'){echo 'selected';}?>   >Gabon (+241)</option>
																<option data-countrycode="GM" Value="220"  <?php if( $users_ccode=='220'){echo 'selected';}?>   >Gambia (+220)</option>
																<option data-countrycode="GE" Value="7880"  <?php if( $users_ccode=='7880'){echo 'selected';}?>   >Georgia (+7880)</option>
																<option data-countrycode="DE" Value="49"  <?php if( $users_ccode=='49'){echo 'selected';}?>   >Germany (+49)</option>
																<option data-countrycode="GH" Value="233"  <?php if( $users_ccode=='233'){echo 'selected';}?>   >Ghana (+233)</option>
																<option data-countrycode="GI" Value="350"  <?php if( $users_ccode=='350'){echo 'selected';}?>   >Gibraltar (+350)</option>
																<option data-countrycode="GR" Value="30"  <?php if( $users_ccode=='30'){echo 'selected';}?>   >Greece (+30)</option>
																<option data-countrycode="GL" Value="299"  <?php if( $users_ccode=='299'){echo 'selected';}?>   >Greenland (+299)</option>
																<option data-countrycode="GD" Value="1473"  <?php if( $users_ccode=='1473'){echo 'selected';}?>   >Grenada (+1473)</option>
																<option data-countrycode="GP" Value="590"  <?php if( $users_ccode=='590'){echo 'selected';}?>   >Guadeloupe (+590)</option>
																<option data-countrycode="GU" Value="671"  <?php if( $users_ccode=='671'){echo 'selected';}?>   >Guam (+671)</option>
																<option data-countrycode="GT" Value="502"  <?php if( $users_ccode=='502'){echo 'selected';}?>   >Guatemala (+502)</option>
																<option data-countrycode="GN" Value="224"  <?php if( $users_ccode=='224'){echo 'selected';}?>   >Guinea (+224)</option>
																<option data-countrycode="GW" Value="245"  <?php if( $users_ccode=='245'){echo 'selected';}?>   >Guinea - Bissau (+245)</option>
																<option data-countrycode="GY" Value="592"  <?php if( $users_ccode=='592'){echo 'selected';}?>   >Guyana (+592)</option>
																<option data-countrycode="HT" Value="509"  <?php if( $users_ccode=='509'){echo 'selected';}?>   >Haiti (+509)</option>
																<option data-countrycode="HN" Value="504"  <?php if( $users_ccode=='504'){echo 'selected';}?>   >Honduras (+504)</option>
																<option data-countrycode="HK" Value="852"  <?php if( $users_ccode=='852'){echo 'selected';}?>   >Hong Kong (+852)</option>
																<option data-countrycode="HU" Value="36"  <?php if( $users_ccode=='36'){echo 'selected';}?>   >Hungary (+36)</option>
																<option data-countrycode="IS" Value="354"  <?php if( $users_ccode=='354'){echo 'selected';}?>   >Iceland (+354)</option>
																<option data-countrycode="IN" Value="91"  <?php if( $users_ccode=='91'){echo 'selected';}?>   >India (+91)</option>
																<option data-countrycode="ID" Value="62"  <?php if( $users_ccode=='62'){echo 'selected';}?>   >Indonesia (+62)</option>
																<option data-countrycode="IR" Value="98"  <?php if( $users_ccode=='98'){echo 'selected';}?>   >Iran (+98)</option>
																<option data-countrycode="IQ" Value="964"  <?php if( $users_ccode=='964'){echo 'selected';}?>   >Iraq (+964)</option>
																<option data-countrycode="IE" Value="353"  <?php if( $users_ccode=='353'){echo 'selected';}?>   >Ireland (+353)</option>
																<option data-countrycode="IT" Value="39"  <?php if( $users_ccode=='39'){echo 'selected';}?>   >Italy (+39)</option>
																<option data-countrycode="JM" Value="1876"  <?php if( $users_ccode=='1876'){echo 'selected';}?>   >Jamaica (+1876)</option>
																<option data-countrycode="JP" Value="81"  <?php if( $users_ccode=='81'){echo 'selected';}?>   >Japan (+81)</option>
																<option data-countrycode="JO" Value="962"  <?php if( $users_ccode=='962'){echo 'selected';}?>   >Jordan (+962)</option>
																<option data-countrycode="KZ" Value="7"  <?php if( $users_ccode=='7'){echo 'selected';}?>   >Kazakhstan (+7)</option>
																<option data-countrycode="KE" Value="254"  <?php if( $users_ccode=='254'){echo 'selected';}?>   >Kenya (+254)</option>
																<option data-countrycode="KI" Value="686"  <?php if( $users_ccode=='686'){echo 'selected';}?>   >Kiribati (+686)</option>
																<option data-countrycode="KP" Value="850"  <?php if( $users_ccode=='850'){echo 'selected';}?>   >Korea North (+850)</option>
																<option data-countrycode="KR" Value="82"  <?php if( $users_ccode=='82'){echo 'selected';}?>   >Korea South (+82)</option>
																<option data-countrycode="KG" Value="996"  <?php if( $users_ccode=='996'){echo 'selected';}?>   >Kyrgyzstan (+996)</option>
																<option data-countrycode="LA" Value="856"  <?php if( $users_ccode=='856'){echo 'selected';}?>   >Laos (+856)</option>
																<option data-countrycode="LV" Value="371"  <?php if( $users_ccode=='371'){echo 'selected';}?>   >Latvia (+371)</option>
																<option data-countrycode="LB" Value="961"  <?php if( $users_ccode=='961'){echo 'selected';}?>   >Lebanon (+961)</option>
																<option data-countrycode="LS" Value="266"  <?php if( $users_ccode=='266'){echo 'selected';}?>   >Lesotho (+266)</option>
																<option data-countrycode="LR" Value="231"  <?php if( $users_ccode=='231'){echo 'selected';}?>   >Liberia (+231)</option>
																<option data-countrycode="LY" Value="218"  <?php if( $users_ccode=='218'){echo 'selected';}?>   >Libya (+218)</option>
																<option data-countrycode="LI" Value="417"  <?php if( $users_ccode=='417'){echo 'selected';}?>   >Liechtenstein (+417)</option>
																<option data-countrycode="LT" Value="370"  <?php if( $users_ccode=='370'){echo 'selected';}?>   >Lithuania (+370)</option>
																<option data-countrycode="LU" Value="352"  <?php if( $users_ccode=='352'){echo 'selected';}?>   >Luxembourg (+352)</option>
																<option data-countrycode="MO" Value="853"  <?php if( $users_ccode=='853'){echo 'selected';}?>   >Macao (+853)</option>
																<option data-countrycode="MK" Value="389"  <?php if( $users_ccode=='389'){echo 'selected';}?>   >Macedonia (+389)</option>
																<option data-countrycode="MG" Value="261"  <?php if( $users_ccode=='261'){echo 'selected';}?>   >Madagascar (+261)</option>
																<option data-countrycode="MW" Value="265"  <?php if( $users_ccode=='265'){echo 'selected';}?>   >Malawi (+265)</option>
																<option data-countrycode="MY" Value="60"  <?php if( $users_ccode=='60'){echo 'selected';}?>   >Malaysia (+60)</option>
																<option data-countrycode="MV" Value="960"  <?php if( $users_ccode=='960'){echo 'selected';}?>   >Maldives (+960)</option>
																<option data-countrycode="ML" Value="223"  <?php if( $users_ccode=='223'){echo 'selected';}?>   >Mali (+223)</option>
																<option data-countrycode="MT" Value="356"  <?php if( $users_ccode=='356'){echo 'selected';}?>   >Malta (+356)</option>
																<option data-countrycode="MH" Value="692"  <?php if( $users_ccode=='692'){echo 'selected';}?>   >Marshall Islands (+692)</option>
																<option data-countrycode="MQ" Value="596"  <?php if( $users_ccode=='596'){echo 'selected';}?>   >Martinique (+596)</option>
																<option data-countrycode="MR" Value="222"  <?php if( $users_ccode=='222'){echo 'selected';}?>   >Mauritania (+222)</option>
																<option data-countrycode="YT" Value="269"  <?php if( $users_ccode=='269'){echo 'selected';}?>   >Mayotte (+269)</option>
																<option data-countrycode="MX" Value="52"  <?php if( $users_ccode=='52'){echo 'selected';}?>   >Mexico (+52)</option>
																<option data-countrycode="FM" Value="691"  <?php if( $users_ccode=='691'){echo 'selected';}?>   >Micronesia (+691)</option>
																<option data-countrycode="MD" Value="373"  <?php if( $users_ccode=='373'){echo 'selected';}?>   >Moldova (+373)</option>
																<option data-countrycode="MC" Value="377"  <?php if( $users_ccode=='377'){echo 'selected';}?>   >Monaco (+377)</option>
																<option data-countrycode="MN" Value="976"  <?php if( $users_ccode=='976'){echo 'selected';}?>   >Mongolia (+976)</option>
																<option data-countrycode="MS" Value="1664"  <?php if( $users_ccode=='1664'){echo 'selected';}?>   >Montserrat (+1664)</option>
																<option data-countrycode="MA" Value="212"  <?php if( $users_ccode=='212'){echo 'selected';}?>   >Morocco (+212)</option>
																<option data-countrycode="MZ" Value="258"  <?php if( $users_ccode=='258'){echo 'selected';}?>   >Mozambique (+258)</option>
																<option data-countrycode="MN" Value="95"  <?php if( $users_ccode=='95'){echo 'selected';}?>   >Myanmar (+95)</option>
																<option data-countrycode="NA" Value="264"  <?php if( $users_ccode=='264'){echo 'selected';}?>   >Namibia (+264)</option>
																<option data-countrycode="NR" Value="674"  <?php if( $users_ccode=='674'){echo 'selected';}?>   >Nauru (+674)</option>
																<option data-countrycode="NP" Value="977"  <?php if( $users_ccode=='977'){echo 'selected';}?>   >Nepal (+977)</option>
																<option data-countrycode="NL" Value="31"  <?php if( $users_ccode=='31'){echo 'selected';}?>   >Netherlands (+31)</option>
																<option data-countrycode="NC" Value="687"  <?php if( $users_ccode=='687'){echo 'selected';}?>   >New Caledonia (+687)</option>
																<option data-countrycode="NZ" Value="64"  <?php if( $users_ccode=='64'){echo 'selected';}?>   >New Zealand (+64)</option>
																<option data-countrycode="NI" Value="505"  <?php if( $users_ccode=='505'){echo 'selected';}?>   >Nicaragua (+505)</option>
																<option data-countrycode="NE" Value="227"  <?php if( $users_ccode=='227'){echo 'selected';}?>   >Niger (+227)</option>
																<option data-countrycode="NG" Value="234"  <?php if( $users_ccode=='234'){echo 'selected';}?>   >Nigeria (+234)</option>
																<option data-countrycode="NU" Value="683"  <?php if( $users_ccode=='683'){echo 'selected';}?>   >Niue (+683)</option>
																<option data-countrycode="NF" Value="672"  <?php if( $users_ccode=='672'){echo 'selected';}?>   >Norfolk Islands (+672)</option>
																<option data-countrycode="NP" Value="670"  <?php if( $users_ccode=='670'){echo 'selected';}?>   >Northern Marianas (+670)</option>
																<option data-countrycode="NO" Value="47"  <?php if( $users_ccode=='47'){echo 'selected';}?>   >Norway (+47)</option>
																<option data-countrycode="OM" Value="968"  <?php if( $users_ccode=='968'){echo 'selected';}?>   >Oman (+968)</option>
																<option data-countrycode="PW" Value="680"  <?php if( $users_ccode=='680'){echo 'selected';}?>   >Palau (+680)</option>
																<option data-countrycode="PA" Value="507"  <?php if( $users_ccode=='507'){echo 'selected';}?>   >Panama (+507)</option>
																<option data-countrycode="PG" Value="675"  <?php if( $users_ccode=='675'){echo 'selected';}?>   >Papua New Guinea (+675)</option>
																<option data-countrycode="PY" Value="595"  <?php if( $users_ccode=='595'){echo 'selected';}?>   >Paraguay (+595)</option>
																<option data-countrycode="PE" Value="51"  <?php if( $users_ccode=='51'){echo 'selected';}?>   >Peru (+51)</option>
																<option data-countrycode="PH" Value="63"  <?php if( $users_ccode=='63'){echo 'selected';}?>   >Philippines (+63)</option>
																<option data-countrycode="PL" Value="48"  <?php if( $users_ccode=='48'){echo 'selected';}?>   >Poland (+48)</option>
																<option data-countrycode="PT" Value="351"  <?php if( $users_ccode=='351'){echo 'selected';}?>   >Portugal (+351)</option>
																<option data-countrycode="PR" Value="1787"  <?php if( $users_ccode=='1787'){echo 'selected';}?>   >Puerto Rico (+1787)</option>
																<option data-countrycode="QA" Value="974"  <?php if( $users_ccode=='974'){echo 'selected';}?>   >Qatar (+974)</option>
																<option data-countrycode="RE" Value="262"  <?php if( $users_ccode=='262'){echo 'selected';}?>   >Reunion (+262)</option>
																<option data-countrycode="RO" Value="40"  <?php if( $users_ccode=='40'){echo 'selected';}?>   >Romania (+40)</option>
																<option data-countrycode="RU" Value="7"  <?php if( $users_ccode=='7'){echo 'selected';}?>   >Russia (+7)</option>
																<option data-countrycode="RW" Value="250"  <?php if( $users_ccode=='250'){echo 'selected';}?>   >Rwanda (+250)</option>
																<option data-countrycode="SM" Value="378"  <?php if( $users_ccode=='378'){echo 'selected';}?>   >San Marino (+378)</option>
																<option data-countrycode="ST" Value="239"  <?php if( $users_ccode=='239'){echo 'selected';}?>   >Sao Tome &amp; Principe (+239)</option>
																<option data-countrycode="SN" Value="221"  <?php if( $users_ccode=='221'){echo 'selected';}?>   >Senegal (+221)</option>
																<option data-countrycode="CS" Value="381"  <?php if( $users_ccode=='381'){echo 'selected';}?>   >Serbia (+381)</option>
																<option data-countrycode="SC" Value="248"  <?php if( $users_ccode=='248'){echo 'selected';}?>   >Seychelles (+248)</option>
																<option data-countrycode="SL" Value="232"  <?php if( $users_ccode=='232'){echo 'selected';}?>   >Sierra Leone (+232)</option>
																<option data-countrycode="SG" Value="65"  <?php if( $users_ccode=='65'){echo 'selected';}?>   >Singapore (+65)</option>
																<option data-countrycode="SK" Value="421"  <?php if( $users_ccode=='421'){echo 'selected';}?>   >Slovak Republic (+421)</option>
																<option data-countrycode="SI" Value="386"  <?php if( $users_ccode=='386'){echo 'selected';}?>   >Slovenia (+386)</option>
																<option data-countrycode="SB" Value="677"  <?php if( $users_ccode=='677'){echo 'selected';}?>   >Solomon Islands (+677)</option>
																<option data-countrycode="SO" Value="252"  <?php if( $users_ccode=='252'){echo 'selected';}?>   >Somalia (+252)</option>
																<option data-countrycode="ZA" Value="27"  <?php if( $users_ccode=='27'){echo 'selected';}?>   >South Africa (+27)</option>
																<option data-countrycode="ES" Value="34"  <?php if( $users_ccode=='34'){echo 'selected';}?>   >Spain (+34)</option>
																<option data-countrycode="LK" Value="94"  <?php if( $users_ccode=='94'){echo 'selected';}?>   >Sri Lanka (+94)</option>
																<option data-countrycode="SH" Value="290"  <?php if( $users_ccode=='290'){echo 'selected';}?>   >St. Helena (+290)</option>
																<option data-countrycode="KN" Value="1869"  <?php if( $users_ccode=='1869'){echo 'selected';}?>   >St. Kitts (+1869)</option>
																<option data-countrycode="SC" Value="1758"  <?php if( $users_ccode=='1758'){echo 'selected';}?>   >St. Lucia (+1758)</option>
																<option data-countrycode="SD" Value="249"  <?php if( $users_ccode=='249'){echo 'selected';}?>   >Sudan (+249)</option>
																<option data-countrycode="SR" Value="597"  <?php if( $users_ccode=='597'){echo 'selected';}?>   >Suriname (+597)</option>
																<option data-countrycode="SZ" Value="268"  <?php if( $users_ccode=='268'){echo 'selected';}?>   >Swaziland (+268)</option>
																<option data-countrycode="SE" Value="46"  <?php if( $users_ccode=='46'){echo 'selected';}?>   >Sweden (+46)</option>
																<option data-countrycode="CH" Value="41"  <?php if( $users_ccode=='41'){echo 'selected';}?>   >Switzerland (+41)</option>
																<option data-countrycode="SI" Value="963"  <?php if( $users_ccode=='963'){echo 'selected';}?>   >Syria (+963)</option>
																<option data-countrycode="TW" Value="886"  <?php if( $users_ccode=='886'){echo 'selected';}?>   >Taiwan (+886)</option>
																<option data-countrycode="TJ" Value="7"  <?php if( $users_ccode=='7'){echo 'selected';}?>   >Tajikstan (+7)</option>
																<option data-countrycode="TH" Value="66"  <?php if( $users_ccode=='66'){echo 'selected';}?>   >Thailand (+66)</option>
																<option data-countrycode="TG" Value="228"  <?php if( $users_ccode=='228'){echo 'selected';}?>   >Togo (+228)</option>
																<option data-countrycode="TO" Value="676"  <?php if( $users_ccode=='676'){echo 'selected';}?>   >Tonga (+676)</option>
																<option data-countrycode="TT" Value="1868"  <?php if( $users_ccode=='1868'){echo 'selected';}?>   >Trinidad &amp; Tobago (+1868)</option>
																<option data-countrycode="TN" Value="216"  <?php if( $users_ccode=='216'){echo 'selected';}?>   >Tunisia (+216)</option>
																<option data-countrycode="TR" Value="90"  <?php if( $users_ccode=='90'){echo 'selected';}?>   >Turkey (+90)</option>
																<option data-countrycode="TM" Value="7"  <?php if( $users_ccode=='7'){echo 'selected';}?>   >Turkmenistan (+7)</option>
																<option data-countrycode="TM" Value="993"  <?php if( $users_ccode=='993'){echo 'selected';}?>   >Turkmenistan (+993)</option>
																<option data-countrycode="TC" Value="1649"  <?php if( $users_ccode=='1649'){echo 'selected';}?>   >Turks &amp; Caicos Islands (+1649)</option>
																<option data-countrycode="TV" Value="688"  <?php if( $users_ccode=='688'){echo 'selected';}?>   >Tuvalu (+688)</option>
																<option data-countrycode="UG" Value="256"  <?php if( $users_ccode=='256'){echo 'selected';}?>   >Uganda (+256)</option>
																<option data-countrycode="GB" Value="44"  <?php if( $users_ccode=='44'){echo 'selected';}?>   >UK (+44)</option>
																<option data-countrycode="UA" Value="380"  <?php if( $users_ccode=='380'){echo 'selected';}?>   >Ukraine (+380)</option>
																<option data-countrycode="UY" Value="598"  <?php if( $users_ccode=='598'){echo 'selected';}?>   >Uruguay (+598)</option>
																<option data-countrycode="US" Value="1"  <?php if( $users_ccode=='1'){echo 'selected';}?>   >USA (+1)</option>
																<option data-countrycode="UZ" Value="7"  <?php if( $users_ccode=='7'){echo 'selected';}?>   >Uzbekistan (+7)</option>
																<option data-countrycode="VU" Value="678"  <?php if( $users_ccode=='678'){echo 'selected';}?>   >Vanuatu (+678)</option>
																<option data-countrycode="VA" Value="379"  <?php if( $users_ccode=='379'){echo 'selected';}?>   >Vatican City (+379)</option>
																<option data-countrycode="VE" Value="58"  <?php if( $users_ccode=='58'){echo 'selected';}?>   >Venezuela (+58)</option>
																<option data-countrycode="VN" Value="84"  <?php if( $users_ccode=='84'){echo 'selected';}?>   >Vietnam (+84)</option>
																<option data-countrycode="VG" Value="84"  <?php if( $users_ccode=='84'){echo 'selected';}?>   >Virgin Islands - British (+1284)</option>
																<option data-countrycode="VI" Value="84"  <?php if( $users_ccode=='84'){echo 'selected';}?>   >Virgin Islands - US (+1340)</option>
																<option data-countrycode="WF" Value="681"  <?php if( $users_ccode=='681'){echo 'selected';}?>   >Wallis &amp; Futuna (+681)</option>
																<option data-countrycode="YE" Value="969"  <?php if( $users_ccode=='969'){echo 'selected';}?>   >Yemen (North)(+969)</option>
																<option data-countrycode="YE" Value="967"  <?php if( $users_ccode=='967'){echo 'selected';}?>   >Yemen (South)(+967)</option>
																<option data-countrycode="ZM" Value="260"  <?php if( $users_ccode=='260'){echo 'selected';}?>   >Zambia (+260)</option>
																<option data-countrycode="ZW" Value="263"  <?php if( $users_ccode=='263'){echo 'selected';}?>   >Zimbabwe (+263)</option>


															</select>
														</div>
														<input type="number" name="users_phone" value="<?php echo $users_phone; ?>" placeholder="رقم الموبايل" class=" pl-24 sm:pl-32  text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">كلمة المرور</p>
													<div class="relative h-10 w-full min-w-[200px]">
														<input type="password" name="rand_password" placeholder="أكتب كلمة مرور هنا" value="<?php echo $users_password; ?>" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50" required>
													</div>
												</div>



										
												<div>
													<p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-gray-900">أدوار المستخدم</p>
													<div class="relative  w-full min-w-[200px]">
														<select name="roles[]" multiple="" class="text-left peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-blue-gray-50 h-[150px] ">
															
<?php

	
			$result1 = $mysqli->query("SELECT * FROM roles") or die($$mysqli->error);
				while ($row1 = $result1->fetch_assoc()){
															
															?>															
															
															<option 
																	<?php if(in_array($row1["roles_id"], $storedusers_access)) echo 'selected'; ?> 
																	value="<?php echo $row1["roles_id"]; ?>"
																	>
																<?php echo $row1["roles_name"]; ?>
															</option>
	<?php } 
															
	
															
															?>
															
			
															
															
														</select>
													</div>
												</div>
		
												
												

												





											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">


								<input class="w-full select-none rounded-lg bg-gray-900 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none" name="updateuser" type="submit" value="تحديث البيانات">


							</div>

						</form>
















					</div>
				</div>


				<?php 
	if($usr_id!=1){
				?>				
<?php if(in_array(14, $unique_permissions)) { ?>

				
				<a href="actions.php?t=Deleteuser&id=<?php echo $usr_id; ?>" class="mt-[100px] w-full select-none rounded-lg bg-red-500 py-3.5 px-7 text-center align-middle font-sans text-sm font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none"> حذف المستخدم </a>

				<?php
	}}
				?>


			</div>


















		</div>






	</div>
</main>


<?php
}
?>
























