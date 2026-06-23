<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Services\Offers\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    public function index(): View
    {
        $offers = Offer::with('application.candidate', 'application.jobPosition')->latest()->paginate(25);

        return view('hr.offers.index', compact('offers'));
    }

    public function store(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($request->user()->can('decisions.make_offer'), 403);
        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:190'],
            'salary'     => ['nullable', 'numeric', 'min:0'],
            'currency'   => ['nullable', 'string', 'size:3'],
            'start_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string'],
        ]);

        $offer = $this->offers->create($application, $data, $request->user()->id);

        return redirect()->route('hr.offers.show', $offer)->with('status', 'Offer created.');
    }

    public function show(Offer $offer): View
    {
        $offer->load('application.candidate', 'application.jobPosition');

        return view('hr.offers.show', compact('offer'));
    }

    public function send(Offer $offer): RedirectResponse
    {
        abort_unless(request()->user()->can('offers.update'), 403);
        $this->offers->send($offer);

        return back()->with('status', 'Offer sent to the candidate.');
    }

    public function withdraw(Offer $offer): RedirectResponse
    {
        abort_unless(request()->user()->can('offers.update'), 403);
        $offer->update(['status' => \App\Enums\OfferStatus::Withdrawn]);

        return back()->with('status', 'Offer withdrawn.');
    }

    public function letterPdf(Offer $offer): StreamedResponse
    {
        if (! $offer->letter_path || ! Storage::exists($offer->letter_path)) {
            $this->offers->generateLetter($offer);
        }

        return Storage::download($offer->letter_path, "offer-{$offer->public_id}.pdf");
    }
}
