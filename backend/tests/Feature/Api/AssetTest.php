<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;

it('allows admin or officer to create asset', function () {
    $user = actingAsRole('officer');
    $category = AssetCategory::factory()->create();
    $location = Location::factory()->create();

    $response = $this->postJson('/api/assets', [
        'category_id' => $category->id,
        'location_id' => $location->id,
        'asset_code' => 'AST-TEST-001',
        'name' => 'Laptop Test',
        'status' => 'available',
    ]);

    $response->assertCreated();
    expect(Asset::where('asset_code', 'AST-TEST-001')->exists())->toBeTrue();
});

it('prevents non-admin from deleting asset', function () {
    $user = actingAsRole('officer');
    $asset = Asset::factory()->create();

    $this->deleteJson("/api/assets/{$asset->id}")->assertForbidden();
});

it('rejects attempt to change location via update with 422', function () {
    $user = actingAsRole('admin');
    $asset = Asset::factory()->create();

    $this->putJson("/api/assets/{$asset->id}", [
        'location_id' => 999,
    ])->assertStatus(422);
});

it('allows auditor to list assets but not create', function () {
    $user = actingAsRole('auditor');
    Asset::factory()->count(3)->create();

    $this->getJson('/api/assets')->assertOk();
    $this->postJson('/api/assets', [])->assertForbidden();
});
