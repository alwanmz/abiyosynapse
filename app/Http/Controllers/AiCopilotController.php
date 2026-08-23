<?php

namespace App\Http\Controllers;

use App\Models\AiActionRun;
use App\Services\AiCopilotService;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AiCopilotController extends Controller
{
    public function index(AiService $ai, AiCopilotService $copilot): Response
    {
        return Inertia::render('ai/copilot/page', [
            'configured' => $ai->isConfigured(),
            'tools' => $copilot->availableTools(),
        ]);
    }

    public function chat(Request $request, AiCopilotService $copilot): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        try {
            return response()->json(
                $copilot->chat($validated['message'], $request->user()->id),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function confirm(
        Request $request,
        AiActionRun $aiActionRun,
        AiCopilotService $copilot,
    ): JsonResponse {
        try {
            return response()->json(
                $copilot->confirm($aiActionRun, $request->user()->id),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reject(
        Request $request,
        AiActionRun $aiActionRun,
        AiCopilotService $copilot,
    ): JsonResponse {
        try {
            return response()->json(
                $copilot->reject($aiActionRun, $request->user()->id),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
