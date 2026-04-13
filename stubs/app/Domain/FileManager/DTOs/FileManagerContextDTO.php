<?php

namespace App\Domain\FileManager\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;
use App\Models\GlobalFileBucket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolved FileManager context — carries the owner model and ownership identity
 * used to scope folder trees, media queries, and PathGenerator output.
 */
readonly class FileManagerContextDTO extends BaseDTO
{
    public function __construct(
        public string $context,
        public ?string $contextId,
        public Model $owner,
        public string $ownerType,
        public string $ownerId,
    ) {}

    /**
     * @param  array{context: string, context_id?: ?string}  $data
     */
    public static function fromArray(array $data): static
    {
        $context = $data['context'] ?? null;
        $contextId = $data['context_id'] ?? null;

        return match ($context) {
            'user' => self::forUser((string) $contextId),
            'global' => self::forGlobal(),
            default => throw new InvalidArgumentException("Unsupported FileManager context: {$context}"),
        };
    }

    public static function forUser(string $userId): self
    {
        $user = User::query()->findOrFail($userId);

        return new self(
            context: 'user',
            contextId: $user->id,
            owner: $user,
            ownerType: User::class,
            ownerId: (string) $user->id,
        );
    }

    public static function forGlobal(): self
    {
        $bucket = GlobalFileBucket::singleton();

        return new self(
            context: 'global',
            contextId: null,
            owner: $bucket,
            ownerType: GlobalFileBucket::class,
            ownerId: (string) $bucket->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'context_id' => $this->contextId,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
        ];
    }
}
