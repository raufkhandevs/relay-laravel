<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->stranger = User::factory()->create(['role' => UserRole::Customer]);
    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
    $this->ticket = Ticket::factory()->for($this->customer, 'customer')->create();
});

it('lets the owning customer view their ticket', function () {
    expect($this->customer->can('view', $this->ticket))->toBeTrue();
});

it('lets any agent view any ticket', function () {
    expect($this->agent->can('view', $this->ticket))->toBeTrue();
});

it('refuses another customer', function () {
    expect($this->stranger->can('view', $this->ticket))->toBeFalse();
});

it('lets the owning customer and any agent reply', function () {
    expect($this->customer->can('reply', $this->ticket))->toBeTrue()
        ->and($this->agent->can('reply', $this->ticket))->toBeTrue()
        ->and($this->stranger->can('reply', $this->ticket))->toBeFalse();
});
