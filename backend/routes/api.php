<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Candidate\CandidatePortalController;
use App\Http\Controllers\Company\CandidateController;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\HumanInterviewController;
use App\Http\Controllers\Company\JobController;
use App\Http\Controllers\Company\OfferController;
use App\Http\Controllers\Company\TalentPoolController;
use App\Http\Controllers\Interview\InterviewController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\Setup\SetupController;
use Illuminate\Support\Facades\Route;

// Setup routes (no auth)
Route::prefix('setup')->group(function () {
    Route::get('/status', [SetupController::class, 'status']);
    Route::get('/check', [SetupController::class, 'check']);
    Route::post('/test-database', [SetupController::class, 'testDatabase']);
    Route::post('/validate-keys', [SetupController::class, 'validateApiKeys']);
    Route::post('/install', [SetupController::class, 'install']);
});

// Public interview routes (no auth)
Route::prefix('interview')->group(function () {
    Route::get('/validate/{token}', [InterviewController::class, 'validateToken']);
    Route::post('/start/{token}', [InterviewController::class, 'start']);
    Route::get('/session/{session}', [InterviewController::class, 'getSession']);
    Route::post('/message/{session}', [InterviewController::class, 'message']);
    Route::post('/message-token/{token}', [InterviewController::class, 'messageByToken']);
    Route::post('/feedback/{session}', [InterviewController::class, 'submitFeedback']);
    Route::post('/feedback-token/{token}', [InterviewController::class, 'submitFeedbackByToken']);
    Route::post('/transcribe/{token}', [InterviewController::class, 'transcribe']);
    Route::post('/heygen/{session}', [InterviewController::class, 'heygenSession']);
    Route::post('/heygen-session/{token}', [InterviewController::class, 'heygenSessionByToken']);
});

// Public career page
Route::prefix('careers')->group(function () {
    Route::get('/{slug}', [\App\Http\Controllers\Public\CareerController::class, 'show']);
    Route::get('/{slug}/jobs', [\App\Http\Controllers\Public\CareerController::class, 'jobs']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// Candidate Portal (public registration + auth)
Route::prefix('candidate')->group(function () {
    Route::post('/register', [CandidatePortalController::class, 'register']);
    Route::get('/jobs', [CandidatePortalController::class, 'availableJobs']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/portal', [CandidatePortalController::class, 'portal']);
        Route::get('/profile', [CandidatePortalController::class, 'profile']);
        Route::put('/profile', [CandidatePortalController::class, 'updateProfile']);
        Route::post('/cv', [CandidatePortalController::class, 'uploadCv']);
        Route::get('/applications', [CandidatePortalController::class, 'applications']);
        Route::post('/apply/{job}', [CandidatePortalController::class, 'applyToJob']);
        Route::get('/notifications', [CandidatePortalController::class, 'notifications']);
        Route::get('/offers', [CandidatePortalController::class, 'offers']);
    });
});

// HR Company Routes (authenticated + tenant)
Route::middleware(['auth:api', 'tenant'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/ai-analytics', [DashboardController::class, 'aiAnalytics']);
    Route::post('/dashboard/copilot', [DashboardController::class, 'copilot']);

    // Jobs
    Route::apiResource('jobs', JobController::class);
    Route::post('/jobs/{job}/publish', [JobController::class, 'publish']);
    Route::post('/jobs/{job}/duplicate', [JobController::class, 'duplicate']);
    Route::post('/jobs/{job}/generate-link', [JobController::class, 'generateLink']);
    Route::post('/jobs/{job}/generate-questions', [JobController::class, 'generateQuestions']);
    Route::post('/jobs/ai-generate', [JobController::class, 'aiGenerate']);

    // Job Criteria
    Route::apiResource('jobs.criteria', \App\Http\Controllers\Company\JobCriteriaController::class)->shallow();

    // Job Questions (per job)
    Route::post('/jobs/{job}/questions', [\App\Http\Controllers\Company\QuestionBankController::class, 'storeForJob']);
    Route::delete('/jobs/{job}/questions/{question}', [\App\Http\Controllers\Company\QuestionBankController::class, 'destroyForJob']);

    // Question Bank
    Route::apiResource('questions', \App\Http\Controllers\Company\QuestionBankController::class);

    // Avatars
    Route::apiResource('avatars', \App\Http\Controllers\Company\AvatarController::class);
    Route::get('/avatars/heygen/list', [\App\Http\Controllers\Company\AvatarController::class, 'heygenAvatars']);

    // Applications / Candidates
    Route::get('/applications', [CandidateController::class, 'index']);
    Route::get('/applications/{application}', [CandidateController::class, 'show']);
    Route::put('/applications/{application}/stage', [CandidateController::class, 'updateStage']);
    Route::post('/applications/bulk-stage', [CandidateController::class, 'bulkUpdateStage']);
    Route::post('/applications/{application}/note', [CandidateController::class, 'addNote']);
    Route::post('/applications/compare', [CandidateController::class, 'compare']);
    Route::post('/applications/{application}/re-evaluate', [CandidateController::class, 'reEvaluate']);
    Route::get('/applications/{application}/export', [CandidateController::class, 'export']);

    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::get('/candidates/{candidate}', [CandidateController::class, 'candidateProfile']);
    Route::post('/candidates/{candidate}/talent-pool', [CandidateController::class, 'addToTalentPool']);

    // Human Interviews
    Route::apiResource('human-interviews', HumanInterviewController::class)->only(['index', 'store', 'update']);
    Route::post('/human-interviews/{interview}/evaluate', [HumanInterviewController::class, 'submitEvaluation']);

    // Offers
    Route::apiResource('offers', OfferController::class)->only(['index', 'store']);
    Route::post('/offers/{offer}/send', [OfferController::class, 'send']);
    Route::get('/offers/{offer}/pdf', [OfferController::class, 'generatePdf']);
    Route::post('/offers/ai-generate', [OfferController::class, 'aiGenerate']);
    Route::post('/offers/{offer}/respond', [OfferController::class, 'candidateRespond']);

    // Talent Pool
    Route::apiResource('talent-pools', TalentPoolController::class);
    Route::get('/talent-pools/{pool}/candidates', [TalentPoolController::class, 'candidates']);
    Route::post('/talent-pools/{pool}/candidates', [TalentPoolController::class, 'addCandidate']);
    Route::delete('/talent-pools/{pool}/candidates/{candidate}', [TalentPoolController::class, 'removeCandidate']);
    Route::post('/talent-pools/search', [TalentPoolController::class, 'search']);

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Company\SettingsController::class, 'index']);
    Route::put('/settings', [\App\Http\Controllers\Company\SettingsController::class, 'update']);

    // Users
    Route::apiResource('users', \App\Http\Controllers\Company\UserController::class);

    // Departments
    Route::apiResource('departments', \App\Http\Controllers\Company\DepartmentController::class);
});

// Super Admin Routes
Route::middleware(['auth:api', 'super_admin'])->prefix('super-admin')->group(function () {
    Route::get('/stats', [SuperAdminController::class, 'stats']);
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
    Route::get('/tenants', [SuperAdminController::class, 'companies']);
    Route::post('/tenants', [SuperAdminController::class, 'createCompany']);
    Route::put('/tenants/{tenant}', [SuperAdminController::class, 'updateCompany']);
    Route::get('/companies', [SuperAdminController::class, 'companies']);
    Route::post('/companies', [SuperAdminController::class, 'createCompany']);
    Route::put('/companies/{tenant}', [SuperAdminController::class, 'updateCompany']);
    Route::post('/companies/{tenant}/toggle-status', [SuperAdminController::class, 'toggleCompanyStatus']);
    Route::post('/users/{user}/impersonate', [SuperAdminController::class, 'impersonate']);
    Route::post('/impersonate/{tenant}', [SuperAdminController::class, 'impersonateTenant']);
    Route::get('/settings', [SuperAdminController::class, 'globalSettings']);
    Route::post('/settings', [SuperAdminController::class, 'saveGlobalSettings']);
    Route::post('/terminal', [SuperAdminController::class, 'terminal']);
    Route::get('/ai-usage', [SuperAdminController::class, 'aiUsage']);
});
