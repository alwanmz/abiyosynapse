<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, CurrentCompany $currentCompany): Response
    {
        $events = [
            'created',
            'updated',
            'submitted',
            'approved',
            'rejected',
            'status_changed',
            'closed',
            'deleted',
        ];

        $logs = AuditLog::query()
            ->forCompany($currentCompany->id())
            ->with('user:id,name')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $query->orWhere('auditable_id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('audit-logs/page', [
            'logs' => $logs,
            'events' => $events,
            'filters' => [
                'event' => $request->string('event')->toString(),
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }
}
