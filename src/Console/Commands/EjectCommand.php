<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Symfony\Component\Process\Process;

/**
 * Eject a kit domain into the consumer app — the inverse of vendor-first
 * relocation. Copies the domain's vendor runtime (Action/DTO/Query/Event/
 * Listener) into `app/Domain/{Name}`, rewrites ONLY that domain's root
 * namespace to `App\`, refreshes its Vue pages, and (for event domains)
 * injects App-FQCN `Event::listen` lines into DomainServiceProvider so the
 * audit log keeps firing. After ejection the file_exists() guard in
 * StarterKitServiceProvider::backwardCompatAliasPlan() automatically disables
 * the matching alias — the consumer copy wins.
 *
 * Trade-off (warned at runtime + docs): once ejected, the consumer owns the
 * files and no longer receives kit security/bugfix updates for that domain —
 * the same known cost as overriding any published file.
 */
class EjectCommand extends Command
{
    protected $signature = 'sk:eject
        {domain : The kit domain to eject (User, Role, Setting, ActivityLog, ApiClient, ApiRoute, Logs, Session, Media)}
        {--force : Overwrite existing app/Domain files}
        {--dry-run : Show what would happen without writing anything}
        {--no-vue : Eject only the backend; leave Vue pages untouched}
        {--destination= : Override destination base path (for testing or custom layouts)}';

    protected $description = 'Eject a kit domain into your app (own it: backend + Vue, alias disabled, no more kit updates for it)';

    /**
     * Static domain manifest. The kit's domain layout is too irregular to derive
     * by convention (domain `User` ↔ folder `Users` ↔ type `user.ts`; `Setting`
     * ↔ `SettingsController`; ApiClient/Token Vue lives inside Settings tabs), so
     * each ejectable domain is described explicitly. Mirrors
     * PublishCommand::PUBLISHABLE_TAGS in spirit: embedded in the command so
     * `config:cache` cannot strip it and a consumer cannot accidentally break it.
     *
     * Each descriptor:
     *   - backend: vendor source dir (relative to package root) → app/Domain/{Name}.
     *   - vue:     [stubSourceRelative => appDestRelative] map; [] when the domain
     *              ships no dedicated Vue page folder (Session, Media, ApiClient).
     *   - events:  [EventShortName => ListenerShortName] for App-FQCN Event::listen
     *              injection; [] for event-less domains.
     *
     * FileManager is intentionally absent — it has its own facade/route-registry
     * infrastructure and is handled separately (out of scope).
     *
     * @var array<string, array{backend: string, vue: array<string, string>, events: array<string, string>}>
     */
    private const DOMAIN_MANIFEST = [
        'User' => [
            'backend' => 'src/Domain/User',
            'vue' => [
                'stubs/resources/js/pages/Admin/Users' => 'resources/js/pages/Admin/Users',
                'stubs/resources/js/types/user.ts' => 'resources/js/types/user.ts',
            ],
            'events' => [
                'UserCreated' => 'LogUserCreated',
                'UserUpdated' => 'LogUserUpdated',
                'UserDeleted' => 'LogUserDeleted',
            ],
        ],
        'Role' => [
            'backend' => 'src/Domain/Role',
            'vue' => [
                'stubs/resources/js/pages/Admin/Roles' => 'resources/js/pages/Admin/Roles',
            ],
            'events' => [
                'RoleCreated' => 'LogRoleCreated',
                'RoleUpdated' => 'LogRoleUpdated',
                'RoleDeleted' => 'LogRoleDeleted',
            ],
        ],
        'Setting' => [
            'backend' => 'src/Domain/Setting',
            'vue' => [
                'stubs/resources/js/pages/Admin/Settings' => 'resources/js/pages/Admin/Settings',
            ],
            'events' => [],
        ],
        'ActivityLog' => [
            'backend' => 'src/Domain/ActivityLog',
            'vue' => [
                'stubs/resources/js/pages/Admin/ActivityLogs' => 'resources/js/pages/Admin/ActivityLogs',
            ],
            'events' => [],
        ],
        'ApiClient' => [
            'backend' => 'src/Domain/ApiClient',
            // ApiClient/ApiToken management Vue lives inside Settings tab components
            // (ApiClientsTab.vue, ApiTokensManageTab.vue), not a dedicated folder —
            // it travels with the Settings domain. No standalone ApiClient page set.
            'vue' => [],
            'events' => [],
        ],
        'ApiRoute' => [
            'backend' => 'src/Domain/ApiRoute',
            'vue' => [
                'stubs/resources/js/pages/Admin/ApiRoutes' => 'resources/js/pages/Admin/ApiRoutes',
            ],
            'events' => [],
        ],
        'Logs' => [
            'backend' => 'src/Domain/Logs',
            'vue' => [
                'stubs/resources/js/pages/Admin/Logs' => 'resources/js/pages/Admin/Logs',
            ],
            'events' => [
                'LogFilesDeleted' => 'LogActivityForLogFilesDeleted',
            ],
        ],
        'Session' => [
            'backend' => 'src/Domain/Session',
            'vue' => [],
            'events' => [],
        ],
        'Media' => [
            'backend' => 'src/Domain/Media',
            'vue' => [],
            'events' => [],
        ],
    ];

    private Filesystem $files;

    public function handle(): int
    {
        $this->files = new Filesystem;

        $domain = (string) $this->argument('domain');

        if (! isset(self::DOMAIN_MANIFEST[$domain])) {
            $this->components->error("Unknown domain: {$domain}");
            $this->line('  Available domains: '.implode(', ', array_keys(self::DOMAIN_MANIFEST)));

            return self::FAILURE;
        }

        $descriptor = self::DOMAIN_MANIFEST[$domain];
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $noVue = (bool) $this->option('no-vue');

        $appDomainDir = $this->resolveDestination('app/Domain/'.$domain);

        // Idempotency guard: an existing app/Domain/{Name} without --force means
        // the consumer already ejected (or hand-authored) it — do not clobber.
        if (! $force && $this->files->isDirectory($appDomainDir)) {
            $this->components->warn("app/Domain/{$domain} already exists — pass --force to overwrite, or remove it first.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  <fg=cyan;options=bold>Ejecting domain: {$domain}</>");
        if ($dryRun) {
            $this->line('  <fg=yellow>DRY RUN — no files will be written.</>');
        }
        $this->newLine();

        $backendCount = $this->ejectBackend($domain, $descriptor['backend'], $dryRun);

        $vueResult = ['copied' => 0, 'skipped' => []];
        if (! $noVue) {
            $vueResult = $this->ejectVue($domain, $descriptor['vue'], $force, $dryRun);
        }

        $bindings = $this->injectEventBindings($domain, $descriptor['events'], $dryRun);

        $autoloadOk = true;
        if (! $dryRun) {
            $autoloadOk = $this->refreshAutoload();
        }

        $this->printSummary($domain, $backendCount, $vueResult, $bindings, $noVue, $dryRun, $autoloadOk);

        // A failed autoload regeneration means the ejected classes may not resolve
        // under optimized / classmap-authoritative autoloaders — the eject is not
        // functionally complete, so signal non-zero for CI/scripts even though the
        // files were already copied.
        return $autoloadOk ? self::SUCCESS : self::FAILURE;
    }

    // ══════════════════════════════════════════════════════════════════════
    // BACKEND EJECT (copy + namespace rewrite)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Copy `src/Domain/{Name}/**` → `app/Domain/{Name}/**`, rewriting only this
     * domain's root namespace to `App\`. Returns the number of files copied.
     */
    private function ejectBackend(string $domain, string $sourceRelative, bool $dryRun): int
    {
        $source = StarterKitServiceProvider::basePath($sourceRelative);
        $destination = $this->resolveDestination('app/Domain/'.$domain);

        if (! $this->files->isDirectory($source)) {
            $this->components->error("Backend source not found: {$source}");

            return 0;
        }

        $count = 0;

        foreach ($this->files->allFiles($source, true) as $file) {
            $relative = $file->getRelativePathname();
            $targetPath = $destination.DIRECTORY_SEPARATOR.$relative;

            if ($dryRun) {
                $this->line("  <fg=gray>backend</> app/Domain/{$domain}/".str_replace('\\', '/', $relative));
                $count++;

                continue;
            }

            $targetDir = dirname($targetPath);
            if (! $this->files->isDirectory($targetDir)) {
                $this->files->makeDirectory($targetDir, 0755, true);
            }

            $contents = $this->files->get($file->getPathname());

            if (str_ends_with($file->getFilename(), '.php')) {
                $contents = $this->rewriteDomainNamespace($contents, $domain);
            }

            $this->files->put($targetPath, $contents);
            $count++;
        }

        return $count;
    }

    /**
     * Rewrite ONLY the ejected domain's root namespace segment
     * `Lvntr\StarterKit\Domain\{Name}\` → `App\Domain\{Name}\`.
     *
     * Deliberately narrow: the trailing backslash in the pattern is a hard
     * boundary, so `Domain\Shared\` (the SAFE_UPDATE base, never ejected),
     * other un-ejected domains, `Http\Responses\ApiResponse`, and any
     * third-party `Lvntr\StarterKit\*` reference are left exactly as written.
     * `preg_quote` keeps the domain name literal so it can never act as a
     * regex metacharacter.
     */
    private function rewriteDomainNamespace(string $contents, string $domain): string
    {
        $quoted = preg_quote($domain, '/');

        $pattern = '/\bLvntr\\\\StarterKit\\\\Domain\\\\'.$quoted.'\\\\/';

        return (string) preg_replace($pattern, 'App\\Domain\\'.$domain.'\\', $contents);
    }

    // ══════════════════════════════════════════════════════════════════════
    // VUE EJECT (refresh pages from stubs)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Copy the manifest's stub→app Vue mappings. An existing destination file is
     * preserved unless --force is passed (symmetric with the backend's
     * directory-level guard in handle()) — eject must never silently overwrite a
     * consumer's customized page. Missing files are always written.
     *
     * @param  array<string, string>  $vueMap
     * @return array{copied: int, skipped: list<string>}
     */
    private function ejectVue(string $domain, array $vueMap, bool $force, bool $dryRun): array
    {
        if ($vueMap === []) {
            return ['copied' => 0, 'skipped' => []];
        }

        $copied = 0;
        $skipped = [];

        foreach ($vueMap as $sourceRelative => $destRelative) {
            $source = StarterKitServiceProvider::basePath($sourceRelative);
            $destination = $this->resolveDestination($destRelative);

            if (! $this->files->exists($source)) {
                $this->components->warn("Vue source not found, skipping: {$sourceRelative}");

                continue;
            }

            if ($this->files->isDirectory($source)) {
                $result = $this->copyVueDirectory($source, $destination, $force, $dryRun, $destRelative);
                $copied += $result['copied'];
                $skipped = [...$skipped, ...$result['skipped']];

                continue;
            }

            // Single-file mapping (e.g. types/user.ts): preserve unless --force.
            if (! $force && $this->files->exists($destination)) {
                $skipped[] = $destRelative;
                if ($dryRun) {
                    $this->line("  <fg=yellow>vue skip</> {$destRelative} <fg=gray>(exists; --force to overwrite)</>");
                }

                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=gray>vue</> {$destRelative}");
                $copied++;

                continue;
            }

            $dir = dirname($destination);
            if (! $this->files->isDirectory($dir)) {
                $this->files->makeDirectory($dir, 0755, true);
            }

            $this->files->copy($source, $destination);
            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * Recursively copy a Vue directory. Each existing destination file is
     * preserved unless --force is passed; missing files are always written.
     * Returns the copied count and the display paths that were skipped.
     *
     * @return array{copied: int, skipped: list<string>}
     */
    private function copyVueDirectory(string $source, string $destination, bool $force, bool $dryRun, string $destRelative): array
    {
        $copied = 0;
        $skipped = [];

        foreach ($this->files->allFiles($source, true) as $file) {
            $relative = $file->getRelativePathname();
            $targetPath = $destination.DIRECTORY_SEPARATOR.$relative;
            $displayPath = $destRelative.'/'.str_replace('\\', '/', $relative);

            if (! $force && $this->files->exists($targetPath)) {
                $skipped[] = $displayPath;
                if ($dryRun) {
                    $this->line('  <fg=yellow>vue skip</> '.$displayPath.' <fg=gray>(exists; --force to overwrite)</>');
                }

                continue;
            }

            if ($dryRun) {
                $this->line('  <fg=gray>vue</> '.$displayPath);
                $copied++;

                continue;
            }

            $targetDir = dirname($targetPath);
            if (! $this->files->isDirectory($targetDir)) {
                $this->files->makeDirectory($targetDir, 0755, true);
            }

            $this->files->copy($file->getPathname(), $targetPath);
            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    // ══════════════════════════════════════════════════════════════════════
    // EVENT BINDING INJECTION (DomainServiceProvider)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Inject App-FQCN `Event::listen(\App\Domain\{Name}\Events\{E}::class,
     * \App\Domain\{Name}\Listeners\{L}::class);` statements into the boot()
     * method of app/Providers/DomainServiceProvider.php.
     *
     * Why this is required: after ejection the consumer's App action dispatches
     * the App event, but the vendor binding in registerEventListeners() is keyed
     * by the VENDOR FQCN — it never matches the App-namespaced dispatch, so the
     * audit listener would silently stop. Re-binding with App FQCNs here keeps it
     * firing. No double-fire: the vendor binding becomes dormant (the vendor
     * event is never dispatched once the App action owns the flow).
     *
     * Idempotent: a binding whose App event+listener FQCNs are already present in
     * the file is not added again.
     *
     * @param  array<string, string>  $events  EventShortName => ListenerShortName
     * @return list<string> the bindings that were (or would be, on dry-run) added
     */
    private function injectEventBindings(string $domain, array $events, bool $dryRun): array
    {
        if ($events === []) {
            return [];
        }

        $providerPath = $this->resolveDestination('app/Providers/DomainServiceProvider.php');

        if (! $this->files->exists($providerPath)) {
            $this->components->warn('DomainServiceProvider not found — event bindings skipped: app/Providers/DomainServiceProvider.php');

            return [];
        }

        $existingCode = $this->files->get($providerPath);

        $toAdd = [];
        foreach ($events as $event => $listener) {
            $eventFqcn = 'App\\Domain\\'.$domain.'\\Events\\'.$event;
            $listenerFqcn = 'App\\Domain\\'.$domain.'\\Listeners\\'.$listener;

            // Idempotency: skip when both FQCNs already appear in the same file.
            // Matching on the bare FQCN strings (with or without a leading
            // backslash) is robust against pretty-printer spacing differences.
            if ($this->bindingAlreadyPresent($existingCode, $eventFqcn, $listenerFqcn)) {
                continue;
            }

            $toAdd[] = [$eventFqcn, $listenerFqcn];
        }

        if ($toAdd === []) {
            return [];
        }

        $added = [];
        foreach ($toAdd as [$eventFqcn, $listenerFqcn]) {
            $added[] = "\\{$eventFqcn}::class → \\{$listenerFqcn}::class";
        }

        if ($dryRun) {
            return $added;
        }

        $updated = $this->insertBindingsIntoBoot($existingCode, $toAdd);

        if ($updated === null) {
            $this->components->warn('Could not locate boot() in DomainServiceProvider — event bindings skipped. Add them manually:');
            foreach ($added as $line) {
                $this->line('  <fg=gray>→</> Event::listen('.$line.');');
            }

            return [];
        }

        $this->files->put($providerPath, $updated);

        return $added;
    }

    /**
     * Insert fully-qualified `Event::listen(...)` lines as the FIRST statements of
     * the provider's `boot()` body, immediately after its opening brace.
     *
     * A targeted string insertion is used (not AST pretty-printing) because the
     * shipped stub's boot() body is comment-only: PhpParser's
     * `printFormatPreserving` silently drops statements appended to a body it
     * cannot anchor to an existing token, whereas anchoring on the literal opening
     * brace is deterministic and leaves the rest of the file byte-for-byte intact.
     * Indentation is inferred from the brace's own line. Returns null when the
     * `boot()` signature cannot be found.
     *
     * @param  list<array{0: string, 1: string}>  $toAdd  [eventFqcn, listenerFqcn] pairs
     */
    private function insertBindingsIntoBoot(string $code, array $toAdd): ?string
    {
        // Match `function boot(...)` ... up to and including its opening brace,
        // tolerant of a return type and arbitrary whitespace/newlines before `{`.
        if (! preg_match('/function\s+boot\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{/', $code, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $bracePos = $m[0][1] + strlen($m[0][0]); // position just after the `{`

        // Indentation of the method body = the method line's indent + one level.
        $lineStart = strrpos(substr($code, 0, $m[0][1]), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $methodIndent = '';
        for ($i = $lineStart; $i < strlen($code) && ($code[$i] === ' ' || $code[$i] === "\t"); $i++) {
            $methodIndent .= $code[$i];
        }
        $bodyIndent = $methodIndent.'    ';

        $lines = '';
        if ($toAdd !== []) {
            // Marker so a consumer reading their own provider after eject is not
            // misled by the shipped stub's "bindings are omitted" note further
            // down — that note predates the eject; these App-owned bindings now
            // match the (rewritten) App event dispatch and fire exactly once.
            $lines .= "\n".$bodyIndent.'// sk:eject: App\\Domain event bindings injected below (the explanatory note further down predates the eject).';
        }
        foreach ($toAdd as [$eventFqcn, $listenerFqcn]) {
            $lines .= "\n".$bodyIndent.'\\Illuminate\\Support\\Facades\\Event::listen('
                .'\\'.$eventFqcn.'::class, \\'.$listenerFqcn.'::class);';
        }

        return substr($code, 0, $bracePos).$lines.substr($code, $bracePos);
    }

    /**
     * Whether a binding for the given App event + listener FQCNs is already
     * declared anywhere in the provider source. Tolerant of an optional leading
     * backslash so `\App\...::class` and `App\...::class` both count as present.
     */
    private function bindingAlreadyPresent(string $code, string $eventFqcn, string $listenerFqcn): bool
    {
        $eventNeedle = '\\'.$eventFqcn.'::class';
        $listenerNeedle = '\\'.$listenerFqcn.'::class';

        $hasEvent = str_contains($code, $eventNeedle) || str_contains($code, $eventFqcn.'::class');
        $hasListener = str_contains($code, $listenerNeedle) || str_contains($code, $listenerFqcn.'::class');

        return $hasEvent && $hasListener;
    }

    // ══════════════════════════════════════════════════════════════════════
    // AUTOLOAD
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Regenerate Composer's autoload so the newly ejected app/Domain classes are
     * discoverable. After this the backwardCompatAliasPlan() file_exists() guard
     * sees the App copy and stops aliasing the domain to vendor — the consumer
     * copy wins.
     *
     * @return bool false when the dump ran and failed (caller exits non-zero);
     *              true when it succeeded or there was nothing to dump.
     */
    private function refreshAutoload(): bool
    {
        $base = $this->resolveDestination('');

        // Only dump autoload against a real Composer project (a composer.json at
        // the destination root). Isolated test destinations have none — skipping
        // there keeps the command side-effect-free under --destination and is not
        // a failure.
        if (! $this->files->exists($base.DIRECTORY_SEPARATOR.'composer.json')) {
            return true;
        }

        $composer = $this->findComposerBinary($base);
        $process = new Process([...$composer, 'dump-autoload', '-q'], $base, null, null, 120);
        $process->run();

        // A silent failure here is dangerous: with the alias now disabled, the
        // ejected App\Domain\{Name}\* classes must land in the regenerated
        // autoload map or they will not resolve under optimized /
        // classmap-authoritative autoloaders. Surface it AND report failure so the
        // command exits non-zero — a broken autoload must not look like a
        // successful eject to CI/scripts.
        if (! $process->isSuccessful()) {
            $this->components->warn(
                'composer dump-autoload failed — run it manually so the ejected classes are discoverable '
                .'(required under optimized / classmap-authoritative autoloaders).'
            );
            $error = trim($process->getErrorOutput());
            if ($error !== '') {
                $this->line('  <fg=gray>'.$error.'</>');
            }

            return false;
        }

        return true;
    }

    /**
     * Locate the composer executable for the given working directory.
     *
     * @return list<string>
     */
    private function findComposerBinary(string $base): array
    {
        if ($this->files->exists($base.DIRECTORY_SEPARATOR.'composer.phar')) {
            return [PHP_BINARY, $base.DIRECTORY_SEPARATOR.'composer.phar'];
        }

        return ['composer'];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SUMMARY
    // ══════════════════════════════════════════════════════════════════════

    /**
     * @param  array{copied: int, skipped: list<string>}  $vueResult
     * @param  list<string>  $bindings
     */
    private function printSummary(string $domain, int $backendCount, array $vueResult, array $bindings, bool $noVue, bool $dryRun, bool $autoloadOk = true): void
    {
        $this->newLine();

        $verb = $dryRun ? 'Would eject' : 'Ejected';
        $this->components->info("{$verb} domain {$domain}.");

        $this->components->twoColumnDetail('<fg=green>Backend files</>', (string) $backendCount.' → app/Domain/'.$domain);

        if ($noVue) {
            $this->components->twoColumnDetail('<fg=yellow>Vue pages</>', 'skipped (--no-vue)');
        } else {
            $this->components->twoColumnDetail('<fg=green>Vue files</>', (string) $vueResult['copied']);

            if ($vueResult['skipped'] !== []) {
                $this->components->twoColumnDetail(
                    '<fg=yellow>Vue preserved</>',
                    count($vueResult['skipped']).' existing file(s) left untouched — pass --force to overwrite'
                );
                foreach ($vueResult['skipped'] as $path) {
                    $this->line('  <fg=gray>•</> '.$path);
                }
            }
        }

        if ($bindings !== []) {
            $verb = $dryRun ? 'would inject' : 'injected';
            $this->components->twoColumnDetail('<fg=green>Event bindings</>', count($bindings)." {$verb} into DomainServiceProvider");
            foreach ($bindings as $binding) {
                $this->line('  <fg=gray>→</> '.$binding);
            }
        }

        $aliasVerb = $dryRun ? 'will be' : 'is now';
        $this->components->twoColumnDetail('<fg=gray>Backward-compat alias</>', "{$aliasVerb} disabled for {$domain} (your copy wins)");

        $this->newLine();

        if (! $dryRun) {
            $this->components->warn("You now own app/Domain/{$domain}. The kit will NOT ship security/bugfix updates for this domain to you anymore.");
            $this->line('  <fg=gray>To revert: delete app/Domain/'.$domain.', remove the injected Event::listen lines from</>');
            $this->line('  <fg=gray>app/Providers/DomainServiceProvider.php, then run `composer dump-autoload`.</>');
        }

        if (! $dryRun && ! $autoloadOk) {
            $this->components->error(
                'Autoload regeneration FAILED — files are ejected but the new classes may not load until you run '
                .'`composer dump-autoload`. The command exits non-zero so CI/scripts halt.'
            );
        }

        $this->newLine();
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATH RESOLUTION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Resolve an absolute destination path. With no --destination the path is
     * rooted at base_path() (real consumer app); with --destination it is rooted
     * at the override so tests can eject into an isolated tree. Mirrors
     * PublishCommand::resolveDestination.
     */
    private function resolveDestination(string $relative): string
    {
        $override = $this->option('destination');

        if (! is_string($override) || $override === '') {
            return $relative === '' ? base_path() : base_path($relative);
        }

        $root = rtrim($override, DIRECTORY_SEPARATOR);

        if ($relative === '') {
            return $root;
        }

        return $root.DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
    }
}
