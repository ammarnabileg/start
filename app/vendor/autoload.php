<?php
/** تحميل المكتبات المضمّنة (من غير Composer) */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Smalot\\PdfParser\\') === 0) {
        $f = __DIR__ . '/smalot-pdfparser/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($f)) require $f;
    }
});
