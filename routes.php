<?php
/**
 * Application Routes (reference route map)
 *
 * NOTE: The shipped front controller (public/index.php) performs manual
 * dispatch and does not load this file. It is kept as a declarative route
 * map usable with the flat Router class, should central dispatch be adopted.
 *
 * Receives a configured Router instance as $router with these middleware:
 *   - 'auth', 'tenant', 'super_admin', 'candidate', 'guest', 'api_auth'
 *
 * Handlers are "Namespace\\Controller::method" strings.
 *
 * @var Router $router
 */

// ----------------------------------------------------------------------
// Public / root
// ----------------------------------------------------------------------
$router->get('/', function () {
    // Send authenticated users to their home; guests to login.
    if (Auth::check()) {
        if (Auth::isSuper()) {
            Response::redirect('/super/dashboard');
        }
        if (Auth::isCandidate()) {
            Response::redirect('/c/dashboard');
        }
        Response::redirect('/dashboard');
    }
    Response::redirect('/login');
});

$router->get('/unauthorized', function () {
    Response::view('errors.unauthorized', ['title' => 'Unauthorized'], 403);
});

$router->get('/health', function () {
    Response::json(['status' => 'ok', 'time' => date('c')]);
});

// ----------------------------------------------------------------------
// Authentication (company users + super admin)
// ----------------------------------------------------------------------
$router->get('/login',  'Auth\\AuthController::showLogin', ['guest']);
$router->post('/login', 'Auth\\AuthController::login',     ['guest']);
$router->get('/logout', 'Auth\\AuthController::logout');

$router->get('/forgot-password',  'Auth\\AuthController::showForgotPassword', ['guest']);
$router->post('/forgot-password', 'Auth\\AuthController::forgotPassword',      ['guest']);
$router->get('/reset-password/{token}',  'Auth\\AuthController::showResetPassword', ['guest']);
$router->post('/reset-password/{token}', 'Auth\\AuthController::resetPassword',      ['guest']);

// Candidate registration / authentication
$router->get('/register',  'Auth\\CandidateAuthController::showRegister', ['guest']);
$router->post('/register', 'Auth\\CandidateAuthController::register',     ['guest']);

// ----------------------------------------------------------------------
// HR / Company routes (auth + tenant)
// ----------------------------------------------------------------------
$router->group('', function (Router $router) {
    $router->get('/dashboard', 'HR\\DashboardController::index');

    // Jobs
    $router->get('/jobs',          'HR\\JobController::index');
    $router->get('/jobs/create',   'HR\\JobController::create');
    $router->post('/jobs',         'HR\\JobController::store');
    $router->get('/jobs/{id}',     'HR\\JobController::show');
    $router->post('/jobs/{id}',    'HR\\JobController::update');
    $router->post('/jobs/{id}/delete', 'HR\\JobController::destroy');

    // Pipeline
    $router->get('/pipeline', 'HR\\PipelineController::index');

    // Candidates
    $router->get('/candidates',          'HR\\CandidateController::index');
    $router->get('/candidates/compare',  'HR\\CandidateController::compare');
    $router->get('/candidates/{id}',     'HR\\CandidateController::show');

    // AI Interviews
    $router->get('/ai-interviews',       'HR\\InterviewController::index');
    $router->get('/ai-interviews/{id}',  'HR\\InterviewController::report');

    // Human Interviews
    $router->get('/human-interviews',    'HR\\HumanInterviewController::index');

    // Offers
    $router->get('/offers', 'HR\\OfferController::index');

    // Talent Pool
    $router->get('/talent-pool', 'HR\\TalentPoolController::index');

    // Avatars
    $router->get('/avatars', 'HR\\AvatarController::index');

    // Users & Roles
    $router->get('/users', 'HR\\UserController::index');
    $router->get('/roles', 'HR\\RoleController::index');

    // Settings
    $router->get('/settings', 'HR\\SettingsController::index');

    // AI Analytics
    $router->get('/ai-analytics', 'HR\\AiAnalyticsController::index');
}, ['auth', 'tenant']);

// ----------------------------------------------------------------------
// Super Admin routes (super_admin)
// ----------------------------------------------------------------------
$router->group('/super', function (Router $router) {
    $router->get('/dashboard', 'SuperAdmin\\DashboardController::index');
    $router->get('/companies', 'SuperAdmin\\CompanyController::index');
    $router->get('/terminal',  'SuperAdmin\\TerminalController::index');
}, ['auth', 'super_admin']);

// ----------------------------------------------------------------------
// Candidate routes (auth + candidate)
// ----------------------------------------------------------------------
$router->group('/c', function (Router $router) {
    $router->get('/dashboard',    'Candidate\\DashboardController::index');
    $router->get('/jobs',         'Candidate\\JobController::index');
    $router->get('/applications', 'Candidate\\ApplicationController::index');
    $router->get('/profile',      'Candidate\\ProfileController::index');
    $router->get('/offers',       'Candidate\\OfferController::index');
}, ['auth', 'candidate']);

// ----------------------------------------------------------------------
// Interview Room (token-gated, public access via secure token)
// ----------------------------------------------------------------------
$router->get('/interview/{token}',        'Interview\\RoomController::show');
$router->post('/interview/{token}/start', 'Interview\\RoomController::start');

// ----------------------------------------------------------------------
// Career Page (public)
// ----------------------------------------------------------------------
$router->get('/careers',                   'Public\\CareerController::index');
$router->get('/careers/{jobSlug}',         'Public\\CareerController::job');
$router->post('/careers/{jobSlug}/apply',  'Public\\CareerController::apply');

// ----------------------------------------------------------------------
// API v1 (Bearer token auth handled inside controllers/api middleware)
// ----------------------------------------------------------------------
$router->group('/api/v1', function (Router $router) {
    $router->post('/auth/login',   'Api\\AuthController::login');
    $router->post('/auth/refresh', 'Api\\AuthController::refresh');
    $router->get('/me',            'Api\\AuthController::me', ['api_auth']);

    $router->get('/jobs',          'Api\\JobController::index',  ['api_auth']);
    $router->get('/jobs/{id}',     'Api\\JobController::show',   ['api_auth']);
    $router->get('/candidates',    'Api\\CandidateController::index', ['api_auth']);
    $router->get('/applications',  'Api\\ApplicationController::index', ['api_auth']);
});
