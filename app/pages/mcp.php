<?php
/**
 * نقطة اتصال MCP (JSON-RPC 2.0 عبر HTTP) لتطبيقات ChatGPT / Claude.
 * المصادقة: ترويسة Authorization: Bearer <token>. التوكن بيحدّد الشركة والصلاحيات.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$rpc = function ($id, $result) { echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
$rpcErr = function ($id, int $code, string $msg, int $http = 200) { http_response_code($http); echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]], JSON_UNESCAPED_UNICODE); exit; };

// التوكن
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($auth === '' && function_exists('apache_request_headers')) { $h = apache_request_headers(); $auth = (string)($h['Authorization'] ?? $h['authorization'] ?? ''); }
$token = preg_match('/Bearer\s+(\S+)/i', $auth, $m) ? $m[1] : (string)($_GET['token'] ?? '');
$tok = $token !== '' ? mcp_token_resolve($token) : null;
if (!$tok) { header('WWW-Authenticate: Bearer'); $rpcErr(null, -32001, 'Unauthorized: provide a valid Bearer token from the panel (المساعد الخارجي).', 401); }
$wid = (int)$tok['workspace_id']; $scopes = (string)$tok['scopes'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') { http_response_code(200); echo json_encode(['ok' => true, 'server' => 'watad-mcp']); exit; }

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) $rpcErr(null, -32700, 'Parse error');
// دعم الدفعات غير مطلوب؛ نتعامل مع رسالة واحدة
$id = $body['id'] ?? null; $method = (string)($body['method'] ?? ''); $params = (array)($body['params'] ?? []);

// إشعارات (بدون id) مفيش رد
if ($method === 'notifications/initialized' || strpos($method, 'notifications/') === 0) { http_response_code(202); exit; }

switch ($method) {
    case 'initialize':
        $rpc($id, ['protocolVersion' => '2024-11-05', 'capabilities' => ['tools' => new stdClass()], 'serverInfo' => ['name' => (string)setting('site_name', 'Watad'), 'version' => defined('APP_VERSION') ? APP_VERSION : '1.0']]);
        break;
    case 'ping':
        $rpc($id, new stdClass());
        break;
    case 'tools/list':
        $rpc($id, ['tools' => mcp_tools()]);
        break;
    case 'tools/call':
        $name = (string)($params['name'] ?? ''); $args = (array)($params['arguments'] ?? []);
        $tid = (int)$tok['id'];
        $res = ai_as_workspace($wid, function () use ($name, $args, $scopes, $tid) { return mcp_call($name, $args, $scopes, $tid); });
        $rpc($id, $res);
        break;
    default:
        $rpcErr($id, -32601, 'Method not found: ' . $method);
}
