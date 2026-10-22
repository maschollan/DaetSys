<?php

use App\Models\User;

it('logs in with valid credentials and returns a bearer token', function () {
    $role = createRole('admin');
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password123',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'token_type',
                'user',
            ],
        ]);

    expect($response->json('data.token_type'))->toBe('Bearer');
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects login for inactive account', function () {
    $role = createRole('officer');
    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => 'password123',
        'role_id' => $role->id,
        'is_active' => false,
    ]);

    $this->postJson('/api/login', [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ])->assertForbidden();
});

it('revokes token on logout', function () {
    $role = createRole('admin');
    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
});

it('returns current user on me endpoint', function () {
    $user = actingAsRole('auditor');

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});
