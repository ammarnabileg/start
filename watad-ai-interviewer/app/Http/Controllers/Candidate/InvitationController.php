<?php

declare(strict_types=1);

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Jobs\AnalyzeCv;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public candidate entry: resolve the invitation link, collect the intake form + CV, then create
 * the candidate and interview and hand off to the interview room. See docs/01 + docs/05.
 */
class InvitationController extends Controller
{
    public function show(InterviewInvitation $invitation): View
    {
        $invitation->load('jobPosition', 'avatar');

        if (! $invitation->isUsable()) {
            return view('candidate.expired', ['invitation' => $invitation]);
        }

        if ($invitation->status === 'pending') {
            $invitation->update(['status' => 'opened', 'opened_at' => now()]);
        }

        return view('candidate.intake', ['invitation' => $invitation]);
    }

    public function intake(IntakeRequest $request, InterviewInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isUsable(), 410, 'This invitation is no longer available.');

        $data = $request->validated();
        $path = $request->file('cv')->store('cvs');

        $candidate = Candidate::create([
            'full_name'        => $data['full_name'],
            'email'            => $data['email'],
            'phone'            => $data['phone'] ?? null,
            'linkedin_url'     => $data['linkedin_url'] ?? null,
            'country'          => $data['country'] ?? null,
            'years_experience' => $data['years_experience'] ?? null,
            'expected_salary'  => $data['expected_salary'] ?? null,
            'salary_currency'  => $data['salary_currency'] ?? $invitation->jobPosition->currency,
            'notice_period'    => $data['notice_period'] ?? null,
            'cv_path'          => $path,
            'cv_original_name' => $request->file('cv')->getClientOriginalName(),
            'source'           => 'link',
            'consent_at'       => now(),
        ]);

        $template = $invitation->template ?? $invitation->jobPosition->defaultTemplate;

        $interview = Interview::create([
            'candidate_id'    => $candidate->id,
            'job_position_id' => $invitation->job_position_id,
            'template_id'     => $template?->id,
            'avatar_id'       => $invitation->avatar_id ?? $template?->avatar_id,
            'invitation_id'   => $invitation->id,
            'mode'            => $template?->mode ?? 'text',
            'language'        => $template?->language ?? 'en',
            'status'          => 'scheduled',
        ]);

        $invitation->update(['status' => 'started', 'candidate_id' => $candidate->id]);

        // Analyze the CV in the background so topics_to_probe are ready before the agent's first turn.
        AnalyzeCv::dispatch($interview->id);

        // Bind this browser session to the interview (lightweight candidate auth for the scaffold).
        session(['interview_id' => $interview->public_id]);

        return redirect()->route('candidate.interview.room', $interview->public_id);
    }
}
