<?php

namespace App\Http\Controllers\Api;

use App\Data\MessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Ticket $ticket): mixed
    {
        $this->authorize('view', $ticket);

        $messages = $ticket->messages()
            ->with('author')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return MessageData::collect($messages);
    }

    public function store(StoreMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reply', $ticket);

        $existing = Message::where('idempotency_key', $request->validated('idempotency_key'))->first();

        if ($existing) {
            return response()->json(
                MessageData::fromModel($existing->load('author')),
                JsonResponse::HTTP_CREATED,
            );
        }

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'idempotency_key' => $request->validated('idempotency_key'),
        ]);

        return response()->json(
            MessageData::fromModel($message->load('author')),
            JsonResponse::HTTP_CREATED,
        );
    }
}
