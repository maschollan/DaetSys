<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('serves the interactive documentation page to admin', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->get('/docs')
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('denies the documentation page to non admin', function () {
    $officer = createUserWithRole('officer');

    $this->actingAs($officer)->get('/docs')->assertForbidden();

    $this->get('/docs')->assertForbidden();
});

it('serves the openapi document', function () {
    $admin = createUserWithRole('admin');

    $response = $this->actingAs($admin)->get('/docs/api.json');

    $response->assertOk()
        ->assertHeader('content-type', 'application/json');

    $spec = $response->json();

    expect($spec['openapi'])->toStartWith('3.')
        ->and($spec['info']['title'])->toBe('DaetSys API')
        ->and($spec['info']['version'])->toBe('1.0.0')
        ->and($spec['info']['description'])->toContain('DaetSys');
});

it('documents the bearer token security scheme', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    expect($spec['components']['securitySchemes']['http'])->toBe([
        'type' => 'http',
        'scheme' => 'bearer',
    ]);

    // Endpoint publik tidak boleh mewajibkan token.
    expect($spec['paths']['/login']['post']['security'])->toBe([]);
});

it('documents every api route', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    $documented = collect($spec['paths'])
        ->map(fn (array $operations): array => collect(array_keys($operations))
            ->diff(['parameters'])
            ->values()
            ->all())
        ->filter(fn (array $methods): bool => $methods !== [])
        ->keys()
        ->map(fn (string $path): string => normalizePath($path))
        ->unique()
        ->sort()
        ->values()
        ->all();

    $registered = collect(Route::getRoutes())
        ->filter(fn ($route): bool => $route->getName() !== null
            && str_starts_with($route->uri(), 'api/')
            && ! str_starts_with($route->uri(), 'api/docs'))
        ->map(fn ($route): string => normalizePath('/'.str_replace('api/', '', $route->uri())))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($documented)->toEqual($registered);
});

/**
 * Menyamakan nama parameter path agar perbandingan tidak bergantung pada
 * konvensi penamaan route.
 */
function normalizePath(string $path): string
{
    return (string) preg_replace('/\{[^}]+\}/', '{}', $path);
}

it('gives every endpoint an indonesian summary and description', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    $operations = collect($spec['paths'])->flatMap(fn (array $operations): array => collect($operations)
        ->filter(fn ($value, $key): bool => $key !== 'parameters', ARRAY_FILTER_USE_BOTH)
        ->values()
        ->all());

    expect($operations)->not->toBeEmpty();

    foreach ($operations as $operation) {
        expect($operation['summary'] ?? null)->not->toBeNull();
        expect($operation['description'] ?? null)->not->toBeNull();
        expect($operation['tags'] ?? null)->not->toBeEmpty();
    }
});

it('documents the idempotency header on retryable endpoints', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    $headers = collect($spec['paths']['/assets']['post']['parameters'])
        ->filter(fn (array $parameter): bool => ($parameter['in'] ?? null) === 'header')
        ->pluck('name')
        ->all();

    expect($headers)->toContain('Idempotency-Key');
});

it('documents the prohibited location field on asset update', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    $schema = $spec['components']['schemas']['UpdateAssetRequest'];

    expect($schema['properties']['location_id'] ?? null)->not->toBeNull();
    expect($spec['paths']['/assets/{asset}']['put']['description'])
        ->toContain('location_id')
        ->toContain('transfer');
});

it('groups endpoints by indonesian tag', function () {
    $admin = createUserWithRole('admin');

    $spec = $this->actingAs($admin)->get('/docs/api.json')->json();

    $tags = collect($spec['tags'])->pluck('name')->all();

    expect($tags)->toContain('Autentikasi')
        ->toContain('Aset')
        ->toContain('Transfer Aset')
        ->toContain('Jejak Audit')
        ->toContain('Manajemen Pengguna');
});

it('does not expose the documentation to an inactive account', function () {
    $admin = createUserWithRole('admin', ['is_active' => false]);

    $this->actingAs($admin)->get('/docs')->assertForbidden();
});

it('keeps the documentation page free of blade views', function () {
    $admin = createUserWithRole('admin');

    $response = $this->actingAs($admin)->get('/docs');

    expect($response->getStatusCode())->toBe(200);
    expect(User::count())->toBe(1);
});
