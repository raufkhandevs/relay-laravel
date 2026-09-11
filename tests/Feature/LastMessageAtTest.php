<?php

use App\Models\Message;
use App\Models\Ticket;

it('stamps last_message_at on the parent ticket when a message is created', function () {
    $ticket = Ticket::factory()->create(['last_message_at' => null]);

    $message = Message::factory()->for($ticket)->create();

    expect($ticket->fresh()->last_message_at->timestamp)
        ->toBe($message->created_at->timestamp);
});

it('advances last_message_at on each new message', function () {
    $ticket = Ticket::factory()->create();

    Message::factory()->for($ticket)->create(['created_at' => now()->subHour()]);
    $first = $ticket->fresh()->last_message_at;

    Message::factory()->for($ticket)->create();

    expect($ticket->fresh()->last_message_at->greaterThan($first))->toBeTrue();
});

it('orders tickets by most recent activity using the denormalised column', function () {
    $quiet = Ticket::factory()->create();
    $busy = Ticket::factory()->create();

    Message::factory()->for($quiet)->create(['created_at' => now()->subDay()]);
    Message::factory()->for($busy)->create();

    $ordered = Ticket::orderByDesc('last_message_at')->pluck('id');

    expect($ordered->first())->toBe($busy->id);
});
