<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\UpdateCommand;
use Lvntr\StarterKit\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// The group-level filesystem scenarios below boot the testbench app so base_path()
// and config() resolve. tests/Feature/Update is not bound to a TestCase in
// Pest.php (its other tests are pure reflection), so bind it at file scope here.
// Harmless for the reflection tests in this file — they simply gain a booted app.
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Task 4 (vendor-first behavior modules, v13.6.0)
|--------------------------------------------------------------------------
|
| The Files/Logs/ActivityLogs/ApiRoutes/Settings HTTP + UI layers moved to
| vendor. sk:update migrates the old published consumer copies under a
| registry-hash guard, decided per MODULE LAYER GROUP (controller+requests as
| one PHP group, the page tree as one Vue group): if any file in a group is
| modified/untracked the WHOLE group is preserved (group atomicity). Vue groups
| are only removed when app.ts ships the '@lvntr/pages' vendor fallback.
|
| The first block exercises the decision rule + manifest in isolation (hash-in /
| bool-out), matching the suite's reflection-test style. The second block boots
| the command against the testbench base_path to prove the GROUP-LEVEL behavior
| (atomicity, the app.ts Vue guard) end-to-end on a real filesystem.
|
*/

/**
 * @return list<string>
 */
function vendorMigratedPaths(): array
{
    $reflection = new ReflectionClassConstant(UpdateCommand::class, 'VENDOR_MIGRATED_PATHS');

    /** @var list<string> */
    return $reflection->getValue();
}

/**
 * @return array<string, array{php: list<string>, vue: list<string>}>
 */
function vendorMigratedModules(): array
{
    $reflection = new ReflectionClassConstant(UpdateCommand::class, 'VENDOR_MIGRATED_MODULES');

    /** @var array<string, array{php: list<string>, vue: list<string>}> */
    return $reflection->getValue();
}

function vendorMigratedCopyIsRemovable(string $currentHash, ?string $recordedHash, bool $force): bool
{
    $method = new ReflectionMethod(UpdateCommand::class, 'vendorMigratedCopyIsRemovable');
    $method->setAccessible(true);

    return $method->invoke(new UpdateCommand, $currentHash, $recordedHash, $force);
}

// ── Filesystem harness ──────────────────────────────────────────────────────

/**
 * Build a fully-wired UpdateCommand: $files initialized, input bound (so
 * option('force') resolves), output + components set (so the warn/line calls in
 * removeVendorMigratedPaths land in a buffer we can assert on). Returns the
 * command plus the output buffer.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: UpdateCommand, 1: BufferedOutput}
 */
function bootUpdateCommand(array $options = []): array
{
    $command = new UpdateCommand;

    $filesProperty = new ReflectionProperty($command, 'files');
    $filesProperty->setAccessible(true);
    $filesProperty->setValue($command, new Filesystem);

    $buffer = new BufferedOutput;
    $style = new OutputStyle(new ArrayInput($options, $command->getDefinition()), $buffer);
    $command->setInput(new ArrayInput($options, $command->getDefinition()));
    $command->setOutput($style);

    $componentsProperty = new ReflectionProperty($command, 'components');
    $componentsProperty->setAccessible(true);
    $componentsProperty->setValue($command, new ComponentsFactory($style));

    return [$command, $buffer];
}

/**
 * Run the private removeVendorMigratedPaths() and return the command's resulting
 * $removed / $preservedDeprecated arrays plus the captured output text.
 *
 * @param  array<string, mixed>  $options
 * @return array{removed: list<string>, preserved: list<string>, output: string}
 */
function runVendorMigration(array $options = []): array
{
    [$command, $buffer] = bootUpdateCommand($options);

    $method = new ReflectionMethod($command, 'removeVendorMigratedPaths');
    $method->setAccessible(true);
    $method->invoke($command, false);

    $read = function (string $prop) use ($command): array {
        $p = new ReflectionProperty($command, $prop);
        $p->setAccessible(true);

        /** @var list<string> */
        return $p->getValue($command);
    };

    return [
        'removed' => $read('removed'),
        'preserved' => $read('preservedDeprecated'),
        'output' => $buffer->fetch(),
    ];
}

/**
 * Point the hash registry at a fresh temp file for the current test and seed it
 * with the given path→hash map (v2 format). Returns the registry file path.
 *
 * @param  array<string, string>  $records
 */
function seedHashRegistry(array $records): string
{
    $fs = new Filesystem;
    $hashFile = sys_get_temp_dir().'/sk_vmc_hashes_'.uniqid('', true).'.json';
    $fs->put($hashFile, json_encode(['_format' => 'v2'] + $records));

    config(['starter-kit.published_hashes' => $hashFile]);

    return $hashFile;
}

/**
 * Write a consumer file under the testbench base_path, creating parent dirs.
 */
function seedAppFile(string $relativePath, string $contents): void
{
    $fs = new Filesystem;
    $target = base_path($relativePath);
    $fs->ensureDirectoryExists(dirname($target));
    $fs->put($target, $contents);
}

/**
 * Write a resources/js/app.ts under the testbench base_path. When $withFallback
 * is true the content carries the '@lvntr/pages' vendor-fallback marker.
 */
function seedAppTs(bool $withFallback): void
{
    $marker = $withFallback
        ? "import.meta.glob('@lvntr/pages/**/*.vue', { eager: true });"
        : '// customized resolver with no vendor fallback';

    seedAppFile('resources/js/app.ts', "// app.ts\n{$marker}\n");
}

/**
 * Remove every path this suite may have seeded under base_path so no test leaks
 * fixtures into the real testbench tree. Safe to call unconditionally.
 */
function cleanupVendorMigrationFixtures(): void
{
    $fs = new Filesystem;

    foreach ([
        'app/Http/Controllers/Admin/LogController.php',
        'app/Http/Controllers/Admin/ActivityLogController.php',
        'app/Http/Controllers/Admin/ApiRouteController.php',
        'app/Http/Controllers/Admin/SettingsController.php',
        'app/Http/Requests/Admin/Log',
        'app/Http/Requests/Admin/Settings',
        'resources/js/pages/Admin/Files',
        'resources/js/pages/Admin/Logs',
        'resources/js/pages/Admin/ActivityLogs',
        'resources/js/pages/Admin/ApiRoutes',
        'resources/js/pages/Admin/Settings',
        'resources/js/app.ts',
    ] as $relative) {
        $target = base_path($relative);

        if ($fs->isDirectory($target)) {
            $fs->deleteDirectory($target);
        } elseif ($fs->exists($target)) {
            $fs->delete($target);
        }
    }
}

afterEach(function (): void {
    cleanupVendorMigrationFixtures();
});

it('registers all five migrated behavior modules (Vue + controllers + requests)', function (): void {
    $paths = vendorMigratedPaths();

    // Vue page trees (Task 1).
    expect($paths)->toContain(
        'resources/js/pages/Admin/Files/',
        'resources/js/pages/Admin/Logs/',
        'resources/js/pages/Admin/ActivityLogs/',
        'resources/js/pages/Admin/ApiRoutes/',
        'resources/js/pages/Admin/Settings/',
    );

    // Controllers (Task 2).
    expect($paths)->toContain(
        'app/Http/Controllers/Admin/LogController.php',
        'app/Http/Controllers/Admin/ActivityLogController.php',
        'app/Http/Controllers/Admin/ApiRouteController.php',
        'app/Http/Controllers/Admin/SettingsController.php',
    );

    // FormRequest trees (Task 2).
    expect($paths)->toContain(
        'app/Http/Requests/Admin/Log/',
        'app/Http/Requests/Admin/Settings/',
    );
});

it('does NOT migrate out-of-scope HTTP layers (ApiClient/ApiToken/ContentLanguage/SystemHealth stay app-owned)', function (): void {
    $paths = vendorMigratedPaths();

    expect($paths)->not->toContain(
        'app/Http/Controllers/Admin/ApiClientController.php',
        'app/Http/Controllers/Admin/ApiTokenController.php',
        'app/Http/Controllers/Admin/ContentLanguageController.php',
        'app/Http/Controllers/Admin/SystemHealthController.php',
        'app/Http/Controllers/Admin/UserController.php',
        'app/Http/Controllers/Admin/RoleController.php',
        'app/Http/Controllers/Admin/DashboardController.php',
    );
});

it('deletes a copy whose on-disk hash matches its registry record (unmodified → vendor takes over)', function (): void {
    $hash = md5('the byte-for-byte content we shipped');

    expect(vendorMigratedCopyIsRemovable($hash, $hash, force: false))->toBeTrue();
});

it('preserves a copy whose content differs from its registry record (user-customized → keeps winning)', function (): void {
    expect(vendorMigratedCopyIsRemovable(
        md5('user edited this controller'),
        md5('what the kit originally shipped'),
        force: false,
    ))->toBeFalse();
});

it('preserves an untracked copy with no registry record (cannot prove unmodified)', function (): void {
    expect(vendorMigratedCopyIsRemovable(md5('anything'), null, force: false))->toBeFalse();
});

it('removes any copy under --force regardless of modification', function (): void {
    expect(vendorMigratedCopyIsRemovable(md5('heavily customized'), md5('original'), force: true))->toBeTrue();
    expect(vendorMigratedCopyIsRemovable(md5('untracked'), null, force: true))->toBeTrue();
});

// ── Grouped manifest shape + drift guard ─────────────────────────────────────

it('groups every migrated path under a module split into php and vue layers', function (): void {
    $modules = vendorMigratedModules();

    expect($modules)->toHaveKeys(['Files', 'Logs', 'ActivityLogs', 'ApiRoutes', 'Settings']);

    foreach ($modules as $module => $layers) {
        expect($layers)->toHaveKeys(['php', 'vue'], "module {$module} must declare php + vue layers");
        expect($layers['php'])->toBeArray();
        expect($layers['vue'])->toBeArray();
    }

    // Settings keeps its controller AND request dir in ONE php group (atomicity
    // depends on them living together).
    expect($modules['Settings']['php'])->toContain(
        'app/Http/Controllers/Admin/SettingsController.php',
        'app/Http/Requests/Admin/Settings/',
    );

    // Files is Vue-only (its backend is the vendor FileManager runtime).
    expect($modules['Files']['php'])->toBe([]);
    expect($modules['Files']['vue'])->toContain('resources/js/pages/Admin/Files/');
});

it('keeps the flat VENDOR_MIGRATED_PATHS in sync with the grouped manifest (no drift)', function (): void {
    $flat = vendorMigratedPaths();

    $unionFromGroups = [];
    foreach (vendorMigratedModules() as $layers) {
        foreach (array_merge($layers['vue'], $layers['php']) as $entry) {
            $unionFromGroups[] = $entry;
        }
    }

    sort($flat);
    sort($unionFromGroups);

    expect($unionFromGroups)->toBe($flat);
});

// ── (a) PHP group atomicity — one edited request pins the unmodified controller

it('preserves an unmodified controller when a sibling request in the same module was modified', function (): void {
    // Settings PHP group: an UNMODIFIED controller alongside a MODIFIED request.
    $controllerBody = '<?php // original SettingsController shipped by the kit';
    $requestRel = 'app/Http/Requests/Admin/Settings/UpdateStorageSettingsRequest.php';
    $controllerRel = 'app/Http/Controllers/Admin/SettingsController.php';

    seedAppFile($controllerRel, $controllerBody);
    seedAppFile($requestRel, '<?php // CONSUMER HARDENED validation + authorize()');

    seedHashRegistry([
        // Controller recorded hash == on-disk hash → unmodified on its own.
        $controllerRel => md5($controllerBody),
        // Request recorded as something else → on-disk differs → user-modified.
        $requestRel => md5('<?php // what the kit originally shipped'),
    ]);

    $result = runVendorMigration();

    // Atomicity: BOTH files preserved — the unmodified controller must NOT be
    // deleted while its hardened request stays on disk (vendor would type-hint
    // the vendor request and never call the consumer's authorize/validation).
    expect($result['removed'])->not->toContain($controllerRel, $requestRel);
    expect($result['preserved'])->toContain($controllerRel, $requestRel);

    expect(file_exists(base_path($controllerRel)))->toBeTrue();
    expect(file_exists(base_path($requestRel)))->toBeTrue();
});

it('still removes a fully-unmodified PHP group (control case for atomicity)', function (): void {
    seedAppTs(withFallback: true); // unrelated; PHP layer ignores app.ts anyway

    $ctrlBody = '<?php // original ActivityLogController';
    $ctrlRel = 'app/Http/Controllers/Admin/ActivityLogController.php';
    seedAppFile($ctrlRel, $ctrlBody);

    seedHashRegistry([$ctrlRel => md5($ctrlBody)]);

    $result = runVendorMigration();

    expect($result['removed'])->toContain($ctrlRel);
    expect($result['preserved'])->not->toContain($ctrlRel);
    expect(file_exists(base_path($ctrlRel)))->toBeFalse();
});

// ── (b) app.ts guard — Vue preserved without marker, removed with it ──────────

it('preserves all Vue groups and warns when app.ts has no vendor page fallback', function (): void {
    seedAppTs(withFallback: false);

    // An UNMODIFIED Logs Vue page that WOULD be removable on hash grounds.
    $vueBody = '<template><div>Logs Index</div></template>';
    $vueRel = 'resources/js/pages/Admin/Logs/Index.vue';
    seedAppFile($vueRel, $vueBody);

    seedHashRegistry([$vueRel => md5($vueBody)]);

    $result = runVendorMigration();

    // Vue group preserved purely because app.ts lacks the fallback resolver.
    expect($result['removed'])->not->toContain($vueRel);
    expect($result['preserved'])->toContain($vueRel);
    expect(file_exists(base_path($vueRel)))->toBeTrue();

    // Actionable warning naming app.ts + the fallback.
    expect($result['output'])->toContain('app.ts');
    expect($result['output'])->toContain('vendor page fallback');
});

it('removes an unmodified Vue group when app.ts ships the vendor page fallback', function (): void {
    seedAppTs(withFallback: true);

    $vueBody = '<template><div>Logs Index</div></template>';
    $vueRel = 'resources/js/pages/Admin/Logs/Index.vue';
    seedAppFile($vueRel, $vueBody);

    seedHashRegistry([$vueRel => md5($vueBody)]);

    $result = runVendorMigration();

    expect($result['removed'])->toContain($vueRel);
    expect($result['preserved'])->not->toContain($vueRel);
    expect(file_exists(base_path($vueRel)))->toBeFalse();
});

// ── (c) Vue group atomicity — one edited component pins the whole tree ────────

it('preserves the entire module Vue tree when a single component was modified', function (): void {
    seedAppTs(withFallback: true); // fallback present, so only atomicity can block

    // Settings Vue tree: an UNMODIFIED Index.vue + a MODIFIED tab component.
    $indexBody = '<template><div>Settings</div></template>';
    $indexRel = 'resources/js/pages/Admin/Settings/Index.vue';
    $tabRel = 'resources/js/pages/Admin/Settings/components/StorageTab.vue';

    seedAppFile($indexRel, $indexBody);
    seedAppFile($tabRel, '<template><!-- consumer-customized tab --></template>');

    seedHashRegistry([
        $indexRel => md5($indexBody),                       // unmodified
        $tabRel => md5('<template><!-- original --></template>'), // differs → modified
    ]);

    $result = runVendorMigration();

    // The whole Settings Vue tree stays — a partial delete would break the build
    // (vendor pages import sibling components by relative path).
    expect($result['removed'])->not->toContain($indexRel, $tabRel);
    expect($result['preserved'])->toContain($indexRel, $tabRel);

    expect(file_exists(base_path($indexRel)))->toBeTrue();
    expect(file_exists(base_path($tabRel)))->toBeTrue();
});

// ── Independence — a blocked Vue group does not block the PHP group ───────────

it('migrates the PHP group even when the Vue group of the same module is preserved', function (): void {
    seedAppTs(withFallback: true);

    // Logs PHP: fully unmodified (removable). Logs Vue: one modified file (blocked).
    $ctrlBody = '<?php // original LogController';
    $ctrlRel = 'app/Http/Controllers/Admin/LogController.php';
    seedAppFile($ctrlRel, $ctrlBody);

    $vueRel = 'resources/js/pages/Admin/Logs/Index.vue';
    seedAppFile($vueRel, '<template><!-- consumer edited --></template>');

    seedHashRegistry([
        $ctrlRel => md5($ctrlBody),                  // unmodified → PHP group removable
        $vueRel => md5('<template>original</template>'), // differs → Vue group preserved
    ]);

    $result = runVendorMigration();

    // PHP layer is evaluated independently of the (blocked) Vue layer.
    expect($result['removed'])->toContain($ctrlRel);
    expect($result['preserved'])->toContain($vueRel);
    expect($result['preserved'])->not->toContain($ctrlRel);

    expect(file_exists(base_path($ctrlRel)))->toBeFalse();
    expect(file_exists(base_path($vueRel)))->toBeTrue();
});
