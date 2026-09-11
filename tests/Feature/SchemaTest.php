<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

it('relates a ticket to its customer, agent and messages', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $agent = User::factory()->create(['role' => UserRole::Agent]);

    $ticket = Ticket::factory()->for($customer, 'customer')->create([
        'assigned_agent_id' => $agent->id,
        'status' => TicketStatus::Open,
    ]);

    Message::factory()->count(3)->for($ticket)->for($customer, 'author')->create();

    expect($ticket->customer->is($customer))->toBeTrue()
        ->and($ticket->assignedAgent->is($agent))->toBeTrue()
        ->and($ticket->messages)->toHaveCount(3)
        ->and($ticket->status)->toBeInstanceOf(TicketStatus::class)
        ->and($customer->role)->toBe(UserRole::Customer);
});

it('rejects a duplicate idempotency key', function () {
    $ticket = Ticket::factory()->create();
    $idempotencyKey = (string) Str::uuid();

    Message::factory()->for($ticket)->create(['idempotency_key' => $idempotencyKey]);

    expect(fn () => Message::factory()->for($ticket)->create(['idempotency_key' => $idempotencyKey]))
        ->toThrow(UniqueConstraintViolationException::class);
});
