<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\TalentPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TalentPoolController extends Controller
{
    public function index(): View
    {
        $pools = TalentPool::with(['candidates' => fn ($q) => $q->limit(50)])->withCount('candidates')->get();

        return view('hr.talent-pool', compact('pools'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('talent_pool.create'), 403);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        TalentPool::create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Talent pool created.');
    }
}
