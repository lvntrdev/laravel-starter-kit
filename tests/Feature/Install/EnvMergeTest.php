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

/*
| First-install-only keys.
|
| The merge path runs on a RE-install; ensureEnvFile() copies .env.example
| wholesale on a first install and never reaches here. So a key skipped below
| lands in a brand-new project and in no existing one — which is the whole
| mechanism behind "a fresh app is fail-closed, an upgraded app is untouched".
*/

it('never merges a first-install-only key into an existing .env', function (): void {
    $env = "APP_NAME=Acme\n";
    $example = "APP_NAME=\nSTARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false\n";

    // The only other example key is already present, so a leaked key would be
    // the sole reason this returns non-null.
    expect(mergeEnv($env, $example))->toBeNull();
});

it('does not let a first-install-only key drag other missing keys along', function (): void {
    $env = "APP_NAME=Acme\n";
    $example = "APP_NAME=\nSTARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false\nCACHE_STORE=redis\n";

    $result = mergeEnv($env, $example);

    expect($result)
        ->toContain('CACHE_STORE=redis')
        ->not->toContain('STARTER_KIT_ALLOW_UNRESOLVED_ROUTES');
});

it('leaves an operator-set value for a first-install-only key alone', function (): void {
    $env = "APP_NAME=Acme\nSTARTER_KIT_ALLOW_UNRESOLVED_ROUTES=true\n";
    $example = "APP_NAME=\nSTARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false\n";

    // Skipping happens before the "is it missing?" test, so an app that
    // deliberately opted back out keeps its own value either way.
    expect(mergeEnv($env, $example))->toBeNull();
});

it('ships the fresh-install default as false in the example env', function (): void {
    $example = file_get_contents(dirname(__DIR__, 3).'/stubs/.env.example');

    // A fresh install copies this file verbatim, so the line here IS the
    // fresh-install default. Commenting it out would silently undo the feature.
    expect($example)->toContain("\nSTARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false");
});

it('copies missing lines verbatim, keeping inline defaults', function (): void {
    $env = "APP_NAME=Acme\n";
    $example = "APP_NAME=\nSESSION_DOMAIN=null\nBCRYPT_ROUNDS=12\n";

    $result = mergeEnv($env, $example);

    expect($result)
        ->toContain('SESSION_DOMAIN=null')
        ->toContain('BCRYPT_ROUNDS=12');
});
