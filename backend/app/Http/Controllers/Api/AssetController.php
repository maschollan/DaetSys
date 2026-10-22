<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetIndexRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AssetResource;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets) {}

    /**
     * GET /api/assets
     */
    public function index(AssetIndexRequest $request): JsonResponse
    {
        $query = Asset::query()
            ->with(['category:id,name', 'location:id,code,name'])
            ->when($request->validated('category_id'), fn ($builder, $value) => $builder->where('category_id', $value))
            ->when($request->validated('location_id'), fn ($builder, $value) => $builder->where('location_id', $value))
            ->when($request->validated('status'), fn ($builder, $value) => $builder->where('status', $value));

        $search = $request->search();

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('asset_code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('serial_number', 'like', '%'.$search.'%');
            });
        }

        $sortColumn = $request->sortColumn() ?? 'created_at';
        $sortDirection = $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return AssetResource::collection($paginator)->additional([
            'message' => 'Assets retrieved successfully.',
        ])->response();
    }

    /**
     * POST /api/assets
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        $asset = $this->assets->create(
            $request->user(),
            $request->validated(),
            $request,
        );

        return (new AssetResource($asset))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/assets/{asset}
     */
    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json([
            'data' => new AssetResource($asset->load(['category', 'location'])),
        ]);
    }

    /**
     * PUT /api/assets/{asset}
     */
    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $asset = $this->assets->update(
            $request->user(),
            $asset,
            $request->validated(),
            $request,
        );

        return response()->json([
            'message' => 'Asset updated successfully.',
            'data' => new AssetResource($asset),
        ]);
    }

    /**
     * DELETE /api/assets/{asset}
     */
    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        $this->assets->delete($request->user(), $asset, $request);

        return response()->json([
            'message' => 'Asset deleted successfully.',
        ]);
    }

    /**
     * GET /api/assets/{asset}/history
     */
    public function history(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $logs = ActivityLog::query()
            ->with(['user:id,name'])
            ->where('asset_id', $asset->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 25))
            ->withQueryString();

        return ActivityLogResource::collection($logs)->additional([
            'message' => 'Asset history retrieved successfully.',
        ])->response();
    }

    /**
     * GET /api/assets/qr/{qr_token}
     */
    public function byQrToken(Request $request, string $qr_token): JsonResponse
    {
        $asset = $this->assets->findByQrToken($qr_token);

        if ($asset === null) {
            return response()->json([
                'message' => 'Asset not found.',
            ], 404);
        }

        $this->authorize('track', $asset);

        return response()->json([
            'data' => new AssetResource($asset),
        ]);
    }
}
