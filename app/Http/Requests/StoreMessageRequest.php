<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Accepted attachment types, decided centrally so the three clients do not each
     * invent their own list. HEIC is included because it is what an iPhone produces.
     */
    public const ALLOWED_ATTACHMENT_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'application/pdf',
        'text/plain',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A photo often needs no caption, so a message is valid with a body, a
            // file, or both, and invalid with neither. `required_without` rather than
            // `required` because three clients were each inventing their own way round
            // the old rule: one fabricated the caption "Sent an attachment.", another
            // disabled its send button until the user typed something.
            //
            // `nullable` matters as much as the rule. Laravel's ConvertEmptyStringsToNull
            // middleware turns an empty body into null before validation runs, so
            // without it a client sending an empty string still fails.
            'body' => ['nullable', 'required_without:file', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            // "mimetypes" (not "mimes") inspects the file's actual content rather than
            // trusting the client's claimed type or its extension. One attachment per
            // message, 10 MB max: limits decided centrally, enforced here regardless of
            // whatever a client's own file picker allows through.
            'file' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:'.implode(',', self::ALLOWED_ATTACHMENT_MIMES),
            ],
        ];
    }
}
