<?php

use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Transfer;
use Laravel\Sanctum\Sanctum;

it('prevents concurrent transfer requests via database guard', function () {
    $requester = createUserWithRole('officer');
    $asset = Asset::factory()->create([
        'status' => AssetStatus::Available->value,
    ]);
    $toLocation = Location::factory()->create();

    // Simulate two concurrent requests by trying to create second while first exists
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => $toLocation->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Pending->value,
        'active_asset_id' => $asset->id,
    ]);

    $asset->update(['status' => AssetStatus::InTransfer->value]);

    // Second request should fail
    Sanctum::actingAs($requester);

    $response = $this->postJson('/api/transfers', [
        'asset_id' => $asset->id,
        'to_location_id' => Location::factory()->create()->id,
        'reason' => 'Test',
    ]);

    $response->assertStatus(409);
});

it('enforces separation of duties: requester cannot approve own transfer', function () {
    $requester = createUserWithRole('officer');
    $admin = createUserWithRole('admin');
    $asset = Asset::factory()->create(['status' => AssetStatus::InTransfer->value]);
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Pending->value,
        'active_asset_id' => $asset->id,
    ]);

    Sanctum::actingAs($requester);
    $this->postJson("/api/transfers/{$transfer->id}/approve")->assertForbidden();

    Sanctum::actingAs($admin);
    $this->postJson("/api/transfers/{$transfer->id}/approve")->assertOk();
});

it('rejects handover giver becoming receiver', function () {
    $requester = createUserWithRole('officer');
    $admin = createUserWithRole('admin');
    $asset = Asset::factory()->create(['status' => AssetStatus::InTransfer->value]);
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Approved->value,
        'active_asset_id' => $asset->id,
    ]);

    Sanctum::actingAs($admin);
    $this->postJson("/api/transfers/{$transfer->id}/handover")->assertOk();

    // Same user cannot receive
    $this->postJson("/api/transfers/{$transfer->id}/receive")->assertForbidden();
});

it('updates location only after receive', function () {
    $requester = createUserWithRole('officer');
    $admin = createUserWithRole('admin');
    $fromLocation = Location::factory()->create();
    $toLocation = Location::factory()->create();
    $asset = Asset::factory()->create([
        'status' => AssetStatus::InTransfer->value,
        'location_id' => $fromLocation->id,
    ]);
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $fromLocation->id,
        'to_location_id' => $toLocation->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Approved->value,
        'active_asset_id' => $asset->id,
    ]);

    Sanctum::actingAs($admin);
    $this->postJson("/api/transfers/{$transfer->id}/handover")->assertOk();

    $officer2 = createUserWithRole('officer');
    Sanctum::actingAs($officer2);
    $this->postJson("/api/transfers/{$transfer->id}/receive")->assertOk();

    $asset->refresh();
    expect($asset->location_id)->toBe($toLocation->id);
    expect($asset->status->value)->toBe(AssetStatus::Available->value);
});

it('resolves the transfer detail by uniqid in the url', function () {
    $admin = createUserWithRole('admin');
    $requester = createUserWithRole('officer');
    $asset = Asset::factory()->create();
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Pending->value,
    ]);

    Sanctum::actingAs($admin);

    expect($transfer->uniqid)->toBeString()->not->toBe((string) $transfer->id);

    $this->getJson("/api/transfers/{$transfer->uniqid}")
        ->assertOk()
        ->assertJsonPath('data.uniqid', $transfer->uniqid)
        ->assertJsonPath('data.id', $transfer->id);
});

it('keeps resolving the transfer detail by legacy numeric id', function () {
    $admin = createUserWithRole('admin');
    $requester = createUserWithRole('officer');
    $asset = Asset::factory()->create();
    $transfer = Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'requested_by' => $requester->id,
        'status' => TransferStatus::Pending->value,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/transfers/{$transfer->id}")
        ->assertOk()
        ->assertJsonPath('data.uniqid', $transfer->uniqid);
});

it('returns 404 for an unknown transfer token', function () {
    $admin = createUserWithRole('admin');

    Sanctum::actingAs($admin);

    $this->getJson('/api/transfers/01JZZZZZZZZZZZZZZZZZZZZZXX')->assertNotFound();
});
