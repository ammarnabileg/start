<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\TalentPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(Request $request): View
    {
        $candidates = Candidate::query()
            ->with(['applications.jobPosition', 'tags', 'interviews' => fn ($q) => $q->latest()->limit(1)])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($w) => $w->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->whereHas('applications', fn ($a) => $a->where('status', $request->string('status'))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total'      => Candidate::count(),
            'in_pipeline' => Candidate::whereHas('applications', fn ($a) => $a->whereNotIn('status', ['hired', 'rejected', 'withdrawn']))->count(),
            'hired'      => Candidate::whereHas('applications', fn ($a) => $a->where('status', 'hired'))->count(),
        ];

        return view('hr.candidates.index', compact('candidates', 'stats'));
    }

    public function show(Candidate $candidate): View
    {
        $candidate->load([
            'applications.jobPosition', 'applications.humanInterviews.evaluations', 'applications.offers',
            'applications.decisions.user',
            'interviews.competencyScores', 'interviews.report', 'interviews.redFlags',
            'documents', 'notes.author', 'tags', 'activities', 'latestCvAnalysis', 'talentPools',
        ]);

        return view('hr.candidates.profile', compact('candidate'));
    }

    public function storeNote(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->can('notes.create'), 403);
        $data = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:internal,private'],
        ]);
        $candidate->notes()->create([
            'user_id'    => $request->user()->id,
            'body'       => $data['body'],
            'visibility' => $data['visibility'] ?? 'internal',
        ]);

        return back()->with('status', 'Note added.');
    }

    public function uploadDocument(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->can('documents.create'), 403);
        $request->validate([
            'document' => ['required', 'file', 'max:10240'],
            'type'     => ['required', 'in:cv,portfolio,certificate,attachment'],
        ]);
        $path = $request->file('document')->store("candidates/{$candidate->id}/docs");
        $version = (int) $candidate->documents()->where('type', $request->string('type'))->max('version') + 1;

        $candidate->documents()->create([
            'uploaded_by'   => $request->user()->id,
            'type'          => $request->string('type'),
            'label'         => $request->input('label'),
            'path'          => $path,
            'original_name' => $request->file('document')->getClientOriginalName(),
            'version'       => $version,
            'size_bytes'    => $request->file('document')->getSize(),
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    public function addTag(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->can('tags.create'), 403);
        $request->validate(['name' => ['required', 'string', 'max:60']]);
        $tag = \App\Models\Tag::firstOrCreate(['name' => $request->string('name')]);
        $candidate->tags()->syncWithoutDetaching([$tag->id]);

        return back()->with('status', 'Tag added.');
    }

    public function addToTalentPool(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($request->user()->can('talent_pool.create'), 403);
        $request->validate(['talent_pool_id' => ['required', 'exists:talent_pools,id']]);
        $candidate->talentPools()->syncWithoutDetaching([
            $request->integer('talent_pool_id') => ['added_by' => $request->user()->id, 'added_at' => now()],
        ]);

        return back()->with('status', 'Added to talent pool.');
    }

    public function documentDownload(Request $request, Candidate $candidate, int $document)
    {
        abort_unless($request->user()->can('documents.view'), 403);
        $doc = $candidate->documents()->findOrFail($document);
        abort_unless(Storage::exists($doc->path), 404);

        return Storage::download($doc->path, $doc->original_name ?? 'document');
    }
}
