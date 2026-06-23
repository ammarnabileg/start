<?php

use App\Http\Controllers\Api\InterviewApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Candidate\InterviewRoomController;
use App\Http\Controllers\Candidate\InvitationController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\InterviewController;
use App\Http\Controllers\Hr\JobController;
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

    Route::get('/jobs', [JobController::class, 'index'])->middleware('can:job.view')->name('jobs.index');
    Route::post('/jobs', [JobController::class, 'store'])->middleware('can:job.create')->name('jobs.store');
    Route::post('/jobs/{job}/invitations', [JobController::class, 'createInvitation'])
        ->middleware('can:invitation.create')->name('jobs.invitations.create');

    Route::get('/interviews', [InterviewController::class, 'index'])->middleware('can:interview.view')->name('interviews.index');
    Route::get('/interviews/{interview}', [InterviewController::class, 'show'])->middleware('can:report.view')->name('interviews.show');
    Route::get('/interviews/{interview}/report.pdf', [InterviewController::class, 'reportPdf'])
        ->middleware('can:report.view')->name('interviews.report.pdf');
    Route::post('/interviews/{interview}/move-stage', [InterviewController::class, 'moveStage'])
        ->middleware('can:interview.move_stage')->name('interviews.move_stage');
});
