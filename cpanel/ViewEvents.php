<?php

if(isset($_GET['task'])){
	if(isset($_GET['id'])){



		$id=$_GET['id'];
?>

<?php

		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_invitation'])) {
			$event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
			$invitation_type = mysqli_real_escape_string($conn, $_POST['invitation_type']);
			$invitation_name = mysqli_real_escape_string($conn, $_POST['invitation_name']);
			$invitation_more = mysqli_real_escape_string($conn, $_POST['invitation_more']);
			$invitation_count = mysqli_real_escape_string($conn, $_POST['invitation_count']);

			$mysqli->query("UPDATE events_invitations SET
            events_invitations_type = '$invitation_type',
            events_invitations_name = '$invitation_name',
            events_invitations_more = '$invitation_more',
            events_invitations_count = '$invitation_count'
            WHERE events_invitations_id = $id ") or die($mysqli->error);	

		}

		$result = $mysqli->query("SELECT * FROM events_invitations where events_invitations_id = $id ") or die($mysqli->error);
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$events_invitations_eventid = $row["events_invitations_eventid"];
				$events_invitations_type = $row["events_invitations_type"];
				$events_invitations_name = $row["events_invitations_name"];
				$events_invitations_more = $row["events_invitations_more"];
				$events_invitations_count = $row["events_invitations_count"];
			}
		}

		$result = $mysqli->query("SELECT * FROM events where events_id = $events_invitations_eventid ") or die($mysqli->error);
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$events_name = $row["events_name"];
			}
		}
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 text-gray-700">
	<div class="container mx-auto p-6">
		<div class="flex justify-between items-center mb-6">
			<h3 class="text-3xl font-medium text-gray-700 ">إدارة الدعوه</h3>
			<a href="cpanel.php?p=ViewEvents&type=inv&id=<?= $events_invitations_eventid; ?>" title="" class="items-center justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">عودة</a>
		</div>
		<div class="bg-white p-6 shadow-lg rounded-lg">
			<h4 class="text-xl font-semibold mb-4"><?php echo htmlspecialchars($row['events_name']); ?></h4>
			<form action="cpanel.php?p=ViewEvents&task=edit&id=<?= $id; ?>" method="POST">
				<div class="mb-4">
					<label class="block text-gray-700"><?= $events_name; ?></label>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">نوع الدعوة</label>

					<select name="invitation_type" class="w-full border rounded-lg p-2" required>

						<?php 	
		$result = $mysqli->query("SELECT * FROM events_inv_type") or die($mysqli->error);
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$events_inv_type_id =$row["events_inv_type_id"];
						?>
						<option <?php if($events_inv_type_id==$events_invitations_type){echo "selected";} ?> value="<?php echo $row["events_inv_type_id"]; ?>"><?php echo $row["events_inv_type_title"]; ?></option>
						<?php 			
			}
		}
						?>

					</select>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">اسم المدعو</label>
					<input type="text" name="invitation_name" value="<?= $events_invitations_name; ?>" class="w-full border rounded-lg p-2" required>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">تفاصيل إضافية</label>
					<textarea name="invitation_more" id="editor1" class="text-blue-400 w-full border rounded-lg p-2 " ><?= $events_invitations_more; ?></textarea>
				</div>
				<script src="assets/editor/build/ckeditor.js"></script>
				<script>
					ClassicEditor
						.create(document.querySelector('#editor1'))
						.then(editor => {
						window.editor1 = editor;
						editor.model.document.on('change:data', () => {
							document.querySelector('textarea[name="invitation_more"]').value = editor.getData();
						});
					})
						.catch(error => console.error(error));
				</script>
				<div class="mb-4">
					<label class="block text-gray-700">عدد الأفراد</label>
					<input type="number" name="invitation_count" value="<?= $events_invitations_count; ?>" class="w-full border rounded-lg p-2" required>
				</div>
				<button type="submit" name="edit_invitation" class="w-full cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white">تعديل الدعوة</button>
			</form>
		</div>

	</div>
</main>


<?php
	}	
}else{


	if(isset($_GET['id'])){

		$event_id=$_GET['id'];
		$result = $mysqli->query("SELECT * FROM events where events_id  = $event_id ") or die($mysqli->error);
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {

				$events_name = $row["events_name"];
				$events_desc = $row["events_desc"];
				$events_date = $row["events_date"];
				$events_org_code = $row["events_org_code"];
				$events_for_url = $row["events_for_url"];
				$events_activity = $row["events_activity"];
			}
		}


		if($events_activity==0){
			header('Location: cpanel.php?p=ViewEvents'); 
			exit;}



?>



<main class="container mx-auto px-6 py-8 text-gray-900">
	<div class="flex justify-between items-center">
		<h3 class="text-3xl font-medium text-gray-700"><?php echo $events_name; ?></h3>
		<a href="cpanel.php?p=ViewEvents" title="" class="items-center justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">عودة</a>
	</div>
	<div class="p-6 bg-white rounded-md shadow-sm mt-6">
		<?= $events_desc; ?>
	</div>


	<div class="mt-8">
		<div class="flex flex-wrap -mx-6">


			<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
				<a href="cpanel.php?p=ViewEvents&type=real&id=<?= $event_id; ?>">
					<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
						<div class="p-3 bg-indigo-600 rounded-full">
							<i class="text-white fa-solid fa-calendar-check text-xl"></i>
						</div>
						<div class="mx-5">
							<h4 class="text-xl font-semibold text-gray-700">الحضور الفعلي</h4>
						</div>
					</div>
				</a>
			</div>


			<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
				<a href="cpanel.php?p=ViewEvents&type=inv&id=<?= $event_id; ?>">
					<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
						<div class="p-3 bg-indigo-600 rounded-full">
							<i class="text-white fa-solid fa-ticket text-xl"></i>
						</div>
						<div class="mx-5">
							<h4 class="text-xl font-semibold text-gray-700">الدعوات</h4>
						</div>
					</div>
				</a>
			</div>



		</div>
	</div>


	<?php 
		if($_GET['type']=="real"){
	?>


	<div class="bg-white rounded-lg mt-6">
		<div class="flex flex-col mt-8">
			<h4 class="text-xl font-semibold px-4 py-4">الحضور الفعلي</h4>
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">

				<div class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full border-collapse">
						<thead>
							<tr class="bg-gray-200 text-right">
								<th class="p-3">#</th>
								<th class="p-3">اسم المدعو</th>
								<th class="p-3">عدد المرافقين الفعلي</th>
								<th class="p-3">نوع التذكرة</th>
								<th class="p-3">التفاصيل الإضافية</th>
								<th class="p-3">الموظف</th>
								<th class="p-3">الدعوة</th>
							</tr>
						</thead>
						<tbody id="event-table-body">
							<?php 
			$count = 0;
			$result = $mysqli->query("
            SELECT ea.events_attendance_id, 
                   ea.events_attendance_invitationid, 
                   ea.events_attendance_realcount, 
                   ea.events_attendance_moreinf, 
                   ea.events_attendance_orgid, 
                   ei.events_invitations_name, 
                   ei.events_invitations_type, 
                   ei.events_invitations_more, 
                   eit.events_inv_type_title
            FROM events_attendance ea
            LEFT JOIN events_invitations ei 
                ON ea.events_attendance_invitationid = ei.events_invitations_id
            LEFT JOIN events_inv_type eit 
                ON ei.events_invitations_type = eit.events_inv_type_id
            WHERE ea.events_attendance_eventid = $event_id
            ORDER BY ea.events_attendance_id DESC
        ") or die($mysqli->error);

			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$count++;                
					$hasDetails = !empty(trim($row['events_attendance_moreinf'])); // التحقق من وجود تفاصيل إضافية
							?>
							<tr class="border-b">
								<td class="p-3"><?= $count; ?></td>
								<td class="p-3"><?= htmlspecialchars($row["events_invitations_name"]); ?></td>
								<td class="p-3"><?= $row['events_attendance_realcount']; ?></td>
								<td class="p-3"><?= htmlspecialchars($row['events_inv_type_title'] ?? 'غير محدد'); ?></td>

								<td class="p-3">
									<?php if ($hasDetails) : ?>
									<button class="cursor-pointer rounded-lg p-2 bg-gray-700 text-white" 
											onclick="openModal('moredetails<?= $row["events_attendance_id"]; ?>')">
										عرض التفاصيل
									</button>

									<!-- Modal -->
									<div id="moredetails<?= $row["events_attendance_id"]; ?>" 
										 class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50">
										<div class="bg-white p-5 rounded-lg shadow-lg w-1/2">
											<h2 class="text-lg font-semibold mb-4">التفاصيل الإضافية</h2>
											<p class="text-gray-700"><?= nl2br(htmlspecialchars($row['events_attendance_moreinf'])); ?></p>
											<button onclick="closeModal('moredetails<?= $row["events_attendance_id"]; ?>')" 
													class="mt-4 px-4 py-2 bg-red-500 text-white rounded-lg">
												إغلاق
											</button>
										</div>
									</div>
									<?php endif; ?>
								</td>
								<td class="p-3"><?php 

					$events_attendance_orgid=$row["events_attendance_orgid"];
					$result1 = $mysqli->query("SELECT * FROM users where users_id = $events_attendance_orgid ") or die($mysqli->error);
					if ($result1->num_rows > 0) {
						while($row1 = $result->fetch_assoc()) {

							$users_name = $row1["users_name"];
						}
					}

					echo $users_name;

									?></td>

								<td class="p-3">

									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='invitation.php?id=<?= $row["events_attendance_invitationid"]; ?>'">
										QR
									</button>
									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='test.php?id=<?= $row["events_attendance_invitationid"]; ?>'">
										QR2
									</button>
									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='invitation_review.php?id=<?= $row["events_attendance_invitationid"]; ?>'">
										عرض
									</button>
								</td>
							</tr>
							<?php
				}
			}
							?>
						</tbody>
					</table>

					<!-- JavaScript for Modal -->
					<script>
						function openModal(modalId) {
							document.getElementById(modalId).classList.remove("hidden");
						}

						function closeModal(modalId) {
							document.getElementById(modalId).classList.add("hidden");
						}
					</script>

				</div>
			</div>
		</div>
	</div>



	<?php 
		}elseif($_GET['type']=="inv"){
	?>


	<div class="bg-white rounded-lg mt-6">
		<div class="flex flex-col mt-8">
			<h4 class="text-xl font-semibold px-4 py-4">الدعوات</h4>
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">

				<div class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full border-collapse">
						<thead>
							<tr class="bg-gray-200 text-right">
								<th class="p-3">#</th>
								<th class="p-3">اسم المدعو</th>
								<th class="p-3">عدد المرافقين</th>
								<th class="p-3">نوع التذكرة</th>
								<th class="p-3">التفاصيل الإضافية</th>
								<th class="p-3">الدعوة</th>
								<th class="p-3">الإجراءات</th>
							</tr>
						</thead>
						<tbody id="event-table-body">
							<?php 
			$count = 0;
			$result = $mysqli->query("
            SELECT ei.events_invitations_id, 
                   ei.events_invitations_name, 
                   ei.events_invitations_count, 
                   ei.events_invitations_type, 
                   ei.events_invitations_more, 
                   eit.events_inv_type_title,
                   IF(ea.events_attendance_invitationid IS NOT NULL, 1, 0) AS is_attended
            FROM events_invitations ei
            LEFT JOIN events_attendance ea 
                ON ei.events_invitations_id = ea.events_attendance_invitationid
            LEFT JOIN events_inv_type eit 
                ON ei.events_invitations_type = eit.events_inv_type_id
            WHERE ei.events_invitations_eventid = $event_id
            ORDER BY ei.events_invitations_id DESC
        ") or die($mysqli->error);

			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$count++;
							?>
							<tr class="border-b <?= $row['is_attended'] ? 'bg-[#90EE90]' : ''; ?>">
								<td class="p-3"><?= $count; ?></td>
								<td class="p-3"><?= htmlspecialchars($row["events_invitations_name"]); ?></td>
								<td class="p-3"><?= $row['events_invitations_count']; ?></td>
								<td class="p-3"><?= htmlspecialchars($row['events_inv_type_title'] ?? 'غير محدد'); ?></td>
								<td class="p-3">
									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="openModal('detailsModal<?= $row["events_invitations_id"]; ?>')">
										عرض التفاصيل
									</button>

									<!-- Modal -->
									<div id="detailsModal<?= $row["events_invitations_id"]; ?>" 
										 class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50">
										<div class="bg-white p-5 rounded-lg shadow-lg w-1/2">
											<h2 class="text-lg font-semibold mb-4">التفاصيل الإضافية</h2>
											<p class="text-gray-700"><?= $row['events_invitations_more']; ?></p>
											<button onclick="closeModal('detailsModal<?= $row["events_invitations_id"]; ?>')" 
													class="mt-4 px-4 py-2 bg-red-500 text-white rounded-lg">
												إغلاق
											</button>
										</div>
									</div>
								</td>
								<td class="p-3">
									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='invitation.php?id=<?= $row["events_invitations_id"]; ?>'">
										QR
									</button>
									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='test.php?id=<?= $row["events_invitations_id"]; ?>'">
										QR عربي
									</button>

									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-white" 
											onclick="window.location.href='invitation_review.php?id=<?= $row["events_invitations_id"]; ?>'">
										عرض
									</button>
								</td>
								<td class="p-3">
									<button onclick="window.location.href='cpanel.php?p=ViewEvents&task=edit&id=<?= $row["events_invitations_id"]; ?>'"
											class="cursor-pointer rounded-lg p-2 bg-blue-500 text-white">تعديل</button>
								</td>
							</tr>
							<?php
				}
			}
							?>
						</tbody>
					</table>

					<!-- JavaScript for Modal -->
					<script>
						function openModal(modalId) {
							document.getElementById(modalId).classList.remove("hidden");
						}

						function closeModal(modalId) {
							document.getElementById(modalId).classList.add("hidden");
						}
					</script>


				</div>
			</div>
		</div>
	</div>

	<?php } ?>	
</main>





<?php

	}else{


		// تحديث الحدث
		if (isset($_POST['update_event'])) {
			$event_id = intval($_POST['event_id']);
			$event_name = $_POST['event_name'];
			$event_desc = $_POST['event_desc'];
			$event_date = $_POST['event_date'];
			$event_org_code = $_POST['event_org_code'];
			$event_url = $_POST['event_url'];

			$stmt = $conn->prepare("UPDATE events SET events_name=?, events_desc=?, events_date=?, events_org_code=?, events_for_url=? WHERE events_id=?");
			$stmt->bind_param("sssssi", $event_name, $event_desc, $event_date, $event_org_code, $event_url, $event_id);
			$stmt->execute();
			$stmt->close();
			exit;
		}

		// عرض الأحداث مع التصفّح
		$limit = 1;
		$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
		$start = ($page - 1) * $limit;
		$stmt = $conn->prepare("SELECT * FROM events where events_activity=1 ORDER BY events_date DESC LIMIT ?, ?");
		$stmt->bind_param("ii", $start, $limit);
		$stmt->execute();
		$result = $stmt->get_result();
		$stmt->close();

		$stmt = $conn->prepare("SELECT COUNT(events_id) AS total FROM events where events_activity=1 ");
		$stmt->execute();
		$total_result = $stmt->get_result();
		$total_events = $total_result->fetch_assoc()['total'];
		$total_pages = ceil($total_events / $limit);
		$stmt->close();
?>

<main class="container mx-auto px-6 py-8 text-gray-900">
	<h3 class="text-3xl font-medium text-gray-700">إدارة الأحداث</h3>

	<div class="mt-8">
		<div class="flex flex-wrap -mx-6">

			<?php
		if (in_array(21, $unique_permissions)) {
			?>
			<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
				<a href="cpanel.php?p=NewEvent">
					<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
						<div class="mx-5">
							<h4 class="text-xl font-semibold text-gray-700">إضافة حدث</h4>
						</div>
					</div>
				</a>
			</div>
			<?php } ?>

			<?php
		if (in_array(24, $unique_permissions)) {
			?>

			<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
				<a href="cpanel.php?p=NewEventInvitation">
					<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
						<div class="mx-5">
							<h4 class="text-xl font-semibold text-gray-700">إنشاء دعوة</h4>
						</div>
					</div>
				</a>
			</div>
			<?php } ?>

			<?php
		if (in_array(26, $unique_permissions)) {
			?>

			<div class="w-full px-6 sm:w-1/2 xl:w-1/3">
				<a href="#cpanel.php?p=NewEventInvitation">
					<div class="flex items-center px-5 py-6 bg-white rounded-md shadow-sm">
						<div class="mx-5">
							<h4 class="text-xl font-semibold text-gray-700">إدارة التذاكر</h4>
						</div>
					</div>
				</a>
			</div>
			<?php } ?>


		</div>
	</div>
	<div class="bg-white rounded-lg mt-6">
		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">

				<div class="inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<table class="min-w-full border-collapse">
						<thead>
							<tr class="bg-gray-200 text-right">
								<th class="p-3">#</th>
								<th class="p-3">اسم الحدث</th>
								<th class="p-3">التاريخ</th>
								<th class="p-3">الإجراءات</th>
							</tr>
						</thead>
						<tbody id="event-table-body">

							<?php 
		$count=1;
		while ($row = $result->fetch_assoc()): ?>
							<tr class="border-b">
								<td class="p-3"><?php echo htmlspecialchars($count); ?></td>
								<td class="p-3"><?php echo htmlspecialchars($row['events_name']); ?></td>
								<td class="p-3"><?php echo htmlspecialchars($row['events_date']); ?></td>
								<td class="p-3">


									<button class="cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white" onclick="window.location.href='cpanel.php?p=ViewEvents&id=<?=$row['events_id']?>'">عرض</button>

									<?php
			if (in_array(23, $unique_permissions)) {
									?>									
									<button onclick="editEvent(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="cursor-pointer rounded-lg p-2 bg-blue-500 text-center align-middle text-white">تعديل</button>
									<?php } ?>

									<?php
		if (in_array(22, $unique_permissions)) {
									?>
									<button id onclick="window.location.href='actions.php?t=DeleteEvent&id=<?php echo $row['events_id']; ?>'" class="cursor-pointer rounded-lg p-2 bg-red-500 text-center align-middle text-white">حذف</button>
									<?php } ?>

								</td>
							</tr>
							<?php 
		$count+=1;
		endwhile; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>		

	<div class="flex justify-center mt-4">
		<div class="flex justify-center mt-4">
			<?php if ($page > 1): ?>
			<a href="?p=ViewEvents&page=<?php echo $page - 1; ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">السابق</a>
			<?php endif; ?>

			<?php for ($i = 1; $i <= $total_pages; $i++): ?>
			<a href="?p=ViewEvents&page=<?php echo $i; ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black  px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px] <?php echo $i == $page ? 'bg-black text-gray-100' : 'bg-white text-gray-900'; ?>">
				<?php echo $i; ?>
			</a>
			<?php endfor; ?>

			<?php if ($page < $total_pages): ?>
			<a href="?p=ViewEvents&page=<?php echo $page + 1; ?>" class="inline-flex size-8 items-center justify-center rounded overflow-hidden border border-solid border-black bg-white text-gray-900 px-3 py-4 [box-shadow:rgb(0,_0,_0)_3px_3px]">التالي</a>
			<?php endif; ?>
		</div>

	</div>

</main>

<div class="editModal fixed inset-0 flex items-center justify-center hidden bg-gray-900 bg-opacity-50 text-gray-900  z-[100]">
	<div class="bg-white p-6 rounded-lg shadow-lg w-96">
		<h2 class="text-xl font-bold mb-4">تعديل الحدث</h2>
		<input type="hidden" class="edit_event_id">
		<input type="text" class="edit_event_name w-full border p-2 mb-2" placeholder="اسم الحدث">
		<textarea class="edit_event_desc w-full border p-2 mb-2" id="editor1" placeholder="وصف الحدث"></textarea>
		<input type="date" class="edit_event_date w-full border p-2 mb-2">
		<input type="number" class="edit_event_org_code w-full border p-2 mb-2" placeholder="كود الدخول">
		<input type="url" class="edit_event_url w-full border p-2 mb-2" placeholder="رابط الحدث">

		<button onclick="saveEdit()" class="bg-blue-500 text-white p-2 rounded-lg w-full">حفظ التعديلات</button>
		<button onclick="closeModal()" class="mt-2 bg-gray-400 text-white p-2 rounded-lg w-full">إلغاء</button>
	</div>
</div>
<script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
<script src="https://start.com.eg/assets/js/ckeditor-4-16-2.js"></script>
<script>
	document.addEventListener("DOMContentLoaded", function () {
		CKEDITOR.replace('editor1');
	});

	function editEvent(event) {
		document.querySelector('.edit_event_id').value = event.events_id;
		document.querySelector('.edit_event_name').value = event.events_name;
		if (CKEDITOR.instances.editor1) {
			CKEDITOR.instances.editor1.setData(event.events_desc);
		}
		document.querySelector('.edit_event_date').value = event.events_date;
		document.querySelector('.edit_event_org_code').value = event.events_org_code;
		document.querySelector('.edit_event_url').value = event.events_for_url;
		document.querySelector('.editModal').classList.remove('hidden');
	}

	function closeModal() {
		document.querySelector('.editModal').classList.add('hidden');
	}

	function saveEdit() {
		var formData = new FormData();
		formData.append('update_event', true);
		formData.append('event_id', document.querySelector('.edit_event_id').value);
		formData.append('event_name', document.querySelector('.edit_event_name').value);
		formData.append('event_desc', CKEDITOR.instances.editor1.getData());
		formData.append('event_date', document.querySelector('.edit_event_date').value);
		formData.append('event_org_code', document.querySelector('.edit_event_org_code').value);
		formData.append('event_url', document.querySelector('.edit_event_url').value);

		fetch('', { method: 'POST', body: formData })
			.then(() => location.reload());
	}



	function loadMoreEvents() {
		window.location.href = "?p=ViewEvents&page=<?php echo $page + 1; ?>";
	}
</script>

<style>
	.cke_notifications_area
	{visibility:hidden;}
</style>


<?php }} ?>