<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — cached alias manifest
|--------------------------------------------------------------------------
|
| resolveBackwardCompatAliases() caches backwardCompatAliasPlan() to a
| bootstrap/cache manifest so the per-request override-existence scan (one
| file_exists() per overridable alias) runs once instead of on every boot.
|
| These tests pin the three-layer invalidation contract:
|   1. dev/test skip           — no manifest is ever written in local/testing
|   2. mtime self-invalidation — a manifest older than a signal is recomputed
|   3. explicit flush          — flushBackwardCompatAliasCache() drops the file
|
| plus the steady-state reuse (a fresh manifest is included, not recomputed).
|
*/

use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Lvntr\StarterKit\StarterKitServiceProvider;

/**
 * A provider subclass that exposes the protected cache surface and lets a test
 * force cache-enabled, a temp manifest path and a controlled invalidation
 * signal — so the caching behaviour is exercised without an APP_ENV switch.
 */
function aliasCacheProvider(string $manifestPath, string $signalPath, bool $enabled = true): StarterKitServiceProvider
{
    return new class(app(), $manifestPath, $signalPath, $enabled) extends StarterKitServiceProvider
    {
        public function __construct(
            $app,
            private string $manifest,
            private string $signal,
            private bool $enabled,
        ) {
            parent::__construct($app);
        }

        protected function backwardCompatAliasCacheEnabled(): bool
        {
            return $this->enabled;
        }

        protected function aliasManifestPath(): string
        {
            return $this->manifest;
        }

        protected function aliasInvalidationSignals(): array
        {
            return [$this->signal];
        }

        public function resolve(string $base): array
        {
            return $this->resolveBackwardCompatAliases($base);
        }

        public function plan(string $base): array
        {
            return $this->backwardCompatAliasPlan($base);
        }

        public function cacheEnabled(): bool
        {
            return $this->backwardCompatAliasCacheEnabled();
        }
    };
}

/** Fresh unique temp paths for a manifest + its invalidation signal. */
function aliasCachePaths(): array
{
    $dir = sys_get_temp_dir().'/sk-alias-cache-'.uniqid();
    @mkdir($dir, 0777, true);

    $signal = $dir.'/signal';
    file_put_contents($signal, 'x');
    touch($signal, time() - 100); // signal is OLD ⇒ manifest built now is fresh

    return [$dir, $dir.'/manifest.php', $signal];
}

function cleanupAliasCacheDir(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
}

it('does not write a manifest when caching is disabled (local/testing default)', function (): void {
    [$dir, $manifest, $signal] = aliasCachePaths();

    try {
        // Default provider in the testing env: cache disabled.
        $provider = aliasCacheProvider($manifest, $signal, enabled: false);

        expect($provider->cacheEnabled())->toBeFalse();

        $plan = $provider->resolve('/no/such/base');

        expect($plan)->toBe($provider->plan('/no/such/base'))
            ->and(file_exists($manifest))->toBeFalse();
    } finally {
        cleanupAliasCacheDir($dir);
    }
});

it('writes the manifest on first resolve and reuses it on the next', function (): void {
    [$dir, $manifest, $signal] = aliasCachePaths();

    try {
        $provider = aliasCacheProvider($manifest, $signal);

        $plan = $provider->resolve('/no/such/base');

        // First resolve computed + persisted the plan.
        expect(file_exists($manifest))->toBeTrue()
            ->and($plan)->toBe($provider->plan('/no/such/base'))
            ->and($plan)->toHaveKey('App\Http\Responses\ApiResponse')
            ->and($plan['App\Http\Responses\ApiResponse'])->toBe(ApiResponse::class);

        // Overwrite the (still-fresh) manifest with a sentinel: a second resolve
        // that returns the sentinel proves the cached file is read, not recomputed.
        file_put_contents($manifest, "<?php return ['App\\\\Sentinel' => 'Vendor\\\\Sentinel'];\n");
        touch($manifest, time()); // keep it newer than the OLD signal ⇒ fresh

        expect($provider->resolve('/no/such/base'))
            ->toBe(['App\Sentinel' => 'Vendor\Sentinel']);
    } finally {
        cleanupAliasCacheDir($dir);
    }
});

it('recomputes when an invalidation signal is newer than the manifest', function (): void {
    [$dir, $manifest, $signal] = aliasCachePaths();

    try {
        $provider = aliasCacheProvider($manifest, $signal);

        // Seed a sentinel manifest, then make the signal NEWER than it ⇒ stale.
        file_put_contents($manifest, "<?php return ['App\\\\Sentinel' => 'Vendor\\\\Sentinel'];\n");
        touch($manifest, time() - 100);
        touch($signal, time()); // signal now newer ⇒ manifest stale

        $plan = $provider->resolve('/no/such/base');

        // Stale ⇒ the sentinel is ignored and the real plan is recomputed
        // (and re-written over the sentinel).
        expect($plan)->not->toHaveKey('App\Sentinel')
            ->and($plan)->toBe($provider->plan('/no/such/base'))
            ->and($plan)->toHaveKey('App\Http\Responses\ApiResponse');
    } finally {
        cleanupAliasCacheDir($dir);
    }
});

it('respects a consumer override through the cached plan', function (): void {
    [$dir, $manifest, $signal] = aliasCachePaths();

    // A consumer base where BaseAction is overridden but ApiResponse is not.
    $base = sys_get_temp_dir().'/sk-alias-base-'.uniqid();
    @mkdir($base.'/app/Domain/Shared/Actions', 0777, true);
    file_put_contents($base.'/app/Domain/Shared/Actions/BaseAction.php', "<?php\n");

    try {
        $plan = aliasCacheProvider($manifest, $signal)->resolve($base);

        // Reloading the written manifest yields the same override-aware plan.
        $cached = include $manifest;

        expect($cached)->toBe($plan)
            ->and($cached)->toHaveKey('App\Http\Responses\ApiResponse')
            ->and($cached)->not->toHaveKey('App\Domain\Shared\Actions\BaseAction');
    } finally {
        cleanupAliasCacheDir($dir);
        @unlink($base.'/app/Domain/Shared/Actions/BaseAction.php');
        @rmdir($base.'/app/Domain/Shared/Actions');
        @rmdir($base.'/app/Domain/Shared');
        @rmdir($base.'/app/Domain');
        @rmdir($base.'/app');
        @rmdir($base);
    }
});

it('flushes the real bootstrap/cache manifest', function (): void {
    // Mirrors StarterKitServiceProvider::ALIAS_MANIFEST_RELATIVE (private const).
    $path = app()->bootstrapPath('cache/starter-kit-aliases.php');
    $dir = dirname($path);

    if (! is_dir($dir) || ! is_writable($dir)) {
        expect(true)->toBeTrue(); // environment without a writable bootstrap/cache — skip

        return;
    }

    file_put_contents($path, "<?php return [];\n");
    expect(file_exists($path))->toBeTrue();

    StarterKitServiceProvider::flushBackwardCompatAliasCache();

    expect(file_exists($path))->toBeFalse();
});
