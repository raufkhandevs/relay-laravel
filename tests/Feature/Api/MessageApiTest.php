<?php

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
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
