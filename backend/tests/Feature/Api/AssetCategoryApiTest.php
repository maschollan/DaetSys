<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;

it('lists categories with counted assets using the category_id foreign key', function () {
    actingAsRole('admin');

    $category = AssetCategory::factory()->create(['name' => 'Elektronik']);
    Asset::factory()->count(2)->create(['category_id' => $category->id]);

    // Regresi: hasMany(Asset) dulu menebak asset_category_id sehingga
    // pencacah withCount('assets') gagal dengan error kolom tak dikenal.
    $response = $this->getJson('/api/asset-categories');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $category->id);
    $response->assertJsonPath('data.0.assets_count', 2);
});

it('paginates and searches categories', function () {
    actingAsRole('admin');

    AssetCategory::factory()->create(['name' => 'Perabot Kantor']);
    AssetCategory::factory()->create(['name' => 'Kendaraan']);

    $response = $this->getJson('/api/asset-categories?search=Kendaraan&per_page=10');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('Kendaraan');
});

it('forbids officer from mutating categories and allows admin', function () {
    $officer = actingAsRole('officer');
    $category = AssetCategory::factory()->create();

    $this->postJson('/api/asset-categories', ['name' => 'Baru'])->assertForbidden();

    $this->actingAs($officer)->putJson("/api/asset-categories/{$category->id}", [
        'name' => 'Diubah',
    ])->assertForbidden();

    actingAsRole('admin');

    $this->postJson('/api/asset-categories', ['name' => 'IT Supplies'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'IT Supplies');

    $this->putJson("/api/asset-categories/{$category->id}", ['name' => 'Elektronik Kantor'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Elektronik Kantor');
});

it('prevents deleting a category that still owns assets', function () {
    actingAsRole('admin');

    $category = AssetCategory::factory()->create();
    Asset::factory()->create(['category_id' => $category->id]);

    $this->deleteJson("/api/asset-categories/{$category->id}")->assertStatus(409);
});

it('lists categories only for authenticated users', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->getJson('/api/asset-categories')->assertUnauthorized();
});
