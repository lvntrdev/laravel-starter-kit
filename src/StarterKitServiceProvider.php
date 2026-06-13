<?php

namespace Lvntr\StarterKit;

use App\Enums\RoleEnum;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Lvntr\StarterKit\Domain\ActivityLog\Queries\ActivityLogDatatableQuery;
use Lvntr\StarterKit\Domain\ApiClient\Actions\CreateApiClientAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\CreatePersonalAccessTokenAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\RevokeApiClientAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\RevokeApiTokenAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\UpdateApiClientAction;
use Lvntr\StarterKit\Domain\ApiRoute\Actions\RegenerateApiDocsAction;
use Lvntr\StarterKit\Domain\ApiRoute\Actions\SyncApidogAction;
use Lvntr\StarterKit\Domain\ApiRoute\Actions\SyncPostmanAction;
use Lvntr\StarterKit\Domain\ApiRoute\Queries\ApiRouteListQuery;
use Lvntr\StarterKit\Domain\ApiRoute\Support\OpenApiExporter;
use Lvntr\StarterKit\Domain\FileManager\Policies\MediaPolicy;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Lvntr\StarterKit\Domain\Logs\Actions\DeleteLogFilesAction;
use Lvntr\StarterKit\Domain\Logs\DTOs\DeleteLogFilesDTO;
use Lvntr\StarterKit\Domain\Logs\DTOs\LogEntryFilterDTO;
use Lvntr\StarterKit\Domain\Logs\Events\LogFilesDeleted;
use Lvntr\StarterKit\Domain\Logs\Listeners\LogActivityForLogFilesDeleted;
use Lvntr\StarterKit\Domain\Logs\Queries\LogEntryQuery;
use Lvntr\StarterKit\Domain\Logs\Queries\LogFileQuery;
use Lvntr\StarterKit\Domain\Media\Actions\ClearMediaAction;
use Lvntr\StarterKit\Domain\Media\Actions\UploadMediaAction;
use Lvntr\StarterKit\Domain\Role\Actions\CreateRoleAction;
use Lvntr\StarterKit\Domain\Role\Actions\DeleteRoleAction;
use Lvntr\StarterKit\Domain\Role\Actions\SyncPermissionsAction;
use Lvntr\StarterKit\Domain\Role\Actions\UpdateRoleAction;
use Lvntr\StarterKit\Domain\Role\DTOs\RoleDTO;
use Lvntr\StarterKit\Domain\Role\Events\RoleCreated;
use Lvntr\StarterKit\Domain\Role\Events\RoleDeleted;
use Lvntr\StarterKit\Domain\Role\Events\RoleUpdated;
use Lvntr\StarterKit\Domain\Role\Listeners\LogRoleCreated;
use Lvntr\StarterKit\Domain\Role\Listeners\LogRoleDeleted;
use Lvntr\StarterKit\Domain\Role\Listeners\LogRoleUpdated;
use Lvntr\StarterKit\Domain\Role\Queries\CanManageRoleQuery;
use Lvntr\StarterKit\Domain\Role\Queries\GroupedPermissionsQuery;
use Lvntr\StarterKit\Domain\Role\Queries\RoleBulkSelectionQuery;
use Lvntr\StarterKit\Domain\Role\Queries\RoleDatatableQuery;
use Lvntr\StarterKit\Domain\Role\Queries\RoleSelectOptionsQuery;
use Lvntr\StarterKit\Domain\Role\Queries\UserGrantablePermissionsQuery;
use Lvntr\StarterKit\Domain\Session\Actions\PurgeOtherSessionsAction;
use Lvntr\StarterKit\Domain\Session\Queries\UserSessionsQuery;
use Lvntr\StarterKit\Domain\Setting\Actions\SendTestMailAction;
use Lvntr\StarterKit\Domain\Setting\Actions\UpdateAuthSettingsAction;
use Lvntr\StarterKit\Domain\Setting\Actions\UpdateSettingsAction;
use Lvntr\StarterKit\Domain\Setting\DTOs\ApidogSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\AppearanceSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\AuthSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\FileManagerSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\GeneralSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\MailSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\PostmanSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\StorageSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\DTOs\TurnstileSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\Queries\SettingsDefaultsQuery;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Domain\User\Actions\CreateUserAction;
use Lvntr\StarterKit\Domain\User\Actions\DeleteUserAction;
use Lvntr\StarterKit\Domain\User\Actions\UpdateUserAction;
use Lvntr\StarterKit\Domain\User\DTOs\UserDTO;
use Lvntr\StarterKit\Domain\User\Events\UserCreated;
use Lvntr\StarterKit\Domain\User\Events\UserDeleted;
use Lvntr\StarterKit\Domain\User\Events\UserUpdated;
use Lvntr\StarterKit\Domain\User\Listeners\LogUserCreated;
use Lvntr\StarterKit\Domain\User\Listeners\LogUserDeleted;
use Lvntr\StarterKit\Domain\User\Listeners\LogUserUpdated;
use Lvntr\StarterKit\Domain\User\Queries\UserBulkSelectionQuery;
use Lvntr\StarterKit\Domain\User\Queries\UserDatatableQuery;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Exceptions\ApiExceptionHandler;
use Lvntr\StarterKit\Facades\FileManager as FileManagerFacade;
use Lvntr\StarterKit\Http\Middleware\AssignTraceId;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;
use Lvntr\StarterKit\Http\Middleware\SecurityHeaders;
use Lvntr\StarterKit\Http\Middleware\SetLocale;
use Lvntr\StarterKit\Http\Middleware\ValidateTurnstile;
use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Lvntr\StarterKit\Support\HtmlSanitizer;
use Lvntr\StarterKit\Support\MediaPathGenerator;
use Lvntr\StarterKit\Support\Scramble\ApiResponseExtension;
use Lvntr\StarterKit\Support\TranslatableQueryHelpers;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StarterKitServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        // Helper autoload order: load the consumer-published copy FIRST (when
        // present) so its function declarations register before the vendor
        // helper's `function_exists` guards run. The vendor file then fills in
        // any helper the consumer did not override.
        //
        // We load helpers here (instead of via `autoload.files` in
        // composer.json) because Composer would load the vendor copy before
        // the consumer's, and the symlinked `vendor/lvntr/laravel-starter-kit`
        // path makes a `dirname(__DIR__, 4)` walk inside the helper file
        // unreliable for locating the consumer copy. Loading from the
        // ServiceProvider lets us use `base_path()` directly.
        $userHelpers = base_path('app/Helpers/sk-helpers.php');
        if (is_file($userHelpers)) {
            require_once $userHelpers;
        }

        require_once __DIR__.'/sk-helpers.php';

        $this->mergeConfigFrom(__DIR__.'/../config/starter-kit.php', 'starter-kit');

        // Task 6/7 hook: file-manager config will live at
        // config/file-manager.php in the package once that domain is moved
        // vendor-first. Guard with file_exists() so we can land this hook
        // in Task 1 without breaking anything until the file ships.
        $fileManagerConfig = __DIR__.'/../config/file-manager.php';
        if (file_exists($fileManagerConfig)) {
            $this->mergeConfigFrom($fileManagerConfig, 'file-manager');
        }

        // Backward-compatibility aliases: consumer apps generated before
        // v13.5.1 still import these classes from the `App\` namespace; the
        // aliases make those imports resolve to the vendor classes so consumer
        // code keeps working. New installs should import directly from
        // `Lvntr\StarterKit\*`. The tiering (unconditional vs. consumer-
        // overridable) lives in backwardCompatAliasPlan().
        //
        // `base_path()` can be unavailable mid-bootstrap, so fall back to the
        // application instance when the helper is missing.
        $basePath = function_exists('base_path') ? base_path() : $this->app->basePath();

        foreach ($this->backwardCompatAliasPlan($basePath) as $appClass => $vendorClass) {
            if (! class_exists($appClass, false) && ! interface_exists($appClass, false)) {
                class_alias($vendorClass, $appClass);
            }
        }

        // Make the vendor `sk-*` PHP lang files resolvable WITHOUT a namespace
        // (e.g. __('sk-bulk.result')), so the 21 vendor src/ callers keep working
        // after the lang bulk-copy is cut (v15.x Faz 5). Registered in register()
        // — before the translator resolves on the first __() call — by extending
        // the framework's `translation.loader` so the vendor lang dir is inserted
        // BETWEEN the framework defaults and the consumer's app/lang path.
        $this->registerNamespacelessKitTranslations();
    }

    /**
     * Insert the vendor `resources/lang` directory into the translation loader's
     * namespace-less path list so `__('sk-*')` group keys resolve from the package
     * without a `starter-kit::` prefix.
     *
     * Precedence (override invariant): the framework's default loader is built with
     * paths `[frameworkDefaults, app/lang]` and `FileLoader::loadPaths()` merges them
     * with `array_replace_recursive` — LAST path wins. We rebuild the loader with
     * `[frameworkDefaults, vendor/resources/lang, app/lang]`, so a consumer's own
     * `app/lang/{locale}/sk-*.php` override still wins over the vendor copy, while
     * the vendor copy wins over (and falls back to) the framework defaults. Missing
     * app keys fall back to vendor; missing vendor keys fall back to framework.
     *
     * This is the PHP half of the two-consumer lang invariant (the Vite/i18n half
     * lives in stubs/resources/js/app.ts). `validation.php` is intentionally NOT
     * vendor-resident — it stays a consumer-owned framework-default override stub —
     * and the existing `starter-kit::` namespace + JSON registration in
     * registerTranslations() is left untouched.
     */
    private function registerNamespacelessKitTranslations(): void
    {
        $vendorLangPath = __DIR__.'/../resources/lang';

        $this->app->extend('translation.loader', function ($loader, $app) use ($vendorLangPath) {
            // Only reorder a FileLoader (the framework default). Custom loaders are
            // left as-is so we never break a consumer's replacement.
            if (! $loader instanceof FileLoader) {
                return $loader;
            }

            $paths = $loader->paths();

            // Skip if already present (idempotent — defensive against double-extend).
            if (in_array($vendorLangPath, $paths, true)) {
                return $loader;
            }

            // Insert the vendor path just before the LAST entry (the app/lang path),
            // so app overrides keep winning. If the shape is unexpected (no app path),
            // fall back to appending — vendor still resolves, app override unaffected.
            if (count($paths) >= 1) {
                array_splice($paths, count($paths) - 1, 0, [$vendorLangPath]);
            } else {
                $paths[] = $vendorLangPath;
            }

            return new FileLoader($app['files'], $paths);
        });
    }

    /**
     * Plan the `App\` → vendor backward-compatibility class aliases to register
     * for a consumer rooted at `$basePath`. Pure (no `class_alias` side effects)
     * so the decision is unit-testable.
     *
     * Two tiers:
     *
     *  - **Unconditional.** `App\Http\Responses\ApiResponse` has NO valid
     *    consumer override: a real `App\` subclass breaks the return-type
     *    covariance of `DatatableQueryBuilder::response()` (which returns the
     *    vendor type) in query classes — that is exactly why it is an alias,
     *    not an extension point. It is therefore aliased on EVERY boot, early
     *    (before any controller return-type check) and regardless of any file
     *    at the consumer path. That determinism is the fix for the intermittent
     *    post-install "Return value must be of type App\Http\Responses\
     *    ApiResponse, Lvntr\StarterKit\Http\Responses\ApiResponse returned"
     *    TypeError: previously the alias was deferred to a class_alias-only stub
     *    (`app/Http/Responses/ApiResponse.php`) that declares no class, so it is
     *    absent from the optimized classmap and its load — and thus the alias's
     *    existence and timing — depended on PSR-4 fallback + opcache state.
     *
     *  - **Overridable.** The rest may be replaced by a consumer's own `app/`
     *    class; the alias is skipped when such a file exists so the override
     *    wins (otherwise `class_alias` would short-circuit Composer's autoloader
     *    and silently drop the override).
     *
     * Note: PHP traits cannot be safely aliased via class_alias() —
     * HasActivityLogging/HasMediaCollections are NOT here. DatatableQueryBuilder,
     * HttpsOrLocalhostUrl and TurnstileRule ship a thin App\ subclass shim in the
     * scaffold, so they need no alias here either.
     *
     * @return array<class-string, class-string>
     */
    protected function backwardCompatAliasPlan(string $basePath): array
    {
        // Aliased unconditionally — no valid consumer override exists.
        $plan = [
            'App\Http\Responses\ApiResponse' => ApiResponse::class,
        ];

        // Aliased only when the consumer ships no override at that path.
        $overridable = [
            'App\Domain\ActivityLog\Queries\ActivityLogDatatableQuery' => ActivityLogDatatableQuery::class,
            // Faz 6 — ApiClient runtime (Passport secret-handling actions). HTTP
            // layer (controller/request/resource/policy) stays app-owned; only the
            // pure-runtime actions are vendor-resident behind these aliases.
            'App\Domain\ApiClient\Actions\CreateApiClientAction' => CreateApiClientAction::class,
            'App\Domain\ApiClient\Actions\CreatePersonalAccessTokenAction' => CreatePersonalAccessTokenAction::class,
            'App\Domain\ApiClient\Actions\RevokeApiClientAction' => RevokeApiClientAction::class,
            'App\Domain\ApiClient\Actions\RevokeApiTokenAction' => RevokeApiTokenAction::class,
            'App\Domain\ApiClient\Actions\UpdateApiClientAction' => UpdateApiClientAction::class,
            // Faz 6 — ApiRoute runtime (Postman/Apidog sync + OpenAPI export).
            // ApiRouteController stays app-owned (Inertia render + app shim).
            'App\Domain\ApiRoute\Actions\RegenerateApiDocsAction' => RegenerateApiDocsAction::class,
            'App\Domain\ApiRoute\Actions\SyncApidogAction' => SyncApidogAction::class,
            'App\Domain\ApiRoute\Actions\SyncPostmanAction' => SyncPostmanAction::class,
            'App\Domain\ApiRoute\Queries\ApiRouteListQuery' => ApiRouteListQuery::class,
            'App\Domain\ApiRoute\Support\OpenApiExporter' => OpenApiExporter::class,
            'App\Domain\Logs\Actions\DeleteLogFilesAction' => DeleteLogFilesAction::class,
            'App\Domain\Logs\DTOs\DeleteLogFilesDTO' => DeleteLogFilesDTO::class,
            'App\Domain\Logs\DTOs\LogEntryFilterDTO' => LogEntryFilterDTO::class,
            'App\Domain\Logs\Events\LogFilesDeleted' => LogFilesDeleted::class,
            'App\Domain\Logs\Listeners\LogActivityForLogFilesDeleted' => LogActivityForLogFilesDeleted::class,
            'App\Domain\Logs\Queries\LogEntryQuery' => LogEntryQuery::class,
            'App\Domain\Logs\Queries\LogFileQuery' => LogFileQuery::class,
            'App\Domain\Media\Actions\ClearMediaAction' => ClearMediaAction::class,
            'App\Domain\Media\Actions\UploadMediaAction' => UploadMediaAction::class,
            // Faz 6 — Role runtime (Actions/DTO/Events/Listeners/Queries). The Role
            // MODEL (extends Spatie Role), Store/UpdateRoleRequest (privilege-boundary
            // validated()), RoleController, RoleResource and RolePolicy stay app-owned.
            // permission-resources.php and RoleEnum are out of scope (sanctuary).
            // Event/listener registration moves to the vendor registerEventListeners()
            // so the dispatched vendor event matches the binding key (class_alias does
            // not rewrite a `::class` literal). BulkActions/BulkDeleteRoleAction stays
            // app-owned: it extends the app-owned App\Http\BulkActions\BulkDeleteAction
            // override base, so it is not vendor-aliased here (a vendor class with an
            // app-owned parent would fatal under class_alias eager-load).
            'App\Domain\Role\Actions\CreateRoleAction' => CreateRoleAction::class,
            'App\Domain\Role\Actions\DeleteRoleAction' => DeleteRoleAction::class,
            'App\Domain\Role\Actions\SyncPermissionsAction' => SyncPermissionsAction::class,
            'App\Domain\Role\Actions\UpdateRoleAction' => UpdateRoleAction::class,
            'App\Domain\Role\DTOs\RoleDTO' => RoleDTO::class,
            'App\Domain\Role\Events\RoleCreated' => RoleCreated::class,
            'App\Domain\Role\Events\RoleDeleted' => RoleDeleted::class,
            'App\Domain\Role\Events\RoleUpdated' => RoleUpdated::class,
            'App\Domain\Role\Listeners\LogRoleCreated' => LogRoleCreated::class,
            'App\Domain\Role\Listeners\LogRoleDeleted' => LogRoleDeleted::class,
            'App\Domain\Role\Listeners\LogRoleUpdated' => LogRoleUpdated::class,
            'App\Domain\Role\Queries\CanManageRoleQuery' => CanManageRoleQuery::class,
            'App\Domain\Role\Queries\RoleBulkSelectionQuery' => RoleBulkSelectionQuery::class,
            'App\Domain\Role\Queries\GroupedPermissionsQuery' => GroupedPermissionsQuery::class,
            'App\Domain\Role\Queries\RoleDatatableQuery' => RoleDatatableQuery::class,
            'App\Domain\Role\Queries\RoleSelectOptionsQuery' => RoleSelectOptionsQuery::class,
            'App\Domain\Role\Queries\UserGrantablePermissionsQuery' => UserGrantablePermissionsQuery::class,
            'App\Domain\Session\Actions\PurgeOtherSessionsAction' => PurgeOtherSessionsAction::class,
            'App\Domain\Session\Queries\UserSessionsQuery' => UserSessionsQuery::class,
            // Faz 6 — Setting runtime: SettingService (encryption/cache core,
            // config('settings.sensitive_keys') read), Actions, 8 settings DTOs and
            // SettingsDefaultsQuery move to vendor. The Setting MODEL and SettingPolicy
            // stay app-owned (the model is a static facade delegating to SettingService
            // via app(); keeping it app-owned avoids an App\Models\Setting alias and
            // preserves Laravel's App\Models\Setting → App\Policies\SettingPolicy
            // auto-discovery). The SettingService alias is the critical one — the
            // app-owned Setting model and _03_SettingSeeder reference it by App\ FQCN.
            'App\Domain\Setting\SettingService' => SettingService::class,
            'App\Domain\Setting\Actions\SendTestMailAction' => SendTestMailAction::class,
            'App\Domain\Setting\Actions\UpdateAuthSettingsAction' => UpdateAuthSettingsAction::class,
            'App\Domain\Setting\Actions\UpdateSettingsAction' => UpdateSettingsAction::class,
            'App\Domain\Setting\DTOs\ApidogSettingsDTO' => ApidogSettingsDTO::class,
            'App\Domain\Setting\DTOs\AppearanceSettingsDTO' => AppearanceSettingsDTO::class,
            'App\Domain\Setting\DTOs\AuthSettingsDTO' => AuthSettingsDTO::class,
            'App\Domain\Setting\DTOs\FileManagerSettingsDTO' => FileManagerSettingsDTO::class,
            'App\Domain\Setting\DTOs\GeneralSettingsDTO' => GeneralSettingsDTO::class,
            'App\Domain\Setting\DTOs\MailSettingsDTO' => MailSettingsDTO::class,
            'App\Domain\Setting\DTOs\PostmanSettingsDTO' => PostmanSettingsDTO::class,
            'App\Domain\Setting\DTOs\StorageSettingsDTO' => StorageSettingsDTO::class,
            'App\Domain\Setting\DTOs\TurnstileSettingsDTO' => TurnstileSettingsDTO::class,
            'App\Domain\Setting\Queries\SettingsDefaultsQuery' => SettingsDefaultsQuery::class,
            'App\Domain\Shared\Actions\BaseAction' => BaseAction::class,
            'App\Domain\Shared\Contracts\PipeableAction' => PipeableAction::class,
            'App\Domain\Shared\DTOs\BaseDTO' => BaseDTO::class,
            'App\Domain\Shared\Pipelines\ActionPipeline' => ActionPipeline::class,
            'App\Domain\Shared\Services\DefinitionService' => DefinitionService::class,
            // Faz 6 — User runtime (Actions/DTO/Events/Listeners/Queries). The User
            // MODEL (Spatie HasRoles + Fortify contracts), Store/UpdateUserRequest,
            // UserController (Admin + Api), UserResource and UserPolicy stay app-owned.
            // Actions/Fortify/CreateNewUser stays app-owned. Rank-hierarchy behaviour in
            // UserDatatableQuery is byte-identical (relocation only). Event/listener
            // registration moves to the vendor registerEventListeners().
            // BulkActions/BulkDeleteUserAction stays app-owned: it extends the app-owned
            // App\Http\BulkActions\BulkDeleteAction override base, so it is not vendor-
            // aliased here (a vendor class with an app-owned parent would fatal under
            // class_alias eager-load).
            'App\Domain\User\Actions\CreateUserAction' => CreateUserAction::class,
            'App\Domain\User\Actions\DeleteUserAction' => DeleteUserAction::class,
            'App\Domain\User\Actions\UpdateUserAction' => UpdateUserAction::class,
            'App\Domain\User\DTOs\UserDTO' => UserDTO::class,
            'App\Domain\User\Events\UserCreated' => UserCreated::class,
            'App\Domain\User\Events\UserDeleted' => UserDeleted::class,
            'App\Domain\User\Events\UserUpdated' => UserUpdated::class,
            'App\Domain\User\Listeners\LogUserCreated' => LogUserCreated::class,
            'App\Domain\User\Listeners\LogUserDeleted' => LogUserDeleted::class,
            'App\Domain\User\Listeners\LogUserUpdated' => LogUserUpdated::class,
            'App\Domain\User\Queries\UserDatatableQuery' => UserDatatableQuery::class,
            'App\Domain\User\Queries\UserBulkSelectionQuery' => UserBulkSelectionQuery::class,
            'App\Exceptions\ApiException' => ApiException::class,
            'App\Exceptions\ApiExceptionHandler' => ApiExceptionHandler::class,
            'App\Http\Middleware\CheckResourcePermission' => CheckResourcePermission::class,
            'App\Http\Middleware\SecurityHeaders' => SecurityHeaders::class,
            'App\Http\Middleware\AssignTraceId' => AssignTraceId::class,
            'App\Http\Middleware\SetLocale' => SetLocale::class,
            'App\Http\Middleware\ValidateTurnstile' => ValidateTurnstile::class,
            'App\Support\HtmlSanitizer' => HtmlSanitizer::class,
            'App\Support\TranslatableQueryHelpers' => TranslatableQueryHelpers::class,
            'App\Support\MediaPathGenerator' => MediaPathGenerator::class,
            'App\Support\Scramble\ApiResponseExtension' => ApiResponseExtension::class,
            'App\Domain\FileManager\Support\ContextRegistry' => ContextRegistry::class,
            // v13.6.0 — behavior-module HTTP layer moved vendor-first. The
            // Log/ActivityLog/ApiRoute/Settings controllers + their FormRequests
            // now live in Lvntr\StarterKit\Http\...; these aliases keep an older
            // consumer's `App\Http\Controllers\Admin\X` / `App\Http\Requests\Admin\X`
            // imports (and any route file still referencing the App\ FQCN)
            // resolving to the vendor classes. Overridable: the file_exists guard
            // skips the alias when the consumer still ships its own copy (an
            // unmodified copy is removed by sk:update, a modified one keeps
            // winning), and `sk:eject` re-homes them under App\ so the override
            // wins again. FQ string literals (not ::class) so no import churn.
            'App\Http\Controllers\Admin\LogController' => 'Lvntr\StarterKit\Http\Controllers\Admin\LogController',
            'App\Http\Controllers\Admin\ActivityLogController' => 'Lvntr\StarterKit\Http\Controllers\Admin\ActivityLogController',
            'App\Http\Controllers\Admin\ApiRouteController' => 'Lvntr\StarterKit\Http\Controllers\Admin\ApiRouteController',
            'App\Http\Controllers\Admin\SettingsController' => 'Lvntr\StarterKit\Http\Controllers\Admin\SettingsController',
            'App\Http\Requests\Admin\Log\DeleteLogFilesRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Log\DeleteLogFilesRequest',
            'App\Http\Requests\Admin\Log\EntryFilterRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Log\EntryFilterRequest',
            'App\Http\Requests\Admin\Settings\SendTestMailRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\SendTestMailRequest',
            'App\Http\Requests\Admin\Settings\UpdateApidogSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateApidogSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateAppearanceSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateAppearanceSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateAuthSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateAuthSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateFileManagerSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateFileManagerSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateMailSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateMailSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdatePostmanSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdatePostmanSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest',
            'App\Http\Requests\Admin\Settings\UpdateTurnstileSettingsRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateTurnstileSettingsRequest',
            'App\Http\Requests\Admin\Settings\UploadAppearanceLogoRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UploadAppearanceLogoRequest',
            'App\Http\Requests\Admin\Settings\UploadFaviconRequest' => 'Lvntr\StarterKit\Http\Requests\Admin\Settings\UploadFaviconRequest',
        ];

        foreach ($overridable as $appClass => $vendorClass) {
            $relativePath = str_replace('\\', '/', $appClass);
            if (str_starts_with($relativePath, 'App/')) {
                $relativePath = substr($relativePath, 4);
            }

            if (! file_exists($basePath.'/app/'.$relativePath.'.php')) {
                $plan[$appClass] = $vendorClass;
            }
        }

        return $plan;
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->applyVendorConfigDefaults();
        $this->registerRouteModelBindings();
        $this->configureModels();
        $this->configurePassport();
        $this->configureGates();
        $this->configurePolicies();
        $this->configureRateLimiting();
        $this->configureScramble();
        $this->registerCommands();
        $this->registerEventListeners();
        $this->registerTranslations();
        $this->registerPublishables();
        $this->registerMigrations();
        $this->registerViews();

        // Middleware aliases — registered here so new vendor-first installs
        // resolve both alias names to the same vendor class. ServiceProvider
        // boot() runs AFTER bootstrap/app.php's withMiddleware() closure, so
        // even when consumer apps call Bootstrap::middleware() and register
        // 'check.permission' with their own App\Http\Middleware\CheckResourcePermission,
        // the vendor alias re-registration here wins last. Functionally this
        // is safe: the consumer's CheckResourcePermission and the vendor's
        // implementation both delegate to the same Spatie permission table.
        // Consumers needing a true override should bind the vendor class to
        // their own subclass via the container, not via alias re-registration.
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('check.permission', CheckResourcePermission::class);
        $router->aliasMiddleware('check.resource.permission', CheckResourcePermission::class);

        $this->registerRoutes();
        $this->shareInertiaData();
    }

    /**
     * Register cache-safe route-model binders for the FileManager `{media}`
     * and `{folder}` route parameters.
     *
     * SECURITY — trashed media access guard: `{media}` is bound to the
     * CONFIGURED media model (`media-library.media_model`) instead of relying
     * on implicit binding against Spatie's base Media class. The consumer's
     * Media model uses SoftDeletes, so its global scope drops trashed rows
     * from every `{media}` binding site (share show, download, rename, copy,
     * delete) with a 404 — trash means "not accessible" until restore, even
     * for otherwise-valid signed share URLs. On bare installs where the
     * config points at the base (non-SoftDeletes) model the binder is a
     * behavioral no-op: same resolution as implicit binding today.
     *
     * Registered here in boot() — NOT in src/routes/file-manager.php —
     * because `Route::model()` binders are not part of the route cache: under
     * `route:cache` the route files are never loaded, so a binder registered
     * only there silently disappears exactly where it matters most
     * (production). Must run AFTER applyVendorConfigDefaults() so the
     * vendor-supplied `media-library.media_model` default is visible.
     *
     * Note: binders are router-global — any consumer route using a `{media}`
     * or `{folder}` parameter resolves through the same configured models
     * (documented kit-wide semantics).
     */
    private function registerRouteModelBindings(): void
    {
        $mediaModel = config('media-library.media_model');

        if (is_string($mediaModel) && $mediaModel !== '' && is_subclass_of($mediaModel, Model::class)) {
            Route::model('media', $mediaModel);
        }

        $folderModel = config('file-manager.models.folder');

        if (is_string($folderModel) && $folderModel !== '' && is_subclass_of($folderModel, Model::class)) {
            Route::model('folder', $folderModel);
        }
    }

    /**
     * Share file-manager settings with Inertia so Vue components can read them
     * without explicit prop passing.
     */
    private function shareInertiaData(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('fileManagerSettings', fn () => [
            'enable_trash' => (bool) config('file-manager.settings.enable_trash', true),
        ]);
    }

    /**
     * Configure Eloquent strict mode.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Configure Passport token lifetimes + optional scopes.
     */
    private function configurePassport(): void
    {
        if (! class_exists('Laravel\Passport\Passport')) {
            return;
        }

        // Access token TTL: prefer minutes-based config, fall back to the
        // legacy `access_token_days` key when explicitly set.
        $accessMinutes = (int) config('starter-kit.passport.access_token_minutes', 60);
        $legacyAccessDays = config('starter-kit.passport.access_token_days');
        if ($legacyAccessDays !== null && $legacyAccessDays !== '') {
            $accessMinutes = (int) $legacyAccessDays * 24 * 60;
        }

        $refreshDays = (int) config('starter-kit.passport.refresh_token_days', 14);

        // Personal access tokens: prefer days-based config, fall back to
        // the legacy `personal_token_months` key when explicitly set.
        $personalDays = (int) config('starter-kit.passport.personal_token_days', 30);
        $legacyPersonalMonths = config('starter-kit.passport.personal_token_months');
        if ($legacyPersonalMonths !== null && $legacyPersonalMonths !== '') {
            $personalDays = (int) $legacyPersonalMonths * 30;
        }

        // Laravel 11 varsayılan auth.php'de 'api' guard artık yok.
        // Passport::createToken() guard'ı config'den aradığı için bulamazsa
        // LogicException fırlatır. Kullanıcı kendi guard'ını tanımlamışsa dokunma.
        if (! config('auth.guards.api')) {
            config(['auth.guards.api' => [
                'driver' => 'passport',
                'provider' => 'users',
            ]]);
        }

        Passport::tokensExpireIn(now()->addMinutes($accessMinutes));
        Passport::refreshTokensExpireIn(now()->addDays($refreshDays));
        Passport::personalAccessTokensExpireIn(now()->addDays($personalDays));

        $scopes = config('starter-kit.passport.scopes', []);

        if (is_array($scopes) && $scopes !== []) {
            Passport::tokensCan($scopes);

            $defaultScopes = config('starter-kit.passport.default_scopes', []);

            if (is_array($defaultScopes) && $defaultScopes !== []) {
                Passport::setDefaultScope($defaultScopes);
            }
        }
    }

    /**
     * Configure authorization gates.
     */
    private function configureGates(): void
    {
        if (! class_exists('App\Enums\RoleEnum') || ! class_exists('App\Models\User')) {
            return;
        }

        $systemAdminRole = RoleEnum::SystemAdmin;

        Gate::before(function (User $user) use ($systemAdminRole): ?bool {
            return $user->hasRole($systemAdminRole) ? true : null;
        });

        Gate::define('viewPulse', function (User $user) use ($systemAdminRole) {
            return $user->hasRole($systemAdminRole);
        });
    }

    /**
     * FileManager domain share gate'lerini register eder.
     *
     * K4 (security): Gate::policy(Media::class, MediaPolicy::class) kaldırıldı.
     * Policy-based kayıt tüm Media abilities'i (view, delete, update, ...) zorunlu
     * yapar ve MediaPolicy sadece share/revokeShare tanımladığından diğer abilities
     * için false dönüyor — consumer uygulamalarda sessiz erişim regression'ı yaratır.
     *
     * Yerine flat gate tanımları kullanılır. MediaPolicy class'ı internal kullanım için
     * hâlâ mevcuttur; ancak artık Gate'e register edilmez. Flat gate'ler yalnızca
     * kendi ability adlarını etkiler, başka Media abilities'e dokunmaz.
     *
     * Gate::before ile admin kullanıcılar zaten tüm gate'leri atlatır;
     * bu tanımlar non-admin kullanıcılar için ownership'i zorlar.
     */
    private function configurePolicies(): void
    {
        if (! config('file-manager.share.enabled', true)) {
            return;
        }

        $policy = new MediaPolicy;

        Gate::define('share-media', function ($user, Media $media) use ($policy): bool {
            // $user null → guest isteği; auth middleware bunu yakalamış olmalı
            // ama gate seviyesinde de güvenli bir şekilde reddediyoruz.
            if ($user === null) {
                return false;
            }

            return $policy->share($user, $media);
        });

        Gate::define('revoke-share-media', function ($user, Media $media) use ($policy): bool {
            if ($user === null) {
                return false;
            }

            return $policy->revokeShare($user, $media);
        });
    }

    /**
     * Apply the kit's third-party config defaults from vendor.
     *
     * These configs (media-library, activitylog, inertia) are no longer
     * published into the consumer app — the kit ships only the few overrides
     * it requires and applies them at runtime here. `mergeConfigFrom()` cannot
     * be used because the third-party providers already register the same keys
     * and shallow merge never overrides an existing key. Each override is
     * skipped when the consumer published their own copy of that config, so
     * publishing (the optional escape hatch) keeps full control.
     */
    private function applyVendorConfigDefaults(): void
    {
        $configPath = fn (string $file): string => function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/'.$file);

        // media-library: the FileManager Trash feature needs the kit's
        // soft-deletes Media model and the context-aware path generator.
        if (! file_exists($configPath('media-library.php'))) {
            config(['media-library.path_generator' => MediaPathGenerator::class]);

            if (class_exists('App\\Models\\Media')) {
                config(['media-library.media_model' => 'App\\Models\\Media']);
            }
        }

        // activitylog: include soft-deleted subjects in the subject relation.
        if (! file_exists($configPath('activitylog.php'))) {
            config(['activitylog.include_soft_deleted_subjects' => true]);
        }

        // inertia: SSR is opt-in (enable via INERTIA_SSR_ENABLED=true).
        if (! file_exists($configPath('inertia.php'))) {
            config(['inertia.ssr.enabled' => (bool) env('INERTIA_SSR_ENABLED', false)]);
        }
    }

    /**
     * Configure rate limiters.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure Scramble API documentation.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        // Teach Scramble to document the ApiResponse envelope. The extension
        // runs from vendor now, so it is registered here rather than relying
        // on a published config/scramble.php in the consumer app.
        config(['scramble.extensions' => array_values(array_unique(array_merge(
            (array) config('scramble.extensions', []),
            [ApiResponseExtension::class],
        )))]);

        Gate::define('viewApiDocs', function (User $user) {
            return $user->hasPermissionTo('api-docs.read');
        });
    }

    /**
     * Register Artisan commands.
     * Domain commands run from vendor only — they are NOT published to the
     * consumer's app/Console/Commands. Stub copies were removed in v13.5.2;
     * the vendor command registration here is the single source.
     */
    private function registerCommands(): void
    {
        // DoctorCommand is registered unconditionally so that Artisan::call('sk:doctor')
        // works from web requests (e.g. SystemHealthController::run).
        $this->commands([Console\Commands\DoctorCommand::class]);

        if ($this->app->runningInConsole()) {
            $commands = [
                Console\Commands\InstallCommand::class,
                Console\Commands\UpdateCommand::class,
                Console\Commands\UpgradeCommand::class,
                Console\Commands\PublishCommand::class,
                Console\Commands\EjectCommand::class,
                Console\Commands\MakeDomainCommand::class,
                Console\Commands\RemoveDomainCommand::class,
                Console\Commands\EnvSyncCommand::class,
                Console\Commands\SeedPermissionsCommand::class,
            ];

            // Register the vendor PurgeFileManagerTrashCommand only when the
            // consumer app does not define its own version. The signature
            // 'file-manager:purge-trash' must appear exactly once — duplicate
            // registration causes an Artisan conflict exception.
            if (! class_exists('App\\Console\\Commands\\PurgeFileManagerTrash')) {
                $commands[] = Console\Commands\PurgeFileManagerTrashCommand::class;
            }

            $this->commands($commands);
        }
    }

    /**
     * Register vendor-resident domain event listeners.
     *
     * Only listeners whose BOTH event and listener live in vendor
     * (`Lvntr\StarterKit\Domain\*`) belong here — the registration key and the
     * dispatched object's `get_class()` are then the same vendor string, so the
     * dispatcher's string-keyed lookup matches.
     *
     * Why this is NOT in the consumer's DomainServiceProvider for the Logs
     * domain: the Logs event+listener were moved vendor-first, and
     * `DeleteLogFilesAction` (vendor) dispatches the VENDOR `LogFilesDeleted`.
     * On a fresh install the stub provider registered the listener under the
     * `App\Domain\Logs\Events\LogFilesDeleted::class` literal — a plain lexical
     * string that the class_alias never rewrites — so the dispatched vendor
     * object never matched and the audit listener silently never fired. Binding
     * here, with the vendor FQCN on both sides, is the fix.
     *
     * No double-fire risk (applies to every binding below):
     *   - Fresh install: only this vendor binding exists; vendor dispatch → 1 run.
     *     The stub DomainServiceProvider no longer registers these (the App-keyed
     *     Event::listen lines were removed when the domain moved vendor-first).
     *   - Existing consumer that kept its App\ event/listener+action: their
     *     App-keyed registration + App dispatch run once; this vendor binding is
     *     dormant (their App action never dispatches the vendor event).
     *   - Existing consumer reconciled to vendor (App copies removed): the alias
     *     makes the App import resolve to vendor, dispatch is the vendor object,
     *     and only this vendor binding matches — still exactly one run.
     *
     * Faz 6 — User and Role audit events (UserCreated/Updated/Deleted +
     * RoleCreated/Updated/Deleted) moved vendor-first alongside their Log*
     * listeners and their dispatching Create/Update/Delete actions. Their
     * registration moved here from the stub DomainServiceProvider for the SAME
     * reason as Logs: the vendor action dispatches the vendor event, and a stub
     * App-keyed `::class` literal would never match it.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            LogFilesDeleted::class,
            LogActivityForLogFilesDeleted::class,
        );

        // ── User audit events (vendor event + vendor listener) ───────────────
        Event::listen(UserCreated::class, LogUserCreated::class);
        Event::listen(UserUpdated::class, LogUserUpdated::class);
        Event::listen(UserDeleted::class, LogUserDeleted::class);

        // ── Role audit events (vendor event + vendor listener) ───────────────
        Event::listen(RoleCreated::class, LogRoleCreated::class);
        Event::listen(RoleUpdated::class, LogRoleUpdated::class);
        Event::listen(RoleDeleted::class, LogRoleDeleted::class);
    }

    /**
     * Register translation/lang files.
     *
     * Two resolution paths for the SAME vendor `resources/lang` directory:
     *  - Namespaced: __('starter-kit::admin.menu.dashboard') via loadTranslationsFrom.
     *  - Namespace-less: __('sk-bulk.result') via registerNamespacelessKitTranslations()
     *    (called in register(); inserts the vendor lang dir into the loader's path
     *    list before app/lang so consumer overrides win).
     *
     * Users can override by publishing to lang/vendor/starter-kit/ (namespaced) or by
     * placing app/lang/{locale}/sk-*.php (namespace-less, app wins — see register()).
     */
    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'starter-kit');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
    }

    /**
     * Register publishable resources.
     *
     * Tag naming convention: every tag is prefixed with `starter-kit-` so
     * the package never collides with consumer-defined tags. Existing tags
     * (`starter-kit-config`, `starter-kit-lang`, `starter-kit-components`)
     * are kept verbatim because `InstallCommand` already references them;
     * Task 1 only adds new placeholder tags for resources that ship in
     * later tasks (views, migrations, stubs, file-manager subset). All
     * placeholder publishes are guarded by file_exists() / is_dir() so
     * `vendor:publish` does not error out before the source ships.
     */
    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__.'/../config/starter-kit.php' => config_path('starter-kit.php'),
        ], 'starter-kit-config');

        // Lang files (optional publish for customization)
        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/starter-kit'),
        ], 'starter-kit-lang');

        // Vue components (optional publish for customization)
        $this->publishes([
            __DIR__.'/../resources/js/components/Lvntr-Starter-Kit' => resource_path('js/components/Lvntr-Starter-Kit'),
        ], 'starter-kit-components');

        // Task 1 placeholders: registered conditionally so they become
        // active automatically once the source files land in later tasks.
        // No existing publish flow changes today.

        // Blade views (Task 2+ may ship a few server-rendered views)
        $viewsPath = __DIR__.'/../resources/views';
        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path('views/vendor/starter-kit'),
            ], 'starter-kit-views');
        }

        // Migrations (Task 8 will move package migrations here)
        $migrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($migrationsPath)) {
            $this->publishes([
                $migrationsPath => database_path('migrations'),
            ], 'starter-kit-migrations');
        }

        // Stubs (Task 5+ may publish customizable scaffolding stubs)
        $stubsPath = __DIR__.'/../stubs';
        if (is_dir($stubsPath)) {
            $this->publishes([
                $stubsPath => base_path('stubs/starter-kit'),
            ], 'starter-kit-stubs');
        }

        // FileManager domain publishables (Task 6/7)
        $fileManagerConfig = __DIR__.'/../config/file-manager.php';
        if (file_exists($fileManagerConfig)) {
            $this->publishes([
                $fileManagerConfig => config_path('file-manager.php'),
            ], 'starter-kit-file-manager-config');
        }

        $fileManagerComponentsPath = __DIR__.'/../resources/js/components/Lvntr-Starter-Kit/FileManager';
        if (is_dir($fileManagerComponentsPath)) {
            $this->publishes([
                $fileManagerComponentsPath => resource_path('js/components/Lvntr-Starter-Kit/FileManager'),
            ], 'starter-kit-file-manager-components');
        }
    }

    /**
     * Register package migrations.
     *
     * Default behaviour: auto-load migrations from the package so consumer
     * apps inherit FileManager schema without a publish step. Existing apps
     * that already ran these files have their basenames recorded in the
     * `migrations` table — Laravel keys migration history by basename, so
     * the duplicate vendor copy is silently skipped on the next migrate run.
     * Filenames inside database/migrations/ are therefore immutable.
     */
    private function registerMigrations(): void
    {
        if ($this->app->runningInConsole() && config('starter-kit.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * The vendor-mountable module route registry.
     *
     * Each descriptor declares a self-contained route module the package can
     * auto-mount on a fresh install — and that the consumer can override by
     * shipping their own route stub.
     *
     * Descriptor shape:
     *   - name:          Human-readable module key (diagnostics / errors only).
     *   - overrideStubs: Consumer route-file paths (absolute, base_path()).
     *                    If ANY of them exists, the package steps aside — the
     *                    consumer's route orchestrator (routes/web.php /
     *                    routes/api.php) loads it instead, so the package must
     *                    not auto-mount or it would double-register names.
     *   - middleware:    The outer middleware tier the group mounts under.
     *                    This is the SINGLE source of truth for the module's
     *                    auth/permission stack on the auto-mount path.
     *   - loader:        Closure that mounts the vendor route group. Held in
     *                    code (not config) so it survives `config:cache` —
     *                    closures are not serializable, which is exactly why
     *                    the registry lives here and not in config/.
     *
     * Adding a module (Faz 3/6 recipe): append one descriptor here with its
     * own override stubs, middleware tier and a `loader` closure that requires
     * the vendor route file (mirroring FileManager::routes()). registerRoutes()
     * picks it up generically — no further wiring needed.
     *
     * @return array<int, array{
     *     name: string,
     *     overrideStubs: array<int, string>,
     *     middleware: array<int, string>,
     *     loader: \Closure(): void
     * }>
     */
    protected function moduleRouteRegistry(): array
    {
        return [
            [
                'name' => 'file-manager',
                'overrideStubs' => [
                    base_path('routes/web/file-manager-route.php'),
                    base_path('routes/api/file-manager-route.php'),
                ],
                // K1 (security): The public share/show endpoint strips
                // auth+verified via withoutMiddleware() inside the route file
                // itself, so anonymous signed-URL access works even though the
                // group mounts under auth+verified here. No special handling
                // needed at this tier — the route file is self-contained.
                'middleware' => ['web', 'auth', 'verified'],
                'loader' => static function (): void {
                    FileManagerFacade::routes();
                },
            ],
            [
                'name' => 'sk-components',
                // Vendor-resident developer showcase (never published). The
                // override stub does not ship by default; a consumer can create
                // it to take over (or disable) the mount.
                'overrideStubs' => [
                    base_path('routes/web/sk-components-route.php'),
                ],
                // role:system_admin is applied inside the route file itself —
                // this tier only guarantees an authenticated, verified session.
                'middleware' => ['web', 'auth', 'verified'],
                'loader' => static function (): void {
                    require __DIR__.'/routes/sk-components.php';
                },
            ],
        ];
    }

    /**
     * Register vendor module routes from the registry.
     *
     * Override mechanism: when the consumer app already ships a module's
     * override stub (e.g. `routes/web/file-manager-route.php`) the orchestrator
     * in `routes/web.php` / `routes/api.php` loads it directly. In that case
     * the package MUST NOT auto-mount that module — doing so would register the
     * same route names twice and clash with the consumer's customized
     * controller.
     *
     * On a fresh install where no override stub exists, the package mounts the
     * module itself under its declared middleware tier — matching the
     * previously published stub's behaviour 1:1.
     */
    private function registerRoutes(): void
    {
        foreach ($this->moduleRouteRegistry() as $module) {
            // Consumer override: if any override stub is present, the consumer
            // owns the mount (via the stub one-liner that calls the module
            // loader, or a fully customized route file). The orchestrator in
            // routes/web.php picks it up automatically — skip this module.
            $overridden = false;

            foreach ($module['overrideStubs'] as $overrideStub) {
                if (file_exists($overrideStub)) {
                    $overridden = true;

                    break;
                }
            }

            if ($overridden) {
                continue;
            }

            // Fresh install fallback: mount under the module's declared
            // middleware tier so the feature works out of the box without
            // requiring a stub route file in the consumer app.
            Route::middleware($module['middleware'])->group($module['loader']);
        }
    }

    /**
     * Register package views (Blade templates).
     */
    private function registerViews(): void
    {
        $viewPath = __DIR__.'/../resources/views';

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, 'starter-kit');
        }
    }

    /**
     * Get the package base path.
     */
    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__).($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the stubs path.
     */
    public static function stubsPath(string $path = ''): string
    {
        return static::basePath('stubs').($path ? DIRECTORY_SEPARATOR.$path : '');
    }
}
