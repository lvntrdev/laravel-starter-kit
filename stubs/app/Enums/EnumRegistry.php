<?php

namespace App\Enums;

use App\Enums\Attributes\InertiaShared;
use App\Enums\Contracts\HasDefinition;
use Illuminate\Support\Str;
use ReflectionEnum;
use Symfony\Component\Finder\Finder;

/**
 * Auto-discovers all enums implementing HasDefinition and provides their data.
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
     * Discover all enums in app/Enums that implement HasDefinition.
     *
     * @return array<string, class-string<HasDefinition>>
     */
    private static function discover(): array
    {
        if (static::$registry !== null) {
            return static::$registry;
        }

        static::$registry = [];

        $path = app_path('Enums');

        if (! is_dir($path)) {
            return static::$registry;
        }

        $files = Finder::create()->files()->name('*.php')->in($path)->notPath('Contracts');

        foreach ($files as $file) {
            $class = 'App\\Enums\\'.$file->getFilenameWithoutExtension();

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

        return static::$registry;
    }
}
