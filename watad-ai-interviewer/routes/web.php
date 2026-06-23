<?php

use App\Http\Controllers\Api\InterviewApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Candidate\InterviewRoomController;
use App\Http\Controllers\Candidate\InvitationController;
use App\Http\Controllers\Hr\ApplicationController;
use App\Http\Controllers\Hr\AvatarController;
use App\Http\Controllers\Hr\CandidateController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\HumanInterviewController;
use App\Http\Controllers\Hr\InterviewController;
use App\Http\Controllers\Hr\JobController;
use App\Http\Controllers\Hr\OfferController;
use App\Http\Controllers\Hr\PipelineController;
use App\Http\Controllers\Hr\QuestionController;
use App\Http\Controllers\Hr\RoleController;
use App\Http\Controllers\Hr\SettingsController;
use App\Http\Controllers\Hr\TalentPoolController;
use App\Http\Controllers\Hr\TemplateController;
use App\Http\Controllers\Hr\UserController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public candidate flow
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('login'));

Route::get('/i/{invitation}', [InvitationController::class, 'show'])->name('candidate.invitation.show');
Route::post('/i/{invitation}/intake', [InvitationController::class, 'intake'])->name('candidate.invitation.intake');
Route::get('/interview/{interview}', [InterviewRoomController::class, 'show'])->name('candidate.interview.room');

// Candidate interview JSON endpoints — session-bound (web middleware gives us the session + CSRF).
Route::prefix('interview/{interview}')->name('api.interview.')->group(function () {
    Route::post('start', [InterviewApiController::class, 'start'])->name('start');
    Route::post('answer', [InterviewApiController::class, 'answer'])->middleware('throttle:30,1')->name('answer');
    Route::post('audio', [InterviewApiController::class, 'uploadAudio'])->middleware('throttle:60,1')->name('audio');
    Route::post('complete', [InterviewApiController::class, 'complete'])->name('complete');
    Route::get('state', [InterviewApiController::class, 'state'])->name('state');
});

/*
|--------------------------------------------------------------------------
| Authentication (HR)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| HR area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/jobs', [JobController::class, 'index'])->middleware('can:jobs.view')->name('jobs.index');
    Route::post('/jobs', [JobController::class, 'store'])->middleware('can:jobs.create')->name('jobs.store');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->middleware('can:jobs.update')->name('jobs.update');
    Route::patch('/jobs/{job}/status', [JobController::class, 'updateStatus'])->middleware('can:jobs.update')->name('jobs.status');
    Route::post('/jobs/{job}/invitations', [JobController::class, 'createInvitation'])
        ->middleware('can:invitations.create')->name('jobs.invitations.create');

    Route::get('/interviews', [InterviewController::class, 'index'])->middleware('can:ai_interviews.view')->name('interviews.index');
    Route::get('/interviews/{interview}', [InterviewController::class, 'show'])->middleware('can:reports.view')->name('interviews.show');
    Route::get('/interviews/{interview}/report.pdf', [InterviewController::class, 'reportPdf'])
        ->middleware('can:reports.view')->name('interviews.report.pdf');
    Route::post('/interviews/{interview}/move-stage', [InterviewController::class, 'moveStage'])
        ->middleware('can:candidates.move_stage')->name('interviews.move_stage');

    Route::get('/pipeline', [PipelineController::class, 'index'])->middleware('can:pipelines.view')->name('pipeline.index');

    // Candidates (master profile)
    Route::get('/candidates', [CandidateController::class, 'index'])->middleware('can:candidates.view')->name('candidates.index');
    Route::get('/candidates/{candidate}', [CandidateController::class, 'show'])->middleware('can:candidates.view')->name('candidates.show');
    Route::post('/candidates/{candidate}/notes', [CandidateController::class, 'storeNote'])->name('candidates.notes.store');
    Route::post('/candidates/{candidate}/documents', [CandidateController::class, 'uploadDocument'])->name('candidates.documents.store');
    Route::get('/candidates/{candidate}/documents/{document}', [CandidateController::class, 'documentDownload'])->name('candidates.documents.download');
    Route::post('/candidates/{candidate}/tags', [CandidateController::class, 'addTag'])->name('candidates.tags.store');
    Route::post('/candidates/{candidate}/talent-pool', [CandidateController::class, 'addToTalentPool'])->name('candidates.talent-pool.add');

    // Applications — decisions & pipeline moves
    Route::post('/applications/{application}/decision', [ApplicationController::class, 'decision'])->name('applications.decision');
    Route::post('/applications/{application}/move-stage', [ApplicationController::class, 'moveStage'])->name('applications.move_stage');

    // Human interviews (Stage 2)
    Route::get('/human-interviews', [HumanInterviewController::class, 'index'])->middleware('can:human_interviews.view')->name('human-interviews.index');
    Route::get('/human-interviews/create', [HumanInterviewController::class, 'create'])->middleware('can:interviews.schedule')->name('human-interviews.create');
    Route::post('/human-interviews', [HumanInterviewController::class, 'store'])->middleware('can:interviews.schedule')->name('human-interviews.store');
    Route::get('/human-interviews/{humanInterview}', [HumanInterviewController::class, 'show'])->middleware('can:human_interviews.view')->name('human-interviews.show');
    Route::post('/human-interviews/{humanInterview}/evaluate', [HumanInterviewController::class, 'submitEvaluation'])->middleware('can:evaluations.create')->name('human-interviews.evaluate');

    // Offers (Stage 3)
    Route::get('/offers', [OfferController::class, 'index'])->middleware('can:offers.view')->name('offers.index');
    Route::post('/applications/{application}/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('/offers/{offer}', [OfferController::class, 'show'])->middleware('can:offers.view')->name('offers.show');
    Route::post('/offers/{offer}/send', [OfferController::class, 'send'])->name('offers.send');
    Route::post('/offers/{offer}/withdraw', [OfferController::class, 'withdraw'])->name('offers.withdraw');
    Route::get('/offers/{offer}/letter.pdf', [OfferController::class, 'letterPdf'])->middleware('can:offers.view')->name('offers.letter');

    // Talent pool
    Route::get('/talent-pool', [TalentPoolController::class, 'index'])->middleware('can:talent_pool.view')->name('talent-pool.index');
    Route::post('/talent-pool', [TalentPoolController::class, 'store'])->middleware('can:talent_pool.create')->name('talent-pool.store');

    // Interview templates
    Route::get('/templates', [TemplateController::class, 'index'])->middleware('can:templates.view')->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store'])->middleware('can:templates.create')->name('templates.store');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->middleware('can:templates.update')->name('templates.update');

    // Avatars (the Watad interviewer cast)
    Route::get('/avatars', [AvatarController::class, 'index'])->middleware('can:avatars.view')->name('avatars.index');
    Route::post('/avatars', [AvatarController::class, 'store'])->middleware('can:avatars.create')->name('avatars.store');
    Route::put('/avatars/{avatar}', [AvatarController::class, 'update'])->middleware('can:avatars.update')->name('avatars.update');

    // Question libraries
    Route::get('/questions', [QuestionController::class, 'index'])->middleware('can:questions.view')->name('questions.index');
    Route::post('/questions/libraries', [QuestionController::class, 'storeLibrary'])->middleware('can:questions.create')->name('questions.libraries.store');
    Route::post('/questions', [QuestionController::class, 'storeQuestion'])->middleware('can:questions.create')->name('questions.store');

    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('can:users.update')->name('users.roles');

    // Roles & permissions (granular CRUD matrix editor)
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:roles.create')->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.update')->name('roles.update');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
});

/*
|--------------------------------------------------------------------------
| Candidate Portal (guard: candidate)
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:candidate')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login']);
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register']);
    });
    Route::post('/logout', [PortalAuthController::class, 'logout'])->middleware('auth:candidate')->name('logout');

    Route::middleware('auth:candidate')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/applications', [PortalController::class, 'applications'])->name('applications');
        Route::get('/applications/{application}', [PortalController::class, 'application'])->name('applications.show');
        Route::get('/interviews', [PortalController::class, 'interviews'])->name('interviews');
        Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('/notifications', [PortalController::class, 'notifications'])->name('notifications');
        Route::get('/offers', [PortalController::class, 'offers'])->name('offers');
        Route::get('/offers/{offer}', [PortalController::class, 'offer'])->name('offers.show');
        Route::post('/offers/{offer}/accept', [PortalController::class, 'acceptOffer'])->name('offers.accept');
        Route::post('/offers/{offer}/decline', [PortalController::class, 'declineOffer'])->name('offers.decline');
    });
});
