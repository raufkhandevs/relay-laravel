<?php

namespace App\Data;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ParticipantData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public UserRole $role,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self($user->id, $user->name, $user->role);
    }
}
