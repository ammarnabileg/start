<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    echo "<p><strong>رقم الهاتف:</strong> $phone</p>";
    // هنا يكون خصم 1 جنيه من الحساب (قابلة للتنفيذ الفعلي باستخدام قواعد بيانات)
}
?>