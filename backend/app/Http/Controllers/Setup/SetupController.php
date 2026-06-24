<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\HeyGenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    private static array $allowedCommands = [
        'php artisan migrate',
        'php artisan migrate:fresh',
        'php artisan migrate:fresh --seed',
        'php artisan migrate --force',
        'php artisan db:seed',
        'php artisan key:generate',
        'php artisan jwt:secret',
        'php artisan config:clear',
        'php artisan cache:clear',
        'php artisan optimize:clear',
        'php artisan optimize',
        'php artisan storage:link',
        'php artisan queue:restart',
        'composer install',
        'composer dump-autoload',
    ];

    public function status(): JsonResponse
    {
        $locked = file_exists(storage_path('setup.lock'));
        $installed = SystemSetting::isInstalled();
        return response()->json(['installed' => $installed, 'locked' => $locked]);
    }

    public function check(): JsonResponse
    {
        return response()->json([
            'PHP 8.2+' => ['status' => version_compare(PHP_VERSION, '8.2.0', '>='), 'value' => PHP_VERSION],
            'PDO MySQL' => ['status' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'مثبت' : 'غير مثبت'],
            'OpenSSL'   => ['status' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'مثبت' : 'غير مثبت'],
            'cURL'      => ['status' => extension_loaded('curl'), 'value' => extension_loaded('curl') ? 'مثبت' : 'غير مثبت'],
            'Fileinfo'  => ['status' => extension_loaded('fileinfo'), 'value' => extension_loaded('fileinfo') ? 'مثبت' : 'غير مثبت'],
            'Storage'   => ['status' => is_writable(storage_path()), 'value' => is_writable(storage_path()) ? 'قابل للكتابة' : 'غير قابل للكتابة'],
            '.env'      => ['status' => is_writable(base_path('.env')) || !file_exists(base_path('.env')), 'value' => file_exists(base_path('.env')) ? 'موجود' : 'غير موجود'],
        ]);
    }

    public function settings(): JsonResponse
    {
        $env = $this->readEnv();
        return response()->json([
            'db' => [
                'host'     => $env['DB_HOST'] ?? '127.0.0.1',
                'port'     => $env['DB_PORT'] ?? '3306',
                'name'     => $env['DB_DATABASE'] ?? '',
                'user'     => $env['DB_USERNAME'] ?? '',
                'password' => '',   // never expose
            ],
            'platform_name'  => SystemSetting::get('platform_name') ?? ($env['APP_NAME'] ?? 'AI Recruit'),
            'has_openai_key' => !empty($env['OPENAI_API_KEY']),
            'has_heygen_key' => !empty($env['HEYGEN_API_KEY']),
            'admin_email'    => User::where('user_type', 'super_admin')->value('email') ?? '',
            'installed'      => SystemSetting::isInstalled(),
            'locked'         => file_exists(storage_path('setup.lock')),
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $request->validate(['host' => 'required', 'port' => 'required', 'database' => 'required', 'username' => 'required']);
        try {
            new \PDO(
                "mysql:host={$request->host};port={$request->port};dbname={$request->database}",
                $request->username,
                $request->password ?? ''
            );
            return response()->json(['success' => true, 'message' => 'تم الاتصال بنجاح ✓']);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function validateApiKeys(Request $request): JsonResponse
    {
        $results = ['openai' => false, 'heygen' => false];
        if ($request->openai_key) {
            try {
                $client = \OpenAI::client($request->openai_key);
                $client->models()->list();
                $results['openai'] = true;
            } catch (\Exception $e) {
                $results['openai_error'] = $e->getMessage();
            }
        }
        if ($request->heygen_key) {
            $heygen = new HeyGenService($request->heygen_key);
            $results['heygen'] = $heygen->validateApiKey();
        }
        return response()->json($results);
    }

    public function install(Request $request): JsonResponse
    {
        if (file_exists(storage_path('setup.lock'))) {
            return response()->json(['message' => 'صفحة الإعداد مقفلة'], 403);
        }

        $db    = $request->input('db', []);
        $admin = $request->input('admin', []);
        $ai    = $request->input('ai', []);

        $dbHost   = $db['host'] ?? $request->db_host ?? '127.0.0.1';
        $dbPort   = $db['port'] ?? $request->db_port ?? '3306';
        $dbName   = $db['name'] ?? $request->db_database ?? 'ai_recruitment';
        $dbUser   = $db['user'] ?? $request->db_username ?? 'root';
        $dbPass   = $db['password'] ?? $request->db_password ?? '';
        $adminName    = $admin['name'] ?? $request->admin_name ?? '';
        $adminEmail   = $admin['email'] ?? $request->admin_email ?? '';
        $adminPassword = $admin['password'] ?? $request->admin_password ?? '';
        $openaiKey = $ai['openai_key'] ?? $request->openai_key ?? '';
        $heygenKey = $ai['heygen_key'] ?? $request->heygen_key ?? '';
        $appName   = $ai['app_name'] ?? $request->platform_name ?? 'AI Recruit';

        if (!$adminName || !$adminEmail || !$adminPassword || !$openaiKey) {
            return response()->json(['message' => 'بيانات مطلوبة مفقودة (اسم المدير، البريد، كلمة المرور، مفتاح OpenAI)'], 422);
        }

        try {
            $this->writeEnvValues([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST'       => $dbHost,
                'DB_PORT'       => $dbPort,
                'DB_DATABASE'   => $dbName,
                'DB_USERNAME'   => $dbUser,
                'DB_PASSWORD'   => $dbPass,
                'APP_NAME'      => $appName,
                'OPENAI_API_KEY' => $openaiKey,
                'HEYGEN_API_KEY' => $heygenKey,
                'QUEUE_CONNECTION' => 'database',
            ]);

            Artisan::call('config:clear');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]);

            // Create or update super admin
            $existingAdmin = User::where('user_type', 'super_admin')->first();
            if ($existingAdmin) {
                $existingAdmin->update([
                    'name'  => $adminName,
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                ]);
            } else {
                $adminUser = User::create([
                    'name'      => $adminName,
                    'email'     => $adminEmail,
                    'password'  => Hash::make($adminPassword),
                    'user_type' => 'super_admin',
                    'is_active' => true,
                ]);
                $adminUser->assignRole('super_admin');
            }

            SystemSetting::set('platform_name', $appName);
            SystemSetting::set('openai_api_key', $openaiKey);
            if ($heygenKey) SystemSetting::set('heygen_api_key', $heygenKey);
            SystemSetting::set('is_installed', '1');

            return response()->json(['success' => true, 'message' => 'تم التثبيت بنجاح 🎉']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (file_exists(storage_path('setup.lock'))) {
            return response()->json(['message' => 'صفحة الإعداد مقفلة'], 403);
        }

        $updates = [];

        if ($request->filled('db_host'))     $updates['DB_HOST']     = $request->db_host;
        if ($request->filled('db_port'))     $updates['DB_PORT']     = $request->db_port;
        if ($request->filled('db_name'))     $updates['DB_DATABASE'] = $request->db_name;
        if ($request->filled('db_user'))     $updates['DB_USERNAME'] = $request->db_user;
        if ($request->filled('db_password')) $updates['DB_PASSWORD'] = $request->db_password;
        if ($request->filled('openai_key'))  $updates['OPENAI_API_KEY'] = $request->openai_key;
        if ($request->filled('heygen_key'))  $updates['HEYGEN_API_KEY'] = $request->heygen_key;
        if ($request->filled('platform_name')) {
            $updates['APP_NAME'] = $request->platform_name;
            SystemSetting::set('platform_name', $request->platform_name);
        }
        if ($request->filled('openai_key'))  SystemSetting::set('openai_api_key', $request->openai_key);
        if ($request->filled('heygen_key'))  SystemSetting::set('heygen_api_key', $request->heygen_key);

        if (!empty($updates)) {
            $this->writeEnvValues($updates);
            Artisan::call('config:clear');
        }

        // Update admin credentials if provided
        if ($request->filled('admin_email') || $request->filled('admin_password')) {
            $admin = User::where('user_type', 'super_admin')->first();
            if ($admin) {
                if ($request->filled('admin_name'))     $admin->name  = $request->admin_name;
                if ($request->filled('admin_email'))    $admin->email = $request->admin_email;
                if ($request->filled('admin_password')) $admin->password = Hash::make($request->admin_password);
                $admin->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'تم حفظ الإعدادات']);
    }

    public function terminal(Request $request): JsonResponse
    {
        if (file_exists(storage_path('setup.lock'))) {
            return response()->json(['output' => 'خطأ: صفحة الإعداد مقفلة', 'success' => false], 403);
        }

        $command = trim($request->input('command', ''));
        $base    = strtok($command, ' ');
        $baseCmd = "$base " . strtok(' ') . ' ' . strtok(' ');
        $baseCmd = rtrim($baseCmd);

        $allowed = false;
        foreach (self::$allowedCommands as $allowed_cmd) {
            if (str_starts_with($command, $allowed_cmd)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return response()->json([
                'output'  => "الأمر غير مسموح به.\n\nالأوامر المتاحة:\n" . implode("\n", self::$allowedCommands),
                'success' => false,
            ]);
        }

        $output = [];
        $code   = 0;
        exec($command . ' 2>&1', $output, $code);

        return response()->json([
            'output'  => implode("\n", $output),
            'success' => $code === 0,
        ]);
    }

    public function lock(): JsonResponse
    {
        file_put_contents(storage_path('setup.lock'), date('Y-m-d H:i:s'));
        return response()->json(['success' => true, 'message' => 'تم قفل صفحة الإعداد. احذف ملف storage/setup.lock لإعادة الفتح.']);
    }

    // ───────────────────────────────────────────────
    private function readEnv(): array
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return [];
        $result = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
            $result[trim($key)] = trim($val);
        }
        return $result;
    }

    private function writeEnvValues(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        foreach ($values as $key => $value) {
            $value = str_contains($value, ' ') ? '"' . $value . '"' : $value;
            if (preg_match("/^{$key}=/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
