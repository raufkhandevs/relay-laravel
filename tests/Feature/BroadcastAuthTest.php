<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

it('authorises the owning customer on their ticket channel', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])
        ->assertOk();
});

it('authorises any agent on any ticket channel', function () {
    $agent = User::factory()->create(['role' => UserRole::Agent]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])
        ->assertOk();
});

it('refuses a stranger subscribing to a ticket channel', function () {
    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])
        ->assertForbidden();
});

it('accepts a real bearer token on the same endpoint', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $token = $customer->createToken('iPhone')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])
        ->assertOk();
});

it('refuses an unauthenticated subscription', function () {
    $ticket = Ticket::factory()->create();

    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-ticket.{$ticket->id}",
    ])->assertUnauthorized();
});
