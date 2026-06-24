<?php

namespace App\Http\Controllers\Interview;

use App\Http\Controllers\Controller;
use App\Models\FeedbackResponse;
use App\Models\InvitationLink;
use App\Models\InterviewSession;
use App\Services\AIService;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct(private InterviewService $interviewService) {}

    public function validateToken(string $token): JsonResponse
    {
        $link = InvitationLink::where('token', $token)->with('application.job.tenant', 'application.candidate')->first();

        if (!$link) return response()->json(['valid' => false, 'reason' => 'invalid'], 404);
        if ($link->expires_at->isPast()) return response()->json(['valid' => false, 'reason' => 'expired'], 410);
        if ($link->used_at && $link->interviewSessions()->where('status', 'completed')->exists()) {
            return response()->json(['valid' => false, 'reason' => 'completed'], 409);
        }

        $existingSession = $link->interviewSessions()->where('status', 'in_progress')->first();

        return response()->json([
            'valid' => true,
            'interview_type' => $link->interview_type,
            'job' => ['title' => $link->application->job->title, 'company' => $link->application->job->tenant->name],
            'candidate_name' => $link->application->candidate->name,
            'can_resume' => !is_null($existingSession),
            'session_id' => $existingSession?->id,
        ]);
    }

    public function start(string $token): JsonResponse
    {
        $link = InvitationLink::where('token', $token)->firstOrFail();

        if (!$link->is_active && !$link->interviewSessions()->where('status', 'in_progress')->exists()) {
            return response()->json(['message' => 'This interview link is no longer valid.'], 410);
        }

        $existingSession = $link->interviewSessions()->where('status', 'in_progress')->first();
        if ($existingSession) {
            return response()->json($this->sessionResponse($existingSession));
        }

        if ($link->expires_at->isPast()) {
            return response()->json(['message' => 'Interview link has expired.'], 410);
        }

        $session = $this->interviewService->startInterview($link);

        return response()->json($this->sessionResponse($session), 201);
    }

    public function message(Request $request, InterviewSession $session): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        if ($session->isComplete()) {
            return response()->json(['message' => 'Interview already completed.'], 409);
        }

        $response = $this->interviewService->processMessage($session, $request->message);

        return response()->json([
            'response' => $response,
            'questions_asked' => $session->fresh()->questions_asked,
            'max_questions' => $session->max_questions,
            'is_complete' => $session->fresh()->isComplete(),
        ]);
    }

    public function getSession(InterviewSession $session): JsonResponse
    {
        return response()->json($this->sessionResponse($session->load('messages')));
    }

    public function submitFeedback(Request $request, InterviewSession $session): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string',
            'suggestions' => 'nullable|string',
        ]);

        $feedback = FeedbackResponse::where('interview_session_id', $session->id)->first();

        if (!$feedback || $feedback->isSubmitted() || $feedback->isExpired()) {
            return response()->json(['message' => 'Feedback not available.'], 422);
        }

        $feedback->update([
            'rating' => $request->rating,
            'feedback' => $request->feedback,
            'suggestions' => $request->suggestions,
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'Thank you for your feedback!']);
    }

    public function heygenSession(Request $request, InterviewSession $session): JsonResponse
    {
        $application = $session->application->load('job.tenant', 'job.avatar');
        $avatar = $application->job->avatar;

        if (!$avatar?->heygen_avatar_id) {
            return response()->json(['message' => 'No avatar configured for this job.'], 422);
        }

        $heygen = new \App\Services\HeyGenService($application->job->tenant->getEffectiveHeygenKey());
        $heygenSession = $heygen->createStreamingSession($avatar->heygen_avatar_id, $avatar->heygen_voice_id);

        return response()->json($heygenSession);
    }

    public function messageByToken(Request $request, string $token): JsonResponse
    {
        $link = InvitationLink::where('token', $token)->firstOrFail();
        $session = $link->interviewSessions()->where('status', 'in_progress')->latest()->firstOrFail();
        return $this->message($request, $session);
    }

    public function submitFeedbackByToken(Request $request, string $token): JsonResponse
    {
        $link = InvitationLink::where('token', $token)->firstOrFail();
        $session = $link->interviewSessions()->latest()->firstOrFail();
        return $this->submitFeedback($request, $session);
    }

    public function heygenSessionByToken(Request $request, string $token): JsonResponse
    {
        $link = InvitationLink::where('token', $token)->firstOrFail();
        $session = $link->interviewSessions()->latest()->firstOrFail();
        return $this->heygenSession($request, $session);
    }

    public function transcribe(Request $request, string $token): JsonResponse
    {
        $request->validate(['audio' => 'required|file|mimes:webm,ogg,mp3,wav,m4a|max:20480']);
        $link = InvitationLink::where('token', $token)->firstOrFail();
        $session = $link->interviewSessions()->where('status', 'in_progress')->latest()->firstOrFail();

        $audioPath = $request->file('audio')->store('interview-audio', 'local');
        $fullPath = storage_path("app/{$audioPath}");

        try {
            $service = new \App\Services\AIService($session->application->job->tenant);
            $text = $service->transcribeAudio($fullPath);
            @unlink($fullPath);
            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            @unlink($fullPath);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function sessionResponse(InterviewSession $session): array
    {
        $session->load('messages');
        return [
            'session_id' => $session->id,
            'type' => $session->type,
            'status' => $session->status,
            'questions_asked' => $session->questions_asked,
            'max_questions' => $session->max_questions,
            'messages' => $session->messages->map(fn($m) => ['role' => $m->role, 'content' => $m->content, 'created_at' => $m->created_at]),
            'is_complete' => $session->isComplete(),
        ];
    }
}
