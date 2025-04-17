<?php 



$project_id=$_GET['id'];

$result = $mysqli->query("SELECT * FROM projects where projects_id   = '$project_id'  ") or die($$mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$projects_name = $row["projects_name"];
		$projects_logo = $row["projects_logo"];
		$projects_thumbnail = $row["projects_thumbnail"];
		$projects_txt1 = $row["projects_txt1"];
		$projects_txt2 = $row["projects_txt2"];
		$projects_txt3 = $row["projects_txt3"];
		$projects_video1 = $row["projects_video1"];
		$projects_video2 = $row["projects_video2"];
		$projects_status = $row["projects_status"];
		$issold = $row["projects_sold"];

	}
}


?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">تحديث بيانات المشروع</h3>

		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div class="bg-white inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<form action="includes/Sections/update_project.php" method="POST" enctype="multipart/form-data">
					
						<input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

						<div class="relative grid px-4 py-4 m-0 overflow-hidden text-center text-white bg-[#cbcbcb] place-items-center rounded-t-xl bg-clip-border shadow-gray-900/20 mb-[50px]">
							<div class="h-20 p-6 text-white">
								<i class="fa-solid fa-city text-3xl"></i>
							</div>
						</div>

						<div class="p-6">
							<div class="block overflow-visible">
								<div class="relative block w-full !overflow-y-visible bg-transparent">
									<div role="tabpanel" class="w-full  leading-relaxed text-gray-700 h-max" data-value="card">

										<div class="flex flex-col gap-4">

											<div>
												<p>شعار المشروع</p>
												<div class="relative w-full min-w-[200px]">
													<input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="default_size" name="project_logo" type="file">
												</div>
											</div>

											<div>
												<p>اسم المشروع</p>
												<div class="relative h-10 w-full min-w-[200px]">
													<input type="text" name="project_name" value="<?php echo $projects_name; ?>" placeholder="اسم المشروع" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0" required>
												</div>
											</div>
											<div>
												<p class="">هل هو مباع بالكامل؟</p>
												<div class="relative h-10 w-full min-w-[200px]">

												<select name="issold" class="w-full border rounded-lg p-2" required>

													<option <?php if($issold==0){echo"selected";} ?> value="0">لا</option>
													<option <?php if($issold==1){echo"selected";} ?> value="1">نعم</option>

												</select>
												</div>
											</div>

											<div>
												<p>النص الفرعي</p>
												<div class="relative w-full pb-2.5">
													<textarea name="txt1" id="editor1" class="editor form-control w-full" required><?php echo $projects_txt1; ?></textarea>
												</div>
											</div>

											<div>
												<p>الصورة الرئيسية</p>
												<div class="relative w-full min-w-[200px]">
													<input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="default_size" name="project_thumbnail" type="file">
													<!-- هذا الحقل إختياري في حالة التحديث -->
												</div>
											</div>

											<div>
												<p>معرف الفيديو الأول على يوتيوب</p>
												<div class="relative mb-2 w-full min-w-[200px]">
													<input type="text" name="video1" value="<?php echo $projects_video1; ?>" placeholder="###########" dir="ltr" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0">
												</div>
											</div>

											<div>
												<p >معرف الفيديو الثاني على يوتيوب</p>
												<div class="relative mb-2 w-full min-w-[200px]">
													<input type="text" name="video2" value="<?php echo $projects_video2; ?>" placeholder="###########" dir="ltr" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 border-t-transparent bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:border-t-transparent focus:outline-0">
												</div>
											</div>

											<div>
												<p >النص الأول</p>
												<div class="relative w-full pb-2.5">
													<textarea name="txt2" id="editor2" class="editor form-control w-full"><?php echo $projects_txt2; ?></textarea>
												</div>
											</div>

											<div>
												<p >النص الثاني</p>
												<div class="relative w-full pb-2.5">
													<textarea name="txt3" id="editor3" class="editor form-control w-full"><?php echo $projects_txt3; ?></textarea>
												</div>
											</div>

											<div>
												<p class="">صور إضافية (يمكن رفع أكثر من صورة)</p>
												<div class="relative w-full min-w-[200px]">
													<input type="file" name="project_photos[]" multiple class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
												</div>
											</div>

										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">
							<button class="w-full cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white" type="submit">تحديث المشروع</button>
						</div>

					</form>

					<script src="assets/editor/build/ckeditor.js"></script>
					<script>
						ClassicEditor.create(document.querySelector('#editor1')).then(editor => {
							window.editor1 = editor;
							editor.model.document.on('change:data', () => {
								document.querySelector('textarea[name="txt1"]').value = editor.getData();
							});
						}).catch(handleSampleError);

						ClassicEditor.create(document.querySelector('#editor2')).then(editor => {
							window.editor2 = editor;
							editor.model.document.on('change:data', () => {
								document.querySelector('textarea[name="txt2"]').value = editor.getData();
							});
						}).catch(handleSampleError);

						ClassicEditor.create(document.querySelector('#editor3')).then(editor => {
							window.editor3 = editor;
							editor.model.document.on('change:data', () => {
								document.querySelector('textarea[name="txt3"]').value = editor.getData();
							});
						}).catch(handleSampleError);

						function handleSampleError(error) {
							const issueUrl = 'https://github.com/ckeditor/ckeditor5/issues';
							const message = [
								'Oops, something went wrong!',
								`Please, report the following error on ${issueUrl} with the build id "3hwielrsb1e-dzsn5wm8qr7c" and the error stack trace:`
							].join('\n');
							console.error(message);
							console.error(error);
						}
					</script>

				</div>
			</div>
		</div>
	</div>
</main>