<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('renders a customers own tickets', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    Ticket::factory()->count(2)->for($customer, 'customer')->create();
    Ticket::factory()->count(3)->create();

    $this->actingAs($customer)
        ->get('/tickets')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tickets/index')
            ->has('tickets.data', 2));
});

it('renders a ticket thread', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer)
        ->get("/tickets/{$ticket->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tickets/show')
            ->where('ticket.id', $ticket->id));
});

it('refuses a stranger opening a ticket over the web', function () {
    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($stranger)->get("/tickets/{$ticket->id}")->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get('/tickets')->assertRedirect(route('login'));
});
