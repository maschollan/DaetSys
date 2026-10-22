<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    /**
     * GET /api/activity-logs
     */
    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query()
            ->with(['user:id,name', 'asset:id,asset_code'])
            ->when($request->validated('user_id'), fn ($builder, $value) => $builder->where('user_id', $value))
            ->when($request->validated('asset_id'), fn ($builder, $value) => $builder->where('asset_id', $value))
            ->when($request->validated('transfer_id'), fn ($builder, $value) => $builder->where('transfer_id', $value))
            ->when($request->validated('action'), fn ($builder, $value) => $builder->where('action', $value))
            ->when($request->validated('date_from'), fn ($builder, $value) => $builder->whereDate('created_at', '>=', $value))
            ->when($request->validated('date_to'), fn ($builder, $value) => $builder->whereDate('created_at', '<=', $value));

        $search = $request->search();

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('action', 'like', '%'.$search.'%')
                    ->orWhereHas('asset', fn ($assetQuery) => $assetQuery->where('asset_code', 'like', '%'.$search.'%'));
            });
        }

        $sortColumn = $request->sortColumn() ?? 'created_at';
        $sortDirection = $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ActivityLogResource::collection($paginator)->additional([
            'message' => 'Activity logs retrieved successfully.',
        ])->response();
    }

    /**
     * GET /api/activity-logs/{activityLog}
     */
    public function show(ActivityLog $activityLog): JsonResponse
    {
        $this->authorize('view', $activityLog);

        return response()->json([
            'data' => new ActivityLogResource($activityLog->load(['user:id,name', 'asset:id,asset_code'])),
        ]);
    }
}
