<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Api');

it('logs location change attempt', function () {
    $user = actingAsRole('admin');
    $asset = Asset::factory()->create();

    $this->putJson("/api/assets/{$asset->id}", [
        'location_id' => 123,
    ])->assertStatus(422);

    expect(ActivityLog::where('action', 'asset.location_change_attempt')->exists())->toBeTrue();
});

it('allows auditor to view activity logs', function () {
    $auditor = actingAsRole('auditor');

    $this->getJson('/api/activity-logs')->assertOk();
});

it('prevents officer from viewing activity logs', function () {
    $officer = actingAsRole('officer');

    $this->getJson('/api/activity-logs')->assertForbidden();
});
