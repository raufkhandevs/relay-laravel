<?php

use App\Enums\UserRole;
use App\Events\MessageCreated;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('cursor paginates message history', function () {
    $agent = User::factory()->create(['role' => UserRole::Agent]);
    $ticket = Ticket::factory()->create();
    Message::factory()->count(30)->for($ticket)->create();

    $first = $this->actingAs($agent, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}/messages");

    $first->assertOk();
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('next_cursor'))->not->toBeNull();

    $cursor = $first->json('next_cursor');
    $second = $this->actingAs($agent, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}/messages?cursor={$cursor}");

    expect($second->json('data'))->toHaveCount(10);

    $firstIds = collect($first->json('data'))->pluck('id');
    $secondIds = collect($second->json('data'))->pluck('id');
    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

it('posts a message and stamps the ticket', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create(['last_message_at' => null]);

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'body' => 'The export still fails.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

    $response->assertCreated()->assertJsonPath('body', 'The export still fails.');
    expect($ticket->fresh()->last_message_at)->not->toBeNull();
});

it('returns the original message for a repeated idempotency key', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $key = (string) Str::uuid();

    $first = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", ['body' => 'Once', 'idempotency_key' => $key]);

    $second = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", ['body' => 'Once', 'idempotency_key' => $key]);

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(Message::where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('returns the racing writer message instead of a 500 for a concurrent idempotency key', function () {
    Event::fake([MessageCreated::class]);

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $key = (string) Str::uuid();

    // Simulate a genuine race by inserting a competing row out of band, right after
    // this request's own lookup query runs (finding nothing) and before its create()
    // runs. The insert happens at the current (non-savepoint) transaction depth, via
    // a raw query rather than the model's create(), so that when the controller's own
    // create() collides and its savepoint is rolled back, only its own failed insert
    // is undone, not this one.
    $racingId = null;
    $hooked = false;
    DB::listen(function ($event) use ($ticket, $key, &$racingId, &$hooked): void {
        if ($hooked || ! str_contains($event->sql, 'idempotency_key')) {
            return;
        }

        $hooked = true;
        $racingId = DB::table('messages')->insertGetId([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->customer_id,
            'body' => 'Racing writer got there first.',
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'body' => 'Slower request.',
            'idempotency_key' => $key,
        ]);

    $response->assertCreated();
    expect($response->json('id'))->toBe((int) $racingId)
        ->and($response->json('body'))->toBe('Racing writer got there first.')
        ->and(Message::where('ticket_id', $ticket->id)->where('idempotency_key', $key)->count())->toBe(1);

    Event::assertNotDispatched(MessageCreated::class);
});

it('allows the same idempotency key on two different tickets', function () {
    $agent = User::factory()->create(['role' => UserRole::Agent]);
    $ticketOne = Ticket::factory()->create();
    $ticketTwo = Ticket::factory()->create();
    $key = (string) Str::uuid();

    $first = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/tickets/{$ticketOne->id}/messages", ['body' => 'On ticket one', 'idempotency_key' => $key]);

    $second = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/tickets/{$ticketTwo->id}/messages", ['body' => 'On ticket two', 'idempotency_key' => $key]);

    $first->assertCreated();
    $second->assertCreated();
    expect($first->json('id'))->not->toBe($second->json('id'));
});

it('refuses a stranger posting to a ticket', function () {
    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'body' => 'let me in',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();
});
