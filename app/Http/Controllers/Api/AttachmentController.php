<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function show(Attachment $attachment): RedirectResponse
    {
        // An attachment is readable exactly when its ticket is. TicketPolicy is the
        // only place that rule is expressed; this reuses it rather than writing a
        // second one, the same way the websocket channel and the Electron IPC proxy do.
        $this->authorize('view', $attachment->message->ticket);

        $url = Storage::disk('attachments')->temporaryUrl(
            $attachment->disk_path,
            now()->addMinutes(5),
        );

        return redirect()->away($url);
    }
}
