<?php

namespace App\Data;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketData extends Data
{
    public function __construct(
        public int $id,
        public string $subject,
        public TicketStatus $status,
        public UserData $customer,
        public ?UserData $assigned_agent,
        public ?CarbonImmutable $last_message_at,
        public CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Ticket $ticket): self
    {
        return new self(
            $ticket->id,
            $ticket->subject,
            $ticket->status,
            UserData::fromModel($ticket->customer),
            $ticket->assignedAgent ? UserData::fromModel($ticket->assignedAgent) : null,
            $ticket->last_message_at?->toImmutable(),
            $ticket->created_at->toImmutable(),
        );
    }
}
