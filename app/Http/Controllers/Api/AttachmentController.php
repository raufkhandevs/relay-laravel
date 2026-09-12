<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /** How long a signed download link stays valid. Long enough to click, short enough that a leaked one is worthless. */
    private const LINK_LIFETIME_MINUTES = 5;

    public function show(Request $request, Attachment $attachment): RedirectResponse|JsonResponse
    {
        // An attachment is readable exactly when its ticket is. TicketPolicy is the
        // only place that rule is expressed; this reuses it rather than writing a
        // second one, the same way the websocket channel and the Electron IPC proxy do.
        $this->authorize('view', $attachment->message->ticket);

        $url = Storage::disk('attachments')->temporaryUrl(
            $attachment->disk_path,
            now()->addMinutes(self::LINK_LIFETIME_MINUTES),
        );

        // A browser wants to be sent to the file, so it gets a redirect. A native
        // client wants the address, because React Native's fetch has no
        // `redirect: 'manual'` and following the redirect to read its destination
        // means downloading the whole file just to learn a URL.
        if ($request->wantsJson()) {
            return response()->json(['url' => $url]);
        }

        return redirect()->away($url);
    }
}
