<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — Frontend `@lvntr` Alias Integrity
|--------------------------------------------------------------------------
|
| The `@lvntr/...` Vite alias is owned by the consumer app's
| vite.config.ts. The package's runtime (ServiceProvider, helpers,
| middleware, Facade) MUST never patch, rewrite or read that file —
| doing so would break consumer apps that customised their alias targets
| and would tie a backend `composer update` to a frontend bundler change.
|
| Strategy:
|   - Read the ServiceProvider source and assert it never references
|     vite.config files at all.
|   - Read the package's runtime helper file and the Bootstrap shim and
|     assert the same.
|   - The Install/Update artisan commands are allowed to publish stub
|     vite.config copies on FRESH installs only; that's a separate
|     scaffolding concern and is exercised by the install-flow tests
|     (out of scope for this regression suite).
|
*/

it('keeps the StarterKitServiceProvider free of vite.config touch points', function (): void {
    $providerPath = dirname(__DIR__, 3).'/src/StarterKitServiceProvider.php';
    expect(is_file($providerPath))->toBeTrue("ServiceProvider missing at {$providerPath}");

    $source = file_get_contents($providerPath);
    expect($source)->toBeString();

    expect($source)->not->toContain(
        'vite.config',
        'StarterKitServiceProvider must not read or write vite.config* files at runtime — that would couple the backend boot path to the consumer Vite setup.'
    );
});

it('keeps sk-helpers.php free of vite.config touch points', function (): void {
    $helpersPath = dirname(__DIR__, 3).'/src/sk-helpers.php';
    expect(is_file($helpersPath))->toBeTrue();

    $source = file_get_contents($helpersPath);

    expect($source)->not->toContain(
        'vite.config',
        'Vendor helpers must not touch the consumer vite.config file.'
    );
});

it('keeps the Bootstrap shim free of vite.config touch points', function (): void {
    $bootstrapPath = dirname(__DIR__, 3).'/src/Bootstrap.php';

    if (! is_file($bootstrapPath)) {
        // Bootstrap.php is optional in some package layouts — skip the
        // assertion if it's been removed; the ServiceProvider check above
        // already covers the canonical runtime entry point.
        expect(true)->toBeTrue();

        return;
    }

    $source = file_get_contents($bootstrapPath);
    expect($source)->not->toContain('vite.config');
});

it('does not register a vite.config publishable tag', function (): void {
    // `vendor:publish` taglar; if the package ever wires a vite.config
    // publish tag, that becomes an opt-in (not silent) override surface.
    // Even so, no current tag should target the consumer Vite config.
    $providerSource = file_get_contents(dirname(__DIR__, 3).'/src/StarterKitServiceProvider.php');

    // Capture every `publishes(...)` array literal and assert none of
    // them mention vite.config as a destination.
    expect($providerSource)
        ->not->toContain("=> base_path('vite.config")
        ->not->toContain('=> base_path("vite.config');
});

it('does not declare a runtime container binding that touches vite.config', function (): void {
    // Pure source-text guard — defence-in-depth against a future PR that
    // sneaks a Vite-config-modifying service into the container.
    $providerSource = file_get_contents(dirname(__DIR__, 3).'/src/StarterKitServiceProvider.php');

    // Allow the word `vite` only when it appears inside documentation
    // comments. Reject any code-level reference.
    $linesOfCode = collect(explode("\n", $providerSource))
        ->reject(fn (string $line): bool => trim($line) === '' || str_starts_with(trim($line), '//') || str_starts_with(trim($line), '*') || str_starts_with(trim($line), '/*'))
        ->values()
        ->all();

    foreach ($linesOfCode as $line) {
        expect(stripos($line, 'vite.config'))->toBeFalse(
            "Code line in StarterKitServiceProvider references vite.config:\n  {$line}"
        );
    }
});
