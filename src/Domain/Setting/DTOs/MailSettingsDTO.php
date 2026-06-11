<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for mail settings.
 */
readonly class MailSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $mailer,
        public ?string $host,
        public ?string $port,
        public ?string $username,
        public ?string $password,
        public ?string $encryption,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyTo,
        public ?string $sendLimit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $sendLimit = $data['send_limit'] ?? null;

        return new static(
            mailer: $data['mailer'],
            host: $data['host'] ?? null,
            port: $data['port'] !== null ? (string) $data['port'] : null,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            encryption: $data['encryption'] ?? null,
            fromAddress: $data['from_address'],
            fromName: $data['from_name'],
            replyTo: ($data['reply_to'] ?? null) ?: null,
            sendLimit: ($sendLimit === null || $sendLimit === '') ? null : (string) $sendLimit,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        $data = [
            'mailer' => $this->mailer,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encryption' => $this->encryption,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'reply_to' => $this->replyTo,
            'send_limit' => $this->sendLimit,
        ];

        // Omit password when blank so the existing stored value is preserved.
        if ($this->password !== null && $this->password !== '') {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
