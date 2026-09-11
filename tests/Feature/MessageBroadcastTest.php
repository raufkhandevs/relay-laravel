<?php

use App\Enums\UserRole;
use App\Events\MessageCreated;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('broadcasts MessageCreated on the ticket channel when a message is posted', function () {
    Event::fake([MessageCreated::class]);

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'body' => 'Still broken.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated();

    Event::assertDispatched(MessageCreated::class, function (MessageCreated $event) use ($ticket) {
        return $event->message->ticket_id === $ticket->id
            && $event->broadcastOn()[0]->name === "private-ticket.{$ticket->id}";
    });
});

it('does not broadcast twice for a repeated idempotency key', function () {
    Event::fake([MessageCreated::class]);

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $key = (string) Str::uuid();

    foreach ([1, 2] as $attempt) {
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/messages", ['body' => 'Once', 'idempotency_key' => $key]);
    }

    Event::assertDispatchedTimes(MessageCreated::class, 1);
});
