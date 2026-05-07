<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor;

/**
 * Her check'in sonucunu taşıyan value object.
 * Immutable: constructor sonrası değiştirilemez.
 */
final readonly class DoctorReport
{
    public function __construct(
        public string $name,
        public DoctorStatus $status,
        public string $message,
        public string $hint = '',
    ) {}

    public function isOk(): bool
    {
        return $this->status === DoctorStatus::Ok;
    }

    public function isWarn(): bool
    {
        return $this->status === DoctorStatus::Warn;
    }

    public function isFail(): bool
    {
        return $this->status === DoctorStatus::Fail;
    }

    /** JSON çıktısı için dizi temsiline dönüştür. */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'message' => $this->message,
            'hint' => $this->hint,
        ];
    }

    /** Shorthand factory metodlar */
    public static function ok(string $name, string $message): self
    {
        return new self($name, DoctorStatus::Ok, $message);
    }

    public static function warn(string $name, string $message, string $hint = ''): self
    {
        return new self($name, DoctorStatus::Warn, $message, $hint);
    }

    public static function fail(string $name, string $message, string $hint = ''): self
    {
        return new self($name, DoctorStatus::Fail, $message, $hint);
    }
}
