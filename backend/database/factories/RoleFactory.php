<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'description' => 'Administrator_instansi',
        ]);
    }

    public function officer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'officer',
            'description' => 'Petugas lapangan',
        ]);
    }

    public function auditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'auditor',
            'description' => 'Auditor internal',
        ]);
    }
}
