<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionType;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Services\Hiring\ApplicationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationWorkflow $workflow) {}

    /** Record a hiring decision (advance / reject / approve / make_offer / hold), with optional AI override. */
    public function decision(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:advance,reject,approve,make_offer,hold'],
            'reason'   => ['nullable', 'string', 'max:1000'],
            'override' => ['nullable', 'boolean'],
        ]);

        $ability = match ($data['decision']) {
            'reject'     => 'decisions.reject',
            'approve'    => 'decisions.approve',
            'make_offer' => 'decisions.make_offer',
            default      => 'decisions.advance',
        };
        abort_unless($request->user()->can($ability), 403);

        $override = (bool) ($data['override'] ?? false);
        if ($override) {
            abort_unless($request->user()->can('decisions.override_ai'), 403);
            if (empty($data['reason'])) {
                return back()->withErrors(['reason' => 'A reason is required to override the AI decision.']);
            }
        }

        $this->workflow->decide(
            $application,
            DecisionType::from($data['decision']),
            $request->user()->id,
            $data['reason'] ?? null,
            $override,
        );

        return back()->with('status', 'Decision recorded.');
    }

    /** Kanban drag-and-drop: set the application status directly. */
    public function moveStage(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($request->user()->can('candidates.move_stage'), 403);
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', ApplicationStatus::values())]]);

        $this->workflow->moveToStatus($application, ApplicationStatus::from($data['status']), $request->user()->id);

        return back()->with('status', 'Stage updated.');
    }
}
