<?php

namespace App\Data;

use App\Models\Attachment;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttachmentData extends Data
{
    public function __construct(
        public int $id,
        public string $original_name,
        public string $mime,
        public int $size_bytes,
        public CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Attachment $attachment): self
    {
        return new self(
            $attachment->id,
            $attachment->original_name,
            $attachment->mime,
            $attachment->size_bytes,
            $attachment->created_at->toImmutable(),
        );
    }
}
