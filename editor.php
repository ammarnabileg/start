<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفحة بها TinyMCE</title>
    <script src="https://cdn.tiny.cloud/1/j53nk8j7a9hp35p5j1tdj3ng0k8yi1514qg7a25b6qjkiicm/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
	
</head>
<body>

<textarea id="mytextarea">مرحبا بكم في TinyMCE!</textarea>

<script>
    tinymce.init({
        selector: '#mytextarea'
    });
</script>

</body>
</html>
