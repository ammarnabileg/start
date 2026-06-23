<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\HumanInterview;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Hiring\ApplicationWorkflow;
use App\Services\Hiring\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HumanInterviewController extends Controller
{
    public function __construct(
        private readonly ApplicationWorkflow $workflow,
        private readonly EvaluationService $evaluations,
    ) {}

    public function index(): View
    {
        $interviews = HumanInterview::with(['application.candidate', 'application.jobPosition', 'panelists.user'])
            ->latest('scheduled_at')
            ->paginate(25);

        return view('hr.human-interviews.index', compact('interviews'));
    }

    public function create(Request $request): View
    {
        $applications = JobApplication::with('candidate', 'jobPosition')
            ->whereNotIn('status', ['hired', 'rejected', 'withdrawn'])->get();

        return view('hr.human-interviews.schedule', [
            'applications' => $applications,
            'users'        => User::where('is_active', true)->orderBy('name')->get(),
            'preselect'    => $request->integer('application'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('interviews.schedule'), 403);
        $data = $request->validate([
            'application_id'  => ['required', 'exists:job_applications,id'],
            'type'            => ['required', 'in:technical,manager,department,panel'],
            'mode'            => ['required', 'in:onsite,online'],
            'meeting_provider' => ['nullable', 'string', 'max:20'],
            'meeting_url'     => ['nullable', 'url', 'max:512'],
            'location'        => ['nullable', 'string', 'max:255'],
            'scheduled_at'    => ['required', 'date'],
            'duration_min'    => ['required', 'integer', 'min:10', 'max:240'],
            'timezone'        => ['nullable', 'string', 'max:40'],
            'panelists'       => ['required', 'array', 'min:1'],
            'panelists.*'     => ['exists:users,id'],
        ]);

        $application = JobApplication::findOrFail($data['application_id']);
        $template = $this->evaluations->resolveTemplate($application, $data['type']);

        $interview = $application->humanInterviews()->create([
            'template_id'      => $template?->id,
            'organizer_id'     => $request->user()->id,
            'type'             => $data['type'],
            'mode'             => $data['mode'],
            'meeting_provider' => $data['meeting_provider'] ?? ($data['mode'] === 'online' ? 'manual' : 'onsite'),
            'meeting_url'      => $data['meeting_url'] ?? null,
            'location'         => $data['location'] ?? null,
            'scheduled_at'     => $data['scheduled_at'],
            'duration_min'     => $data['duration_min'],
            'timezone'         => $data['timezone'] ?? config('app.timezone'),
            'status'           => 'scheduled',
        ]);

        foreach ($data['panelists'] as $i => $userId) {
            $interview->panelists()->create(['user_id' => $userId, 'is_lead' => $i === 0]);
        }

        // Move the application into the matching stage.
        $target = match ($data['type']) {
            'manager' => ApplicationStatus::ManagerInterview,
            default   => ApplicationStatus::TechInterview,
        };
        if (! $application->status->isTerminal()) {
            $this->workflow->moveToStatus($application, $target, $request->user()->id);
        }
        $this->workflow->logActivity($application, 'human_interview_scheduled',
            ucfirst($data['type']).' interview scheduled', $request->user()->id);

        return redirect()->route('hr.human-interviews.show', $interview)->with('status', 'Interview scheduled.');
    }

    public function show(HumanInterview $humanInterview): View
    {
        $humanInterview->load([
            'application.candidate', 'application.jobPosition',
            'panelists.user', 'evaluations.user', 'template.criteria',
        ]);
        $myEvaluation = $humanInterview->evaluations->firstWhere('user_id', request()->user()->id);

        return view('hr.human-interviews.show', compact('humanInterview', 'myEvaluation'));
    }

    public function submitEvaluation(Request $request, HumanInterview $humanInterview): RedirectResponse
    {
        abort_unless($request->user()->can('evaluations.create'), 403);
        $data = $request->validate([
            'overall_rating'  => ['nullable', 'numeric', 'min:1', 'max:5'],
            'recommendation'  => ['nullable', 'in:strong_yes,yes,neutral,no,strong_no'],
            'strengths'       => ['nullable', 'string'],
            'weaknesses'      => ['nullable', 'string'],
            'notes'           => ['nullable', 'string'],
            'criteria_scores' => ['nullable', 'array'],
        ]);

        $humanInterview->evaluations()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'template_id'     => $humanInterview->template_id,
                'overall_rating'  => $data['overall_rating'] ?? null,
                'recommendation'  => $data['recommendation'] ?? null,
                'strengths'       => array_filter(array_map('trim', explode("\n", $data['strengths'] ?? ''))),
                'weaknesses'      => array_filter(array_map('trim', explode("\n", $data['weaknesses'] ?? ''))),
                'notes'           => $data['notes'] ?? null,
                'criteria_scores' => $data['criteria_scores'] ?? null,
                'submitted_at'    => now(),
            ],
        );

        $humanInterview->panelists()->where('user_id', $request->user()->id)->update(['responded' => true]);
        $this->evaluations->aggregate($humanInterview);
        $this->workflow->logActivity($humanInterview->application, 'evaluation_submitted',
            'Evaluation submitted by '.$request->user()->name, $request->user()->id);

        return back()->with('status', 'Evaluation submitted.');
    }
}
