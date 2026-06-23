<?php

use App\Http\Controllers\Api\InterviewApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Candidate\InterviewRoomController;
use App\Http\Controllers\Candidate\InvitationController;
use App\Http\Controllers\Hr\AvatarController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\InterviewController;
use App\Http\Controllers\Hr\JobController;
use App\Http\Controllers\Hr\PipelineController;
use App\Http\Controllers\Hr\QuestionController;
use App\Http\Controllers\Hr\RoleController;
use App\Http\Controllers\Hr\SettingsController;
use App\Http\Controllers\Hr\TemplateController;
use App\Http\Controllers\Hr\UserController;
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
    Route::post('/jobs/{job}/invitations', [JobController::class, 'createInvitation'])
        ->middleware('can:invitations.create')->name('jobs.invitations.create');

    Route::get('/interviews', [InterviewController::class, 'index'])->middleware('can:interviews.view')->name('interviews.index');
    Route::get('/interviews/{interview}', [InterviewController::class, 'show'])->middleware('can:reports.view')->name('interviews.show');
    Route::get('/interviews/{interview}/report.pdf', [InterviewController::class, 'reportPdf'])
        ->middleware('can:reports.view')->name('interviews.report.pdf');
    Route::post('/interviews/{interview}/move-stage', [InterviewController::class, 'moveStage'])
        ->middleware('can:interviews.move_stage')->name('interviews.move_stage');

    Route::get('/pipeline', [PipelineController::class, 'index'])->middleware('can:interviews.view')->name('pipeline.index');

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
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.update')->name('roles.update');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
});
