<?php

namespace Database\Factories;

use App\Models\AssetHandover;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends Factory<AssetHandover>
 */
class AssetHandoverFactory extends Factory
{
    /** @use HasFactory<AssetHandoverFactory> */
    use HasFactory;

    protected $model = AssetHandover::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'handed_over_by' => User::factory(),
            'received_by' => null,
            'handed_over_at' => now(),
            'received_at' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'received_by' => User::factory(),
            'received_at' => now(),
        ]);
    }
}
