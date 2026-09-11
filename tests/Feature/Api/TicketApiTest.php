<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

it('shows a customer only their own tickets', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $stranger = User::factory()->create(['role' => UserRole::Customer]);

    Ticket::factory()->count(2)->for($customer, 'customer')->create();
    Ticket::factory()->count(3)->for($stranger, 'customer')->create();

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/tickets');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('shows an agent every ticket', function () {
    $agent = User::factory()->create(['role' => UserRole::Agent]);
    Ticket::factory()->count(5)->create();

    $response = $this->actingAs($agent, 'sanctum')->getJson('/api/tickets');

    expect($response->json('data'))->toHaveCount(5);
});

it('refuses a stranger reading another customers ticket', function () {
    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}")
        ->assertForbidden();
});

it('refuses an unauthenticated request', function () {
    $ticket = Ticket::factory()->create();

    $this->getJson("/api/tickets/{$ticket->id}")->assertUnauthorized();
});

it('paginates the ticket list', function () {
    $agent = User::factory()->create(['role' => UserRole::Agent]);
    Ticket::factory()->count(25)->create();

    $response = $this->actingAs($agent, 'sanctum')->getJson('/api/tickets');

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('total'))->toBe(25);
});
