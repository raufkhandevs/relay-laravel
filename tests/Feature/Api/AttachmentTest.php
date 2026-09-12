<?php

use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Illuminate\Http\Testing\File (what UploadedFile::fake() returns) reports a MIME
// type guessed from the filename's extension, not its content, which would make
// "judged by content" tests pass for the wrong reason. A plain Illuminate\Http\
// UploadedFile pointed at a real temp file has no such override: getMimeType()
// runs Symfony's real, content-based guesser, exactly like a genuine upload.
function realUploadedFile(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attachment-test-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

it('lets a ticket participant upload a file with their message', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $file = UploadedFile::fake()->createWithContent('diagnostic.txt', 'plain text content');

    $response = $this->actingAs($customer, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'Here is the log.',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ]);

    $response->assertCreated();
    expect($response->json('attachments'))->toHaveCount(1);

    $attachment = $response->json('attachments.0');
    expect($attachment['original_name'])->toBe('diagnostic.txt')
        ->and($attachment['mime'])->toBe('text/plain')
        ->and(Attachment::where('message_id', $response->json('id'))->count())->toBe(1);

    // The stored key is exactly "{message_id}/{uuid}", not a directory that some
    // other invented name got dropped into.
    $stored = Attachment::where('message_id', $response->json('id'))->firstOrFail();
    expect($stored->disk_path)->toMatch('/^'.$response->json('id').'\/[0-9a-f-]{36}$/')
        ->and(Storage::disk('attachments')->exists($stored->disk_path))->toBeTrue();
});

it('refuses a stranger uploading a file to a ticket they cannot see', function () {
    Storage::fake('attachments');

    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->create();
    $file = UploadedFile::fake()->createWithContent('note.txt', 'let me in');

    $this->actingAs($stranger, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'let me in',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ])
        ->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

it('refuses a stranger downloading an attachment on a ticket they cannot see', function () {
    $owner = User::factory()->create(['role' => UserRole::Customer]);
    $stranger = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($owner, 'customer')->create();
    $message = Message::factory()->for($ticket)->for($owner, 'author')->create();
    $attachment = Attachment::factory()->for($message)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('lets the ticket owner download their attachment through a presigned redirect', function () {
    Storage::fake('attachments');
    Storage::disk('attachments')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration, array $options) => "https://minio.test/{$path}?signed=1",
    );

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $message = Message::factory()->for($ticket)->for($customer, 'author')->create();
    $attachment = Attachment::factory()->for($message)->create(['disk_path' => 'the-key']);

    $this->actingAs($customer, 'sanctum')
        ->get("/api/attachments/{$attachment->id}")
        ->assertRedirect('https://minio.test/the-key?signed=1');
});

it('refuses a file over the size limit', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $file = UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf');

    $this->actingAs($customer, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'too big',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ])
        ->assertStatus(422);

    expect(Attachment::count())->toBe(0);
});

it('refuses a disallowed file type judged from its real content', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    // A tiny, valid GIF, wearing a .jpg extension (jpeg is on the allow list): the
    // mimetypes rule inspects the bytes, not the name, so this must still be refused.
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    $file = realUploadedFile('sneaky.jpg', $gif);

    $this->actingAs($customer, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'a gif',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ])
        ->assertStatus(422);

    expect(Attachment::count())->toBe(0);
});

it('judges file type from real content, not a renamed extension', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    // A plain text file wearing a .png extension: the mimetypes rule inspects the
    // bytes, not the name, so this must be accepted and recorded as text/plain.
    $file = realUploadedFile('renamed.png', 'This is genuinely plain text, not a PNG.');

    $response = $this->actingAs($customer, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'sneaky extension',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ]);

    $response->assertCreated();

    $attachment = Attachment::where('message_id', $response->json('id'))->firstOrFail();
    expect($attachment->mime)->toBe('text/plain')
        ->and($attachment->original_name)->toBe('renamed.png');
});

it('produces one attachment, not two, for a retried idempotency key', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $key = (string) Str::uuid();

    foreach ([1, 2] as $attempt) {
        $file = UploadedFile::fake()->createWithContent('retry.txt', 'same file each time');

        $this->actingAs($customer, 'sanctum')
            ->post("/api/tickets/{$ticket->id}/messages", [
                'body' => 'Once',
                'idempotency_key' => $key,
                'file' => $file,
            ])
            ->assertCreated();
    }

    expect(Attachment::count())->toBe(1);
});

it('does not let the filename influence the stored object key', function () {
    Storage::fake('attachments');

    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $ticket = Ticket::factory()->for($customer, 'customer')->create();
    $file = realUploadedFile('../../etc/passwd', 'not actually /etc/passwd');

    $response = $this->actingAs($customer, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'traversal attempt',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $file,
        ]);

    $response->assertCreated();

    $attachment = Attachment::where('message_id', $response->json('id'))->firstOrFail();
    // Symfony's UploadedFile already strips directory components from the reported
    // original name (getClientOriginalName() basenames it), so "../../etc/passwd"
    // surfaces as "passwd" here; either way, the disk key never derives from it. Pinned
    // to the exact "{message_id}/{uuid}" shape, not just an absence of bad substrings,
    // so a key that silently gains an extra path segment cannot pass unnoticed.
    expect($attachment->disk_path)->toMatch('/^\d+\/[0-9a-f-]{36}$/')
        ->and($attachment->original_name)->toBe('passwd');
});
