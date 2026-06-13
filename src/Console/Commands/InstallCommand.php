<?php

namespace Lvntr\StarterKit\Console\Commands;

use App\Models\User;
use Composer\Autoload\ClassLoader;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lvntr\StarterKit\StarterKitServiceProvider;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'sk:install
        {--force : Overwrite existing files}
        {--without-ai-skill : Skip stubs/.claude/skills/ AI skill files}
        {--without-eject : Keep User/Role runtime in vendor (skip default domain eject)}';

    protected $description = 'Install the Lvntr Starter Kit application skeleton (v13.5.0+: package runtime runs from vendor; User + Role are ejected into app/Domain on first install)';

    /**
     * Domains ejected into app/Domain automatically on the FIRST install so the
     * consumer owns the code they edit most. Each name MUST match a key of
     * EjectCommand::DOMAIN_MANIFEST verbatim. User↔Role have no cross-domain
     * references (only their own namespace + the never-ejected Domain\Shared and
     * app-owned classes), so ejecting both leaves no stale vendor link behind.
     *
     * @var list<string>
     */
    private const DEFAULT_EJECT_DOMAINS = ['User', 'Role'];

    private Filesystem $files;

    /** @var list<string> */
    private array $published = [];

    /** @var list<string> */
    private array $skipped = [];

    /**
     * Default domains successfully ejected during this install run. Drives the
     * end-of-install ownership summary; empty when the eject step was skipped
     * (--without-eject or re-install) or every eject failed.
     *
     * @var list<string>
     */
    private array $ejectedDomains = [];

    /**
     * Default Laravel files that conflict with Starter Kit stubs.
     *
     * @var list<string>
     */
    private array $conflictingFiles = [
        'vite.config.js',
        'vite.config.mjs',
        'resources/js/app.js',
        'resources/js/bootstrap.js',
        'resources/views/welcome.blade.php',
        'package-lock.json',
    ];

    /**
     * Paths that may be skipped if they already exist (user-customizable on re-install).
     * Everything NOT in this list will always be overwritten, even without --force.
     *
     * v15.x+ (Faz 5): the kit's `sk-*` UI translations are no longer shipped under
     * stubs/lang — they are vendor-resident (resources/lang/{en,tr}/sk-*.php) and
     * resolved namespace-less at runtime, so publishDirectory never copies them.
     * `lang/` is still preservable because stubs/lang/{en,tr}/validation.php (the
     * consumer-owned framework-default override stub) DOES still ship and must be
     * kept when the consumer has customized it on re-install.
     *
     * @var list<string>
     */
    private array $preservablePaths = [
        'lang/',
    ];

    /**
     * The full .gitignore ignore set, grouped by category.
     *
     * On install these are MERGED into the consumer's existing .gitignore:
     * missing lines are appended under their category header, and lines the
     * project already has (Laravel defaults or user-added) are left untouched.
     *
     * @var array<string, list<string>>
     */
    private array $gitignoreGroups = [
        'OS / Editör' => [
            '.DS_Store',
            'Thumbs.db',
            '.phpactor.json',
            '/.codex',
            '/.cursor/',
            '/.idea',
            '/.nova',
            '/.vscode',
            '/.zed',
        ],
        'Env / Secret' => [
            '.env',
            '.env.*',
            '!.env.example',
            '/auth.json',
            '/storage/*.key',
        ],
        'Bağımlılıklar' => [
            '/node_modules',
            '/vendor',
        ],
        'Log / Cache' => [
            '*.log',
            '.phpunit.result.cache',
            '/.phpunit.cache',
            '/storage/pail',
        ],
        'Laravel build / public' => [
            '/public/build',
            '/public/hot',
            '/public/storage',
            '/public/fonts-manifest.dev.json',
            '_ide_helper.php',
            'Homestead.json',
            'Homestead.yaml',
        ],
        'Starter Kit runtime state (üretilen hash manifesti, ~2MB)' => [
            '/storage/starter-kit/',
        ],
        'Claude Code yerel ayarları' => [
            '.claude/settings.local.json',
        ],
        'Laravel Wayfinder üretimi (actions / routes / helpers)' => [
            '/resources/js/wayfinder/',
            '/resources/js/actions/',
            '/resources/js/routes/',
        ],
        'Vite SSR build çıktısı (Inertia)' => [
            '/bootstrap/ssr',
        ],
        'unplugin otomatik üretilen tip tanımları' => [
            '/auto-imports.d.ts',
            '/components.d.ts',
        ],
        'Derlenmiş JSON çeviriler (kök seviye)' => [
            '/lang/*.json',
        ],
        'Üretilen tema manifesti (sk-theme-build resolver çıktısı)' => [
            '/resources/css/theme/_active.css',
            '/resources/css/theme/.sk-active-theme',
        ],
    ];

    public function handle(): int
    {
        $this->files = new Filesystem;

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Lvntr Starter Kit Installer (v13.6.x)</>');
        $this->newLine();
        $this->line('  <fg=gray>Package runtime runs from vendor/lvntr/laravel-starter-kit.</>');
        $this->line('  <fg=gray>This command copies the application skeleton (auth, layout,</>');
        $this->line('  <fg=gray>user/role/setting domains, config) to your app directory, and on the</>');
        $this->line('  <fg=gray>first install ejects the User + Role runtime into app/Domain so you own it.</>');
        $this->newLine();
        $this->components->info('Installing Lvntr Starter Kit...');
        $this->newLine();

        // 1. Publish stubs first so the kit's .env.example, package.json, and
        // scaffolding are in place before any .env / database wiring runs.
        // On first install (no hash registry), force-copy so preservable paths
        // like lang/ are populated from stubs.
        $isFirstInstall = $this->isFirstInstall();

        $this->step('Publishing application scaffolding', function () use ($isFirstInstall) {
            $stubsPath = StarterKitServiceProvider::stubsPath();
            $this->publishDirectory(
                $stubsPath,
                base_path(),
                $this->option('force') || $isFirstInstall,
            );
        });

        // 1b. Merge package.json (stub wins for shared deps, user extras preserved)
        $this->step('Merging package.json', function () {
            $this->mergePackageJson();
        });

        // 1c. Seed .env from the freshly published .env.example so consumers
        // get every kit key without copying by hand, then generate APP_KEY
        // when it is blank. Runs before the database step so DB_* values are
        // written into an already-seeded .env.
        $this->step('Ensuring .env file', function () use ($isFirstInstall) {
            $this->ensureEnvFile($isFirstInstall);
        });

        // 2. Database configuration (writes DB_* into the now-seeded .env)
        $this->configureDatabaseStep();

        // 3. Remove conflicting default Laravel files
        $this->step('Removing conflicting default files', function () {
            foreach ($this->conflictingFiles as $file) {
                $path = base_path($file);
                if ($this->files->exists($path)) {
                    $this->files->delete($path);
                }
            }
        });

        // 3b. Ensure .gitignore entries — merge the kit's ignore set into the
        // project's existing .gitignore without dropping any current lines.
        $this->step('Ensuring .gitignore entries', function () {
            $this->ensureGitignore();
        });

        // 4. Publish config
        $this->step('Publishing configuration', function () {
            // Core starter-kit config
            $this->callSilently('vendor:publish', [
                '--tag' => 'starter-kit-config',
                '--force' => $this->option('force'),
            ]);

            // FileManager config — vendor runtime reads its defaults from here.
            // Published separately so users can override file-manager.php settings
            // (allowed mime types, max size, model bindings) without touching vendor code.
            $this->callSilently('vendor:publish', [
                '--tag' => 'starter-kit-file-manager-config',
                '--force' => $this->option('force'),
            ]);
        });

        // 4b. Inject required config keys into config/app.php
        $this->step('Configuring application settings', function () {
            $this->injectAppConfig();
        });

        // 4c. Inject DigitalOcean Spaces disk into config/filesystems.php
        $this->step('Configuring filesystem disks', function () {
            $this->injectFilesystemsConfig();
        });

        // 4c-2. Inject Turnstile keys into config/services.php so the kit's
        // CAPTCHA code (middleware, Fortify action, validation rule) can read
        // services.turnstile.* from the TURNSTILE_* env vars.
        $this->step('Configuring third-party services', function () {
            $this->injectServicesConfig();
        });

        // 4d. Wire starter kit bootstrap hooks into bootstrap/app.php
        $this->step('Configuring bootstrap/app.php', function () {
            $this->injectBootstrapApp();
        });

        // 4f. Register starter kit service providers in bootstrap/providers.php
        $this->step('Registering service providers', function () {
            $this->injectBootstrapProviders();
        });

        // 4g. Register custom helpers autoload entry in composer.json
        $this->step('Registering custom helpers autoload', function () {
            $this->injectHelpersAutoload();
        });

        // 4h. Default domain eject (User + Role) on first install only.
        //
        // Ordering rationale:
        //  - AFTER stub publish + provider injection (4f) so DomainServiceProvider
        //    already exists on disk — it is the target the eject injects App-FQCN
        //    Event::listen bindings into.
        //  - BEFORE the autoload regeneration (step 6) so the install's own single
        //    `composer dump-autoload` also picks up the freshly ejected
        //    app/Domain/{User,Role} classes — that is why each eject is invoked with
        //    --skip-autoload (the per-domain dump would be redundant work).
        //  - --no-vue because the Users/Roles Vue pages were already copied by the
        //    stub publish step; eject only needs to relocate the vendor runtime.
        if ($this->shouldEjectDefaultDomains($isFirstInstall)) {
            $this->step('Ejecting default domains (User, Role) to app/Domain', function () {
                $this->ejectDefaultDomains();
            });
        }

        // 5. Create hash registry directory
        $dir = storage_path('starter-kit');
        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        // 6. Regenerate autoload so published classes are available for migrations/seeders
        $this->step('Regenerating autoload', function () {
            $composer = $this->findComposerBinary();
            $process = new Process([...$composer, 'dump-autoload', '-q'], base_path(), null, null, 120);
            $process->run();

            // Reload the in-process autoloader so newly published classes (e.g. App\Enums\RoleEnum)
            // are discoverable during the seeder step that runs in the same PHP process.
            $this->refreshAutoloader();
        });

        // 7. Run migrations
        if ($this->confirmStep('Run database migrations?')) {
            $this->runMigrations();
        }

        // 8. Run seeders
        if ($this->confirmStep('Run database seeders?')) {
            $this->runSeeders();
        }

        // 9. Seed permissions (config-driven, vendor runtime reads from permission-resources.php)
        if ($this->confirmStep('Seed permissions from config/permission-resources.php?')) {
            $this->step('Seeding permissions', function () {
                $this->callSilently('sk:seed-permissions', ['--fresh' => true]);
            });
        }

        // 10. Passport keys + personal access client
        if ($this->confirmStep('Generate Passport encryption keys?')) {
            $this->step('Generating Passport keys', function () {
                $this->callSilently('passport:keys', ['--force' => true]);
            });
            $this->step('Creating Passport personal access client', function () {
                $this->callSilently('passport:client', ['--personal' => true, '--name' => config('app.name').' Personal Access Client', '--provider' => 'users', '--no-interaction' => true]);
            });

            // ROOT CAUSE FIX: passport:client is invoked with --no-interaction,
            // whose configurePrompts() flips the GLOBAL static
            // Laravel\Prompts\Prompt::$interactive to false. Laravel's
            // restorePrompts() only restores the output stream, NOT that flag, so
            // it leaks into every later step — the "Create admin user?" /
            // "Install npm?" confirms would silently auto-accept their default
            // (never asking the operator) and the required admin-detail prompts
            // would throw NonInteractiveValidationException. Re-assert this
            // command's own prompt interactivity before any further prompting.
            $this->configurePrompts($this->input);
        }

        // 11. Create admin user
        if ($this->confirmStep('Create default admin user?')) {
            $this->createAdminUser();
        }

        // 12. Install npm dependencies
        if ($this->confirmStep('Install npm dependencies and build assets?')) {
            $this->installFrontend();
        }

        // 12b. Finalize the application key as the last setup action. ensureAppKey
        // is guarded — it generates APP_KEY only when the .env value is blank, so
        // an existing key (e.g. on re-install) is preserved and already-encrypted
        // data / sessions stay decryptable.
        $this->step('Finalizing application key', function () {
            $this->ensureAppKey(base_path('.env'));
        });

        // 13. Save stub hashes for update tracking
        $this->saveStubHashes();

        // Summary
        $this->newLine();
        $this->components->info('Lvntr Starter Kit installed successfully!');
        $this->newLine();

        if (! empty($this->published)) {
            $this->components->twoColumnDetail('<fg=green>Published</>', count($this->published).' files');
        }
        if (! empty($this->skipped)) {
            $this->components->twoColumnDetail('<fg=yellow>Skipped</>', count($this->skipped).' files (already exist, use --force to overwrite)');
        }

        if ($this->ejectedDomains !== []) {
            $this->newLine();
            $this->components->twoColumnDetail(
                '<fg=green>Ejected domains</>',
                implode(', ', $this->ejectedDomains).' → app/Domain/ (consumer-owned)',
            );
            $this->line('  <fg=gray>The kit no longer ships runtime updates for these domains to you.</>');
            $this->line('  <fg=gray>To revert: delete the directories, remove the injected Event::listen lines</>');
            $this->line('  <fg=gray>from app/Providers/DomainServiceProvider.php, then run `composer dump-autoload`.</>');
            $this->line('  <fg=gray>To keep them in vendor next time: `php artisan sk:install --without-eject`.</>');
        }

        $this->newLine();
        $this->components->warn('Run the following commands to ensure all components work correctly:');
        $this->line('  <fg=cyan>npm install && npm run build</>');
        $this->newLine();

        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════════════
    // STEP RUNNER
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Run a step with simple before/after output (no spinner).
     */
    private function step(string $label, callable $callback): void
    {
        $this->line("  <fg=gray>→</> {$label}...");
        $callback();
        $this->components->twoColumnDetail($label, '<fg=green>DONE</>');
    }

    // ══════════════════════════════════════════════════════════════════════
    // DATABASE CONFIGURATION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Configure database connection interactively.
     */
    private function configureDatabaseStep(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if (! confirm('Configure database connection?', default: true)) {
            return;
        }

        $this->newLine();

        $driver = select(
            label: 'Database driver',
            options: [
                'mysql' => 'MySQL',
                'mariadb' => 'MariaDB',
            ],
            default: 'mysql',
        );

        $envValues = ['DB_CONNECTION' => $driver];

        $host = text(label: 'Database host', default: '127.0.0.1', required: true);
        $port = text(label: 'Database port', default: '3306', required: true);
        $database = text(label: 'Database name', default: 'starter_kit', required: true);
        $username = text(label: 'Database username', default: 'root', required: true);
        $password = text(label: 'Database password', default: '');

        $envValues['DB_HOST'] = $host;
        $envValues['DB_PORT'] = $port;
        $envValues['DB_DATABASE'] = $database;
        $envValues['DB_USERNAME'] = $username;
        $envValues['DB_PASSWORD'] = $password;

        // Write to .env
        $this->updateEnvFile($envValues);

        // Reload config so Laravel picks up the new values
        $this->laravel['config']->set('database.default', $driver);
        $this->laravel['config']->set("database.connections.{$driver}.host", $envValues['DB_HOST']);
        $this->laravel['config']->set("database.connections.{$driver}.port", $envValues['DB_PORT']);
        $this->laravel['config']->set("database.connections.{$driver}.database", $envValues['DB_DATABASE']);
        $this->laravel['config']->set("database.connections.{$driver}.username", $envValues['DB_USERNAME']);
        $this->laravel['config']->set("database.connections.{$driver}.password", $envValues['DB_PASSWORD']);

        // Purge old connection so new config is used
        DB::purge();

        // Test connection
        $this->testDatabaseConnection();

        $this->newLine();
        $this->components->info('Database configured successfully.');
    }

    /**
     * Update values in the .env file.
     *
     * @param  array<string, string>  $values
     */
    private function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');

        if (! $this->files->exists($envPath)) {
            $examplePath = base_path('.env.example');
            if ($this->files->exists($examplePath)) {
                $this->files->copy($examplePath, $envPath);
            } else {
                $this->files->put($envPath, '');
            }
        }

        $content = $this->files->get($envPath);

        foreach ($values as $key => $value) {
            // Wrap value in quotes if it contains spaces or is empty
            $escapedValue = $value;
            if ($value === '' || str_contains($value, ' ') || str_contains($value, '#')) {
                $escapedValue = "\"{$value}\"";
            }

            if (preg_match("/^{$key}=.*/m", $content)) {
                // Replace existing key
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escapedValue}", $content);
            } else {
                // Add new key
                $content .= "\n{$key}={$escapedValue}";
            }
        }

        $this->files->put($envPath, $content);
    }

    /**
     * Ensure the consumer's .env exists and carries every key the kit ships in
     * .env.example.
     *
     * On a fresh install the kit's .env.example becomes the base — any values
     * the user already set (APP_URL, APP_KEY, DB_*) are re-applied on top so
     * nothing critical is lost. On a re-install only keys missing from the
     * existing .env are appended — existing lines and values are never touched.
     * Finally APP_KEY is generated when blank so the app boots.
     */
    private function ensureEnvFile(bool $isFirstInstall = false): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        // Nothing to seed from. The publish step is expected to have placed the
        // kit's .env.example, so reaching here means a malformed install.
        if (! $this->files->exists($examplePath)) {
            return;
        }

        if (! $this->files->exists($envPath) || $isFirstInstall) {
            $this->files->copy($examplePath, $envPath);
        } else {
            $this->mergeMissingEnvKeys($envPath, $examplePath);
        }

        $this->ensureAppKey($envPath);
    }

    /**
     * Append keys present in .env.example but absent from .env, preserving the
     * user's existing lines and values. Comment/blank lines are ignored when
     * detecting keys; the missing lines are copied verbatim so their inline
     * comments and defaults survive.
     */
    private function mergeMissingEnvKeys(string $envPath, string $examplePath): void
    {
        $merged = $this->buildMergedEnvContent(
            $this->files->get($envPath),
            $this->files->get($examplePath),
        );

        if ($merged === null) {
            return;
        }

        $this->files->put($envPath, $merged);
    }

    /**
     * Compute the merged .env body, appending any example key absent from the
     * current .env under a kit header. Returns null when nothing is missing so
     * the caller can skip the write (making the operation idempotent).
     *
     * Pure string-in / string-out — no filesystem access — so it can be unit
     * tested in isolation.
     */
    private function buildMergedEnvContent(string $envContent, string $exampleContent): ?string
    {
        $existing = $this->parseEnvKeys($envContent);
        $exampleLines = preg_split('/\r\n|\r|\n/', $exampleContent) ?: [];

        $missing = [];
        foreach ($exampleLines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                continue;
            }

            $key = trim(explode('=', $trimmed, 2)[0]);

            if ($key !== '' && ! array_key_exists($key, $existing)) {
                $missing[] = $line;
            }
        }

        if ($missing === []) {
            return null;
        }

        return rtrim($envContent, "\n")
            ."\n\n# ---- Lvntr Starter Kit ----\n"
            .implode("\n", $missing)."\n";
    }

    /**
     * Parse an .env body into a set of declared keys, ignoring comment and
     * blank lines.
     *
     * @return array<string, true>
     */
    private function parseEnvKeys(string $content): array
    {
        $keys = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                continue;
            }

            $keys[trim(explode('=', $trimmed, 2)[0])] = true;
        }

        return $keys;
    }

    /**
     * Generate APP_KEY via artisan when the .env value is blank, so a freshly
     * seeded environment boots without a manual `key:generate`.
     */
    private function ensureAppKey(string $envPath): void
    {
        $content = $this->files->get($envPath);

        // A non-empty APP_KEY is already set — leave it untouched.
        if (preg_match('/^APP_KEY=.+$/m', $content)) {
            return;
        }

        $this->callSilently('key:generate', ['--force' => true]);
    }

    /**
     * Test the database connection.
     */
    private function testDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
            $this->components->twoColumnDetail('Connection test', '<fg=green>OK</>');
        } catch (\Exception $e) {
            $this->components->warn('Could not connect to database: '.$e->getMessage());
            $this->line('  <fg=gray>You may need to create the database manually before running migrations.</>');
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUBLISH STUBS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Recursively publish a directory.
     */
    private function publishDirectory(string $source, string $destination, bool $force): void
    {
        if (! $this->files->isDirectory($source)) {
            return;
        }

        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0755, true);
        }

        foreach ($this->files->allFiles($source, true) as $file) {
            $relativePath = $file->getRelativePathname();
            $normalizedPath = str_replace('\\', '/', $relativePath);

            // package.json is merged separately to preserve user-added dependencies.
            if ($normalizedPath === 'package.json') {
                continue;
            }

            // Dependencies, build output, generated type files, and OS junk are
            // regenerated locally and must never reach a consumer app. Guarded here
            // (independent of git / Composer export-ignore) so that path or working-copy
            // installs are protected too.
            if ($this->isIgnoredStubFile($normalizedPath)) {
                continue;
            }

            foreach ($this->getSkipPaths() as $skipPath) {
                if (str_starts_with($normalizedPath, $skipPath)) {
                    continue 2; // skip this file
                }
            }

            $targetPath = $destination.DIRECTORY_SEPARATOR.$relativePath;
            $targetDir = dirname($targetPath);

            if (! $this->files->isDirectory($targetDir)) {
                $this->files->makeDirectory($targetDir, 0755, true);
            }

            if (! $force && $this->isPreservable($relativePath) && $this->files->exists($targetPath)) {
                $this->skipped[] = $relativePath;

                continue;
            }

            // Re-install guard: if hash registry exists and has a record for this file
            // but the target is missing, the user intentionally deleted it — don't restore.
            if (! $force && ! $this->files->exists($targetPath)) {
                $hashFile = config('starter-kit.published_hashes', storage_path('starter-kit/hashes.json'));
                if ($this->files->exists($hashFile)) {
                    $hashes = json_decode($this->files->get($hashFile), true) ?: [];
                    if (isset($hashes[$normalizedPath])) {
                        $this->skipped[] = $relativePath;

                        continue;
                    }
                }
            }

            $this->files->copy($file->getPathname(), $targetPath);
            $this->published[] = $relativePath;
        }
    }

    /**
     * Detect if this is the first install by checking for the hash registry file.
     * The registry is written at the end of install, so its absence means no prior install.
     */
    private function isFirstInstall(): bool
    {
        $hashFile = config('starter-kit.published_hashes', storage_path('starter-kit/hashes.json'));

        return ! $this->files->exists($hashFile);
    }

    // ══════════════════════════════════════════════════════════════════════
    // DEFAULT DOMAIN EJECT (User + Role on first install)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Whether the default domains (User + Role) should be ejected into
     * app/Domain on this run. True only on the first install when the opt-out
     * --without-eject flag is absent. Re-installs ($isFirstInstall === false)
     * and explicit opt-out both return false so the consumer's already-owned
     * app/Domain is never touched.
     *
     * Pure decision logic — no filesystem or eject side effects — so it is unit
     * testable in isolation (mirrors the env/gitignore merge helpers).
     */
    private function shouldEjectDefaultDomains(bool $isFirstInstall): bool
    {
        return $isFirstInstall && ! $this->option('without-eject');
    }

    /**
     * Eject each default domain into app/Domain by delegating to sk:eject.
     *
     * Each call passes:
     *   - --no-vue: the domain's Vue pages were already copied by the stub
     *     publish step, so eject only relocates the vendor backend runtime.
     *   - --skip-autoload: the installer runs ONE composer dump-autoload (step 6)
     *     after this, covering all ejected copies — a per-domain dump would be
     *     wasted work.
     *   - --force: REQUIRED here, and NOT a clobber risk. The stub publish step
     *     (step 1) runs BEFORE this one and ships
     *     stubs/app/Domain/{User,Role}/BulkActions/*, so by the time eject runs
     *     the app/Domain/{Name} directory already exists. sk:eject's
     *     directory-level idempotency guard (EjectCommand::handle():
     *     `! $force && isDirectory(app/Domain/{Name})`) would otherwise fire and
     *     make the eject a SILENT no-op — it warns, but callSilently swallows the
     *     warning, so nothing is copied yet the install would still report the
     *     domain as consumer-owned. --force tells eject to proceed. This is safe
     *     because the vendor source (src/Domain/{Name}) ships only
     *     Actions/DTOs/Events/Listeners/Queries and NO BulkActions: a forced eject
     *     can never overwrite the stub-published BulkActions files — the two file
     *     sets do not overlap.
     *
     * Why the old "never forward --force" assumption was wrong: it treated
     * app/Domain as purely the consumer's own code, but missed that the stub
     * publish step itself creates app/Domain/{Name} (BulkActions only) on a fresh
     * install — so the un-forced eject always no-op'd. The new contract is:
     * pre-existing-runtime gate + --force + post-eject evidence check (below).
     *
     * Pre-existing-runtime gate (the genuine consumer-code protection): BEFORE
     * ejecting, if app/Domain/{Name}/Actions already exists, real runtime — a
     * prior eject or hand-authored code — is present, so the domain is skipped
     * entirely (no --force over it, not added to the owned list). The
     * stub-published BulkActions-only directory has no Actions/ subdir, so a
     * genuinely fresh install passes the gate; real runtime does not.
     *
     * A non-SUCCESS eject only warns (does not abort the install). Because the
     * guard can no longer no-op the eject, SUCCESS is ALSO re-checked against the
     * filesystem: the domain is recorded as ejected only when its runtime
     * (app/Domain/{Name}/Actions) actually materialized — a skipped/no-op eject
     * must never be reported as "ejected". After a verified eject the App-FQCN
     * Event::listen injection is checked against DomainServiceProvider; a silent
     * injection failure (callSilently swallows the eject's own warning) would
     * break the audit log, so it is surfaced here.
     */
    private function ejectDefaultDomains(): void
    {
        foreach (self::DEFAULT_EJECT_DOMAINS as $domain) {
            // Pre-existing-runtime gate: a populated app/Domain/{Name}/Actions
            // means real runtime (a prior eject or hand-written code) is already
            // there — never --force over it; treat the domain as already owned and
            // move on. The stub-published BulkActions-only directory has no
            // Actions/ subdir, so a genuinely fresh install passes this gate.
            if ($this->domainHasRuntime($domain)) {
                $this->components->warn(
                    "app/Domain/{$domain} already contains runtime code — eject skipped to avoid overwriting it. "
                    .'On a fresh install this is unexpected; otherwise the domain is already owned by your app.'
                );

                continue;
            }

            $exitCode = $this->callSilently('sk:eject', [
                'domain' => $domain,
                '--no-vue' => true,
                '--skip-autoload' => true,
                // See the --force rationale in the method docblock: required so the
                // stub-published BulkActions-only directory does not trip eject's
                // idempotency guard; cannot clobber (vendor ships no BulkActions).
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->components->warn(
                    "Eject for {$domain} returned a non-success status — it may not be fully owned by app/Domain. "
                    ."Re-run `php artisan sk:eject {$domain} --no-vue --force` after install to retry."
                );

                continue;
            }

            // Runtime-evidence check: SUCCESS alone is not proof the runtime was
            // copied. Record ownership only when app/Domain/{Name}/Actions truly
            // exists now — never let a no-op masquerade as a completed eject.
            if (! $this->domainHasRuntime($domain)) {
                $this->components->warn(
                    "Eject for {$domain} reported success but app/Domain/{$domain}/Actions was not created — "
                    ."the domain still runs from vendor. Re-run `php artisan sk:eject {$domain} --no-vue --force` to retry."
                );

                continue;
            }

            $this->ejectedDomains[] = $domain;

            $this->verifyEventBindingsInjected($domain);
        }
    }

    /**
     * Whether app/Domain/{Name} already holds real ejected/hand-authored runtime,
     * detected by the presence of its Actions/ subdirectory.
     *
     * Actions/ is the discriminator on purpose: every ejectable domain's vendor
     * source ships an Actions/ folder, whereas the stub publish step seeds only
     * app/Domain/{Name}/BulkActions/* (no Actions/). So this returns false for a
     * freshly stub-published BulkActions-only directory (the eject should proceed)
     * and true once genuine runtime has landed — which is exactly what both the
     * pre-eject gate and the post-eject evidence check need.
     *
     * Pure filesystem probe (no eject side effects) so it is unit testable in
     * isolation, mirroring shouldEjectDefaultDomains().
     */
    private function domainHasRuntime(string $domain): bool
    {
        return $this->files->isDirectory(base_path("app/Domain/{$domain}/Actions"));
    }

    /**
     * Lightweight audit-chain check: confirm the App-FQCN Event::listen bindings
     * the eject was supposed to inject are actually present in
     * app/Providers/DomainServiceProvider.php. callSilently() swallows sk:eject's
     * own warning if injection failed, so without this check a broken audit log
     * would pass install silently. A missing binding only warns (does not abort).
     */
    private function verifyEventBindingsInjected(string $domain): void
    {
        $providerPath = base_path('app/Providers/DomainServiceProvider.php');

        if (! $this->files->exists($providerPath)) {
            $this->components->warn(
                "DomainServiceProvider not found after ejecting {$domain} — audit-log event bindings could not be verified."
            );

            return;
        }

        $code = $this->files->get($providerPath);

        // Marker FQCN per domain; presence implies the eject's boot()-injection ran.
        $marker = match ($domain) {
            'User' => 'App\\Domain\\User\\Events\\UserCreated',
            'Role' => 'App\\Domain\\Role\\Events\\RoleCreated',
            default => null,
        };

        if ($marker === null) {
            return;
        }

        if (! str_contains($code, $marker)) {
            $this->components->warn(
                "Event bindings for {$domain} were not found in DomainServiceProvider — the audit log may not fire for "
                ."{$domain} actions. Add the App\\Domain\\{$domain} Event::listen lines manually, or re-run "
                ."`php artisan sk:eject {$domain} --no-vue --force`."
            );
        }
    }

    /**
     * Merge the stub package.json into the application's package.json.
     *
     * Strategy: stub version wins for shared dependency versions. User-added
     * dependencies (and any extra root-level keys) are preserved.
     */
    private function mergePackageJson(): void
    {
        $stubPath = StarterKitServiceProvider::stubsPath('package.json');
        $targetPath = base_path('package.json');

        if (! $this->files->exists($stubPath)) {
            return;
        }

        if (! $this->files->exists($targetPath)) {
            $this->files->copy($stubPath, $targetPath);

            return;
        }

        /** @var array<string, mixed>|null $stub */
        $stub = json_decode($this->files->get($stubPath), true);
        /** @var array<string, mixed>|null $current */
        $current = json_decode($this->files->get($targetPath), true);

        if (! is_array($stub) || ! is_array($current)) {
            // Malformed JSON — fall back to stub to guarantee a working build.
            $this->files->copy($stubPath, $targetPath);

            return;
        }

        // Stub keys win at the root level; user-added extra keys are preserved.
        $merged = array_merge($current, $stub);

        // For dependency sections, union the two maps so user extras survive
        // while stub versions override any shared dependency versions.
        foreach (['dependencies', 'devDependencies'] as $section) {
            $stubSection = $stub[$section] ?? [];
            $currentSection = $current[$section] ?? [];

            if (! is_array($stubSection) || ! is_array($currentSection)) {
                continue;
            }

            $mergedSection = array_merge($currentSection, $stubSection);
            ksort($mergedSection);
            $merged[$section] = $mergedSection;
        }

        $this->files->put(
            $targetPath,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    /**
     * Locate the composer executable. Falls back to `php composer.phar` or `composer`
     * when a direct binary cannot be resolved from PATH.
     *
     * @return list<string>
     */
    private function findComposerBinary(): array
    {
        if ($this->files->exists(base_path('composer.phar'))) {
            return [PHP_BINARY, base_path('composer.phar')];
        }

        return ['composer'];
    }

    /**
     * Re-register Composer's autoloader in the current process so that classes
     * published during this install (e.g. app/Enums/RoleEnum.php) can be resolved
     * by the seeders that run later in the same PHP request.
     */
    private function refreshAutoloader(): void
    {
        $autoloadPath = base_path('vendor/autoload.php');

        if (! $this->files->exists($autoloadPath)) {
            return;
        }

        // Clear any opcache entries for the regenerated composer autoload files.
        if (function_exists('opcache_invalidate')) {
            foreach (['autoload_classmap.php', 'autoload_psr4.php', 'autoload_static.php', 'autoload_real.php'] as $file) {
                $path = base_path('vendor/composer/'.$file);
                if ($this->files->exists($path)) {
                    @opcache_invalidate($path, true);
                }
            }
        }

        // Re-include the freshly generated classmap/psr4 maps into the active ClassLoader
        // instance so newly published files become discoverable immediately.
        $loaders = ClassLoader::getRegisteredLoaders();
        foreach ($loaders as $vendorDir => $loader) {
            $classMap = $vendorDir.'/composer/autoload_classmap.php';
            if (file_exists($classMap)) {
                $map = require $classMap;
                if (is_array($map)) {
                    $loader->addClassMap($map);
                }
            }
        }
    }

    /**
     * Paths within the stubs directory to skip during publish.
     * Returned as relative paths under stubsPath() with forward slashes.
     *
     * @return list<string>
     */
    private function getSkipPaths(): array
    {
        $skip = [];

        if ($this->option('without-ai-skill')) {
            $skip[] = '.claude/skills/';
        }

        return $skip;
    }

    /**
     * Build/generated/dependency/OS-junk files that must never be copied into a
     * consumer app nor recorded in the hash registry.
     *
     * These are reproduced locally (npm install, vite build, wayfinder/unplugin
     * codegen) and are never the kit's source of truth. Matching is done by
     * directory prefix, exact generated-file name, and `.DS_Store` basename at any
     * depth. `resources/js/env.d.ts` is hand-written and intentionally NOT matched.
     */
    private function isIgnoredStubFile(string $normalizedPath): bool
    {
        $ignoredPrefixes = [
            'node_modules/',
            'public/build/',
            'bootstrap/ssr/',
            'resources/js/routes/',
            'vendor/',
        ];

        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return true;
            }
        }

        // Generated type declarations (unplugin auto-import / components resolver)
        // and the theme resolver's generated manifest (sk-theme-build.mjs output).
        // package-lock.json: the stub-side npm lockfile must never land in a consumer
        // app — installFrontend() regenerates it via `npm install`, and copying the
        // kit's lock would pin the consumer to the kit's exact dependency graph and
        // break `npm ci` against their own package.json.
        $ignoredExact = [
            'auto-imports.d.ts',
            'components.d.ts',
            'resources/css/theme/_active.css',
            'package-lock.json',
        ];

        if (in_array($normalizedPath, $ignoredExact, true)) {
            return true;
        }

        return basename($normalizedPath) === '.DS_Store';
    }

    /**
     * Check if a path is user-customizable and should be preserved on re-install.
     * Only these paths are skipped when the file already exists (without --force).
     * Everything else is always overwritten to ensure a working installation.
     */
    private function isPreservable(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach ($this->preservablePaths as $path) {
            if (str_starts_with($normalized, $path)) {
                return true;
            }
        }

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════
    // GITIGNORE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Ensure the project's .gitignore contains the kit's full ignore set.
     *
     * Reads the existing file (an empty string when absent), merges in any
     * missing entries, and writes back only when something changed.
     */
    private function ensureGitignore(): void
    {
        $path = base_path('.gitignore');

        $existing = $this->files->exists($path) ? $this->files->get($path) : '';

        $merged = $this->buildGitignoreContent($existing);

        if ($merged === $existing) {
            return;
        }

        $this->files->put($path, $merged);
    }

    /**
     * Merge the desired ignore groups into existing .gitignore content.
     *
     * Only entries not already present (compared line-by-line, trimmed) are
     * appended, grouped under their category header. Existing lines — Laravel
     * defaults and any user-added entries — are preserved verbatim. Returns the
     * input unchanged when nothing is missing, which makes the operation
     * idempotent.
     */
    private function buildGitignoreContent(string $existing): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $existing) ?: [];
        $present = array_map('trim', $lines);

        $blocks = [];

        foreach ($this->gitignoreGroups as $label => $entries) {
            $missing = array_values(array_filter(
                $entries,
                static fn (string $entry): bool => ! in_array($entry, $present, true),
            ));

            if ($missing === []) {
                continue;
            }

            $blocks[] = "# ---- {$label} ----\n".implode("\n", $missing);
        }

        if ($blocks === []) {
            return $existing;
        }

        $appendix = implode("\n\n", $blocks)."\n";

        if (trim($existing) === '') {
            return $appendix;
        }

        return rtrim($existing, "\n")."\n\n".$appendix;
    }

    // ══════════════════════════════════════════════════════════════════════
    // MIGRATIONS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Run migrations with existing data check.
     */
    private function runMigrations(): void
    {
        // Check if database has existing tables
        $hasExistingTables = false;

        try {
            $tables = Schema::getTables();
            // Filter out the migrations table itself
            $appTables = array_filter($tables, fn ($table) => ($table['name'] ?? $table) !== 'migrations');
            $hasExistingTables = ! empty($appTables);
        } catch (\Exception) {
            // Connection failed or database doesn't exist — will be handled by migrate
        }

        if ($hasExistingTables) {
            $this->newLine();
            $this->components->warn('The database already contains tables.');

            // Never offer a destructive full reset in production-like environments.
            // Mirrors site:install's guard so a mis-set APP_ENV cannot wipe live
            // data through the installer.
            $allowFresh = ! $this->isProductionLikeEnvironment();

            if (! $allowFresh) {
                $this->line('  <fg=gray>Detected a production-like environment — the destructive "fresh" option is disabled.</>');
            }

            $options = ['migrate' => 'Run pending migrations only (keep existing data)'];

            if ($allowFresh) {
                $options = [
                    'fresh' => 'Drop all tables and run fresh migrations (data will be lost)',
                ] + $options;
            }

            $options['skip'] = 'Skip migrations';

            $action = select(
                label: 'How would you like to proceed?',
                options: $options,
                default: 'migrate',
            );

            if ($action === 'skip') {
                $this->components->info('Migrations skipped.');

                return;
            }

            // `$allowFresh` re-checked as belt-and-suspenders: in production the
            // option is never presented, so select() cannot return it here.
            if ($action === 'fresh' && $allowFresh) {
                if (! confirm('Are you sure? ALL existing data will be permanently deleted.', default: false)) {
                    $this->components->info('Migrations skipped.');

                    return;
                }

                $this->step('Running migrate:fresh', function () {
                    $this->callSilently('migrate:fresh', ['--force' => true]);
                });

                return;
            }
        }

        $this->step('Running migrations', function () {
            $this->callSilently('migrate', ['--force' => true]);
        });
    }

    /**
     * Whether the current environment looks like production, where a destructive
     * full reset (migrate:fresh) must never be offered.
     *
     * Matches 'prod'/'production' as a case-insensitive substring so 'prod',
     * 'production', 'prod-eu', 'my-prod' all trip the guard — mirrors the
     * stubbed site:install command's blocklist philosophy.
     */
    private function isProductionLikeEnvironment(): bool
    {
        $environment = strtolower((string) app()->environment());

        foreach (['prod', 'production'] as $keyword) {
            if (str_contains($environment, $keyword)) {
                return true;
            }
        }

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════
    // SEEDERS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Discover and run seeders from the seeders directory.
     */
    private function runSeeders(): void
    {
        // Reload config files that were published during install
        // (Laravel booted before these files existed, so they're not in the config repository)
        $this->reloadPublishedConfigs();

        $seederPath = database_path('seeders');
        $files = glob($seederPath.'/_*.php');
        sort($files);

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $displayName = preg_replace('/^_\d+_/', '', $className);
            $fqcn = 'Database\\Seeders\\'.$className;

            if (! class_exists($fqcn)) {
                require_once $file;
            }

            if (! class_exists($fqcn)) {
                $this->components->warn("Class [{$fqcn}] not found — skipping.");

                continue;
            }

            $this->step("Seeding: {$displayName}", function () use ($fqcn) {
                $this->callSilently('db:seed', [
                    '--class' => $fqcn,
                    '--force' => true,
                ]);
            });
        }
    }

    /**
     * Reload config files that were published during install.
     * Laravel was already booted before these files existed, so they need to be loaded manually.
     */
    private function reloadPublishedConfigs(): void
    {
        $configPath = config_path();

        foreach ($this->files->files($configPath) as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);

            if (config($key) === null) {
                config([$key => require $file]);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADMIN USER
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Whether this session may prompt the operator for input.
     *
     * Mirrors the exact condition Laravel uses to enable laravel/prompts
     * (Illuminate\Console\Concerns\ConfiguresPrompts): an interactive input AND
     * a real TTY on STDIN (or a unit-test run). A genuinely TTY-less session
     * (--no-interaction, real CI, piped stdin) must fall back to defaults rather
     * than fire a required prompt that would throw NonInteractiveValidationException.
     */
    private function canPrompt(): bool
    {
        if ($this->option('no-interaction')) {
            return false;
        }

        return ($this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN))
            || $this->getLaravel()->runningUnitTests();
    }

    /**
     * Create the admin user.
     *
     * When the session can actually prompt (interactive TTY), every field
     * (first/last name, email, password) is required and prompted with NO
     * pre-filled default — the password is masked and confirmed by re-entry.
     * When it cannot prompt (--no-interaction, or any TTY-less session such as
     * CI / Herd / piped stdin / site:install automation), there is no human to
     * prompt, so the documented fallback credentials are used so the flow still
     * produces a working admin instead of crashing on a required prompt.
     */
    private function createAdminUser(): void
    {
        $firstName = 'Admin';
        $lastName = 'User';
        $email = 'admin@lvntr.dev';
        $password = 'password';
        $usedDefaults = true;

        if ($this->canPrompt()) {
            $usedDefaults = false;

            $firstName = text('Admin first name:', required: true);
            $lastName = text('Admin last name:', required: true);

            $email = text(
                label: 'Admin email:',
                required: true,
                validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL)
                    ? null
                    : 'Geçerli bir e-posta adresi girin.',
            );

            $password = password(
                label: 'Admin password:',
                required: true,
                validate: fn (string $value): ?string => strlen($value) >= 8
                    ? null
                    : 'Şifre en az 8 karakter olmalı.',
            );

            // Re-entry only validates against $password; the value itself is discarded.
            password(
                label: 'Confirm admin password:',
                required: true,
                validate: fn (string $value): ?string => $value === $password
                    ? null
                    : 'Şifreler eşleşmiyor.',
            );
        }

        // Use DB::table directly because the User model loaded in memory
        // is the default Laravel model, not the published stub model.
        $this->step("Creating admin user ({$email})", function () use ($firstName, $lastName, $email, $password) {
            $id = (string) Str::uuid();

            DB::table('users')->insert([
                'id' => $id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign system_admin role if roles table exists
            if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
                $role = DB::table('roles')->where('name', 'system_admin')->first();
                if ($role) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $role->id,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $id,
                    ]);
                }
            }
        });

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Admin Email</>', $email);

        // Never echo a password the operator typed into their terminal. Only the
        // documented --no-interaction fallback password is surfaced, because
        // automation has no other way to learn the generated credentials.
        $this->components->twoColumnDetail(
            '<fg=green>Admin Password</>',
            $usedDefaults ? $password : '<fg=gray>(girdiğiniz şifre)</>',
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // FRONTEND
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Install frontend dependencies and build.
     */
    private function installFrontend(): void
    {
        // Remove old node_modules and lock file to ensure clean install with new package.json
        $nodeModules = base_path('node_modules');
        $lockFile = base_path('package-lock.json');

        if ($this->files->isDirectory($nodeModules)) {
            $this->step('Removing old node_modules', function () use ($nodeModules) {
                $this->files->deleteDirectory($nodeModules);
            });
        }

        if ($this->files->exists($lockFile)) {
            $this->files->delete($lockFile);
        }

        // 1. npm install
        $this->line('  <fg=gray>→</> Installing npm dependencies...');

        $npmInstall = new Process(['npm', 'install'], base_path(), null, null, 300);
        $npmInstall->run();

        if (! $npmInstall->isSuccessful()) {
            $this->components->twoColumnDetail('Installing npm dependencies', '<fg=red>FAILED</>');
            $this->line('  <fg=red>'.$npmInstall->getErrorOutput().'</>');

            return;
        }

        $this->components->twoColumnDetail('Installing npm dependencies', '<fg=green>DONE</>');

        // 2. Clear config/route cache so wayfinder sees fresh routes
        $this->runProcess(['php', 'artisan', 'config:clear'], 'Clearing config cache');
        $this->runProcess(['php', 'artisan', 'route:clear'], 'Clearing route cache');

        // 3. Generate Wayfinder route/action TypeScript files (required for build)
        $this->line('  <fg=gray>→</> Generating Wayfinder types...');

        $wayfinderProcess = new Process(['php', 'artisan', 'wayfinder:generate'], base_path(), null, null, 60);
        $wayfinderProcess->run();

        if (! $wayfinderProcess->isSuccessful()) {
            $this->components->twoColumnDetail('Generating Wayfinder types', '<fg=red>FAILED</>');
            $this->line('  <fg=red>'.$wayfinderProcess->getErrorOutput().'</>');
            $this->newLine();
            $this->components->warn('Wayfinder types could not be generated. Build will fail.');
            $this->line('  Fix the issue, then run:');
            $this->line('  <fg=cyan>php artisan wayfinder:generate && npm run build</>');

            return;
        }

        $this->components->twoColumnDetail('Generating Wayfinder types', '<fg=green>DONE</>');

        // 4. Build frontend
        $this->line('  <fg=gray>→</> Building frontend assets...');

        $npmBuild = new Process(['npm', 'run', 'build'], base_path(), null, null, 300);
        $npmBuild->run();

        if ($npmBuild->isSuccessful()) {
            $this->components->twoColumnDetail('Building frontend assets', '<fg=green>DONE</>');
        } else {
            $this->components->twoColumnDetail('Building frontend assets', '<fg=red>FAILED</>');
            $this->line('  <fg=red>'.$npmBuild->getErrorOutput().'</>');
        }
    }

    /**
     * Run a process silently, only for cache/clear type operations.
     */
    private function runProcess(array $command, string $label): void
    {
        $process = new Process($command, base_path(), null, null, 30);
        $process->run();
    }

    // ══════════════════════════════════════════════════════════════════════
    // APP CONFIG INJECTION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Inject required config keys into config/app.php if not already present.
     */
    private function injectAppConfig(): void
    {
        $configPath = config_path('app.php');

        if (! $this->files->exists($configPath)) {
            return;
        }

        $this->modifyPhpFileAst($configPath, function (array $stmts): bool {
            $array = $this->findConfigRootArray($stmts);

            if ($array === null) {
                return false;
            }

            // Idempotent — skip if already injected.
            if ($this->configArrayHasKey($array, 'available_languages')) {
                return false;
            }

            $array->items[] = new Node\ArrayItem(
                $this->envCallNode('APP_TIMEZONE', 'UTC'),
                new Node\Scalar\String_('display_timezone'),
            );

            $array->items[] = new Node\ArrayItem(
                new Node\Expr\Array_([
                    new Node\ArrayItem(new Node\Scalar\String_('English'), new Node\Scalar\String_('en')),
                    new Node\ArrayItem(new Node\Scalar\String_('Türkçe'), new Node\Scalar\String_('tr')),
                ]),
                new Node\Scalar\String_('available_languages'),
            );

            $array->items[] = new Node\ArrayItem(
                new Node\Expr\Array_([
                    new Node\ArrayItem(new Node\Scalar\String_('English'), new Node\Scalar\String_('en')),
                ]),
                new Node\Scalar\String_('languages'),
            );

            return true;
        });

        // Also set in runtime config so seeders can use it immediately.
        config([
            'app.display_timezone' => 'UTC',
            'app.available_languages' => ['en' => 'English', 'tr' => 'Türkçe'],
            'app.languages' => ['en' => 'English'],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // FILESYSTEMS CONFIG INJECTION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Inject DigitalOcean Spaces disk into config/filesystems.php if not already present.
     */
    private function injectFilesystemsConfig(): void
    {
        $configPath = config_path('filesystems.php');

        if (! $this->files->exists($configPath)) {
            return;
        }

        $this->modifyPhpFileAst($configPath, function (array $stmts): bool {
            $root = $this->findConfigRootArray($stmts);

            if ($root === null) {
                return false;
            }

            $disksItem = $this->findArrayItem($root, 'disks');

            if ($disksItem === null || ! $disksItem->value instanceof Node\Expr\Array_) {
                return false;
            }

            // Idempotent — skip if the 'do' disk is already present.
            if ($this->configArrayHasKey($disksItem->value, 'do')) {
                return false;
            }

            $disksItem->value->items[] = new Node\ArrayItem(
                new Node\Expr\Array_([
                    new Node\ArrayItem(new Node\Scalar\String_('s3'), new Node\Scalar\String_('driver')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_KEY'), new Node\Scalar\String_('key')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_SECRET'), new Node\Scalar\String_('secret')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_REGION'), new Node\Scalar\String_('region')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_BUCKET'), new Node\Scalar\String_('bucket')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_ENDPOINT'), new Node\Scalar\String_('endpoint')),
                    new Node\ArrayItem($this->envCallNode('DO_SPACES_URL'), new Node\Scalar\String_('url')),
                    new Node\ArrayItem(new Node\Scalar\String_('private'), new Node\Scalar\String_('visibility')),
                    new Node\ArrayItem(new Node\Expr\ConstFetch(new Node\Name('false')), new Node\Scalar\String_('throw')),
                    new Node\ArrayItem(new Node\Expr\ConstFetch(new Node\Name('false')), new Node\Scalar\String_('report')),
                ]),
                new Node\Scalar\String_('do'),
            );

            return true;
        });

        // Also set in runtime config so it's available immediately.
        config([
            'filesystems.disks.do' => [
                'driver' => 's3',
                'key' => env('DO_SPACES_KEY'),
                'secret' => env('DO_SPACES_SECRET'),
                'region' => env('DO_SPACES_REGION'),
                'bucket' => env('DO_SPACES_BUCKET'),
                'endpoint' => env('DO_SPACES_ENDPOINT'),
                'url' => env('DO_SPACES_URL'),
                'visibility' => 'private',
                'throw' => false,
                'report' => false,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // SERVICES CONFIG INJECTION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Inject a `turnstile` block into config/services.php if not already present.
     *
     * The kit reads `services.turnstile.*` (enabled / site_key / secret_key /
     * verify_url) from the CAPTCHA middleware, the Fortify ValidateTurnstile
     * action, and the TurnstileRule. Laravel's stock services.php has no such
     * block, so without this the TURNSTILE_* env vars are silently ignored.
     */
    private function injectServicesConfig(): void
    {
        $configPath = config_path('services.php');

        if (! $this->files->exists($configPath)) {
            return;
        }

        $this->modifyPhpFileAst($configPath, function (array $stmts): bool {
            $root = $this->findConfigRootArray($stmts);

            if ($root === null) {
                return false;
            }

            // Idempotent — skip if the 'turnstile' block is already present.
            if ($this->configArrayHasKey($root, 'turnstile')) {
                return false;
            }

            $root->items[] = new Node\ArrayItem(
                new Node\Expr\Array_([
                    new Node\ArrayItem(
                        new Node\Expr\FuncCall(new Node\Name('env'), [
                            new Node\Arg(new Node\Scalar\String_('TURNSTILE_ENABLED')),
                            new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('false'))),
                        ]),
                        new Node\Scalar\String_('enabled'),
                    ),
                    new Node\ArrayItem($this->envCallNode('TURNSTILE_SITE_KEY'), new Node\Scalar\String_('site_key')),
                    new Node\ArrayItem($this->envCallNode('TURNSTILE_SECRET_KEY'), new Node\Scalar\String_('secret_key')),
                    new Node\ArrayItem(
                        $this->envCallNode('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
                        new Node\Scalar\String_('verify_url'),
                    ),
                ]),
                new Node\Scalar\String_('turnstile'),
            );

            return true;
        });

        // Also set in runtime config so it's available immediately.
        config([
            'services.turnstile' => [
                'enabled' => (bool) env('TURNSTILE_ENABLED', false),
                'site_key' => env('TURNSTILE_SITE_KEY'),
                'secret_key' => env('TURNSTILE_SECRET_KEY'),
                'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // BOOTSTRAP INJECTION (format-preserving)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Wire the starter kit into bootstrap/app.php without overwriting the
     * Laravel defaults. Adds:
     *   - `api: __DIR__ . '/../routes/api.php'` to `withRouting()`
     *   - `\Lvntr\StarterKit\Bootstrap::middleware($middleware);` call inside
     *     the `withMiddleware()` closure
     *   - `\Lvntr\StarterKit\Bootstrap::exceptions($exceptions);` call inside
     *     the `withExceptions()` closure
     */
    /**
     * Add `app/Helpers/custom.php` to composer.json `autoload.files` so users
     * can register their own global helpers. Idempotent — skips if already
     * present, also rewrites the legacy `app/helpers.php` entry.
     */
    private function injectHelpersAutoload(): void
    {
        $path = base_path('composer.json');

        if (! $this->files->exists($path)) {
            return;
        }

        $data = json_decode($this->files->get($path), true);

        if (! is_array($data)) {
            return;
        }

        $files = $data['autoload']['files'] ?? [];
        $hasLegacy = in_array('app/helpers.php', $files, true);
        $hasCustom = in_array('app/Helpers/custom.php', $files, true);

        if (! $hasLegacy && $hasCustom) {
            return;
        }

        $files = array_values(array_filter($files, fn ($entry) => $entry !== 'app/helpers.php'));

        if (! $hasCustom) {
            $files[] = 'app/Helpers/custom.php';
        }

        $data['autoload']['files'] = array_values(array_unique($files));

        $this->files->put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        // Dosya yoksa minimal stub oluştur — composer dump-autoload'dan ÖNCE
        // çalıştığı için eksik dosya her artisan çağrısını kırar.
        $helpersPath = base_path('app/Helpers/custom.php');
        if (! $this->files->exists($helpersPath)) {
            $this->files->makeDirectory(dirname($helpersPath), 0755, true, true);
            $this->files->put($helpersPath, "<?php\n");
        }
    }

    private function injectBootstrapApp(): void
    {
        $path = base_path('bootstrap/app.php');

        if (! $this->files->exists($path)) {
            return;
        }

        // Idempotent — the helper reference is the strongest marker.
        if (str_contains($this->files->get($path), 'Lvntr\\StarterKit\\Bootstrap')) {
            return;
        }

        $this->modifyPhpFileAst($path, function (array $stmts): bool {
            $return = null;
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Stmt\Return_) {
                    $return = $stmt;
                    break;
                }
            }

            if ($return === null || ! $return->expr instanceof Node\Expr\MethodCall) {
                return false;
            }

            $changed = false;

            $this->walkMethodChain($return->expr, function (Node\Expr\MethodCall $call) use (&$changed): void {
                if (! $call->name instanceof Node\Identifier) {
                    return;
                }

                match ($call->name->name) {
                    'withRouting' => $this->addApiRouteArg($call, $changed),
                    'withMiddleware' => $this->addBootstrapCall($call, 'middleware', '$middleware', $changed),
                    'withExceptions' => $this->addBootstrapCall($call, 'exceptions', '$exceptions', $changed),
                    default => null,
                };
            });

            return $changed;
        });
    }

    /**
     * Register starter kit providers in bootstrap/providers.php without
     * dropping the user's existing entries.
     */
    private function injectBootstrapProviders(): void
    {
        $path = base_path('bootstrap/providers.php');

        if (! $this->files->exists($path)) {
            return;
        }

        $providers = [
            'App\\Providers\\DomainServiceProvider',
            'App\\Providers\\FortifyServiceProvider',
            'App\\Providers\\SettingsServiceProvider',
        ];

        $this->modifyPhpFileAst($path, function (array $stmts) use ($providers): bool {
            // The shipped stub lists these providers by their short name via
            // `use` imports (e.g. `DomainServiceProvider::class`). Resolving the
            // use-alias map lets us compare against the canonical FQCN so we do
            // not append a fully-qualified duplicate of an already-listed
            // provider — a duplicate would boot the provider twice and register
            // its event listeners twice.
            $useMap = $this->collectUseAliases($stmts);

            $return = null;
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Stmt\Return_) {
                    $return = $stmt;
                    break;
                }
            }

            if ($return === null || ! $return->expr instanceof Node\Expr\Array_) {
                return false;
            }

            $array = $return->expr;
            $existing = $this->collectProviderClassNames($array, $useMap);
            $changed = false;

            foreach ($providers as $fqcn) {
                if (in_array($fqcn, $existing, true)) {
                    continue;
                }

                $array->items[] = new Node\ArrayItem(
                    new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified($fqcn), 'class'),
                );
                $changed = true;
            }

            return $changed;
        });
    }

    /**
     * Walk a left-associative method chain (`foo()->bar()->baz()`) from the
     * outermost call inward, invoking the callback on each MethodCall node.
     */
    private function walkMethodChain(Node\Expr\MethodCall $call, callable $callback): void
    {
        $callback($call);

        if ($call->var instanceof Node\Expr\MethodCall) {
            $this->walkMethodChain($call->var, $callback);
        }
    }

    /**
     * Add `api: __DIR__ . '/../routes/api.php'` to a `withRouting()` call if
     * no `api` named argument exists yet.
     */
    private function addApiRouteArg(Node\Expr\MethodCall $call, bool &$changed): void
    {
        foreach ($call->args as $arg) {
            if ($arg instanceof Node\Arg && $arg->name instanceof Node\Identifier && $arg->name->name === 'api') {
                return;
            }
        }

        $apiValue = new Node\Expr\BinaryOp\Concat(
            new Node\Scalar\MagicConst\Dir,
            new Node\Scalar\String_('/../routes/api.php'),
        );

        $newArg = new Node\Arg($apiValue, name: new Node\Identifier('api'));

        // Keep ordering stable: append after the existing `web:` arg when present,
        // otherwise insert at the front of the argument list.
        $insertAt = count($call->args);
        foreach ($call->args as $index => $arg) {
            if ($arg instanceof Node\Arg && $arg->name instanceof Node\Identifier && $arg->name->name === 'web') {
                $insertAt = $index + 1;
                break;
            }
        }

        array_splice($call->args, $insertAt, 0, [$newArg]);
        $changed = true;
    }

    /**
     * Append `\Lvntr\StarterKit\Bootstrap::{$method}(${$paramName})` as the
     * first statement of the closure passed to `withMiddleware()` / `withExceptions()`.
     */
    private function addBootstrapCall(Node\Expr\MethodCall $call, string $method, string $paramName, bool &$changed): void
    {
        $closure = $call->args[0] ?? null;

        if (! $closure instanceof Node\Arg || ! $closure->value instanceof Node\Expr\Closure) {
            return;
        }

        $paramIdent = ltrim($paramName, '$');

        $bootstrapCall = new Stmt\Expression(
            new Node\Expr\StaticCall(
                new Node\Name\FullyQualified('Lvntr\\StarterKit\\Bootstrap'),
                $method,
                [new Node\Arg(new Node\Expr\Variable($paramIdent))],
            ),
        );

        // Prepend to preserve any user-added statements below it.
        array_unshift($closure->value->stmts, $bootstrapCall);
        $changed = true;
    }

    /**
     * Collect all class names currently listed in a providers array, resolved to
     * their canonical fully-qualified form so a `Foo::class` written against a
     * `use App\Providers\Foo;` import compares equal to `App\Providers\Foo`.
     *
     * @param  array<string, string>  $useMap  alias => FQCN from the file's `use` statements
     * @return list<string>
     */
    private function collectProviderClassNames(Node\Expr\Array_ $array, array $useMap = []): array
    {
        $names = [];

        foreach ($array->items as $item) {
            if (! $item instanceof Node\ArrayItem) {
                continue;
            }

            if ($item->value instanceof Node\Expr\ClassConstFetch
                && $item->value->class instanceof Node\Name
            ) {
                $names[] = $this->resolveClassName($item->value->class, $useMap);
            }
        }

        return $names;
    }

    /**
     * Build an alias => FQCN map from the file's top-level `use` statements
     * (class imports only) so short class references can be resolved.
     *
     * @param  array<Stmt>  $stmts
     * @return array<string, string>
     */
    private function collectUseAliases(array $stmts): array
    {
        $map = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Stmt\Use_ || $stmt->type !== Stmt\Use_::TYPE_NORMAL) {
                continue;
            }

            foreach ($stmt->uses as $use) {
                $fqcn = $use->name->toString();
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $map[$alias] = $fqcn;
            }
        }

        return $map;
    }

    /**
     * Resolve a class-reference name to its canonical FQCN (no leading slash).
     *
     * Fully-qualified names are returned as-is; an unqualified name whose first
     * segment matches a `use` alias is expanded against that import; otherwise
     * the name is returned verbatim.
     *
     * @param  array<string, string>  $useMap
     */
    private function resolveClassName(Node\Name $name, array $useMap): string
    {
        if ($name instanceof Node\Name\FullyQualified) {
            return $name->toString();
        }

        $first = $name->getFirst();

        if (isset($useMap[$first])) {
            $rest = array_slice($name->getParts(), 1);

            return $rest === [] ? $useMap[$first] : $useMap[$first].'\\'.implode('\\', $rest);
        }

        return $name->toString();
    }

    // ══════════════════════════════════════════════════════════════════════
    // HASH REGISTRY
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Save hashes of published stub files for update tracking.
     *
     * v13.5.0+: Only app-owned skeleton stubs are tracked. Vendor-runtime files
     * (Domain/FileManager, Domain/Shared, Traits, Helpers/sk-helpers.php,
     * Http/Responses/ApiResponse.php, Http/Middleware/CheckResourcePermission.php,
     * Exceptions/ApiException.php) are NOT in the stubs directory and therefore
     * never appear in this registry. They run from vendor and need no tracking.
     */
    private function saveStubHashes(): void
    {
        $hashFile = config('starter-kit.published_hashes', storage_path('starter-kit/hashes.json'));
        $hashes = [];

        $stubsPath = StarterKitServiceProvider::stubsPath();
        $skipPaths = $this->getSkipPaths();

        foreach ($this->files->allFiles($stubsPath, true) as $file) {
            $relativePath = $file->getRelativePathname();
            $normalizedPath = str_replace('\\', '/', $relativePath);
            $targetPath = base_path($relativePath);

            // Build/generated/junk files are never published and never tracked —
            // skip entirely so the hash registry does not fill with node_modules etc.
            if ($this->isIgnoredStubFile($normalizedPath)) {
                continue;
            }

            // Mark explicitly-skipped paths (e.g. --without-ai-skill) so sk:update
            // knows not to re-add them. Mirrors the '__deleted__' sentinel pattern.
            $skipped = false;
            foreach ($skipPaths as $skipPath) {
                if (str_starts_with($normalizedPath, $skipPath)) {
                    $skipped = true;
                    break;
                }
            }

            if ($skipped) {
                $hashes[$relativePath] = '__skipped__';

                continue;
            }

            if ($this->files->exists($targetPath)) {
                // Store STUB hash — this is what we shipped, used to detect user modifications
                $hashes[$relativePath] = md5_file($file->getPathname());
            }
        }

        $hashes['_format'] = 'v2';

        $dir = dirname($hashFile);
        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        $this->files->put($hashFile, json_encode($hashes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Confirm a step, auto-accepting in no-interaction mode.
     */
    private function confirmStep(string $question): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        return confirm($question, default: true);
    }

    // ══════════════════════════════════════════════════════════════════════
    // AST HELPERS (format-preserving config editing)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Parse a PHP file, invoke the mutator with the clone-traversed statement
     * list, and write the file back with format-preserving pretty printing
     * only when the mutator reports a change.
     *
     * @param  callable(array<Stmt>): bool  $mutator
     */
    private function modifyPhpFileAst(string $path, callable $mutator): bool
    {
        if (! $this->files->exists($path)) {
            return false;
        }

        $code = $this->files->get($path);

        $parser = (new ParserFactory)->createForHostVersion();

        try {
            $oldStmts = $parser->parse($code);
        } catch (Error) {
            return false;
        }

        if ($oldStmts === null) {
            return false;
        }

        $oldTokens = $parser->getTokens();

        $traverser = new NodeTraverser(new CloningVisitor);
        /** @var array<Stmt> $newStmts */
        $newStmts = $traverser->traverse($oldStmts);

        if (! $mutator($newStmts)) {
            return false;
        }

        $printer = new PrettyPrinter\Standard;
        $newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

        $this->files->put($path, $newCode);

        return true;
    }

    /**
     * Locate the top-level `return [...]` array used by Laravel config files.
     *
     * @param  array<Stmt>  $stmts
     */
    private function findConfigRootArray(array $stmts): ?Node\Expr\Array_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                return $stmt->expr;
            }
        }

        return null;
    }

    /**
     * Check if an Array_ node already contains the given string key.
     */
    private function configArrayHasKey(Node\Expr\Array_ $array, string $key): bool
    {
        return $this->findArrayItem($array, $key) !== null;
    }

    /**
     * Find an ArrayItem by its string key, or null when absent.
     */
    private function findArrayItem(Node\Expr\Array_ $array, string $key): ?Node\ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item instanceof Node\ArrayItem
                && $item->key instanceof Node\Scalar\String_
                && $item->key->value === $key
            ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Build an `env('KEY')` or `env('KEY', 'default')` call expression.
     */
    private function envCallNode(string $key, ?string $default = null): Node\Expr\FuncCall
    {
        $args = [new Node\Arg(new Node\Scalar\String_($key))];

        if ($default !== null) {
            $args[] = new Node\Arg(new Node\Scalar\String_($default));
        }

        return new Node\Expr\FuncCall(new Node\Name('env'), $args);
    }
}
