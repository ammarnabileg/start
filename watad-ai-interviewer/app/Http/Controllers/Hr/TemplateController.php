<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Competency;
use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\InterviewTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        return view('hr.templates', [
            'templates'    => InterviewTemplate::with('competencies', 'avatar')->latest()->get(),
            'avatars'      => Avatar::where('is_active', true)->get(),
            'competencies' => Competency::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:150'],
            'avatar_id'        => ['nullable', 'exists:avatars,id'],
            'mode'             => ['required', 'in:text,voice,video'],
            'language'         => ['required', 'in:en,ar'],
            'min_questions'    => ['required', 'integer', 'min:1', 'max:30'],
            'max_questions'    => ['required', 'integer', 'min:1', 'max:40'],
            'max_duration_min' => ['required', 'integer', 'min:5', 'max:120'],
            'follow_up_depth'  => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $template = InterviewTemplate::create([...$data, 'difficulty' => 'adaptive', 'is_active' => true]);

        foreach (Competency::cases() as $competency) {
            $template->competencies()->create([
                'competency' => $competency->value,
                'weight'     => $competency->defaultWeight(),
                'is_enabled' => true,
            ]);
        }

        return back()->with('status', "Template “{$template->name}” created.");
    }

    public function update(Request $request, InterviewTemplate $template): RedirectResponse
    {
        $template->update($request->only([
            'name', 'avatar_id', 'mode', 'language',
            'min_questions', 'max_questions', 'max_duration_min', 'follow_up_depth',
        ]));

        $enabled = $request->input('enabled', []);
        foreach ($request->input('weights', []) as $competency => $weight) {
            $template->competencies()->updateOrCreate(
                ['competency' => $competency],
                ['weight' => (float) $weight, 'is_enabled' => in_array($competency, $enabled, true)],
            );
        }

        return back()->with('status', 'Template updated.');
    }
}
