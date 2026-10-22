<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * GET /api/dashboard/summary
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->summary(),
        ]);
    }

    /**
     * GET /api/dashboard/assets-by-location
     */
    public function assetsByLocation(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->assetsByLocation(),
        ]);
    }

    /**
     * GET /api/dashboard/assets-by-status
     */
    public function assetsByStatus(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->assetsByStatus(),
        ]);
    }

    /**
     * GET /api/dashboard/recent-activities
     */
    public function recentActivities(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->recentActivities(),
        ]);
    }

    /**
     * GET /api/dashboard/pending-transfers
     */
    public function pendingTransfers(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->pendingTransfers(),
        ]);
    }
}
