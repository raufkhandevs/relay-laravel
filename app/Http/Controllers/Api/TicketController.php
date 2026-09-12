<?php

namespace App\Http\Controllers\Api;

use App\Data\TicketData;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): mixed
    {
        $tickets = Ticket::query()
            ->with(['customer', 'assignedAgent'])
            ->when(
                $request->user()->role !== UserRole::Agent,
                fn ($query) => $query->where('customer_id', $request->user()->id),
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return TicketData::collect($tickets);
    }

    public function show(Request $request, Ticket $ticket): TicketData
    {
        $this->authorize('view', $ticket);

        return TicketData::fromModel($ticket->load(['customer', 'assignedAgent']));
    }
}
