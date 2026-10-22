<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Api', 'Feature/Workflow');

function createRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['description' => $name]);
}

function createUserWithRole(string $roleName, array $attributes = []): User
{
    $role = createRole($roleName);

    $user = User::factory()->create(array_merge([
        'role_id' => $role->id,
        'is_active' => true,
    ], $attributes));

    return $user;
}

function actingAsRole(string $roleName, array $attributes = []): User
{
    $user = createUserWithRole($roleName, $attributes);

    Sanctum::actingAs($user);

    return $user;
}
