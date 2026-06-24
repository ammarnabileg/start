<?php
/**
 * Web front controller. Renders server-side views and handles web routes.
 * API traffic is handled separately by /api/v1/index.php.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

// ---------------------------------------------------------------------------
// Installation gate: if no .env or DB not ready, force the setup wizard.
// ---------------------------------------------------------------------------
$envExists = file_exists(BASE_PATH . '/.env');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!$envExists) {
    if (strpos($uri, '/setup') !== 0) {
        Response::redirect('/setup/');
    }
    require BASE_PATH . '/setup/index.php';
    return;
}

// Boot DB + tenant. If DB fails, send to setup.
try {
    Database::instance(require BASE_PATH . '/config/database.php');
    Database::instance()->connect();
    (new Tenant())->resolve();
} catch (\Throwable $e) {
    if (strpos($uri, '/setup') !== 0) {
        Response::redirect('/setup/');
    }
}

$app = new App();
$auth = new Auth();

// ---- Middleware ----------------------------------------------------------
$app->middleware('auth', function () use ($auth) {
    if (!$auth->check()) {
        Response::redirect('/login');
        return false;
    }
    return true;
});

$app->middleware('super', function () use ($auth) {
    if (!$auth->check()) {
        Response::redirect('/login');
        return false;
    }
    $u = $auth->user();
    if ((int) ($u['is_super_admin'] ?? 0) !== 1) {
        Response::redirect('/dashboard');
        return false;
    }
    return true;
});

// Helper: render a view inside a layout.
$render = function (string $view, array $data = [], string $layout = 'layouts.app') use ($auth) {
    $data['__auth'] = $auth;
    $data['__user'] = $auth->user();
    $data['csrf'] = Request::csrfToken();
    $content = Response::render($view, $data);
    Response::view($layout, array_merge($data, ['content' => $content, 'active' => $data['active'] ?? '']));
};

// ---------------------------------------------------------------------------
// Public / auth routes
// ---------------------------------------------------------------------------
$app->get('/', function () use ($auth) {
    Response::redirect($auth->check() ? '/dashboard' : '/login');
});

$app->get('/login', function () use ($auth) {
    if ($auth->check()) {
        Response::redirect('/dashboard');
    }
    Response::view('layouts.auth', [
        'content' => Response::render('auth.login', ['csrf' => Request::csrfToken()]),
        'title'   => 'Sign In',
    ]);
});

$app->post('/login', function () use ($auth) {
    $req = new Request();
    $email = trim((string) $req->post('email', ''));
    $password = (string) $req->post('password', '');
    $tenantId = (new Tenant())->currentId();
    $token = $auth->login($email, $password, $tenantId);
    if ($token === false) {
        Response::view('layouts.auth', [
            'content' => Response::render('auth.login', [
                'csrf'  => Request::csrfToken(),
                'error' => 'Invalid email or password.',
                'email' => $email,
            ]),
            'title' => 'Sign In',
        ]);
        return;
    }
    $u = $auth->user();
    Response::redirect((int) ($u['is_super_admin'] ?? 0) === 1 ? '/admin/dashboard' : '/dashboard');
});

$app->get('/logout', function () use ($auth) {
    $auth->logout();
    Response::redirect('/login');
});
$app->post('/logout', function () use ($auth) {
    $auth->logout();
    Response::redirect('/login');
});

// ---------------------------------------------------------------------------
// HR app routes (require auth)
// ---------------------------------------------------------------------------
$app->get('/dashboard', function () use ($render) {
    $render('hr.dashboard', ['active' => 'dashboard', 'title' => 'Dashboard']);
}, ['auth']);

$app->get('/jobs', function () use ($render) {
    $render('hr.jobs.index', ['active' => 'jobs', 'title' => 'Jobs']);
}, ['auth']);
$app->get('/jobs/create', function () use ($render) {
    $render('hr.jobs.create', ['active' => 'jobs', 'title' => 'Create Job']);
}, ['auth']);
$app->get('/jobs/{id}', function ($p) use ($render) {
    $render('hr.jobs.show', ['active' => 'jobs', 'title' => 'Job', 'jobId' => $p['id']]);
}, ['auth']);
$app->get('/jobs/{id}/edit', function ($p) use ($render) {
    $render('hr.jobs.create', ['active' => 'jobs', 'title' => 'Edit Job', 'jobId' => $p['id'], 'editing' => true]);
}, ['auth']);

$app->get('/candidates', function () use ($render) {
    $render('hr.candidates.index', ['active' => 'candidates', 'title' => 'Candidates']);
}, ['auth']);
$app->get('/candidates/compare', function () use ($render) {
    $render('hr.candidates.compare', ['active' => 'candidates', 'title' => 'Compare Candidates']);
}, ['auth']);
$app->get('/candidates/{id}', function ($p) use ($render) {
    $render('hr.candidates.show', ['active' => 'candidates', 'title' => 'Candidate', 'candidateId' => $p['id']]);
}, ['auth']);

$app->get('/interviews', function () use ($render) {
    $render('hr.interviews.index', ['active' => 'interviews', 'title' => 'Interviews']);
}, ['auth']);
$app->get('/interviews/{id}/report', function ($p) use ($render) {
    $render('hr.interviews.report', ['active' => 'interviews', 'title' => 'Interview Report', 'interviewId' => $p['id']]);
}, ['auth']);

$app->get('/pipeline', function () use ($render) {
    $render('hr.pipeline', ['active' => 'pipeline', 'title' => 'Pipeline']);
}, ['auth']);
$app->get('/human-interviews', function () use ($render) {
    $render('hr.human-interviews', ['active' => 'interviews', 'title' => 'Human Interviews']);
}, ['auth']);
$app->get('/offers', function () use ($render) {
    $render('hr.offers', ['active' => 'offers', 'title' => 'Offers']);
}, ['auth']);
$app->get('/talent-pool', function () use ($render) {
    $render('hr.talent-pool', ['active' => 'talent_pool', 'title' => 'Talent Pool']);
}, ['auth']);
$app->get('/avatars', function () use ($render) {
    $render('hr.avatars', ['active' => 'avatars', 'title' => 'Avatars']);
}, ['auth']);
$app->get('/users', function () use ($render) {
    $render('hr.users', ['active' => 'users', 'title' => 'Users']);
}, ['auth']);
$app->get('/roles', function () use ($render) {
    $render('hr.roles', ['active' => 'users', 'title' => 'Roles']);
}, ['auth']);
$app->get('/settings', function () use ($render) {
    $render('hr.settings', ['active' => 'settings', 'title' => 'Settings']);
}, ['auth']);

// ---------------------------------------------------------------------------
// Super admin routes
// ---------------------------------------------------------------------------
$adminRender = function (string $view, array $data = []) use ($auth) {
    $data['__auth'] = $auth;
    $data['__user'] = $auth->user();
    $data['csrf'] = Request::csrfToken();
    $content = Response::render($view, $data);
    Response::view('layouts.admin', array_merge($data, ['content' => $content, 'active' => $data['active'] ?? '']));
};

$app->get('/admin', function () { Response::redirect('/admin/dashboard'); }, ['super']);
$app->get('/admin/dashboard', function () use ($adminRender) {
    $adminRender('super-admin.dashboard', ['active' => 'dashboard', 'title' => 'Platform Dashboard']);
}, ['super']);
$app->get('/admin/companies', function () use ($adminRender) {
    $adminRender('super-admin.companies', ['active' => 'companies', 'title' => 'Companies']);
}, ['super']);
$app->get('/admin/ai-analytics', function () use ($adminRender) {
    $adminRender('super-admin.ai-analytics', ['active' => 'analytics', 'title' => 'AI Analytics']);
}, ['super']);
$app->get('/admin/terminal', function () use ($adminRender) {
    $adminRender('super-admin.terminal', ['active' => 'terminal', 'title' => 'Terminal']);
}, ['super']);

// ---------------------------------------------------------------------------
// Candidate portal
// ---------------------------------------------------------------------------
$candidateRender = function (string $view, array $data = []) {
    $data['csrf'] = Request::csrfToken();
    $content = Response::render($view, $data);
    Response::view('layouts.candidate', array_merge($data, ['content' => $content]));
};
$app->get('/candidate', function () { Response::redirect('/candidate/dashboard'); });
$app->get('/candidate/dashboard', function () use ($candidateRender) {
    $candidateRender('candidate.dashboard', ['title' => 'My Dashboard']);
});
$app->get('/candidate/jobs', function () use ($candidateRender) {
    $candidateRender('candidate.jobs', ['title' => 'Open Positions']);
});
$app->get('/candidate/applications', function () use ($candidateRender) {
    $candidateRender('candidate.applications', ['title' => 'My Applications']);
});
$app->get('/candidate/profile', function () use ($candidateRender) {
    $candidateRender('candidate.profile', ['title' => 'My Profile']);
});
$app->get('/candidate/offers', function () use ($candidateRender) {
    $candidateRender('candidate.offers', ['title' => 'My Offers']);
});

// ---------------------------------------------------------------------------
// Public interview room (token based, no auth, no layout chrome)
// ---------------------------------------------------------------------------
$app->get('/interview/room/{token}', function ($p) {
    Response::view('interview.room', ['token' => $p['token']]);
});
$app->get('/interview/complete/{token}', function ($p) {
    Response::view('interview.complete', ['token' => $p['token']]);
});

// Public offer accept/reject landing.
$app->get('/offer/{token}', function ($p) {
    Response::view('candidate.offers', ['token' => $p['token'], 'public' => true, 'title' => 'Your Offer']);
});

// Public career page: /careers/{subdomain}
$app->get('/careers/{subdomain}', function ($p) {
    Response::view('layouts.candidate', [
        'content' => Response::render('candidate.jobs', ['subdomain' => $p['subdomain'], 'public' => true]),
        'title'   => 'Careers',
    ]);
});

$app->notFound(function () {
    http_response_code(404);
    Response::view('layouts.auth', [
        'content' => '<div class="text-center"><h1 class="text-6xl font-bold text-violet-600">404</h1>'
            . '<p class="mt-4 text-gray-500">Page not found.</p>'
            . '<a href="/" class="mt-6 inline-block px-6 py-2 rounded-full bg-violet-600 text-white">Go Home</a></div>',
        'title' => 'Not Found',
    ]);
});

$app->run();
