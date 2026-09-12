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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MessageController extends Controller
{
    public function index(Request $request, Ticket $ticket): mixed
    {
        $this->authorize('view', $ticket);

        $messages = $ticket->messages()
            ->with(['author', 'attachments'])
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
            // Same key seen before: return the original rather than creating a second
            // message. Any file on this retry is discarded rather than stored, so a
            // retried upload never produces a second attachment or an orphaned object.
            return response()->json(
                MessageData::fromModel($existing->load(['author', 'attachments'])),
                JsonResponse::HTTP_CREATED,
            );
        }

        try {
            // A savepoint (not a bare create()) so that a unique-constraint violation
            // only rolls back this insert, not any outer transaction, and the re-fetch
            // in the catch block below can still run.
            $message = DB::transaction(fn () => $ticket->messages()->create([
                'user_id' => $request->user()->id,
                // Empty string, not null, when a file arrives with no caption. The
                // column is NOT NULL, and "no caption" versus "empty caption" is a
                // distinction without a difference here: making it nullable would
                // ripple a nullable type through three clients to express nothing.
                'body' => $request->validated('body') ?? '',
                'idempotency_key' => $idempotencyKey,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another request with the same key committed first.
            // Return its message rather than a 500, and do not broadcast a duplicate.
            // The file on this losing request, if any, is never uploaded: storage is
            // only touched below, after a message this request owns actually exists.
            $message = Message::where('ticket_id', $ticket->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return response()->json(
                MessageData::fromModel($message->load(['author', 'attachments'])),
                JsonResponse::HTTP_CREATED,
            );
        }

        if ($file = $request->file('file')) {
            $this->storeAttachment($file, $message);
        }

        MessageCreated::dispatch($message);

        return response()->json(
            MessageData::fromModel($message->load(['author', 'attachments'])),
            JsonResponse::HTTP_CREATED,
        );
    }

    private function storeAttachment(UploadedFile $file, Message $message): void
    {
        // Generated key, owing nothing to the client-supplied filename: that name is
        // attacker-controlled (a path traversal attempt waiting to happen) and is kept
        // only as display metadata below, never used to build a path.
        $diskPath = $message->id.'/'.(string) Str::uuid();

        // getMimeType() guesses from the file's real content (via finfo), the same
        // content-based check the "mimetypes" validation rule already applied. Never
        // the client's claimed Content-Type or the filename's extension.
        $mime = $file->getMimeType() ?? 'application/octet-stream';

        // Deliberately not put($diskPath, $file, ...): given a File/UploadedFile
        // instance, put() delegates to putFile(), which treats $diskPath as a
        // directory and invents its own hashed name inside it, discarding the exact
        // key generated above. Reading the contents keeps that key authoritative.
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException("Unable to read uploaded file [{$file->getRealPath()}].");
        }

        Storage::disk('attachments')->put($diskPath, $contents, [
            'ContentType' => $mime,
        ]);

        $message->attachments()->create([
            'disk_path' => $diskPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
        ]);
    }
}
