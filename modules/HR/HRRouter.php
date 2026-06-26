<?php
declare(strict_types=1);

require_once MODULES_PATH . '/Jobs/JobController.php';
require_once MODULES_PATH . '/HR/DashboardController.php';
require_once MODULES_PATH . '/HR/CandidateController.php';
require_once MODULES_PATH . '/HR/PipelineController.php';
require_once MODULES_PATH . '/HR/AIInterviewController.php';
require_once MODULES_PATH . '/HR/HumanInterviewController.php';
require_once MODULES_PATH . '/HR/OfferController.php';
require_once MODULES_PATH . '/HR/TalentPoolController.php';
require_once MODULES_PATH . '/HR/AvatarController.php';
require_once MODULES_PATH . '/HR/UserController.php';
require_once MODULES_PATH . '/HR/RoleController.php';
require_once MODULES_PATH . '/HR/ComparisonController.php';
require_once MODULES_PATH . '/HR/AnalyticsController.php';
require_once MODULES_PATH . '/Company/CompanyController.php';

class HRRouter
{
    public static function dispatch(Request $r, string $path, string $method): void
    {
        Auth::requireAuth();

        if (Auth::isCandidate()) {
            http_response_code(403);
            renderView('errors/403', [], 'app');
            return;
        }

        // Dashboard
        if ($method === 'GET' && $path === '/dashboard') {
            DashboardController::index($r);
            return;
        }

        // Jobs
        if ($method === 'GET' && $path === '/jobs') { JobController::index($r); return; }
        if ($method === 'GET' && $path === '/jobs/create') { JobController::create($r); return; }
        if ($method === 'POST' && $path === '/jobs/create') { JobController::store($r); return; }
        if (preg_match('#^/jobs/(\d+)/generate-link$#', $path, $m) && $method === 'POST') { JobController::generateLink($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)/settings$#', $path, $m)) { JobController::settings($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)/criteria$#', $path, $m)) { JobController::criteria($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)/questions$#', $path, $m)) { JobController::questions($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)/edit$#', $path, $m) && $method === 'POST') { JobController::update($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)/archive$#', $path, $m) && $method === 'POST') { JobController::archive($r, (int)$m[1]); return; }
        if (preg_match('#^/jobs/(\d+)$#', $path, $m) && $method === 'GET') { JobController::show($r, (int)$m[1]); return; }

        // Candidates
        if ($method === 'GET' && $path === '/candidates') { CandidateController::index($r); return; }
        if (preg_match('#^/candidates/(\d+)/move$#', $path, $m) && $method === 'POST') { CandidateController::move($r, (int)$m[1]); return; }
        if (preg_match('#^/candidates/(\d+)/note$#', $path, $m) && $method === 'POST') { CandidateController::addNote($r, (int)$m[1]); return; }
        if (preg_match('#^/candidates/(\d+)/schedule$#', $path, $m) && $method === 'POST') { CandidateController::scheduleInterview($r, (int)$m[1]); return; }
        if (preg_match('#^/candidates/(\d+)/send-interview$#', $path, $m) && $method === 'POST') { CandidateController::sendInterview($r, (int)$m[1]); return; }
        if (preg_match('#^/candidates/(\d+)$#', $path, $m) && $method === 'GET') { CandidateController::show($r, (int)$m[1]); return; }

        // Pipeline
        if ($method === 'GET' && $path === '/pipeline') { PipelineController::index($r); return; }
        if ($method === 'POST' && $path === '/pipeline/move') { PipelineController::move($r); return; }
        if ($method === 'POST' && $path === '/pipeline/bulk-move') { PipelineController::bulkMove($r); return; }

        // AI Interviews
        if ($method === 'GET' && $path === '/ai-interviews') { AIInterviewController::index($r); return; }
        if (preg_match('#^/ai-interviews/(\d+)$#', $path, $m) && $method === 'GET') { AIInterviewController::show($r, (int)$m[1]); return; }

        // Human Interviews
        if ($method === 'GET' && $path === '/human-interviews') { HumanInterviewController::index($r); return; }
        if ($method === 'POST' && $path === '/human-interviews/create') { HumanInterviewController::create($r); return; }
        if (preg_match('#^/human-interviews/(\d+)/evaluate$#', $path, $m) && $method === 'POST') { HumanInterviewController::evaluate($r, (int)$m[1]); return; }

        // Offers
        if ($method === 'GET' && $path === '/offers') { OfferController::index($r); return; }
        if ($method === 'POST' && $path === '/offers/create') { OfferController::create($r); return; }
        if (preg_match('#^/offers/(\d+)/send$#', $path, $m) && $method === 'POST') { OfferController::send($r, (int)$m[1]); return; }
        if (preg_match('#^/offers/(\d+)/revoke$#', $path, $m) && $method === 'POST') { OfferController::revoke($r, (int)$m[1]); return; }

        // Talent Pool
        if ($method === 'GET' && $path === '/talent-pool') { TalentPoolController::index($r); return; }
        if ($method === 'POST' && $path === '/talent-pool/groups') { TalentPoolController::createGroup($r); return; }
        if ($method === 'POST' && $path === '/talent-pool/add') { TalentPoolController::addMember($r); return; }
        if ($method === 'POST' && $path === '/talent-pool/remove') { TalentPoolController::removeMember($r); return; }

        // Avatars
        if ($method === 'GET' && $path === '/avatars') { AvatarController::index($r); return; }
        if ($method === 'POST' && $path === '/avatars/save') { AvatarController::save($r); return; }
        if (preg_match('#^/avatars/(\d+)/delete$#', $path, $m) && $method === 'POST') { AvatarController::delete($r, (int)$m[1]); return; }

        // Users
        if ($method === 'GET' && $path === '/users') { UserController::index($r); return; }
        if ($method === 'POST' && $path === '/users/create') { UserController::create($r); return; }
        if (preg_match('#^/users/(\d+)/toggle$#', $path, $m) && $method === 'POST') { UserController::toggle($r, (int)$m[1]); return; }
        if (preg_match('#^/users/(\d+)/roles$#', $path, $m) && $method === 'POST') { UserController::syncRoles($r, (int)$m[1]); return; }
        if (preg_match('#^/users/(\d+)/delete$#', $path, $m) && $method === 'POST') { UserController::delete($r, (int)$m[1]); return; }

        // Roles
        if ($method === 'GET' && $path === '/roles') { RoleController::index($r); return; }
        if (preg_match('#^/roles/(\d+)/permissions$#', $path, $m) && $method === 'POST') { RoleController::savePermissions($r, (int)$m[1]); return; }

        // Settings
        if ($path === '/settings/ai') { CompanyController::aiSettings($r); return; }
        if ($path === '/settings/career-page') { CompanyController::careerPage($r); return; }
        if ($path === '/settings') { CompanyController::settings($r); return; }

        // Comparisons
        if ($method === 'GET' && $path === '/comparisons') { ComparisonController::index($r); return; }
        if ($method === 'POST' && $path === '/comparisons/create') { ComparisonController::create($r); return; }
        if (preg_match('#^/comparisons/(\d+)$#', $path, $m) && $method === 'GET') { ComparisonController::show($r, (int)$m[1]); return; }

        // Analytics / Reports
        if ($method === 'GET' && $path === '/analytics') { AnalyticsController::index($r); return; }
        if ($method === 'GET' && $path === '/reports') { AnalyticsController::reports($r); return; }

        http_response_code(404);
        renderView('errors/404', [], 'app');
    }
}
