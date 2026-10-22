<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => AssetCategory::factory(),
            'location_id' => Location::factory(),
            'asset_code' => strtoupper(fake()->unique()->bothify('AST-####')),
            'qr_token' => Str::random(40),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN-######')),
            'status' => AssetStatus::Available->value,
            'acquisition_date' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
        ];
    }

    public function inTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::InTransfer->value,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Maintenance->value,
        ]);
    }

    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Missing->value,
        ]);
    }

    public function withoutLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => null,
        ]);
    }
}
