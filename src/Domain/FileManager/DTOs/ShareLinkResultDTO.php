<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\DTOs;

use Carbon\Carbon;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * CreateShareLinkAction'ın döndürdüğü sonuç.
 */
readonly class ShareLinkResultDTO extends BaseDTO
{
    public function __construct(
        /** Tam imzalı URL */
        public string $url,
        /** Linkin geçerlilik bitiş zamanı */
        public Carbon $expiresAt,
        /** Signature parametresinin SHA256 hash'i (revocation için) */
        public string $tokenHash,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            url: $data['url'],
            expiresAt: $data['expires_at'] instanceof Carbon
                ? $data['expires_at']
                : Carbon::parse($data['expires_at']),
            tokenHash: $data['token_hash'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'token_hash' => $this->tokenHash,
        ];
    }
}
