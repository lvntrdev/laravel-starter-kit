<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — ApiResponse alias is deterministic
|--------------------------------------------------------------------------
|
| Regression guard for the recurring post-install TypeError:
|
|   "App\Http\Controllers\...::updateAppearance(): Return value must be of
|    type App\Http\Responses\ApiResponse, Lvntr\StarterKit\Http\Responses\
|    ApiResponse returned"
|
| Root cause: the App\Http\Responses\ApiResponse → vendor mapping used to be
| split between (a) this ServiceProvider and (b) a shipped class_alias-only
| stub at app/Http/Responses/ApiResponse.php. The provider SKIPPED the alias
| whenever that file existed (the file_exists override-guard), deferring to
| the stub. But a class_alias-only file declares no class, so it is absent
| from Composer's optimized classmap → its load (and therefore the alias's
| existence and timing) depends on PSR-4 fallback + opcache state. Right
| after install (stale autoloader/opcache, or a leftover `extends` stub from
| a prior version) the alias was not the vendor identity at the moment a
| `: App\Http\Responses\ApiResponse` return type was checked → TypeError.
|
| Fix: ApiResponse has NO valid consumer override (a real App\ subclass
| breaks the return-type covariance of DatatableQueryBuilder::response(),
| which returns the vendor type — that is precisely why it is an alias, not
| an extension point). It must therefore be aliased UNCONDITIONALLY, on every
| boot, regardless of any file at the consumer path. These tests pin that
| contract on the provider's pure alias planner.
|
*/

use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Lvntr\StarterKit\StarterKitServiceProvider;

/**
 * Expose the provider's protected, side-effect-free alias planner.
 */
function planBackwardCompatAliases(string $basePath): array
{
    $provider = new class(app()) extends StarterKitServiceProvider
    {
        public function plan(string $base): array
        {
            return $this->backwardCompatAliasPlan($base);
        }
    };

    return $provider->plan($basePath);
}

it('always plans the ApiResponse alias even when a file exists at the consumer path', function (): void {
    // Simulate a consumer base path where BOTH an ApiResponse file (e.g. a
    // leftover stub) AND a BaseAction override file exist.
    $base = sys_get_temp_dir().'/sk-alias-'.uniqid();
    @mkdir($base.'/app/Http/Responses', 0777, true);
    @mkdir($base.'/app/Domain/Shared/Actions', 0777, true);
    file_put_contents($base.'/app/Http/Responses/ApiResponse.php', "<?php\n");
    file_put_contents($base.'/app/Domain/Shared/Actions/BaseAction.php', "<?php\n");

    try {
        $plan = planBackwardCompatAliases($base);

        // ApiResponse is UNCONDITIONAL → present despite the file (the fix).
        expect($plan)->toHaveKey('App\Http\Responses\ApiResponse')
            ->and($plan['App\Http\Responses\ApiResponse'])->toBe(ApiResponse::class);

        // BaseAction IS overridable → a consumer file defers it (override wins).
        expect($plan)->not->toHaveKey('App\Domain\Shared\Actions\BaseAction');
    } finally {
        @unlink($base.'/app/Http/Responses/ApiResponse.php');
        @unlink($base.'/app/Domain/Shared/Actions/BaseAction.php');
        @rmdir($base.'/app/Http/Responses');
        @rmdir($base.'/app/Http');
        @rmdir($base.'/app/Domain/Shared/Actions');
        @rmdir($base.'/app/Domain/Shared');
        @rmdir($base.'/app/Domain');
        @rmdir($base.'/app');
        @rmdir($base);
    }
});

it('plans both unconditional and overridable aliases when the consumer ships no files', function (): void {
    $plan = planBackwardCompatAliases('/no/such/consumer/base/path');

    expect($plan)->toHaveKey('App\Http\Responses\ApiResponse')
        ->and($plan)->toHaveKey('App\Domain\Shared\Actions\BaseAction');
});

it('resolves App\Http\Responses\ApiResponse to the vendor class after the provider boots', function (): void {
    // The TestCase already booted StarterKitServiceProvider, whose register()
    // aliases ApiResponse unconditionally. Testbench ships no stub file at
    // app/Http/Responses/ApiResponse.php, so this proves the alias is actually
    // created at runtime by the provider (not by a class_alias-only stub) — i.e.
    // a controller/query typed `: App\Http\Responses\ApiResponse` that returns a
    // vendor instance (to_api(), DatatableQueryBuilder::response()) passes its
    // return-type check. This is the end-to-end guard for the reported TypeError.
    expect(class_exists('App\Http\Responses\ApiResponse'))->toBeTrue();

    expect((new ReflectionClass('App\Http\Responses\ApiResponse'))->getName())
        ->toBe(ApiResponse::class);

    // The semantics that were failing: a vendor instance satisfies the App\ type.
    expect(ApiResponse::success(['ok' => true]))
        ->toBeInstanceOf('App\Http\Responses\ApiResponse');
});
