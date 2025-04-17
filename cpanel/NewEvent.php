<?php
session_start(); // بدء الجلسة لتخزين حالة الإدراج

$message = ""; // متغير لتخزين الرسائل

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if (!isset($_SESSION['event_added'])) { // التحقق من عدم تكرار الإدخال
		$event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
		$event_desc = mysqli_real_escape_string($conn, $_POST['event_desc']);
		$event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
		$event_org_code = mysqli_real_escape_string($conn, $_POST['event_org_code']);
		$event_url = mysqli_real_escape_string($conn, $_POST['event_url']);

		$sql = "INSERT INTO events (events_name, events_desc, events_date, events_org_code, events_for_url) 
                VALUES ('$event_name', '$event_desc', '$event_date', '$event_org_code', '$event_url')";

		if (mysqli_query($conn, $sql)) {
			$_SESSION['event_added'] = true; // تخزين الحالة في الجلسة
			header("Location: " . $_SERVER['PHP_SELF'] . "?p=NewEvent&success=1"); // إعادة توجيه لمنع التكرار
			exit();
		} else {
			$message = "<p class='text-red-500'>خطأ: " . mysqli_error($conn) . "</p>";
		}
	}
}

// حذف الجلسة بعد تحميل الصفحة لمنع حظر الإضافة المستقبلية
if (isset($_SESSION['event_added'])) {
	unset($_SESSION['event_added']);
}

// عرض رسالة النجاح بعد إعادة التوجيه
if (isset($_GET['success'])) {
	$message = "<p class='text-green-500'>تمت إضافة الحدث بنجاح!</p>";
}
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<div class="flex justify-between items-center">
			<h3 class="text-3xl font-medium text-gray-700">إضافة حدث جديد</h3>
		<a href="<?= $_SERVER['HTTP_REFERER']; ?>" title="" class="items-center justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">عودة</a>
		</div>        
		<?= $message ?> 

		<form action="" method="POST">
			<div class="bg-white p-6 shadow-lg rounded-lg mt-6 text-gray-700">
				<div class="mb-4">
					<label class="block text-gray-700">اسم الحدث</label>
					<input type="text" name="event_name" required class="text-gray-700 w-full border rounded-lg p-2">
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">تفاصيل الحدث</label>
					<textarea name="event_desc" id="editor1" class="text-gray-700 w-full border rounded-lg p-2" required></textarea>
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">تاريخ الحدث</label>
					<input type="date" name="event_date" required class="text-gray-700 w-full border rounded-lg p-2">
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">كود الدخول</label>
					<input type="number" name="event_org_code" required class="text-gray-700 w-full border rounded-lg p-2">
				</div>
				<div class="mb-4">
					<label class="block text-gray-700">رابط الحدث</label>
					<input type="url" name="event_url" required class="text-gray-700 w-full border rounded-lg p-2">
				</div>

				<button type="submit" class="w-full cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white">إضافة حدث</button>
			</div>
		</form>
	</div>
</main>

<script src="assets/editor/build/ckeditor.js"></script>
<script>
	ClassicEditor
		.create(document.querySelector('#editor1'))
		.then(editor => {
		window.editor1 = editor;
		editor.model.document.on('change:data', () => {
			document.querySelector('textarea[name="event_desc"]').value = editor.getData();
		});
	})
		.catch(error => console.error(error));
</script>
