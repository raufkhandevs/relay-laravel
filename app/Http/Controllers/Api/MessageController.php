<?php

namespace App\Http\Controllers\Api;

use App\Data\MessageData;
use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $idempotencyKey = $request->validated('idempotency_key');

        $existing = Message::where('ticket_id', $ticket->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return response()->json(
                MessageData::fromModel($existing->load('author')),
                JsonResponse::HTTP_CREATED,
            );
        }

        try {
            // A savepoint (not a bare create()) so that a unique-constraint violation
            // only rolls back this insert, not any outer transaction, and the re-fetch
            // in the catch block below can still run.
            $message = DB::transaction(fn () => $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $request->validated('body'),
                'idempotency_key' => $idempotencyKey,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another request with the same key committed first.
            // Return its message rather than a 500, and do not broadcast a duplicate.
            $message = Message::where('ticket_id', $ticket->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return response()->json(
                MessageData::fromModel($message->load('author')),
                JsonResponse::HTTP_CREATED,
            );
        }

        MessageCreated::dispatch($message);

        return response()->json(
            MessageData::fromModel($message->load('author')),
            JsonResponse::HTTP_CREATED,
        );
    }
}
