<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Offer;
use App\Services\AIService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfferController extends Controller
{
    public function index(): JsonResponse
    {
        $offers = Offer::where('tenant_id', auth()->user()->tenant_id)
            ->with(['application.candidate', 'application.job', 'creator'])
            ->latest()->paginate(20);
        return response()->json($offers);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'application_id' => 'required|exists:applications,id',
            'title' => 'required|string',
            'salary' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'employment_type' => 'nullable|in:full_time,part_time,contract,remote',
            'start_date' => 'nullable|date',
            'benefits' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $application = Application::findOrFail($request->application_id);
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);

        $offer = Offer::create(array_merge($request->validated(), [
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
        ]));

        $application->update(['pipeline_stage' => 'offer']);

        return response()->json($offer->load(['application.candidate', 'creator']), 201);
    }

    public function send(Offer $offer): JsonResponse
    {
        abort_unless($offer->tenant_id === auth()->user()->tenant_id, 403);
        $offer->update(['status' => 'sent', 'sent_at' => now()]);
        return response()->json($offer);
    }

    public function generatePdf(Offer $offer): Response
    {
        abort_unless($offer->tenant_id === auth()->user()->tenant_id, 403);
        $offer->load('application.candidate', 'application.job');
        $pdf = Pdf::loadView('pdfs.offer', compact('offer'));
        $path = "offers/offer-{$offer->id}.pdf";
        \Storage::put("public/{$path}", $pdf->output());
        $offer->update(['pdf_path' => $path]);
        return $pdf->download("offer-{$offer->application->candidate->name}.pdf");
    }

    public function candidateRespond(Request $request, Offer $offer): JsonResponse
    {
        $request->validate(['response' => 'required|in:accepted,rejected', 'notes' => 'nullable|string']);
        $offer->update([
            'candidate_response' => $request->response,
            'candidate_notes' => $request->notes,
            'responded_at' => now(),
            'status' => $request->response,
        ]);

        if ($request->response === 'accepted') {
            $offer->application->update(['pipeline_stage' => 'hired']);
        } else {
            $offer->application->update(['pipeline_stage' => 'rejected']);
        }

        return response()->json($offer);
    }

    public function aiGenerate(Request $request): JsonResponse
    {
        $request->validate(['application_id' => 'required|exists:applications,id']);
        $application = Application::with(['candidate', 'job', 'aiEvaluation'])->findOrFail($request->application_id);
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);

        $service = new AIService(auth()->user()->tenant);
        $result = $service->generateOffer($application->toArray());
        return response()->json($result);
    }
}
