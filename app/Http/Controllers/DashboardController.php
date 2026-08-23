<?php

namespace App\Http\Controllers;

use App\Services\ExecutiveDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, ExecutiveDashboardService $dashboard): Response
    {
        $fromDate = $request->input('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $fromDate = Carbon::parse($fromDate)->toDateString();
        $toDate = Carbon::parse($toDate)->toDateString();

        return Inertia::render('dashboard', $dashboard->build($fromDate, $toDate));
    }
}
