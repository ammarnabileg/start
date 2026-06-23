<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\InterviewInvitation;
use App\Models\JobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(): View
    {
        $jobs = JobPosition::with('department')->latest()->paginate(20);

        $stats = [
            'open'   => JobPosition::where('status', 'open')->count(),
            'draft'  => JobPosition::where('status', 'draft')->count(),
            'closed' => JobPosition::whereIn('status', ['closed', 'paused'])->count(),
        ];

        return view('hr.jobs', compact('jobs', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:200'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'seniority'     => ['required', 'in:intern,junior,mid,senior,lead,manager,director,executive'],
            'description'   => ['nullable', 'string'],
            'requirements'  => ['nullable', 'array'],
            'salary_min'    => ['nullable', 'numeric'],
            'salary_max'    => ['nullable', 'numeric'],
            'currency'      => ['nullable', 'string', 'size:3'],
        ]);

        $job = JobPosition::create([
            ...$data,
            'slug'   => Str::slug($data['title']).'-'.Str::random(6),
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('hr.jobs.index')->with('status', "Job “{$job->title}” created.");
    }

    public function update(Request $request, JobPosition $job): RedirectResponse
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:200'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'seniority'     => ['required', 'in:intern,junior,mid,senior,lead,manager,director,executive'],
            'description'   => ['nullable', 'string'],
            'salary_min'    => ['nullable', 'numeric'],
            'salary_max'    => ['nullable', 'numeric'],
            'currency'      => ['nullable', 'string', 'size:3'],
            'status'        => ['required', 'in:draft,open,paused,closed'],
        ]);

        $job->update($data);

        return redirect()->route('hr.jobs.index')->with('status', "Job “{$job->title}” updated.");
    }

    /** Quick status change (e.g. archive → closed/paused, or re-open). */
    public function updateStatus(Request $request, JobPosition $job): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,open,paused,closed'],
        ]);

        $job->update($data);

        return redirect()->route('hr.jobs.index')->with('status', "Job “{$job->title}” marked {$data['status']}.");
    }

    public function createInvitation(Request $request, JobPosition $job): RedirectResponse
    {
        $invitation = InterviewInvitation::create([
            'job_position_id' => $job->id,
            'template_id'     => $job->default_template_id,
            'created_by'      => $request->user()->id,
            'email'           => $request->input('email'),
            'expires_at'      => now()->addDays(14),
            'status'          => 'pending',
        ]);

        $link = route('candidate.invitation.show', $invitation->token);

        return back()->with('invitation_link', $link);
    }
}
