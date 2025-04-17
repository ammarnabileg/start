<?php
$invitation_types = mysqli_query($conn, "SELECT * FROM events_inv_type");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_invitation'])) {
	$event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
	$invitation_type = mysqli_real_escape_string($conn, $_POST['invitation_type']);
	$invitation_name = mysqli_real_escape_string($conn, $_POST['invitation_name']);
	$invitation_more = mysqli_real_escape_string($conn, $_POST['invitation_more']);
	$invitation_count = mysqli_real_escape_string($conn, $_POST['invitation_count']);

	$sql = "INSERT INTO events_invitations (events_invitations_eventid, events_invitations_type, events_invitations_name, events_invitations_count,events_invitations_more) 
            VALUES ('$event_id', '$invitation_type', '$invitation_name', '$invitation_count','$invitation_more')";
	mysqli_query($conn, $sql);
}

$events = mysqli_query($conn, "SELECT * FROM events where events_activity=1 ORDER BY events_date DESC");
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 text-gray-700">
	<div class="container mx-auto p-6">
		<div class="flex justify-between items-center mb-6">
			<h3 class="text-3xl font-medium text-gray-700 ">إدارة الدعوات</h3>
			
		<a href="cpanel.php?p=ViewEvents" title="" class="items-center justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">عودة</a>
		</div>

		<div class="bg-white p-6 shadow-lg rounded-lg">
			<h4 class="text-xl font-semibold mb-4">إنشاء دعوة جديدة</h4>			
			<form action="cpanel.php?p=NewEventInvitation" method="POST">
				<div class="mb-4">
					<label class="block text-gray-700">الحدث</label>
					<select name="event_id" class="w-full border rounded-lg p-2" required>
						<?php while ($row = mysqli_fetch_assoc($events)) { ?>
						<option value="<?php echo $row['events_id']; ?>">
							<?php echo $row['events_name']; ?>
						</option>
						<?php } ?>
					</select>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">نوع الدعوة</label>
					<select name="invitation_type" class="w-full border rounded-lg p-2" required>
						<?php while ($type = mysqli_fetch_assoc($invitation_types)) { ?>
						<option value="<?php echo $type['events_inv_type_id']; ?>">
							<?php echo $type['events_inv_type_title']; ?>
						</option>
						<?php } ?>
					</select>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">اسم المدعو</label>
					<input type="text" name="invitation_name" class="w-full border rounded-lg p-2" required>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">تفاصيل إضافية</label>
					<textarea name="invitation_more" id="editor1" class="text-blue-400 w-full border rounded-lg p-2 " ></textarea>
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
					<input type="number" name="invitation_count" class="w-full border rounded-lg p-2" required>
				</div>
				<button type="submit" name="add_invitation" class="w-full cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white">إضافة الدعوة</button>
			</form>
		</div>

	</div>
</main>
