<?php

namespace App\Domain\FileManager\DTOs;

use App\Domain\FileManager\Support\ContextRegistry;
use App\Domain\Shared\DTOs\BaseDTO;
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

        if (! is_string($context) || $context === '') {
            throw new InvalidArgumentException('FileManager context key is required.');
        }

        /** @var ContextRegistry $registry */
        $registry = app(ContextRegistry::class);
        $definition = $registry->get($context);

        if ($definition->requiresId() && ! is_string($contextId)) {
            throw new InvalidArgumentException("FileManager context '{$context}' requires a context_id.");
        }

        $owner = $definition->resolveOwner($contextId);

        return new self(
            context: $context,
            contextId: $contextId,
            owner: $owner,
            // Matches what Spatie MediaLibrary (and any morph-aware query)
            // stores as `model_type`: the morph alias when the model is in
            // Laravel's morph map, otherwise the fully-qualified class name.
            ownerType: $owner->getMorphClass(),
            ownerId: (string) $owner->getKey(),
        );
    }

    public static function forUser(string $userId): self
    {
        return self::fromArray(['context' => 'user', 'context_id' => $userId]);
    }

    public static function forGlobal(): self
    {
        return self::fromArray(['context' => 'global']);
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
