<?php 
$post_id=$_GET['id'];

$result = $mysqli->query("SELECT * FROM blog_posts where blog_posts_id  = '$post_id'  ") or die($$mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$blog_posts_title = $row["blog_posts_title"];
		$blog_posts_img = $row["blog_posts_img"];
		$blog_posts_text = $row["blog_posts_text"];

	}
}


?>
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f3f4f6]">
    <div class="container px-6 py-8 mx-auto">
        <h3 class="text-3xl font-medium text-gray-700">تعديل مقالة</h3>

        <div class="flex flex-col mt-8">
            <div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="bg-white inline-block min-w-full overflow-hidden align-middle border-b border-gray-200 text-blue-900 shadow sm:rounded-lg">
                    <form action="includes/Sections/update_blog.php" method="POST" enctype="multipart/form-data">
                        <!-- Hidden field to store the post ID -->
                        <input type="hidden" name="blog_posts_id" value="<?php echo $post_id; ?>">

                        <div class="relative grid px-4 py-4 m-0 overflow-hidden text-center text-white bg-[#cbcbcb] place-items-center rounded-t-xl bg-clip-border shadow-gray-900/20 mb-[50px]">
                            <div class="h-20 p-6 text-white">
                                <i class="fa-regular fa-pen-to-square text-3xl"></i>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="flex flex-col gap-4">
                                <div>
                                    <p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-900">تحديث صورة المقالة (اختياري)</p>
                                    <div class="relative w-full min-w-[200px]">
                                        <input class="block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="default_size" name="project_logo" type="file">
                                    </div>
                                    <!-- Display the current image if it exists -->
                                    <?php if (!empty($blog_posts_img)) : ?>
                                        <div class="mt-4">
                                            <img src="<?php echo $blog_posts_img; ?>" alt="Current Image" class="w-full max-w-xs rounded-lg">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-900">عنوان المقالة</p>
                                    <div class="relative h-10 w-full min-w-[200px]">
                                        <input type="text" name="project_name" placeholder="عنوان المقالة" value="<?php echo $blog_posts_title; ?>" class="peer h-full w-full rounded-[7px] border border-blue-gray-200 bg-transparent px-3 py-2.5 font-sans text-sm font-normal text-blue-900" required>
                                    </div>
                                </div>

                                <div>
                                    <p class="block mb-2 font-sans text-sm antialiased font-medium leading-normal text-blue-900">موضوع المقالة</p>
                                    <div class="relative w-full pb-2.5">
                                        <textarea name="txt1" id="editor1" class="editor form-control w-full text-blue-900" required><?php echo $blog_posts_text; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative px-4 py-4 m-0 overflow-hidden text-center text-white bg-clip-border shadow-gray-900/20 my-[50px]">
                            <button class="w-full select-none rounded-lg bg-gray-900 py-3.5 px-7 text-center font-sans text-sm font-bold uppercase text-white shadow-md" type="submit">تحديث المقالة</button>
                        </div>
                    </form>

                    <!-- CKEditor Script -->
                    <script src="assets/editor/build/ckeditor.js"></script>
                    <script>
                        ClassicEditor
                            .create(document.querySelector('#editor1'))
                            .then(editor => {
                            window.editor1 = editor;
                            editor.model.document.on('change:data', () => {
                                document.querySelector('textarea[name="txt1"]').value = editor.getData();
                            });
                        })
                            .catch(handleSampleError);

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
