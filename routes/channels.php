<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('ticket.{ticketId}', function (User $user, string $ticketId) {
    if (! ctype_digit($ticketId)) {
        return false;
    }

    $ticket = Ticket::find($ticketId);

    return $ticket && $user->can('view', $ticket);
});
