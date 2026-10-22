<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected $model = Transfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $asset = Asset::factory();

        return [
            'asset_id' => $asset,
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'requested_by' => User::factory(),
            'status' => TransferStatus::Pending->value,
            'reason' => fake()->sentence(),
            'requested_at' => now(),
            'completed_at' => null,
            'active_asset_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::Approved->value,
        ]);
    }

    public function handover(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::Handover->value,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::Completed->value,
            'completed_at' => now(),
            'active_asset_id' => null,
        ]);
    }
}
