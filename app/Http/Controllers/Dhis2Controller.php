<?php

namespace App\Http\Controllers;

use App\Services\Dhis2HealthOutcomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Dhis2Controller extends Controller
{
    public function status(Dhis2HealthOutcomeService $service): JsonResponse
    {
        return response()->json($service->status());
    }

    public function sync(Request $request, Dhis2HealthOutcomeService $service): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $result = $service->sync($data['period'] ?? null);

        return response()->json($result, $result['status'] === 'failed' ? 422 : 200);
    }
}
