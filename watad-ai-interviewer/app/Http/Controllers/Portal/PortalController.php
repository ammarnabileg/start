<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Services\Offers\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Candidate-facing pages. Every query is scoped to the logged-in candidate. */
class PortalController extends Controller
{
    private function candidate()
    {
        return Auth::guard('candidate')->user()->candidate;
    }

    public function dashboard(): View
    {
        $candidate = $this->candidate();
        $candidate->load(['applications.jobPosition', 'interviews']);

        $upcoming = \App\Models\HumanInterview::whereIn('application_id', $candidate->applications->pluck('id'))
            ->where('status', 'scheduled')->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')->get();

        $offers = Offer::whereIn('application_id', $candidate->applications->pluck('id'))
            ->whereIn('status', ['sent', 'viewed'])->get();

        return view('portal.dashboard', compact('candidate', 'upcoming', 'offers'));
    }

    public function applications(): View
    {
        $applications = $this->candidate()->applications()->with('jobPosition')->latest()->get();

        return view('portal.applications', compact('applications'));
    }

    public function application(JobApplication $application): View
    {
        abort_unless($application->candidate_id === $this->candidate()->id, 403);
        $application->load('jobPosition', 'humanInterviews');

        return view('portal.application', compact('application'));
    }

    public function interviews(): View
    {
        $candidate = $this->candidate();
        $candidate->load('interviews.jobPosition');
        $human = \App\Models\HumanInterview::with('application.jobPosition')
            ->whereIn('application_id', $candidate->applications()->pluck('id'))
            ->orderByDesc('scheduled_at')->get();

        return view('portal.interviews', compact('candidate', 'human'));
    }

    public function profile(): View
    {
        $candidate = $this->candidate();
        $candidate->load('documents');

        return view('portal.profile', compact('candidate'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name'        => ['required', 'string', 'max:190'],
            'phone'            => ['nullable', 'string', 'max:40'],
            'linkedin_url'     => ['nullable', 'url', 'max:512'],
            'country'          => ['nullable', 'string', 'max:80'],
            'years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'expected_salary'  => ['nullable', 'numeric', 'min:0'],
            'notice_period'    => ['nullable', 'string', 'max:60'],
            'cv'               => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:8192'],
        ]);

        $candidate = $this->candidate();
        $candidate->update(collect($data)->except('cv')->toArray());

        if ($request->hasFile('cv')) {
            $path = $request->file('cv')->store("candidates/{$candidate->id}/docs");
            $version = (int) $candidate->documents()->where('type', 'cv')->max('version') + 1;
            $candidate->documents()->create([
                'type' => 'cv', 'path' => $path, 'version' => $version,
                'original_name' => $request->file('cv')->getClientOriginalName(),
                'is_primary' => true,
            ]);
            $candidate->update(['cv_path' => $path]);
        }

        return back()->with('status', 'Profile updated.');
    }

    public function notifications(): View
    {
        $candidate = $this->candidate();
        $notifications = \App\Models\Notification::where('notifiable_type', \App\Models\Interview::class)
            ->orWhere(fn ($q) => $q->where('recipient', $candidate->email))
            ->latest()->limit(50)->get();

        return view('portal.notifications', compact('notifications'));
    }

    public function offers(): View
    {
        $offers = Offer::with('application.jobPosition')
            ->whereIn('application_id', $this->candidate()->applications()->pluck('id'))
            ->latest()->get();

        return view('portal.offers', compact('offers'));
    }

    public function offer(Offer $offer): View
    {
        abort_unless(in_array($offer->application->candidate_id, [$this->candidate()->id], true), 403);
        if ($offer->status->value === 'sent') {
            $offer->update(['status' => \App\Enums\OfferStatus::Viewed]);
        }

        return view('portal.offer', compact('offer'));
    }

    public function acceptOffer(Request $request, Offer $offer, OfferService $service): RedirectResponse
    {
        abort_unless($offer->application->candidate_id === $this->candidate()->id, 403);
        $request->validate(['signature' => ['required', 'string', 'max:190'], 'agree' => ['accepted']]);
        $service->accept($offer, $request->string('signature'));

        return redirect()->route('portal.offers')->with('status', 'Offer accepted. Welcome aboard!');
    }

    public function declineOffer(Offer $offer, OfferService $service): RedirectResponse
    {
        abort_unless($offer->application->candidate_id === $this->candidate()->id, 403);
        $service->decline($offer);

        return redirect()->route('portal.offers')->with('status', 'Offer declined.');
    }
}
