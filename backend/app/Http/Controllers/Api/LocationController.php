<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationIndexRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * GET /api/locations
     */
    public function index(LocationIndexRequest $request): JsonResponse
    {
        $query = Location::query()->withCount('assets');

        $search = $request->search();

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $isActive = $request->validated('is_active');

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $sortColumn = $request->sortColumn() ?? 'name';
        $sortDirection = $request->sortColumn() === null ? 'asc' : $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return LocationResource::collection($paginator)->additional([
            'message' => 'Locations retrieved successfully.',
        ])->response();
    }

    /**
     * POST /api/locations
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = DB::transaction(function () use ($request): Location {
            $location = Location::create($request->validated());

            $this->audit->log(
                ActivityAction::LocationCreated,
                user: $request->user(),
                newValues: ['code' => $location->code, 'name' => $location->name],
                request: $request,
            );

            return $location;
        });

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/locations/{location}
     */
    public function show(Location $location): JsonResponse
    {
        $this->authorize('view', $location);

        return response()->json([
            'data' => new LocationResource($location->loadCount('assets')),
        ]);
    }

    /**
     * PUT /api/locations/{location}
     */
    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        DB::transaction(function () use ($request, $location): void {
            $oldValues = [
                'code' => $location->code,
                'name' => $location->name,
                'is_active' => (bool) $location->is_active,
            ];

            $location->fill($request->validated())->save();

            $this->audit->log(
                ActivityAction::LocationUpdated,
                user: $request->user(),
                oldValues: $oldValues,
                newValues: [
                    'code' => $location->code,
                    'name' => $location->name,
                    'is_active' => (bool) $location->is_active,
                ],
                request: $request,
            );
        });

        return response()->json([
            'message' => 'Location updated successfully.',
            'data' => new LocationResource($location->fresh()->loadCount('assets')),
        ]);
    }

    /**
     * DELETE /api/locations/{location}
     */
    public function destroy(Request $request, Location $location): JsonResponse
    {
        $this->authorize('delete', $location);

        $isReferenced = $location->assets()->exists()
            || $location->outgoingTransfers()->exists()
            || $location->incomingTransfers()->exists();

        if ($isReferenced) {
            return response()->json([
                'message' => 'Location cannot be deleted because it is referenced by assets or transfers.',
            ], 409);
        }

        DB::transaction(function () use ($request, $location): void {
            $this->audit->log(
                ActivityAction::LocationDeleted,
                user: $request->user(),
                oldValues: ['code' => $location->code, 'name' => $location->name],
                request: $request,
            );

            $location->delete();
        });

        return response()->json([
            'message' => 'Location deleted successfully.',
        ]);
    }
}
