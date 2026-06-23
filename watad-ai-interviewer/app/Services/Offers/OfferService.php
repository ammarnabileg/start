<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Services\Hiring\ApplicationWorkflow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Offer lifecycle: create → generate PDF letter → send → candidate accept (e-sign) / decline.
 * Accept moves the application to Hired; decline/expire to Rejected. See docs/21 (Stage 3).
 */
class OfferService
{
    public function __construct(private readonly ApplicationWorkflow $workflow) {}

    public function create(JobApplication $application, array $data, ?int $userId): Offer
    {
        $offer = $application->offers()->create([
            'created_by' => $userId,
            'title'      => $data['title'] ?? $application->jobPosition?->title,
            'salary'     => $data['salary'] ?? null,
            'currency'   => $data['currency'] ?? $application->jobPosition?->currency,
            'start_date' => $data['start_date'] ?? null,
            'expires_at' => $data['expires_at'] ?? now()->addDays(14),
            'notes'      => $data['notes'] ?? null,
            'status'     => OfferStatus::Draft,
        ]);

        $this->generateLetter($offer);
        $this->workflow->logActivity($application, 'offer_sent', 'Offer drafted', $userId);

        return $offer;
    }

    public function generateLetter(Offer $offer): string
    {
        $offer->loadMissing('application.candidate', 'application.jobPosition');
        $pdf = Pdf::loadView('reports.offer', ['offer' => $offer])->setPaper('a4');
        $key = "offers/{$offer->public_id}.pdf";
        Storage::put($key, $pdf->output());
        $offer->update(['letter_path' => $key]);

        return $key;
    }

    public function send(Offer $offer): void
    {
        $offer->update(['status' => OfferStatus::Sent, 'sent_at' => now()]);
        $this->workflow->moveToStatus($offer->application, ApplicationStatus::Offer, null);
        $this->workflow->logActivity($offer->application, 'offer_sent', 'Offer sent to candidate');
    }

    public function accept(Offer $offer, ?string $signature): void
    {
        $offer->update([
            'status'       => OfferStatus::Accepted,
            'signed_at'    => now(),
            'responded_at' => now(),
            'signature_path' => $signature,
        ]);
        $this->workflow->moveToStatus($offer->application, ApplicationStatus::Hired, null);
        $this->workflow->logActivity($offer->application, 'offer_accepted', 'Candidate accepted & signed the offer');
    }

    public function decline(Offer $offer): void
    {
        $offer->update(['status' => OfferStatus::Declined, 'responded_at' => now()]);
        $this->workflow->moveToStatus($offer->application, ApplicationStatus::Rejected, null);
        $this->workflow->logActivity($offer->application, 'offer_declined', 'Candidate declined the offer');
    }
}
