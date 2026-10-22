<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCategoryIndexRequest;
use App\Http\Requests\StoreAssetCategoryRequest;
use App\Http\Requests\UpdateAssetCategoryRequest;
use App\Http\Resources\AssetCategoryResource;
use App\Models\AssetCategory;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetCategoryController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * GET /api/asset-categories
     */
    public function index(AssetCategoryIndexRequest $request): JsonResponse
    {
        $query = AssetCategory::query()->withCount('assets');

        $search = $request->search();

        if ($search !== null) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $sortColumn = $request->sortColumn() ?? 'name';
        $sortDirection = $request->sortColumn() === null ? 'asc' : $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return AssetCategoryResource::collection($paginator)->additional([
            'message' => 'Asset categories retrieved successfully.',
        ])->response();
    }

    /**
     * POST /api/asset-categories
     */
    public function store(StoreAssetCategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(function () use ($request): AssetCategory {
            $category = AssetCategory::create($request->validated());

            $this->audit->log(
                ActivityAction::CategoryCreated,
                user: $request->user(),
                newValues: ['name' => $category->name],
                request: $request,
            );

            return $category;
        });

        return (new AssetCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/asset-categories/{asset_category}
     */
    public function show(AssetCategory $asset_category): JsonResponse
    {
        $this->authorize('view', $asset_category);

        return response()->json([
            'data' => new AssetCategoryResource($asset_category->loadCount('assets')),
        ]);
    }

    /**
     * PUT /api/asset-categories/{asset_category}
     */
    public function update(UpdateAssetCategoryRequest $request, AssetCategory $asset_category): JsonResponse
    {
        DB::transaction(function () use ($request, $asset_category): void {
            $oldValues = ['name' => $asset_category->name, 'description' => $asset_category->description];

            $asset_category->fill($request->validated())->save();

            $this->audit->log(
                ActivityAction::CategoryUpdated,
                user: $request->user(),
                oldValues: $oldValues,
                newValues: ['name' => $asset_category->name, 'description' => $asset_category->description],
                request: $request,
            );
        });

        return response()->json([
            'message' => 'Asset category updated successfully.',
            'data' => new AssetCategoryResource($asset_category->fresh()->loadCount('assets')),
        ]);
    }

    /**
     * DELETE /api/asset-categories/{asset_category}
     */
    public function destroy(Request $request, AssetCategory $asset_category): JsonResponse
    {
        $this->authorize('delete', $asset_category);

        if ($asset_category->assets()->exists()) {
            return response()->json([
                'message' => 'Asset category cannot be deleted while assets are still assigned to it.',
            ], 409);
        }

        DB::transaction(function () use ($request, $asset_category): void {
            $this->audit->log(
                ActivityAction::CategoryDeleted,
                user: $request->user(),
                oldValues: ['name' => $asset_category->name],
                request: $request,
            );

            $asset_category->delete();
        });

        return response()->json([
            'message' => 'Asset category deleted successfully.',
        ]);
    }
}
