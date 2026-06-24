<?php
namespace App\Modules\Terminal;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Maintenance terminal for platform operators.
 *
 * SECURITY MODEL
 * --------------
 * User input never reaches a shell. The request carries only a `command`
 * KEY which is looked up in a fixed allow-list ($this->commands) mapping the
 * key to a private handler. Shell-backed handlers run a hard-coded command
 * string (further hardened with escapeshellcmd) — there is no concatenation
 * of user-supplied data. Anything not present in the allow-list is rejected.
 * The endpoint additionally requires the `platform.terminal` permission.
 */
class TerminalController
{
    private Auth $auth;
    private Request $request;

    /** @var array<string, callable():string> key => handler */
    private array $commands;

    public function __construct(?Auth $auth = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->request = $request ?? new Request();

        // Build the allow-list. Keys are the ONLY values accepted from clients.
        $this->commands = [
            'php_version' => fn(): string => $this->runShell('php -v'),
            'php_modules' => fn(): string => $this->runShell('php -m'),
            'php_info'    => fn(): string => $this->phpInfoSummary(),
            'disk'        => fn(): string => $this->runShell('df -h'),
            'memory'      => fn(): string => $this->runShell('free -m'),
            'top'         => fn(): string => $this->runTop(),
            'logs'        => fn(): string => $this->tailLog(),
            'clear_cache' => fn(): string => $this->clearCache(),
            'db_test'     => fn(): string => $this->dbTest(),
            'permissions' => fn(): string => $this->checkPermissions(),
        ];
    }

    /**
     * Render the terminal UI.
     */
    public function show(array $params = []): void
    {
        $this->auth->requirePermission('platform.terminal');

        Response::view('super-admin.terminal', [
            'commands'   => array_keys($this->commands),
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Execute a whitelisted command (POST).
     */
    public function execute(array $params = []): void
    {
        $this->auth->requirePermission('platform.terminal');

        if (!$this->request->isPost()) {
            Response::error('Method not allowed', 405);
            return;
        }

        $key = $this->request->input('command');
        if (!is_string($key) || $key === '') {
            Response::error('A command key is required', 422);
            return;
        }

        // Strict allow-list check. Never pass the value to a shell.
        if (!array_key_exists($key, $this->commands)) {
            Response::error('Command not allowed', 403, [
                'allowed' => array_keys($this->commands),
            ]);
            return;
        }

        try {
            $output = ($this->commands[$key])();
            Response::success([
                'command' => $key,
                'output'  => $output,
            ]);
        } catch (\Throwable $e) {
            logger('Terminal command failed [' . $key . ']: ' . $e->getMessage(), 'error');
            Response::error('Command execution failed', 500);
        }
    }

    // ----------------------------------------------------------------------
    // Shell-backed handlers (FIXED command strings only).
    // ----------------------------------------------------------------------

    /**
     * Run a fixed, hard-coded command. The string is never built from user
     * input; escapeshellcmd is a belt-and-braces guard against accidental
     * metacharacters in the literal.
     */
    private function runShell(string $fixedCommand): string
    {
        $safe = escapeshellcmd($fixedCommand);
        $output = $this->procRun($safe);
        return $output !== '' ? $output : '(no output)';
    }

    private function runTop(): string
    {
        // top -bn1 then trim to the first 20 lines.
        $raw = $this->procRun(escapeshellcmd('top -bn1'));
        return $this->limitLines($raw, 20);
    }

    private function phpInfoSummary(): string
    {
        $raw = $this->procRun(escapeshellcmd('php -i'));
        if ($raw === '') {
            // Fallback to the in-process phpinfo if the CLI binary is absent.
            ob_start();
            phpinfo(INFO_GENERAL | INFO_CONFIGURATION);
            $raw = (string) ob_get_clean();
        }
        return $this->limitLines($raw, 80);
    }

    /**
     * Execute a command via proc_open and capture stdout+stderr.
     */
    private function procRun(string $command): string
    {
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]) ?: '';
                $stderr = stream_get_contents($pipes[2]) ?: '';
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $combined = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
                return $combined;
            }
        }

        // Fallback to shell_exec if proc_open is disabled.
        if (function_exists('shell_exec')) {
            $out = @shell_exec($command . ' 2>&1');
            return is_string($out) ? trim($out) : '';
        }

        return '(shell execution is disabled on this server)';
    }

    // ----------------------------------------------------------------------
    // PHP-native handlers (NO shell).
    // ----------------------------------------------------------------------

    /**
     * Tail the last 50 lines of storage/logs/app.log via file I/O.
     */
    private function tailLog(): string
    {
        $path = BASE_PATH . '/storage/logs/app.log';
        if (!is_file($path) || !is_readable($path)) {
            return '(log file not found)';
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '(unable to read log file)';
        }
        $tail = array_slice($lines, -50);
        return $tail === [] ? '(log is empty)' : implode("\n", $tail);
    }

    /**
     * Delete files inside storage/cache via glob/unlink (no shell rm).
     */
    private function clearCache(): string
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            return '(cache directory does not exist)';
        }

        $deleted = 0;
        $failed = 0;
        $entries = glob($dir . '/*') ?: [];
        foreach ($entries as $entry) {
            // Keep dotfiles like .gitignore; only clear regular cache files/dirs.
            $base = basename($entry);
            if ($base === '' || $base[0] === '.') {
                continue;
            }
            if (is_file($entry)) {
                if (@unlink($entry)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } elseif (is_dir($entry)) {
                if ($this->removeDirectory($entry)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
        }

        return sprintf('Cache cleared. Removed: %d, failed: %d.', $deleted, $failed);
    }

    /**
     * Attempt a database connection and report the result.
     */
    private function dbTest(): string
    {
        try {
            $pdo = Database::instance()->connect();
            $version = '';
            try {
                $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\Throwable $e) {
                $version = 'unknown';
            }
            return 'Database connection OK. Server version: ' . ($version !== '' ? $version : 'unknown');
        } catch (\Throwable $e) {
            return 'Database connection FAILED: ' . $e->getMessage();
        }
    }

    /**
     * Report writability of key storage paths and the .env file.
     */
    private function checkPermissions(): string
    {
        $paths = [
            'storage/logs'    => BASE_PATH . '/storage/logs',
            'storage/cache'   => BASE_PATH . '/storage/cache',
            'storage/uploads' => BASE_PATH . '/storage/uploads',
            '.env'            => BASE_PATH . '/.env',
        ];

        $lines = [];
        foreach ($paths as $label => $path) {
            if (!file_exists($path)) {
                $lines[] = sprintf('%-16s : MISSING', $label);
                continue;
            }
            $writable = is_writable($path);
            $perms = @fileperms($path);
            $mode = $perms !== false ? substr(sprintf('%o', $perms), -4) : '----';
            $lines[] = sprintf(
                '%-16s : %s (mode %s)',
                $label,
                $writable ? 'WRITABLE' : 'NOT WRITABLE',
                $mode
            );
        }

        return implode("\n", $lines);
    }

    // ----------------------------------------------------------------------
    // Helpers.
    // ----------------------------------------------------------------------

    private function limitLines(string $text, int $max): string
    {
        if ($text === '') {
            return '(no output)';
        }
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $lines = array_slice($lines, 0, $max);
        return implode("\n", $lines);
    }

    private function removeDirectory(string $dir): bool
    {
        $entries = glob($dir . '/*') ?: [];
        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);
            } else {
                @unlink($entry);
            }
        }
        return @rmdir($dir);
    }
}
