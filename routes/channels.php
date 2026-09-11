<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('ticket.{ticket}', function (User $user, Ticket $ticket) {
    return $user->can('view', $ticket);
});
