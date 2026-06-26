<?php
declare(strict_types=1);

require_once MODULES_PATH . '/Candidates/CandidateController.php';

class CandidatePortalRouter
{
    public static function dispatch(Request $r, string $path, string $method): void
    {
        if (!Auth::isCandidate()) {
            http_response_code(403);
            renderView('errors/403', [], 'app');
            return;
        }

        if ($method === 'GET' && $path === '/c/dashboard') { CandidateController::dashboard($r); return; }
        if ($method === 'GET' && $path === '/c/jobs') { CandidateController::jobs($r); return; }
        if (preg_match('#^/c/jobs/(\d+)/apply$#', $path, $m) && $method === 'POST') { CandidateController::apply($r, (int)$m[1]); return; }
        if ($method === 'GET' && $path === '/c/applications') { CandidateController::applications($r); return; }
        if (preg_match('#^/c/applications/(\d+)$#', $path, $m) && $method === 'GET') { CandidateController::applicationDetail($r, (int)$m[1]); return; }
        if ($method === 'GET' && $path === '/c/profile') { CandidateController::profile($r); return; }
        if ($method === 'POST' && $path === '/c/profile') { CandidateController::updateProfile($r); return; }
        if ($method === 'GET' && $path === '/c/offers') { CandidateController::offers($r); return; }
        if (preg_match('#^/c/offers/(\d+)/accept$#', $path, $m) && $method === 'POST') { CandidateController::acceptOffer($r, (int)$m[1]); return; }
        if (preg_match('#^/c/offers/(\d+)/reject$#', $path, $m) && $method === 'POST') { CandidateController::rejectOffer($r, (int)$m[1]); return; }
        if ($method === 'GET' && $path === '/c/notifications') { CandidateController::notifications($r); return; }
        if ($method === 'POST' && $path === '/c/documents/upload') { CandidateController::uploadDocument($r); return; }

        // Fallback
        if ($path === '/c' || $path === '/c/') { CandidateController::dashboard($r); return; }

        http_response_code(404);
        renderView('errors/404', [], 'app');
    }
}
