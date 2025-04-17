<?php
$content_file = 'saved_content.html';
$saved = file_exists($content_file) ? file_get_contents($content_file) : '';
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8" />
  <title>واجهة كتابة مقال</title>
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
  <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow">
    <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">📝 واجهة كتابة مقال</h1>

    <form method="POST" action="save.php">
      <label class="block mb-2 text-lg font-medium text-gray-700">المحتوى:</label>
      <div id="editor" class="h-64 mb-4 border border-gray-300 rounded"><?php echo $saved; ?></div>
      <input type="hidden" name="content" id="content">
      <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">نشر المقال</button>
    </form>

    <hr class="my-8">
    <h2 class="text-2xl font-semibold mb-3 text-gray-700">📄 معاينة المقال المنشور:</h2>
    <div class="border border-gray-300 p-4 rounded bg-gray-50">
      <?php echo $saved; ?>
    </div>
  </div>

  <script>
    const quill = new Quill('#editor', {
      theme: 'snow',
      modules: {
        toolbar: {
          container: [
            ['bold', 'italic', 'underline'],
            [{ 'header': [1, 2, false] }],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['image']
          ],
          handlers: {
            image: imageHandler
          }
        }
      }
    });

    function imageHandler() {
      const input = document.createElement('input');
      input.setAttribute('type', 'file');
      input.setAttribute('accept', 'image/*');
      input.click();

      input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;

        if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
          alert('الرجاء اختيار صورة بصيغة PNG أو JPG فقط.');
          return;
        }

        if (file.size > 2 * 1024 * 1024) {
          alert('الحد الأقصى لحجم الصورة هو 2MB.');
          return;
        }

        const formData = new FormData();
        formData.append('image', file);

        const res = await fetch('upload.php', {
          method: 'POST',
          body: formData
        });

        const data = await res.json();
        if (data.url) {
          const range = quill.getSelection();
          quill.insertEmbed(range.index, 'image', data.url);
        } else {
          alert('فشل في رفع الصورة.');
        }
      };
    }

    document.querySelector('form').onsubmit = () => {
      document.querySelector('#content').value = quill.root.innerHTML;
    };
  </script>
</body>
</html>
