<?php

/*
|--------------------------------------------------------------------------
| sk:eject — EjectCommand Feature Tests
|--------------------------------------------------------------------------
|
| All tests use --destination to write into a temp directory so the real
| consumer app/  is never touched.  The temp tree is torn down after each
| test via afterEach().
|
| Covered scenarios:
|
|  1. --dry-run  lists plan but writes nothing
|  2. Backend ns-rewrite: eject namespace → App\, Shared reference preserved
|  3. DomainServiceProvider: 3 bindings injected for User (event domain)
|  4. Event binding idempotency: second eject does not add duplicate lines
|  5. --no-vue  does not touch Vue directory
|  6. Event/vue-less domain (Session) leaves provider and Vue untouched
|  7. Alias skip: after eject, backwardCompatAliasPlan() omits the User alias
|  8. Unknown domain returns failure + shows valid list
|  9. Existing app/Domain without --force exits early (idempotency guard)
| 10. Manifest backend paths all resolve to real directories (integrity check)
| 11. Vue guard: existing customized page preserved without --force (no data loss)
| 12. Vue guard: existing page overwritten when --force is passed
| 13. Autoload failure → non-zero exit code (CI-safe); files still ejected
|
*/

use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\EjectCommand;
use Lvntr\StarterKit\StarterKitServiceProvider;

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Absolute path to the package root (one level above src/).
 */
function pkgRoot(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Create a minimal DomainServiceProvider.php at the given destination root.
 * Mirrors the shipped stub exactly so injection can locate boot().
 */
function makeDomainServiceProvider(string $destRoot): void
{
    $fs = new Filesystem;
    $dir = $destRoot.'/app/Providers';
    $fs->makeDirectory($dir, 0755, true, true);

    $fs->put($dir.'/DomainServiceProvider.php', <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // domain events go here
    }
}
PHP);
}

// ── Temp-dir lifecycle ─────────────────────────────────────────────────────

/** @var string|null */
$tempDest = null;

beforeEach(function () use (&$tempDest): void {
    $tempDest = sys_get_temp_dir().'/sk_eject_test_'.uniqid('', true);
    (new Filesystem)->makeDirectory($tempDest, 0755, true);
});

afterEach(function () use (&$tempDest): void {
    if ($tempDest && is_dir($tempDest)) {
        (new Filesystem)->deleteDirectory($tempDest);
    }
    $tempDest = null;
});

// ── Test 1: --dry-run lists plan but writes nothing ────────────────────────

it('--dry-run shows plan and writes no files', function () use (&$tempDest): void {
    $dest = $tempDest;

    $this->artisan('sk:eject', [
        'domain' => 'Session',
        '--dry-run' => true,
        '--destination' => $dest,
    ])->assertSuccessful();

    // Nothing written into destination
    expect(is_dir($dest.'/app/Domain/Session'))->toBeFalse();
});

// ── Test 2: namespace rewrite — domain ns → App\, Shared preserved ─────────

it('ejects backend with correct namespace rewrite; Shared reference preserved', function () use (&$tempDest): void {
    $dest = $tempDest;

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--no-vue' => true,
        '--destination' => $dest,
    ])->assertSuccessful();

    $actionPath = $dest.'/app/Domain/User/Actions/CreateUserAction.php';
    expect(file_exists($actionPath))->toBeTrue();

    $contents = file_get_contents($actionPath);

    // Namespace declaration rewritten to App\
    expect($contents)->toContain('namespace App\\Domain\\User\\Actions;');

    // use of the same domain class also rewritten
    expect($contents)->toContain('use App\\Domain\\User\\DTOs\\UserDTO;');
    expect($contents)->toContain('use App\\Domain\\User\\Events\\UserCreated;');

    // Shared base import left untouched (Lvntr FQCN must remain)
    expect($contents)->toContain('use Lvntr\\StarterKit\\Domain\\Shared\\Actions\\BaseAction;');

    // Lvntr\StarterKit\Domain\User\ must NOT appear anywhere anymore
    expect($contents)->not->toContain('Lvntr\\StarterKit\\Domain\\User\\');
});

// ── Test 3: DomainServiceProvider gets 3 Event::listen bindings ───────────

it('injects 3 App-FQCN event bindings into DomainServiceProvider for User', function () use (&$tempDest): void {
    $dest = $tempDest;
    makeDomainServiceProvider($dest);

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--no-vue' => true,
        '--destination' => $dest,
    ])->assertSuccessful();

    $providerPath = $dest.'/app/Providers/DomainServiceProvider.php';
    $code = file_get_contents($providerPath);

    // All three User event bindings must be injected
    expect($code)->toContain('App\\Domain\\User\\Events\\UserCreated::class');
    expect($code)->toContain('App\\Domain\\User\\Listeners\\LogUserCreated::class');

    expect($code)->toContain('App\\Domain\\User\\Events\\UserUpdated::class');
    expect($code)->toContain('App\\Domain\\User\\Listeners\\LogUserUpdated::class');

    expect($code)->toContain('App\\Domain\\User\\Events\\UserDeleted::class');
    expect($code)->toContain('App\\Domain\\User\\Listeners\\LogUserDeleted::class');

    // Count exactly 3 Event::listen calls added (not more)
    expect(substr_count($code, 'Event::listen('))->toBe(3);
});

// ── Test 4: Event binding idempotency ─────────────────────────────────────

it('second eject does not duplicate event bindings in DomainServiceProvider', function () use (&$tempDest): void {
    $dest = $tempDest;
    makeDomainServiceProvider($dest);

    $args = [
        'domain' => 'User',
        '--force' => true,
        '--no-vue' => true,
        '--destination' => $dest,
    ];

    $this->artisan('sk:eject', $args)->assertSuccessful();
    $this->artisan('sk:eject', $args)->assertSuccessful();

    $code = file_get_contents($dest.'/app/Providers/DomainServiceProvider.php');

    // Must still be exactly 3 — not 6
    expect(substr_count($code, 'Event::listen('))->toBe(3);
});

// ── Test 5: --no-vue leaves Vue directory untouched ───────────────────────

it('--no-vue does not write any Vue files', function () use (&$tempDest): void {
    $dest = $tempDest;

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--no-vue' => true,
        '--destination' => $dest,
    ])->assertSuccessful();

    // resources/js/pages/Admin/Users must NOT exist
    expect(is_dir($dest.'/resources/js/pages/Admin/Users'))->toBeFalse();
    expect(file_exists($dest.'/resources/js/types/user.ts'))->toBeFalse();
});

// ── Test 6: Event/vue-less domain (Session) leaves provider & Vue alone ───

it('ejecting Session (no events, no vue) leaves DomainServiceProvider unchanged', function () use (&$tempDest): void {
    $dest = $tempDest;
    makeDomainServiceProvider($dest);

    $originalProvider = file_get_contents($dest.'/app/Providers/DomainServiceProvider.php');

    $this->artisan('sk:eject', [
        'domain' => 'Session',
        '--destination' => $dest,
    ])->assertSuccessful();

    // Provider file unchanged (no Event::listen added)
    $updatedProvider = file_get_contents($dest.'/app/Providers/DomainServiceProvider.php');
    expect($updatedProvider)->toBe($originalProvider);

    // No Vue directory created
    expect(is_dir($dest.'/resources'))->toBeFalse();
});

// ── Test 7: alias skip — backwardCompatAliasPlan() omits User after eject ─

it('backwardCompatAliasPlan() skips User aliases when app/Domain/User exists', function () use (&$tempDest): void {
    $dest = $tempDest;

    // Simulate the eject: create the sentinel file that the alias guard checks.
    // The guard is: file_exists($basePath . '/app/' . $relativePath . '.php')
    // For 'App\Domain\User\Actions\CreateUserAction' the relative path is
    // 'Domain/User/Actions/CreateUserAction', so the full check path is
    // $basePath/app/Domain/User/Actions/CreateUserAction.php.
    $fs = new Filesystem;
    $actionDir = $dest.'/app/Domain/User/Actions';
    $fs->makeDirectory($actionDir, 0755, true);
    $fs->put($actionDir.'/CreateUserAction.php', '<?php');

    $provider = new StarterKitServiceProvider(app());
    $reflect = new ReflectionMethod($provider, 'backwardCompatAliasPlan');
    $reflect->setAccessible(true);

    /** @var array<class-string, class-string> $plan */
    $plan = $reflect->invoke($provider, $dest);

    // The User CreateUserAction alias must NOT be in the plan
    expect(array_key_exists('App\\Domain\\User\\Actions\\CreateUserAction', $plan))->toBeFalse();

    // A domain that was NOT ejected (e.g. Session) must still be aliased
    expect(array_key_exists('App\\Domain\\Session\\Actions\\PurgeOtherSessionsAction', $plan))->toBeTrue();
});

// ── Test 8: unknown domain returns failure ─────────────────────────────────

it('unknown domain returns FAILURE exit code and lists valid domains', function () use (&$tempDest): void {
    $this->artisan('sk:eject', [
        'domain' => 'NonExistentDomain',
        '--destination' => $tempDest,
    ])->assertFailed();
});

// ── Test 9: idempotency guard — existing app/Domain without --force exits ──

it('exits early without --force when app/Domain/{name} already exists', function () use (&$tempDest): void {
    $dest = $tempDest;
    $fs = new Filesystem;

    // Pre-create the domain directory
    $fs->makeDirectory($dest.'/app/Domain/User', 0755, true);
    $sentinel = $dest.'/app/Domain/User/sentinel.php';
    $fs->put($sentinel, '<?php // existing file');

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--no-vue' => true,
        '--destination' => $dest,
    ])->assertSuccessful(); // exits 0 (warn + exit, not FAILURE)

    // The sentinel must NOT have been overwritten
    expect(file_get_contents($sentinel))->toBe('<?php // existing file');

    // No additional files (CreateUserAction etc.) should have been copied
    expect(file_exists($dest.'/app/Domain/User/Actions/CreateUserAction.php'))->toBeFalse();
});

// ── Test 10: manifest backend paths resolve to real directories ────────────

it('every manifest backend path exists as a real directory in the package', function (): void {
    $manifestProp = new ReflectionClassConstant(EjectCommand::class, 'DOMAIN_MANIFEST');
    /** @var array<string, array{backend: string, vue: array<string, string>, events: array<string, string>}> $manifest */
    $manifest = $manifestProp->getValue();

    foreach ($manifest as $domain => $descriptor) {
        $path = StarterKitServiceProvider::basePath($descriptor['backend']);
        expect(is_dir($path))->toBeTrue(
            "DOMAIN_MANIFEST['$domain']['backend'] = '{$descriptor['backend']}' is not a real directory at: $path"
        );
    }
});

// ── Test 11: Vue guard — existing customized page preserved without --force ─

it('preserves an existing customized Vue page when --force is not passed', function () use (&$tempDest): void {
    $dest = $tempDest;
    $fs = new Filesystem;

    // Consumer customized Users/Index.vue (a page shipped earlier by sk:install).
    $vueDir = $dest.'/resources/js/pages/Admin/Users';
    $fs->makeDirectory($vueDir, 0755, true);
    $indexPath = $vueDir.'/Index.vue';
    $fs->put($indexPath, '<!-- CUSTOM USER INDEX -->');

    // First eject: app/Domain/User does not exist, so the idempotency guard does
    // not fire and the command proceeds into the Vue step.
    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--destination' => $dest,
    ])->assertSuccessful();

    // The customized page must survive untouched — no silent data loss.
    expect(file_get_contents($indexPath))->toBe('<!-- CUSTOM USER INDEX -->');
});

// ── Test 12: Vue guard — --force overwrites the existing page ───────────────

it('overwrites an existing Vue page when --force is passed', function () use (&$tempDest): void {
    $dest = $tempDest;
    $fs = new Filesystem;

    $vueDir = $dest.'/resources/js/pages/Admin/Users';
    $fs->makeDirectory($vueDir, 0755, true);
    $indexPath = $vueDir.'/Index.vue';
    $fs->put($indexPath, '<!-- CUSTOM USER INDEX -->');

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--force' => true,
        '--destination' => $dest,
    ])->assertSuccessful();

    // With --force the kit stub replaces the custom content.
    expect(file_exists($indexPath))->toBeTrue();
    expect(file_get_contents($indexPath))->not->toBe('<!-- CUSTOM USER INDEX -->');
});

// ── Test 13: autoload failure returns a non-zero exit code (CI-safe) ────────

it('returns a non-zero exit code when composer dump-autoload fails', function () use (&$tempDest): void {
    $dest = $tempDest;
    $fs = new Filesystem;

    // A real composer.json so refreshAutoload() does not early-return, plus a
    // fake composer.phar that always fails — findComposerBinary() prefers the
    // phar, so `php composer.phar dump-autoload -q` runs and exits non-zero.
    $fs->put($dest.'/composer.json', '{}');
    $fs->put($dest.'/composer.phar', '<?php exit(23);');

    $this->artisan('sk:eject', [
        'domain' => 'User',
        '--no-vue' => true,
        '--destination' => $dest,
    ])->assertFailed();

    // The backend files were still copied — the failure is the autoload step,
    // not the eject itself — but the command signals failure so automation halts.
    expect(file_exists($dest.'/app/Domain/User/Actions/CreateUserAction.php'))->toBeTrue();
});
