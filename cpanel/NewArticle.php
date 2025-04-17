<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
	<div class="container px-6 py-8 mx-auto">
		<h3 class="text-3xl font-medium text-gray-700">إضافة مقالة جديدة</h3>

		<div class="flex flex-col mt-8">
			<div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
				<div class="bg-white inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 shadow sm:rounded-lg">
					<!-- تعديل في action ليشير إلى المسار الجديد -->
					<form action="includes/Sections/submit_blog.php" method="POST" enctype="multipart/form-data">
						<div class="relative grid px-4 py-4 m-0 overflow-hidden text-center text-white bg-[#cbcbcb] place-items-center rounded-t-xl bg-clip-border shadow-gray-900/20 mb-[50px]">
							<div class="h-20 p-6 text-white">
								<i class="fa-regular fa-pen-to-square text-3xl"></i>
							</div>
						</div>

						<div class="p-6 text-blue-900">
							<div class="flex flex-col gap-4">
								<div>
									<p class="block text-gray-700">صورة المقالة</p>
									<div class="relative w-full min-w-[200px]">
										<input class="block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="default_size" name="project_logo" type="file" required>
									</div>
								</div>

								<div>
									<p class="block text-gray-700">عنوان المقالة</p>
									<div class="relative h-10 w-full min-w-[200px]">
										<input type="text" name="project_name" placeholder="عنوان المقالة" class="w-full border rounded-lg p-2" required>
									</div>
								</div>

								<div>
									<p class="block text-gray-700">موضوع المقالة</p>
									<div class="relative w-full pb-2.5">
										<textarea name="txt1" id="editor1" class="editor form-control w-full" required></textarea>
									</div>
								</div>
							</div>
						</div>

						<div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">
							<button class="w-full cursor-pointer rounded-lg p-2 bg-gray-900 text-center align-middle text-white" type="submit">إضافة المقالة</button>
						</div>
					</form>

					<!-- تضمين CKEditor -->
					<script src="../../assets/editor/build/ckeditor.js"></script>
					<script>
						ClassicEditor
							.create(document.querySelector('#editor1'), {
								toolbar: {
									items: [
										'sourceEditing',
										'|',
										'heading',
										'|',
										'fontSize',
										'fontFamily',
										'fontColor',
										'fontBackgroundColor',
										'|',
										'bold',
										'italic',
										'underline',
										'strikethrough',
										'|',
										'alignment',
										'bulletedList',
										'numberedList',
										'|',
										'outdent',
										'indent',
										'|',
										'link',
										'imageUpload',
										'blockQuote',
										'insertTable',
										'|',
										'undo',
										'redo'
									]
								},
								heading: {
									options: [
										{ model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
										{ model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
										{ model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
										{ model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
										{ model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
									]
								},
								fontSize: {
									options: [
										9, 11, 13, 'default', 17, 19, 21, 27, 35
									]
								},
								image: {
									toolbar: [
										'imageTextAlternative',
										'imageStyle:inline',
										'imageStyle:block',
										'imageStyle:side',
										'toggleImageCaption',
										'imageResize'
									],
									upload: {
										types: ['jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'],
										url: 'includes/Sections/upload.php'
									}
								},
								table: {
									contentToolbar: [
										'tableColumn',
										'tableRow',
										'mergeTableCells'
									]
								},
								htmlSupport: {
									allow: [
										{
											name: /.*/,
											attributes: true,
											classes: true,
											styles: true
										}
									]
								}
							})
							.then(editor => {
								window.editor1 = editor;
								editor.model.document.on('change:data', () => {
									document.querySelector('textarea[name="txt1"]').value = editor.getData();
								});
							})
							.catch(error => {
								console.error('Error initializing editor:', error);
								alert('حدث خطأ في تحميل المحرر. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
							});
					</script>
				</div>
			</div>
		</div>
	</div>
</main>
