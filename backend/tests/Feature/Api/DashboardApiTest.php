<?php

use App\Models\Asset;
use App\Models\Location;
use App\Models\Transfer;

it('returns dashboard summary with asset counts', function () {
    actingAsRole('admin');
    Asset::factory()->count(2)->create(['status' => 'available']);
    Asset::factory()->create(['status' => 'maintenance']);

    $response = $this->getJson('/api/dashboard/summary');

    $response->assertOk()
        ->assertJsonPath('data.total_assets', 3)
        ->assertJsonPath('data.assets_available', 2)
        ->assertJsonPath('data.assets_maintenance', 1);
});

it('returns assets-by-status with status as plain string', function () {
    // Regresi: enum AssetStatus pada agregasi tidak boleh gagal dikonversi
    // ke string (terminal: "could not be converted to string").
    actingAsRole('admin');
    Asset::factory()->count(2)->create(['status' => 'available']);
    Asset::factory()->create(['status' => 'missing']);

    $response = $this->getJson('/api/dashboard/assets-by-status');

    $response->assertOk();
    $rows = collect($response->json('data'));

    expect($rows->where('status', 'available')->value('total'))->toBe(2)
        ->and($rows->where('status', 'missing')->value('total'))->toBe(1)
        ->and($rows->pluck('status')->every(fn ($status) => is_string($status)))->toBeTrue();
});

it('returns assets-by-location including unassigned total', function () {
    actingAsRole('admin');
    $location = Location::factory()->create();
    Asset::factory()->count(3)->create(['location_id' => $location->id]);
    Asset::factory()->create(['location_id' => null]);

    $response = $this->getJson('/api/dashboard/assets-by-location');

    $response->assertOk();
    $rows = collect($response->json('data'));

    expect($rows->sum('total'))->toBe(4)
        ->and($rows->firstWhere('location_id', null)['location_name'])->toBe('Unassigned');
});

it('returns pending transfers with asset context', function () {
    actingAsRole('admin');
    $transfer = Transfer::factory()->create(['status' => 'pending']);

    $response = $this->getJson('/api/dashboard/pending-transfers');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $transfer->id)
        ->assertJsonPath('data.0.asset_code', $transfer->asset->asset_code);
});
