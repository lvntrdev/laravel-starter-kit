<?php

namespace App\Http\Middleware;

use App\Models\ContentLanguage;
use App\Models\Setting;
use Composer\InstalledVersions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Installer routes: minimal shared data (no DB queries)
        if ($request->is('install*')) {
            return [
                ...parent::share($request),
                'appName' => config('app.name'),
                'locale' => app()->getLocale(),
                'availableLocales' => config('app.languages', []),
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ],
            ];
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'appLogo' => fn () => ($logo = Setting::getValue('general.logo')) ? Storage::disk('public')->url($logo) : null,
            'appVersion' => InstalledVersions::getPrettyVersion('lvntr/laravel-starter-kit'),
            // Only share env/debug signals in non-production environments —
            // exposing them to every authenticated user in prod leaks useful
            // fingerprinting info (and advertises that APP_DEBUG is on).
            'appEnv' => fn () => app()->environment('production') ? null : config('app.env'),
            'appDebug' => fn () => app()->environment('production') ? false : (bool) config('app.debug'),
            'locale' => app()->getLocale(),
            'availableLocales' => config('app.languages', []),
            // Content languages are a separate concept from the admin UI locale:
            // they drive multilingual *content* fields (TranslatableInput), not the
            // interface translation. Shared lazily so it's only resolved on demand.
            //
            // Falls back to the configured UI languages on a fresh install /
            // pre-migrate request (the table may not exist yet) instead of
            // throwing. Cached ~1h; the ContentLanguage model flushes the cache
            // on every save/delete, so CRUD reflects immediately.
            'availableContentLocales' => fn () => $this->availableContentLocales(),
            'auth' => [
                'user' => $request->user()?->loadMissing('media'),
                'role' => (function () use ($request) {
                    $role = $request->user()?->roles->first();
                    if (! $role) {
                        return null;
                    }

                    $locale = app()->getLocale();

                    // 1. DB-stored display_name for the current locale
                    if (is_array($role->display_name) && ! empty($role->display_name[$locale])) {
                        return $role->display_name[$locale];
                    }

                    // 2. Config-driven localized name (no DB dependency)
                    $fromConfig = config("permission-resources.display_names.roles.{$role->name}.{$locale}");
                    if (is_string($fromConfig) && $fromConfig !== '') {
                        return $fromConfig;
                    }

                    // 3. Prettify the slug (e.g. "system_admin" → "System Admin")
                    return Str::headline((string) $role->name);
                })(),
                'role_names' => $request->user()?->roles->pluck('name')->values() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'features' => [
                'registration' => Features::enabled(Features::registration()),
                'email_verification' => Features::enabled(Features::emailVerification()),
                'two_factor' => Features::enabled(Features::twoFactorAuthentication()),
                'password_reset' => Features::enabled(Features::resetPasswords()),
            ],
            'turnstile' => [
                'enabled' => (bool) config('services.turnstile.enabled'),
                'site_key' => config('services.turnstile.enabled') ? config('services.turnstile.site_key') : null,
            ],
        ];
    }

    /**
     * Active content languages as a { code: name } map for translatable fields.
     *
     * Falls back to the configured admin UI languages when the table is absent
     * (fresh install / pre-migrate) or empty, so multilingual forms never break.
     *
     * @return array<string, string>
     */
    protected function availableContentLocales(): array
    {
        $fallback = config('app.languages', []);

        // On a cache hit the closure never runs — no per-request schema probe
        // and no DB round-trip. The QueryException catch covers the pre-migrate
        // case (table absent) without the cost of Schema::hasTable every request.
        try {
            $locales = Cache::remember(ContentLanguage::AVAILABLE_CACHE_KEY, 3600, function () {
                return ContentLanguage::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->pluck('name', 'code')
                    ->all();
            });
        } catch (QueryException) {
            return $fallback;
        }

        return empty($locales) ? $fallback : $locales;
    }
}
