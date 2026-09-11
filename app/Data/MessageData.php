<?php

namespace App\Data;

use App\Models\Message;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class MessageData extends Data
{
    public function __construct(
        public int $id,
        public int $ticket_id,
        public string $body,
        public UserData $author,
        public CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Message $message): self
    {
        return new self(
            $message->id,
            $message->ticket_id,
            $message->body,
            UserData::fromModel($message->author),
            $message->created_at->toImmutable(),
        );
    }
}
