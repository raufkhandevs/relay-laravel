<?php

namespace App\Http\Controllers;

use App\Data\MessageData;
use App\Data\TicketData;
use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketWebController extends Controller
{
    public function index(Request $request): Response
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

        return Inertia::render('tickets/index', [
            'tickets' => TicketData::collect($tickets),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load(['customer', 'assignedAgent']);

        return Inertia::render('tickets/show', [
            'ticket' => TicketData::fromModel($ticket),
            'messages' => MessageData::collect(
                $ticket->messages()->with('author')->orderBy('id')->get()
            ),
        ]);
    }
}
