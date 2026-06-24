<?php
/**
 * AI Recruit — Setup Wizard
 * Access: /setup.php
 */

define('BACKEND', __DIR__ . '/backend');
define('LOCK',    __DIR__ . '/.setup.lock');
define('ENVFILE', BACKEND  . '/.env');
define('PHP_BIN', PHP_BINARY ?: 'php');

// ═══════════════════════════════════════
// AJAX HANDLERS
// ═══════════════════════════════════════
if (isset($_GET['a'])) {
    header('Content-Type: application/json; charset=utf-8');
    $a    = $_GET['a'];
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($a !== 'status' && file_exists(LOCK)) { echo json(['error'=>'مقفل','locked'=>true]); exit; }
    match($a) {
        'status'   => print(apiStatus()),
        'check'    => print(apiCheck()),
        'test_db'  => print(apiTestDb($body)),
        'install'  => print(apiInstall($body)),
        'update'   => print(apiUpdate($body)),
        'terminal' => print(apiTerminal($body)),
        'lock'     => print(apiLock()),
        'settings' => print(apiSettings()),
        default    => print(json(['error'=>'unknown'])),
    };
    exit;
}

// ───────────────────────────────────────
function json($d){ return json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }

function apiStatus(){
    return json(['installed'=>isInstalled(),'locked'=>file_exists(LOCK),'exec'=>canExec()]);
}

function apiCheck(){
    $c = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?? '')));
    return json([
        'PHP 8.2+'          =>['status'=>version_compare(PHP_VERSION,'8.2.0','>='),'value'=>PHP_VERSION],
        'PDO MySQL'         =>['status'=>extension_loaded('pdo_mysql'),'value'=>extension_loaded('pdo_mysql')?'✓ مثبت':'✗ غير مثبت'],
        'OpenSSL'           =>['status'=>extension_loaded('openssl'),'value'=>extension_loaded('openssl')?'✓ مثبت':'✗ غير مثبت'],
        'cURL'              =>['status'=>extension_loaded('curl'),'value'=>extension_loaded('curl')?'✓ مثبت':'✗ غير مثبت'],
        'exec() مفعل'       =>['status'=>$c,'value'=>$c?'✓ نعم':'✗ معطل في php.ini'],
        'مجلد backend'      =>['status'=>is_dir(BACKEND),'value'=>is_dir(BACKEND)?'✓ موجود':'✗ غير موجود'],
        'صلاحية الكتابة'    =>['status'=>is_writable(BACKEND),'value'=>is_writable(BACKEND)?'✓ قابل':'✗ غير قابل'],
    ]);
}

function apiTestDb($b){
    try{
        $pdo=new PDO("mysql:host={$b['host']};port={$b['port']};charset=utf8mb4",$b['user'],$b['password'],[PDO::ATTR_TIMEOUT=>5]);
        $db=preg_replace('/[^a-zA-Z0-9_]/','',($b['name'] ?? ''));
        if($db) $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}`");
        return json(['success'=>true,'message'=>"✓ تم الاتصال، قاعدة البيانات `{$db}` جاهزة"]);
    }catch(\PDOException $e){
        return json(['success'=>false,'message'=>$e->getMessage()]);
    }
}

function apiInstall($body){
    $db  = $body['db']    ?? [];
    $adm = $body['admin'] ?? [];
    $ai  = $body['ai']    ?? [];
    if(empty($adm['name'])||empty($adm['email'])||empty($adm['password'])||empty($ai['openai_key'])){
        return json(['success'=>false,'message'=>'يرجى ملء جميع الحقول المطلوبة']);
    }

    // 1. Write .env
    $appName = $ai['app_name'] ?? 'AI Recruit';
    writeEnv([
        'APP_NAME'        => $appName,
        'APP_ENV'         => 'production',
        'APP_KEY'         => '',
        'APP_DEBUG'       => 'false',
        'APP_URL'         => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        'DB_CONNECTION'   => 'mysql',
        'DB_HOST'         => $db['host']     ?? '127.0.0.1',
        'DB_PORT'         => $db['port']     ?? '3306',
        'DB_DATABASE'     => $db['name']     ?? 'ai_recruitment',
        'DB_USERNAME'     => $db['user']     ?? 'root',
        'DB_PASSWORD'     => $db['password'] ?? '',
        'QUEUE_CONNECTION'=> 'database',
        'CACHE_STORE'     => 'database',
        'SESSION_DRIVER'  => 'database',
        'OPENAI_API_KEY'  => $ai['openai_key'] ?? '',
        'HEYGEN_API_KEY'  => $ai['heygen_key'] ?? '',
    ]);

    $log = [];

    // 2. key:generate + jwt:secret
    $log[] = ['cmd'=>'key:generate',  'result'=>artisan('key:generate --force')];
    $log[] = ['cmd'=>'jwt:secret',    'result'=>artisan('jwt:secret --force')];
    $log[] = ['cmd'=>'config:clear',  'result'=>artisan('config:clear')];
    $log[] = ['cmd'=>'migrate',       'result'=>artisan('migrate --force')];
    $log[] = ['cmd'=>'PermissionSeeder','result'=>artisan('db:seed --class=PermissionSeeder --force')];
    $log[] = ['cmd'=>'storage:link',  'result'=>artisan('storage:link --force')];

    // 3. Create super admin via PDO
    try{
        $pdo = new PDO(
            "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4",
            $db['user'], $db['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $now  = date('Y-m-d H:i:s');
        $hash = password_hash($adm['password'], PASSWORD_BCRYPT, ['cost'=>12]);

        $pdo->exec("DELETE FROM users WHERE user_type='super_admin'");
        $st = $pdo->prepare("INSERT INTO users (name,email,password,user_type,is_active,created_at,updated_at) VALUES (?,?,?,'super_admin',1,?,?)");
        $st->execute([$adm['name'],$adm['email'],$hash,$now,$now]);
        $uid = $pdo->lastInsertId();

        $role = $pdo->query("SELECT id FROM roles WHERE name='super_admin' LIMIT 1")->fetchColumn();
        if($role){
            $pdo->exec("INSERT IGNORE INTO model_has_roles(role_id,model_type,model_id) VALUES($role,'App\\\\Models\\\\User',$uid)");
        }

        // System settings
        $rows=[
            'platform_name'  => $appName,
            'openai_api_key' => $ai['openai_key'] ?? '',
            'heygen_api_key' => $ai['heygen_key'] ?? '',
            'is_installed'   => '1',
        ];
        foreach($rows as $k=>$v){
            $pdo->exec("INSERT INTO system_settings(`key`,`value`,created_at,updated_at) VALUES("
                .$pdo->quote($k).",".$pdo->quote($v).",'{$now}','{$now}') "
                ."ON DUPLICATE KEY UPDATE `value`=".$pdo->quote($v).",updated_at='{$now}'");
        }
        $log[] = ['cmd'=>'create admin','result'=>['output'=>'✓ تم إنشاء حساب المدير بنجاح','success'=>true]];
    }catch(\Exception $e){
        $log[] = ['cmd'=>'create admin','result'=>['output'=>'خطأ: '.$e->getMessage(),'success'=>false]];
    }

    $allOk = empty(array_filter($log, fn($l)=>!$l['result']['success']));
    return json(['success'=>$allOk,'log'=>$log,'message'=>$allOk?'🎉 تم التثبيت بنجاح! يمكنك تسجيل الدخول الآن.':'⚠ بعض الخطوات فشلت — راجع السجل أدناه']);
}

function apiUpdate($body){
    $up=[];
    if(!empty($body['db_host']))      $up['DB_HOST']      = $body['db_host'];
    if(!empty($body['db_port']))      $up['DB_PORT']      = $body['db_port'];
    if(!empty($body['db_name']))      $up['DB_DATABASE']  = $body['db_name'];
    if(!empty($body['db_user']))      $up['DB_USERNAME']  = $body['db_user'];
    if(!empty($body['db_password']))  $up['DB_PASSWORD']  = $body['db_password'];
    if(!empty($body['openai_key']))   $up['OPENAI_API_KEY']= $body['openai_key'];
    if(!empty($body['heygen_key']))   $up['HEYGEN_API_KEY']= $body['heygen_key'];
    if(!empty($body['platform_name']))$up['APP_NAME']     = $body['platform_name'];
    if($up) updateEnv($up);
    artisan('config:clear');

    if(!empty($body['admin_email'])||!empty($body['admin_password'])){
        try{
            $e=parseEnv();
            $pdo=new PDO("mysql:host={$e['DB_HOST']};port={$e['DB_PORT']};dbname={$e['DB_DATABASE']};charset=utf8mb4",$e['DB_USERNAME'],$e['DB_PASSWORD']);
            $s=[]; $p=[];
            if(!empty($body['admin_name'])){    $s[]='name=?';     $p[]=$body['admin_name']; }
            if(!empty($body['admin_email'])){   $s[]='email=?';    $p[]=$body['admin_email']; }
            if(!empty($body['admin_password'])){ $s[]='password=?';$p[]=password_hash($body['admin_password'],PASSWORD_BCRYPT,['cost'=>12]); }
            if($s){ $p[]='super_admin'; $pdo->prepare("UPDATE users SET ".implode(',',$s)." WHERE user_type=?")->execute($p); }
        }catch(\Exception $e){}
    }
    return json(['success'=>true,'message'=>'✓ تم الحفظ']);
}

function apiTerminal($body){
    static $ALLOWED=[
        'php artisan migrate --force','php artisan migrate:fresh --seed','php artisan migrate:fresh',
        'php artisan migrate','php artisan db:seed','php artisan key:generate',
        'php artisan jwt:secret','php artisan config:clear','php artisan cache:clear',
        'php artisan optimize:clear','php artisan optimize','php artisan storage:link',
        'php artisan queue:restart','php artisan route:clear','php artisan view:clear',
        'composer install --no-dev','composer install','composer dump-autoload',
    ];
    $cmd=trim($body['command']??'');
    $ok=false;
    foreach($ALLOWED as $a){ if(str_starts_with($cmd,$a)){$ok=true;break;} }
    if(!$ok) return json(['output'=>"الأمر غير مسموح.\n\nالأوامر المتاحة:\n".implode("\n",$ALLOWED),'success'=>false]);
    // Prepend php binary for artisan
    if(str_starts_with($cmd,'php artisan')) $cmd = PHP_BIN.' '.ltrim(substr($cmd,3),' ');
    return json(runIn($cmd, BACKEND));
}

function apiLock(){
    file_put_contents(LOCK, date('Y-m-d H:i:s'));
    return json(['success'=>true]);
}

function apiSettings(){
    $e=parseEnv(); $adm='';
    if(isInstalled()&&!empty($e['DB_HOST'])){
        try{
            $pdo=new PDO("mysql:host={$e['DB_HOST']};port={$e['DB_PORT']};dbname={$e['DB_DATABASE']};charset=utf8mb4",$e['DB_USERNAME'],$e['DB_PASSWORD']);
            $adm=$pdo->query("SELECT email FROM users WHERE user_type='super_admin' LIMIT 1")->fetchColumn()??'';
        }catch(\Exception $ex){}
    }
    return json([
        'db'=>['host'=>$e['DB_HOST']??'127.0.0.1','port'=>$e['DB_PORT']??'3306','name'=>$e['DB_DATABASE']??'','user'=>$e['DB_USERNAME']??''],
        'platform_name' =>$e['APP_NAME']??'AI Recruit',
        'has_openai_key'=>!empty($e['OPENAI_API_KEY']),
        'has_heygen_key'=>!empty($e['HEYGEN_API_KEY']),
        'admin_email'   =>$adm,
        'installed'     =>isInstalled(),
    ]);
}

// ─ Utilities ───────────────────────────────────────────────────
function artisan($cmd){ return runIn(PHP_BIN.' artisan '.$cmd.' 2>&1', BACKEND); }

function runIn($cmd,$cwd=null){
    if(!canExec()) return['output'=>'exec() معطل في إعدادات PHP (disable_functions)','success'=>false];
    $prev=getcwd(); if($cwd) @chdir($cwd);
    exec($cmd.' 2>&1',$out,$code);
    if($prev) @chdir($prev);
    return['output'=>implode("\n",$out)?:'(no output)','success'=>$code===0];
}

function canExec(){
    if(!function_exists('exec')) return false;
    $dis=array_map('trim',explode(',',ini_get('disable_functions')??''));
    return !in_array('exec',$dis);
}

function isInstalled(){
    $e=parseEnv();
    return !empty($e['APP_KEY'])&&!empty($e['DB_DATABASE']);
}

function parseEnv(){
    if(!file_exists(ENVFILE)) return[];
    $r=[];
    foreach(file(ENVFILE,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
        if(str_starts_with(trim($line),'#')) continue;
        [$k,$v]=array_pad(explode('=',$line,2),2,'');
        $r[trim($k)]=trim($v," \t\n\r\0\x0B\"'");
    }
    return $r;
}

function writeEnv(array $vals){
    $src=BACKEND.'/.env.example';
    $content=file_exists($src)?file_get_contents($src)
        :"APP_NAME=\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=\n"
        ."DB_CONNECTION=mysql\nDB_HOST=\nDB_PORT=3306\nDB_DATABASE=\nDB_USERNAME=\nDB_PASSWORD=\n"
        ."QUEUE_CONNECTION=database\nCACHE_STORE=database\nSESSION_DRIVER=database\n"
        ."OPENAI_API_KEY=\nHEYGEN_API_KEY=\n";
    mergeEnv($content,$vals);
}

function updateEnv(array $vals){
    $content=file_exists(ENVFILE)?file_get_contents(ENVFILE):'';
    mergeEnv($content,$vals);
}

function mergeEnv(string $content, array $vals){
    foreach($vals as $k=>$v){
        $v=str_contains($v,' ')?'"'.$v.'"':$v;
        if(preg_match("/^{$k}=/m",$content))
            $content=preg_replace("/^{$k}=.*/m","{$k}={$v}",$content);
        else $content.="\n{$k}={$v}";
    }
    file_put_contents(ENVFILE,$content);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>إعداد المنصة</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#0f0c29;color:#e2e8f0;min-height:100vh;direction:rtl}
.stars{position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:0}
.star{position:absolute;border-radius:50%;background:#fff;opacity:.15}
.wrap{position:relative;z-index:1;max-width:820px;margin:0 auto;padding:32px 16px 80px}
/* header */
.hdr{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.logo{width:44px;height:44px;background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 0 20px #7c3aed55}
.hdr-title{font-size:20px;font-weight:800;color:#fff}
.hdr-sub{font-size:12px;color:#64748b;margin-top:2px}
.badge{margin-right:auto;font-size:11px;padding:4px 12px;border-radius:99px;border:1px solid}
.badge-installed{background:#064e3b22;color:#34d399;border-color:#34d39944}
.badge-login{background:#4c1d9522;color:#a78bfa;border-color:#a78bfa44;text-decoration:none}
/* sections */
.section{background:#1e1b3a;border:1px solid #2d2b5a;border-radius:16px;overflow:hidden;margin-bottom:16px}
.section-head{display:flex;align-items:center;gap:8px;padding:12px 18px;background:#16133060;border-bottom:1px solid #2d2b5a;font-size:13px;font-weight:700;color:#c4b5fd}
.section-body{padding:20px;display:grid;gap:14px}
.g2{grid-template-columns:1fr 1fr}
/* fields */
label{display:block;font-size:11px;font-weight:600;color:#94a3b8;margin-bottom:5px}
input[type=text],input[type=password],input[type=email]{width:100%;background:#0f0c29;border:1px solid #2d2b5a;border-radius:8px;padding:10px 12px;color:#e2e8f0;font-size:13px;outline:none;transition:.2s}
input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px #7c3aed22}
input::placeholder{color:#374151}
.hint{font-size:10px;color:#475569;margin-top:4px}
/* buttons */
.btn{cursor:pointer;border:none;border-radius:10px;font-size:13px;font-weight:700;padding:10px 20px;transition:.2s;display:inline-flex;align-items:center;gap:6px}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn-sm{padding:7px 14px;font-size:12px}
.btn-ghost{background:#1e293b;color:#94a3b8;border:1px solid #2d2b5a}
.btn-ghost:hover:not(:disabled){background:#273044;color:#e2e8f0}
.btn-violet{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;box-shadow:0 4px 16px #7c3aed44}
.btn-violet:hover:not(:disabled){opacity:.9}
.btn-green{background:linear-gradient(135deg,#059669,#0d9488);color:#fff;box-shadow:0 4px 16px #05966944}
.btn-green:hover:not(:disabled){opacity:.9}
.btn-red{background:#7f1d1d44;color:#fca5a5;border:1px solid #7f1d1d66}
.btn-red:hover:not(:disabled){background:#7f1d1d66}
.btn-full{width:100%;justify-content:center;padding:14px;font-size:15px;border-radius:12px;margin-top:4px}
/* alerts */
.alert{border-radius:8px;padding:10px 14px;font-size:12px;margin-top:6px}
.alert-ok{background:#064e3b22;color:#34d399;border:1px solid #34d39944}
.alert-err{background:#7f1d1d22;color:#fca5a5;border:1px solid #7f1d1d44}
.alert-info{background:#1e3a5f22;color:#7dd3fc;border:1px solid #7dd3fc44}
/* sys checks */
.checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px}
.check{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:12px}
.check-ok{background:#064e3b22;color:#34d399}
.check-err{background:#7f1d1d22;color:#fca5a5}
/* install log */
.ilog{background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:14px;max-height:220px;overflow-y:auto;font-family:monospace;font-size:11px;line-height:1.7}
.ilog .ok{color:#3fb950}
.ilog .err{color:#f85149}
.ilog .cmd{color:#79c0ff}
/* terminal */
.term{background:#0d1117;border:1px solid #30363d;border-radius:16px;overflow:hidden}
.term-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#161b22;border-bottom:1px solid #30363d}
.term-dots{display:flex;gap:6px}
.term-dots span{width:12px;height:12px;border-radius:50%}
.term-title{font-size:11px;color:#8b949e;font-family:monospace}
.quick-cmds{padding:10px 14px 8px;border-bottom:1px solid #30363d;display:flex;flex-wrap:wrap;gap:6px}
.qbtn{background:#1c2128;border:1px solid #30363d;color:#8b949e;font-size:10px;padding:3px 8px;border-radius:4px;cursor:pointer;font-family:monospace;transition:.15s}
.qbtn:hover{border-color:#58a6ff;color:#c9d1d9}
.term-out{height:200px;overflow-y:auto;padding:14px;font-family:monospace;font-size:11px;line-height:1.7;background:#0d1117}
.term-out .tline-cmd{color:#58a6ff}
.term-out .tline-ok{color:#3fb950;white-space:pre-wrap}
.term-out .tline-err{color:#f85149;white-space:pre-wrap}
.term-out .tline-dim{color:#484f58}
.term-inp{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#161b22;border-top:1px solid #30363d}
.term-prompt{color:#58a6ff;font-family:monospace;font-size:13px}
.term-inp input{flex:1;background:transparent;border:none;color:#c9d1d9;font-family:monospace;font-size:12px;outline:none}
.term-inp input::placeholder{color:#484f58}
/* security */
.sec{background:#1a0808;border:1px solid #7f1d1d55;border-radius:16px;padding:20px;margin-top:16px}
.sec h3{color:#f87171;font-size:13px;margin-bottom:8px}
.sec p{color:#9f7b7b;font-size:12px;line-height:1.7;margin-bottom:14px}
.sec code{color:#f87171;background:#2a0a0a;padding:2px 6px;border-radius:4px}
/* spinner */
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;width:14px;height:14px;border:2px solid #fff4;border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
/* locked screen */
.locked{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:24px}
.locked h1{font-size:28px;color:#fff;margin:16px 0 8px}
.locked p{color:#64748b;line-height:1.7;max-width:380px}
@media(max-width:600px){.g2{grid-template-columns:1fr}}
</style>
</head>
<body>
<!-- Stars -->
<div class="stars" id="stars"></div>

<?php if(file_exists(LOCK)): ?>
<!-- LOCKED -->
<div class="locked">
  <div style="font-size:64px">🔒</div>
  <h1>صفحة الإعداد مقفلة</h1>
  <p>تم قفل هذه الصفحة لأسباب أمنية.<br>لإعادة الفتح، احذف الملف <code style="color:#a78bfa;background:#1e1b3a;padding:2px 8px;border-radius:4px">.setup.lock</code> من جذر المشروع عبر File Manager في Plesk.</p>
  <a href="/login" style="margin-top:24px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:10px 28px;border-radius:10px;text-decoration:none;font-weight:700">تسجيل الدخول</a>
</div>

<?php else: ?>
<div class="wrap">
  <!-- Header -->
  <div class="hdr">
    <div class="logo">⚡</div>
    <div>
      <div class="hdr-title">إعداد المنصة</div>
      <div class="hdr-sub">Setup Wizard</div>
    </div>
    <span id="hdrBadge" style="display:none" class="badge badge-installed">✓ مُثبَّت</span>
    <a href="/login" id="hdrLogin" style="display:none" class="badge badge-login">تسجيل الدخول →</a>
  </div>

  <!-- System Status -->
  <div class="section">
    <div class="section-head">🖥 فحص متطلبات النظام</div>
    <div class="section-body" style="padding:16px">
      <div id="checks" class="checks"><div class="tline-dim" style="font-size:12px;color:#484f58">جاري الفحص...</div></div>
    </div>
  </div>

  <!-- DB -->
  <div class="section">
    <div class="section-head">🗄️ قاعدة البيانات</div>
    <div class="section-body g2">
      <div><label>المضيف (Host)</label><input id="dbHost" value="127.0.0.1" placeholder="127.0.0.1"></div>
      <div><label>المنفذ (Port)</label><input id="dbPort" value="3306" placeholder="3306"></div>
      <div><label>اسم قاعدة البيانات</label><input id="dbName" value="ai_recruitment" placeholder="ai_recruitment"></div>
      <div><!-- spacer --></div>
      <div><label>اسم المستخدم</label><input id="dbUser" value="root" placeholder="root"></div>
      <div><label>كلمة المرور</label><input type="password" id="dbPass" placeholder="••••••"><span id="dbPassHint" class="hint" style="display:none">اتركها فارغة للإبقاء على الحالية</span></div>
      <div style="grid-column:1/-1;display:flex;align-items:center;gap:10px">
        <button class="btn btn-ghost btn-sm" onclick="testDb()"><span id="testSpinner" style="display:none" class="spin"></span>⚡ اختبار الاتصال</button>
        <span id="testResult" style="font-size:12px"></span>
      </div>
    </div>
  </div>

  <!-- Admin -->
  <div class="section">
    <div class="section-head">👤 حساب المسؤول الأعلى (Super Admin)</div>
    <div class="section-body g2">
      <div><label>الاسم الكامل</label><input id="admName" placeholder="اسمك الكامل"></div>
      <div><label>البريد الإلكتروني</label><input type="email" id="admEmail" placeholder="admin@company.com"></div>
      <div style="grid-column:1/-1"><label id="passLabel">كلمة المرور *</label><input type="password" id="admPass" placeholder="••••••••"><span id="admPassHint" class="hint" style="display:none">اتركها فارغة للإبقاء على الحالية</span></div>
    </div>
  </div>

  <!-- Platform -->
  <div class="section">
    <div class="section-head">🔑 المنصة ومفاتيح الذكاء الاصطناعي</div>
    <div class="section-body">
      <div><label>اسم المنصة</label><input id="platName" value="AI Recruit" placeholder="AI Recruit"></div>
      <div><label>مفتاح OpenAI API *</label><input type="password" id="openaiKey" placeholder="sk-..."><span id="openaiHint" class="hint">مطلوب لجميع ميزات الذكاء الاصطناعي</span></div>
      <div><label>مفتاح HeyGen API (اختياري)</label><input type="password" id="heygenKey" placeholder="..."><span class="hint">للمقابلات بالفيديو — يمكن تركه فارغاً</span></div>
    </div>
  </div>

  <!-- Main Button -->
  <button id="mainBtn" class="btn btn-violet btn-full" onclick="mainAction()">
    🚀 تثبيت المنصة
  </button>

  <!-- Install Log -->
  <div id="ilogWrap" style="display:none;margin-top:14px">
    <div class="ilog" id="ilog"></div>
    <div id="ilogMsg" style="margin-top:10px;font-size:13px;text-align:center"></div>
  </div>

  <!-- ══════════ TERMINAL ══════════ -->
  <div style="margin-top:20px">
    <div class="term">
      <div class="term-bar">
        <div class="term-dots">
          <span style="background:#ff5f57"></span>
          <span style="background:#febc2e"></span>
          <span style="background:#28c840"></span>
        </div>
        <span class="term-title">server terminal</span>
        <span style="font-size:10px;color:#484f58" id="termStatus">● جاهز</span>
      </div>
      <div class="quick-cmds" id="quickCmds"></div>
      <div class="term-out" id="termOut"><div class="tline-dim"># اكتب أمراً أو اختر من الأوامر السريعة أعلاه</div></div>
      <div class="term-inp">
        <span class="term-prompt">$</span>
        <input id="termIn" placeholder="php artisan ..." onkeydown="termKey(event)">
        <button class="btn btn-ghost btn-sm" onclick="runTerm()" style="font-size:11px;padding:5px 12px">تشغيل</button>
      </div>
    </div>
  </div>

  <!-- Security -->
  <div class="sec">
    <h3>🔐 الأمان — قفل صفحة الإعداد</h3>
    <p>بعد الانتهاء، يُنصح بقفل هذه الصفحة لمنع أي وصول غير مصرح.<br>
    سيُنشأ ملف <code>.setup.lock</code> في جذر المشروع. لإعادة الفتح: احذف هذا الملف من File Manager في Plesk.</p>
    <button class="btn btn-red btn-sm" onclick="lockSetup()" id="lockBtn">🔒 قفل صفحة الإعداد نهائياً</button>
  </div>
</div><!-- /wrap -->

<script>
const QUICK = [
  'php artisan migrate --force',
  'php artisan migrate:fresh --seed',
  'php artisan key:generate',
  'php artisan jwt:secret',
  'php artisan config:clear',
  'php artisan cache:clear',
  'php artisan optimize:clear',
  'php artisan storage:link',
  'php artisan queue:restart',
  'composer install --no-dev',
  'composer dump-autoload',
];

let termHistory = [], histIdx = -1, installed = false;

// ─── Init ───────────────────────────────────────────────────
async function init() {
  // Stars
  const s = document.getElementById('stars');
  for(let i=0;i<60;i++){
    const d=document.createElement('div');
    d.className='star';
    const sz=i%5===0?2:1;
    Object.assign(d.style,{width:sz+'px',height:sz+'px',top:`${(i*37+11)%100}%`,left:`${(i*61+7)%100}%`});
    s.appendChild(d);
  }

  // Quick cmds
  const qc = document.getElementById('quickCmds');
  QUICK.forEach(c=>{ const b=document.createElement('button'); b.className='qbtn'; b.textContent=c; b.onclick=()=>{document.getElementById('termIn').value=c;}; qc.appendChild(b); });

  // System checks
  const cr = await api('check'); renderChecks(cr);

  // Status
  const st = await api('status');
  installed = st.installed;
  if(installed) {
    switchToEdit();
    const cfg = await api('settings');
    prefill(cfg);
  }
}

function switchToEdit(){
  document.getElementById('hdrBadge').style.display='';
  document.getElementById('hdrLogin').style.display='';
  document.getElementById('mainBtn').textContent='💾 حفظ التغييرات';
  document.getElementById('mainBtn').className='btn btn-green btn-full';
  document.getElementById('passLabel').textContent='كلمة مرور جديدة';
  document.getElementById('dbPassHint').style.display='';
  document.getElementById('admPassHint').style.display='';
  document.getElementById('openaiHint').textContent='اتركه فارغاً للإبقاء على المفتاح الحالي';
}

function prefill(cfg){
  if(cfg.db){
    document.getElementById('dbHost').value = cfg.db.host||'';
    document.getElementById('dbPort').value = cfg.db.port||'';
    document.getElementById('dbName').value = cfg.db.name||'';
    document.getElementById('dbUser').value = cfg.db.user||'';
  }
  if(cfg.platform_name) document.getElementById('platName').value = cfg.platform_name;
  if(cfg.admin_email)   document.getElementById('admEmail').value = cfg.admin_email;
}

function renderChecks(data){
  const el = document.getElementById('checks');
  el.innerHTML='';
  for(const [k,v] of Object.entries(data)){
    el.innerHTML+=`<div class="check ${v.status?'check-ok':'check-err'}"><span>${v.status?'✓':'✗'}</span><span>${k}</span><span style="margin-right:auto;font-size:10px">${v.value}</span></div>`;
  }
}

// ─── Main action ────────────────────────────────────────────
async function mainAction(){
  if(installed) await saveChanges();
  else await installPlatform();
}

async function installPlatform(){
  const btn=document.getElementById('mainBtn');
  btn.disabled=true; btn.innerHTML='<span class="spin"></span> جاري التثبيت...';
  document.getElementById('ilogWrap').style.display='';
  document.getElementById('ilog').innerHTML='';
  document.getElementById('ilogMsg').innerHTML='';

  const body={
    db:{ host:v('dbHost'),port:v('dbPort'),name:v('dbName'),user:v('dbUser'),password:v('dbPass') },
    admin:{ name:v('admName'),email:v('admEmail'),password:v('admPass') },
    ai:{ app_name:v('platName'),openai_key:v('openaiKey'),heygen_key:v('heygenKey') }
  };

  const r = await api('install',body);

  if(r.log) r.log.forEach(l=>{
    const ok=l.result.success;
    document.getElementById('ilog').innerHTML+=`<div class="cmd">▶ ${l.cmd}</div><div class="${ok?'ok':'err'}">${l.result.output}</div><br>`;
    document.getElementById('ilog').scrollTop=9999;
  });

  const msg=document.getElementById('ilogMsg');
  if(r.success){
    msg.innerHTML=`<span style="color:#34d399;font-size:14px">🎉 ${r.message}</span><br><a href="/login" style="color:#a78bfa;margin-top:6px;display:inline-block">→ انتقل لتسجيل الدخول</a>`;
    installed=true; switchToEdit();
  } else {
    msg.innerHTML=`<span style="color:#f87171">${r.message||'حدث خطأ'}</span>`;
    btn.disabled=false; btn.innerHTML='🚀 تثبيت المنصة';
  }
}

async function saveChanges(){
  const btn=document.getElementById('mainBtn');
  btn.disabled=true; btn.innerHTML='<span class="spin"></span> جاري الحفظ...';
  const r=await api('update',{
    db_host:v('dbHost'),db_port:v('dbPort'),db_name:v('dbName'),db_user:v('dbUser'),db_password:v('dbPass'),
    platform_name:v('platName'),openai_key:v('openaiKey'),heygen_key:v('heygenKey'),
    admin_name:v('admName'),admin_email:v('admEmail'),admin_password:v('admPass'),
  });
  btn.disabled=false; btn.innerHTML='💾 حفظ التغييرات';
  showToast(r.message||'تم',r.success?'ok':'err');
}

// ─── DB Test ────────────────────────────────────────────────
async function testDb(){
  const el=document.getElementById('testResult');
  document.getElementById('testSpinner').style.display='';
  el.textContent='';
  const r=await api('test_db',{host:v('dbHost'),port:v('dbPort'),name:v('dbName'),user:v('dbUser'),password:v('dbPass')});
  document.getElementById('testSpinner').style.display='none';
  el.innerHTML=`<span style="color:${r.success?'#34d399':'#f87171'}">${r.message}</span>`;
}

// ─── Terminal ───────────────────────────────────────────────
function termKey(e){
  if(e.key==='Enter'){ e.preventDefault(); runTerm(); }
  else if(e.key==='ArrowUp'){ e.preventDefault(); if(histIdx+1<termHistory.length){histIdx++;document.getElementById('termIn').value=termHistory[termHistory.length-1-histIdx];} }
  else if(e.key==='ArrowDown'){ e.preventDefault(); if(histIdx>0){histIdx--;document.getElementById('termIn').value=termHistory[termHistory.length-1-histIdx];}else{histIdx=-1;document.getElementById('termIn').value='';} }
}

async function runTerm(){
  const inp=document.getElementById('termIn');
  const cmd=inp.value.trim(); if(!cmd) return;
  inp.value=''; histIdx=-1;
  termHistory.push(cmd);
  const out=document.getElementById('termOut');
  out.innerHTML+=`<div class="tline-cmd">$ ${cmd}</div>`;
  document.getElementById('termStatus').textContent='● جاري التنفيذ...';

  const r=await api('terminal',{command:cmd});
  out.innerHTML+=`<div class="${r.success?'tline-ok':'tline-err'}">${escHtml(r.output)}</div><br>`;
  out.scrollTop=9999;
  document.getElementById('termStatus').textContent='● جاهز';
}

// ─── Lock ───────────────────────────────────────────────────
async function lockSetup(){
  if(!confirm('هل أنت متأكد؟ لن تتمكن من الوصول لهذه الصفحة إلا بحذف ملف .setup.lock من File Manager في Plesk.')) return;
  const r=await api('lock');
  if(r.success) location.reload();
}

// ─── Helpers ────────────────────────────────────────────────
function v(id){ return document.getElementById(id).value.trim(); }
function escHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showToast(msg,type){
  const t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:'+(type==='ok'?'#064e3b':'#7f1d1d')+';color:'+(type==='ok'?'#34d399':'#fca5a5')+';border:1px solid '+(type==='ok'?'#34d39944':'#f8514944')+';padding:10px 22px;border-radius:10px;font-size:13px;z-index:999;font-weight:600';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(()=>t.remove(),3500);
}

async function api(action, body=null){
  try{
    const r=await fetch(`?a=${action}`,{method:body?'POST':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body||{})});
    return await r.json();
  }catch(e){ return {error:e.message,success:false,output:e.message}; }
}

init();
</script>
<?php endif; ?>
</body>
</html>
