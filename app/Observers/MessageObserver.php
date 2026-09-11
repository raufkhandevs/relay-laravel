<?php

namespace App\Observers;

use App\Models\Message;

class MessageObserver
{
    public function created(Message $message): void
    {
        $message->ticket()->update(['last_message_at' => $message->created_at]);
    }
}
