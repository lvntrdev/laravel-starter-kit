<?php

namespace App\Domain\SampleContent\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for SampleContent.
 * Translatable fields (title, description, content) are carried as locale-keyed arrays.
 */
final readonly class SampleContentDTO extends BaseDTO
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $description
     * @param  array<string, string>  $content
     */
    public function __construct(
        public array $title,
        public array $description,
        public array $content,
        public string $user_id,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            title: $data['title'] ?? [],
            description: $data['description'] ?? [],
            content: $data['content'] ?? [],
            user_id: $data['user_id'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'user_id' => $this->user_id,
        ];
    }
}
