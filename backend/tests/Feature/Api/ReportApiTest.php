<?php

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Transfer;

it('lists transfers on the report endpoint without missing-status crash', function () {
    actingAsRole('admin');

    $asset = Asset::factory()->create(['status' => AssetStatus::Available->value]);
    Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'status' => TransferStatus::Pending->value,
    ]);

    // Regresi: eager load asset tanpa kolom status membuat TransferResource
    // gagal membaca status->value (500).
    $response = $this->getJson('/api/reports/transfers');

    $response->assertOk();
    $response->assertJsonPath('data.0.status', TransferStatus::Pending->value);
    expect($response->json('data.0.asset.status'))->toBe(AssetStatus::Available->value);
});

it('searches the transfers report by asset code or name', function () {
    actingAsRole('admin');

    $asset = Asset::factory()->create(['asset_code' => 'RPT-SEARCH-01', 'name' => 'Kamera Vimeo']);
    $other = Asset::factory()->create(['asset_code' => 'RPT-OTHER-09']);
    foreach ([$asset, $other] as $item) {
        Transfer::factory()->create([
            'asset_id' => $item->id,
            'from_location_id' => $item->location_id,
            'to_location_id' => Location::factory()->create()->id,
            'status' => TransferStatus::Pending->value,
        ]);
    }

    $response = $this->getJson('/api/reports/transfers?search=Kamera');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.asset.asset_code'))->toBe('RPT-SEARCH-01');
});

it('downloads the transfer report as csv', function () {
    actingAsRole('admin');

    $asset = Asset::factory()->create();
    Transfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->location_id,
        'to_location_id' => Location::factory()->create()->id,
        'status' => TransferStatus::Completed->value,
    ]);

    $response = $this->getJson('/api/reports/transfers?format=csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('transfer');
});

it('scopes the asset report by status and date range', function () {
    actingAsRole('admin');

    Asset::factory()->create(['status' => AssetStatus::Available->value, 'acquisition_date' => '2026-01-10']);
    Asset::factory()->create(['status' => AssetStatus::Missing->value, 'acquisition_date' => '2026-01-20']);

    $response = $this->getJson('/api/reports/assets?status=missing&date_from=2026-01-15&date_to=2026-01-31');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.status'))->toBe('missing');
});

it('downloads the activity log report as csv', function () {
    actingAsRole('admin');

    $asset = Asset::factory()->create();

    ActivityLog::factory()->create([
        'asset_id' => $asset->id,
        'action' => ActivityAction::AssetCreated->value,
    ]);

    $response = $this->getJson('/api/reports/activity-logs?format=csv');

    // Regresi: tanpa branch CSV endpoint mengembalikan JSON meski format=csv,
    // sehingga berkas yang diunduh berisi JSON.
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('activity-logs')
        ->and($response->getContent())->toContain('Asset Code')
        ->and($response->getContent())->toContain($asset->asset_code);
});

it('lists activity logs on the report endpoint', function () {
    actingAsRole('admin');

    $asset = Asset::factory()->create();

    ActivityLog::factory()->create(['asset_id' => $asset->id]);

    $response = $this->getJson('/api/reports/activity-logs');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.asset.asset_code'))->toBe($asset->asset_code);
});
