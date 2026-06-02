<?php

use Lvntr\StarterKit\Console\Commands\InstallCommand;

/**
 * Invoke the command's private .env merge logic in isolation.
 * Pure string-in / string-out — no filesystem or app boot required.
 */
function mergeEnv(string $env, string $example): ?string
{
    $command = new InstallCommand;
    $method = new ReflectionMethod($command, 'buildMergedEnvContent');
    $method->setAccessible(true);

    return $method->invoke($command, $env, $example);
}

it('appends keys present in the example but missing from .env', function (): void {
    $env = "APP_NAME=Acme\nAPP_KEY=base64:abc\n";
    $example = "APP_NAME=\nAPP_KEY=\nCACHE_STORE=redis\nREDIS_HOST=127.0.0.1\n";

    $result = mergeEnv($env, $example);

    expect($result)
        ->toContain('CACHE_STORE=redis')
        ->toContain('REDIS_HOST=127.0.0.1')
        ->toContain('# ---- Lvntr Starter Kit ----');
});

it('never overwrites an existing key or its value', function (): void {
    $env = "APP_NAME=Acme\nDB_PASSWORD=supersecret\n";
    $example = "APP_NAME=\nDB_PASSWORD=\nCACHE_STORE=redis\n";

    $result = mergeEnv($env, $example);

    // User value preserved, the key appears exactly once.
    expect($result)->toContain('DB_PASSWORD=supersecret');
    expect(substr_count($result, 'DB_PASSWORD='))->toBe(1);
    expect($result)->not->toContain('supersecret'."\nDB_PASSWORD=");
});

it('returns null when the .env already has every example key', function (): void {
    $env = "APP_NAME=Acme\nCACHE_STORE=redis\n";
    $example = "APP_NAME=\nCACHE_STORE=file\n";

    expect(mergeEnv($env, $example))->toBeNull();
});

it('ignores comment and blank lines in the example', function (): void {
    $env = "APP_NAME=Acme\n";
    $example = "APP_NAME=\n\n# a comment\n# STARTER_KIT_DATATABLE_PER_PAGE=10\n";

    // The only non-comment key (APP_NAME) is already present → nothing to add.
    expect(mergeEnv($env, $example))->toBeNull();
});

it('copies missing lines verbatim, keeping inline defaults', function (): void {
    $env = "APP_NAME=Acme\n";
    $example = "APP_NAME=\nSESSION_DOMAIN=null\nBCRYPT_ROUNDS=12\n";

    $result = mergeEnv($env, $example);

    expect($result)
        ->toContain('SESSION_DOMAIN=null')
        ->toContain('BCRYPT_ROUNDS=12');
});
