<?php

namespace Database\Factories;

use App\Enums\TransferDecision;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends Factory<TransferApproval>
 */
class TransferApprovalFactory extends Factory
{
    /** @use HasFactory<TransferApprovalFactory> */
    use HasFactory;

    protected $model = TransferApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'approver_id' => User::factory(),
            'decision' => TransferDecision::Approved->value,
            'notes' => fake()->sentence(),
            'decided_at' => now(),
        ];
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => TransferDecision::Rejected->value,
        ]);
    }
}
