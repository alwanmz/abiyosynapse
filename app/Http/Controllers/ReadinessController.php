<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'not_ready',
                'checks' => ['database' => 'failed'],
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'checks' => ['database' => 'ok'],
        ]);
    }
}
