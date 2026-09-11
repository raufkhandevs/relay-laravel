<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'status' => TicketStatus::Open,
            'customer_id' => User::factory()->state(['role' => UserRole::Customer]),
            'assigned_agent_id' => null,
            'last_message_at' => null,
        ];
    }
}
