<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\QuestionBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $questions = QuestionBank::where('tenant_id', auth()->user()->tenant_id)
            ->when($request->job_id, fn($q) => $q->where('recruitment_job_id', $request->job_id))
            ->when($request->difficulty, fn($q) => $q->where('difficulty', $request->difficulty))
            ->when($request->language, fn($q) => $q->where('language', $request->language))
            ->latest()->get();
        return response()->json($questions);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['question' => 'required|string', 'question_ar' => 'nullable|string', 'recruitment_job_id' => 'nullable|exists:recruitment_jobs,id', 'skill_category' => 'nullable|string', 'difficulty' => 'nullable|in:easy,medium,hard', 'language' => 'nullable|in:ar,en']);
        $question = QuestionBank::create(array_merge($request->validated(), ['tenant_id' => auth()->user()->tenant_id]));
        return response()->json($question, 201);
    }

    public function update(Request $request, QuestionBank $question): JsonResponse
    {
        abort_unless($question->tenant_id === auth()->user()->tenant_id, 403);
        $question->update($request->validated());
        return response()->json($question);
    }

    public function destroy(QuestionBank $question): JsonResponse
    {
        abort_unless($question->tenant_id === auth()->user()->tenant_id, 403);
        $question->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function storeForJob(Request $request, \App\Models\RecruitmentJob $job): JsonResponse
    {
        abort_unless($job->tenant_id === auth()->user()->tenant_id, 403);
        $request->validate([
            'question' => 'required|string',
            'question_ar' => 'nullable|string',
            'skill_category' => 'nullable|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'ideal_answer_hints' => 'nullable|string',
        ]);

        $question = QuestionBank::create([
            'tenant_id' => auth()->user()->tenant_id,
            'recruitment_job_id' => $job->id,
            'question' => $request->question,
            'question_ar' => $request->question_ar,
            'skill_category' => $request->skill_category,
            'difficulty' => $request->difficulty ?? 'medium',
            'ideal_answer_hints' => $request->ideal_answer_hints,
            'language' => 'en',
        ]);

        return response()->json($question, 201);
    }

    public function destroyForJob(\App\Models\RecruitmentJob $job, QuestionBank $question): JsonResponse
    {
        abort_unless($job->tenant_id === auth()->user()->tenant_id, 403);
        abort_unless($question->recruitment_job_id === $job->id, 404);
        $question->delete();
        return response()->json(['message' => 'Question deleted']);
    }
}
