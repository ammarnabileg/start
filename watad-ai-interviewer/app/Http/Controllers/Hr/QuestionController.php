<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Competency;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(): View
    {
        return view('hr.questions', [
            'libraries'    => QuestionLibrary::with('questions')->latest()->get(),
            'competencies' => Competency::cases(),
        ]);
    }

    public function storeLibrary(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:150']]);
        QuestionLibrary::create($request->only('name', 'description'));

        return back()->with('status', 'Library created.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'library_id' => ['required', 'exists:question_libraries,id'],
            'competency' => ['required', 'in:'.implode(',', Competency::values())],
            'text'       => ['required', 'string'],
            'text_ar'    => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,standard,hard'],
        ]);

        Question::create([...$data, 'is_active' => true]);

        return back()->with('status', 'Question added.');
    }

    public function updateLibrary(Request $request, QuestionLibrary $library): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $library->update($data);

        return back()->with('status', 'Library updated.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate([
            'competency' => ['required', 'in:'.implode(',', Competency::values())],
            'text'       => ['required', 'string'],
            'text_ar'    => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,standard,hard'],
        ]);
        $question->update($data);

        return back()->with('status', 'Question updated.');
    }

    /** Archive / restore a question (kept out of the AI's adaptive pool when inactive). */
    public function toggleQuestion(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        return back()->with('status', $question->is_active ? 'Question restored.' : 'Question archived.');
    }
}
