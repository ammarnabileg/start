<?php
/**
 * تشفير المفاتيح السرية (مفاتيح API) قبل تخزينها في قاعدة البيانات.
 * مفتاح التشفير موجود في config.php بس، فلو حد سرق نسخة من قاعدة البيانات مش هيقدر يقرا المفاتيح.
 */

function app_key(): string
{
    $k = app_config()['app_key'] ?? ($GLOBALS['__app_key'] ?? '');
    $raw = base64_decode((string)$k, true);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException('مفتاح التشفير مش موجود. افتح صفحة setup.php وشغّل التحديث.');
    }
    return $raw;
}

function new_app_key(): string
{
    return base64_encode(random_bytes(32));
}

function encrypt_secret(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $stored): string
{
    if ($stored === null || $stored === '') {
        return '';
    }
    if (strpos($stored, 'v1:') !== 0) {
        return '';
    }
    $bin = base64_decode(substr($stored, 3), true);
    if ($bin === false || strlen($bin) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    return $plain === false ? '' : $plain;
}

/** يكتب config.php (بيانات القاعدة + مفتاح التشفير) */
function write_config(array $config): void
{
    $php = "<?php\n// تم إنشاؤه تلقائياً من صفحة التسطيب. لا تشاركه مع أحد ولا تمسحه.\nreturn " . var_export($config, true) . ";\n";
    $tmp = CONFIG_FILE . '.tmp';
    if (file_put_contents($tmp, $php, LOCK_EX) === false || !rename($tmp, CONFIG_FILE)) {
        @unlink($tmp);
        throw new RuntimeException('مقدرناش نكتب ملف config.php. اطلب من الاستضافة صلاحية الكتابة على مجلد الموقع.');
    }
    @chmod(CONFIG_FILE, 0640);
}
