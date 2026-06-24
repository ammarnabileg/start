<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AIService;
use App\Services\HeyGenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json(['installed' => SystemSetting::isInstalled()]);
    }

    public function check(): JsonResponse
    {
        $checks = [
            'PHP 8.2+' => [
                'status' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'value' => PHP_VERSION,
            ],
            'PDO MySQL' => [
                'status' => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? 'مثبت' : 'غير مثبت',
            ],
            'OpenSSL' => [
                'status' => extension_loaded('openssl'),
                'value' => extension_loaded('openssl') ? 'مثبت' : 'غير مثبت',
            ],
            'cURL' => [
                'status' => extension_loaded('curl'),
                'value' => extension_loaded('curl') ? 'مثبت' : 'غير مثبت',
            ],
            'Fileinfo' => [
                'status' => extension_loaded('fileinfo'),
                'value' => extension_loaded('fileinfo') ? 'مثبت' : 'غير مثبت',
            ],
            'Storage Writable' => [
                'status' => is_writable(storage_path()),
                'value' => is_writable(storage_path()) ? 'قابل للكتابة' : 'غير قابل للكتابة',
            ],
        ];

        return response()->json($checks);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required', 'port' => 'required', 'database' => 'required',
            'username' => 'required',
        ]);

        try {
            $pdo = new \PDO(
                "mysql:host={$request->host};port={$request->port};dbname={$request->database}",
                $request->username,
                $request->password ?? ''
            );
            return response()->json(['success' => true, 'message' => 'Connection successful']);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function validateApiKeys(Request $request): JsonResponse
    {
        $results = ['openai' => false, 'heygen' => false];

        if ($request->openai_key) {
            try {
                config(['openai.api_key' => $request->openai_key]);
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
        // Support both nested {db, admin, ai} and flat field formats
        $db = $request->input('db', []);
        $admin = $request->input('admin', []);
        $ai = $request->input('ai', []);

        // Flatten for backwards compatibility
        $dbHost = $db['host'] ?? $request->db_host ?? '127.0.0.1';
        $dbPort = $db['port'] ?? $request->db_port ?? '3306';
        $dbName = $db['name'] ?? $request->db_database ?? 'ai_recruitment';
        $dbUser = $db['user'] ?? $request->db_username ?? 'root';
        $dbPass = $db['password'] ?? $request->db_password ?? '';
        $adminName = $admin['name'] ?? $request->admin_name ?? '';
        $adminEmail = $admin['email'] ?? $request->admin_email ?? '';
        $adminPassword = $admin['password'] ?? $request->admin_password ?? '';
        $openaiKey = $ai['openai_key'] ?? $request->openai_key ?? '';
        $heygenKey = $ai['heygen_key'] ?? $request->heygen_key ?? '';
        $appName = $ai['app_name'] ?? $request->platform_name ?? 'AI Recruit';

        if (!$adminName || !$adminEmail || !$adminPassword || !$openaiKey) {
            return response()->json(['message' => 'بيانات مطلوبة مفقودة'], 422);
        }

        try {
            $this->writeEnvValues($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $openaiKey, $heygenKey);

            Artisan::call('config:clear');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]);

            $adminUser = User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]);

            $adminUser->assignRole('super_admin');

            SystemSetting::set('platform_name', $appName);
            SystemSetting::set('openai_api_key', $openaiKey);
            if ($heygenKey) SystemSetting::set('heygen_api_key', $heygenKey);
            SystemSetting::set('is_installed', '1');

            return response()->json(['success' => true, 'message' => 'تم التثبيت بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function writeEnvValues(string $host, string $port, string $db, string $user, string $pass, string $openai, string $heygen = ''): void
    {
        $envPath = base_path('.env');
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        $replacements = [
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $db,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
            'OPENAI_API_KEY' => $openai,
        ];

        if ($heygen) $replacements['HEYGEN_API_KEY'] = $heygen;

        foreach ($replacements as $key => $value) {
            if (preg_match("/^{$key}=/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
