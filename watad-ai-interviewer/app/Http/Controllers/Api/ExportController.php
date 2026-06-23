<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Services\Export\ExcelExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function interviews(Request $request, ExcelExportService $export): StreamedResponse
    {
        $query = Interview::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('recommendation'), fn ($q) => $q->where('recommendation', $request->string('recommendation')))
            ->when($request->filled('job'), fn ($q) => $q->where('job_position_id', $request->integer('job')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('completed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('completed_at', '<=', $request->date('to')))
            ->latest('completed_at');

        // Audit the export.
        \App\Models\AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => 'exported',
            'auditable_type' => Interview::class,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return $export->download($query, 'watad-interviews-'.now()->format('Ymd').'.xlsx');
    }
}
