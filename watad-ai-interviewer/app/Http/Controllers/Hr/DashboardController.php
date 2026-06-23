<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Recommendation;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Interview;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $metrics = [
            'total_candidates' => Candidate::count(),
            'interviews_today' => Interview::whereDate('created_at', today())->count(),
            'hired'            => Interview::whereIn('recommendation', ['strong_hire', 'hire'])->count(),
            'rejected'         => Interview::where('recommendation', 'reject')->count(),
            'avg_score'        => round((float) Interview::whereNotNull('overall_score')->avg('overall_score'), 1),
        ];

        $completed = Interview::where('status', 'completed')->count();
        $metrics['conversion'] = $metrics['total_candidates'] > 0
            ? round(($metrics['hired'] / $metrics['total_candidates']) * 100, 1)
            : 0.0;

        $funnel = [
            'applied'     => Candidate::count(),
            'screened'    => $completed,
            'shortlisted' => Interview::whereIn('recommendation', ['strong_hire', 'hire', 'maybe'])->count(),
            'hire'        => $metrics['hired'],
            'rejected'    => $metrics['rejected'],
        ];

        $recent = Interview::with(['candidate', 'jobPosition'])
            ->where('status', 'completed')
            ->latest('completed_at')
            ->limit(12)
            ->get();

        return view('hr.dashboard', compact('metrics', 'funnel', 'recent'));
    }
}
