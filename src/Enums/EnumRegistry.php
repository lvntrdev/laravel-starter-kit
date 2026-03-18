<?php

namespace Lvntr\StarterKit\Enums;

use Lvntr\StarterKit\Enums\Attributes\InertiaShared;
use Lvntr\StarterKit\Enums\Contracts\HasDefinition;
use Illuminate\Support\Str;
use ReflectionEnum;
use Symfony\Component\Finder\Finder;

/**
 * Auto-discovers all enums implementing HasDefinition and provides their data.
 * Scans both package and application Enums directories.
 * New enums are picked up automatically — no manual registration needed.
 */
class EnumRegistry
{
    /** @var array<string, class-string<HasDefinition>>|null */
    private static ?array $registry = null;

    /**
     * Get all definition enums as a keyed array.
     *
     * @return array<string, array<int, array{value: string|int, label: string, severity: string}>>
     */
    public static function all(): array
    {
        return collect(static::discover())
            ->mapWithKeys(fn (string $enum, string $key) => [$key => $enum::toArray()])
            ->all();
    }

    /**
     * Get only enums marked with #[InertiaShared].
     *
     * @return array<string, array<int, array{value: string|int, label: string, severity: string}>>
     */
    public static function shared(): array
    {
        return collect(static::discover())
            ->filter(fn (string $enum) => ! empty((new ReflectionEnum($enum))->getAttributes(InertiaShared::class)))
            ->mapWithKeys(fn (string $enum, string $key) => [$key => $enum::toArray()])
            ->all();
    }

    /**
     * Get a single enum's array by its camelCase key.
     *
     * @return array<int, array{value: string|int, label: string, severity: string}>
     */
    public static function get(string $key): array
    {
        $registry = static::discover();

        return isset($registry[$key]) ? $registry[$key]::toArray() : [];
    }

    /**
     * Discover all enums that implement HasDefinition.
     * Scans both the application's app/Enums and package Enums directories.
     *
     * @return array<string, class-string<HasDefinition>>
     */
    private static function discover(): array
    {
        if (static::$registry !== null) {
            return static::$registry;
        }

        static::$registry = [];

        // Scan app/Enums directory
        $appPath = app_path('Enums');
        if (is_dir($appPath)) {
            static::scanDirectory($appPath, 'App\\Enums\\');
        }

        return static::$registry;
    }

    /**
     * Scan a directory for HasDefinition enums.
     */
    private static function scanDirectory(string $path, string $namespace): void
    {
        $files = Finder::create()->files()->name('*.php')->in($path)->notPath('Contracts');

        foreach ($files as $file) {
            $class = $namespace.$file->getFilenameWithoutExtension();

            if (! enum_exists($class)) {
                continue;
            }

            $reflection = new ReflectionEnum($class);

            if (! $reflection->implementsInterface(HasDefinition::class)) {
                continue;
            }

            $key = Str::camel($file->getFilenameWithoutExtension());
            static::$registry[$key] = $class;
        }
    }
}
