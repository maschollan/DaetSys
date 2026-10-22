<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * GET /api/users
     */
    public function index(UserIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with('role:id,name,description')
            ->when($request->validated('role_id'), fn ($builder, $value) => $builder->where('role_id', $value))
            ->when($request->validated('is_active') !== null, fn ($builder) => $builder->where(
                'is_active',
                filter_var($request->validated('is_active'), FILTER_VALIDATE_BOOLEAN),
            ));

        $search = $request->search();

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $sortColumn = $request->sortColumn() ?? 'name';
        $sortDirection = $request->sortColumn() === null ? 'asc' : $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return UserResource::collection($paginator)->additional([
            'message' => 'Users retrieved successfully.',
        ])->response();
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $validated['role_id'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->audit->log(
                ActivityAction::UserCreated,
                user: $request->user(),
                newValues: [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ],
                request: $request,
            );

            return $user;
        });

        return (new UserResource($user->load('role')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/users/{user}
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => new UserResource($user->load('role')),
        ]);
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $user, $validated): void {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => (bool) $user->is_active,
            ];

            $attributes = array_filter(
                [
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'role_id' => $validated['role_id'] ?? null,
                    'is_active' => $validated['is_active'] ?? null,
                ],
                fn ($value): bool => $value !== null,
            );

            if (isset($validated['password'])) {
                $attributes['password'] = $validated['password'];
            }

            $user->fill($attributes)->save();

            // Akun yang dinonaktifkan harus kehilangan seluruh token aktifnya.
            if (! $user->is_active) {
                $user->tokens()->delete();
            }

            $this->audit->log(
                ActivityAction::UserUpdated,
                user: $request->user(),
                oldValues: $oldValues,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'is_active' => (bool) $user->is_active,
                ],
                request: $request,
            );

            if (! $user->is_active && $oldValues['is_active'] === true) {
                $this->audit->log(
                    ActivityAction::UserDeactivated,
                    user: $request->user(),
                    newValues: ['user_id' => $user->id],
                    request: $request,
                );
            }
        });

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new UserResource($user->fresh()->load('role')),
        ]);
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            DB::transaction(function () use ($request, $user): void {
                $this->audit->log(
                    ActivityAction::UserDeleted,
                    user: $request->user(),
                    oldValues: ['user_id' => $user->id, 'email' => $user->email],
                    request: $request,
                );

                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException $exception) {
            // Pengguna masih tercatat pada jejak audit atau riwayat transaksi,
            // sehingga tidak boleh dihapus agar integritas riwayat terjaga.
            throw BusinessRuleException::conflict(
                'User cannot be deleted because they are still referenced by activity logs or transfers.',
                [],
                $exception,
            );
        }

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * POST /api/users/{user}/reset-password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($request, $user, $validated): void {
            $user->update(['password' => $validated['password']]);
            $user->tokens()->delete();

            $this->audit->log(
                ActivityAction::PasswordChanged,
                user: $request->user(),
                newValues: ['user_id' => $user->id, 'by_admin' => true],
                request: $request,
            );
        });

        return response()->json([
            'message' => 'Password reset successfully. All sessions have been revoked.',
        ]);
    }
}
