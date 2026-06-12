<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Setting\Actions\SendTestMailAction;
use App\Domain\Setting\Actions\UpdateAuthSettingsAction;
use App\Domain\Setting\Actions\UpdateSettingsAction;
use App\Domain\Setting\DTOs\ApidogSettingsDTO;
use App\Domain\Setting\DTOs\AppearanceSettingsDTO;
use App\Domain\Setting\DTOs\AuthSettingsDTO;
use App\Domain\Setting\DTOs\FileManagerSettingsDTO;
use App\Domain\Setting\DTOs\GeneralSettingsDTO;
use App\Domain\Setting\DTOs\MailSettingsDTO;
use App\Domain\Setting\DTOs\PostmanSettingsDTO;
use App\Domain\Setting\DTOs\StorageSettingsDTO;
use App\Domain\Setting\DTOs\TurnstileSettingsDTO;
use App\Domain\Setting\Queries\SettingsDefaultsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\SendTestMailRequest;
use App\Http\Requests\Admin\Settings\UpdateApidogSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateAppearanceSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateAuthSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateFileManagerSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateMailSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdatePostmanSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateTurnstileSettingsRequest;
use App\Http\Requests\Admin\Settings\UploadAppearanceLogoRequest;
use App\Http\Requests\Admin\Settings\UploadFaviconRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Passport;
use Lvntr\StarterKit\Support\ThemeResolver;

/**
 * Admin panel settings controller.
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Data mapping → DTO
 *   - Business logic → Action
 *   - Read queries → Query
 */
class SettingsController extends Controller
{
    /**
     * Display the settings page with all groups.
     */
    public function index(SettingsDefaultsQuery $query, Request $request): Response
    {
        // OAuth/Token tab'ları ve sistem sağlığı yalnızca system_admin için
        // render ediliyor; payload'u şişirmemek için diğer rollerde boş gönder.
        $isSystemAdmin = (bool) $request->user()?->hasRole('system_admin');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $query->all(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'availableLanguages' => config('app.available_languages', ['en' => 'English']),
            'availableScopes' => $isSystemAdmin ? Passport::scopes()->values() : [],
            'healthReport' => $isSystemAdmin ? session('doctor_report') : null,
        ]);
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(UpdateGeneralSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('general', GeneralSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.general'));
    }

    /**
     * Update authentication settings.
     */
    public function updateAuth(UpdateAuthSettingsRequest $request, UpdateAuthSettingsAction $action): RedirectResponse
    {
        $action->execute(AuthSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.auth'));
    }

    /**
     * Update mail settings.
     */
    public function updateMail(UpdateMailSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('mail', MailSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.mail'));
    }

    /**
     * Update storage settings.
     */
    public function updateStorage(UpdateStorageSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('storage', StorageSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.storage'));
    }

    /**
     * Update FileManager settings.
     */
    public function updateFileManager(UpdateFileManagerSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('file_manager', FileManagerSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.file_manager'));
    }

    /**
     * Update turnstile settings.
     */
    public function updateTurnstile(UpdateTurnstileSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('turnstile', TurnstileSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.turnstile'));
    }

    /**
     * Update Postman integration settings.
     */
    public function updatePostman(UpdatePostmanSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('postman', PostmanSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.postman'));
    }

    /**
     * Update Apidog integration settings.
     */
    public function updateApidog(UpdateApidogSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('apidog', ApidogSettingsDTO::fromArray($request->validated()));

        return back()->with('success', __('sk-setting.flash.apidog'));
    }

    /**
     * Update appearance settings (theme, accent, dark-mode default, sidebar).
     *
     * Persists the appearance group, then reconciles the build-time theme
     * marker against the two-layer theme model:
     *
     *   - A RUNTIME theme (main/aura) is applied live via `data-sk-theme` and
     *     needs no rebuild, so the marker is reset to the default (`main`). This
     *     neutralizes the build-time slot layer — coming back from a custom
     *     theme, `_active.css` then resolves deterministically to `main` instead
     *     of leaving a stale custom build active underneath the runtime layer.
     *   - A build-time CUSTOM theme keeps the legacy behavior: write its marker
     *     so the next build resolves it.
     *
     * The theme name is slug-validated both by the FormRequest (must be a
     * member of ThemeResolver::availableThemes()) and by ThemeResolver before
     * the marker write — no traversal value can reach the path-segment file.
     */
    public function updateAppearance(UpdateAppearanceSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $dto = AppearanceSettingsDTO::fromArray($request->validated());

        $action->execute('appearance', $dto);

        // Bridge the DB choice to the build marker the node resolver reads
        // (sk-theme-build.mjs). Runtime themes neutralize the build layer
        // (marker → default); custom themes select themselves for the next
        // build. Validation already restricted $dto->theme to an installed,
        // slug-safe theme; writeMarker re-validates defensively.
        ThemeResolver::writeMarker(
            ThemeResolver::isRuntimeTheme($dto->theme)
                ? ThemeResolver::DEFAULT_THEME
                : $dto->theme
        );

        return back()->with('success', __('sk-setting.flash.appearance'));
    }

    /**
     * Upload application logo.
     */
    public function uploadLogo(Request $request): ApiResponse
    {
        // SVG is intentionally excluded — can embed <script>/onload and execute
        // in the app origin when served from the public disk.
        $request->validate([
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ]);

        // Delete old logo if exists
        $oldLogo = Setting::getValue('general.logo');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        $path = $request->file('logo')->store('logo', 'public');
        Setting::setValue('general.logo', $path);

        return to_api(['logo_url' => Storage::disk('public')->url($path)], __('sk-setting.flash.logo_uploaded'));
    }

    /**
     * Delete application logo.
     */
    public function deleteLogo(): JsonResponse
    {
        $path = Setting::getValue('general.logo');
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Setting::setValue('general.logo', null);

        return to_api(status: 204);
    }

    /**
     * Upload an appearance logo variant (`logo_light` | `logo_dark`).
     *
     * Stored under the `appearance/` directory on the public disk. SVG is
     * intentionally excluded (same rationale as uploadLogo) — it can embed
     * <script>/onload and execute in the app origin when served publicly.
     */
    public function uploadAppearanceLogo(UploadAppearanceLogoRequest $request, string $variant): ApiResponse
    {
        $key = $this->appearanceLogoKey($variant);

        $this->deleteStoredFile($key);

        $path = $request->file('logo')->store('appearance', 'public');
        Setting::setValue($key, $path);

        return to_api(['logo_url' => Storage::disk('public')->url($path)], __('sk-setting.flash.logo_uploaded'));
    }

    /**
     * Delete an appearance logo variant (`logo_light` | `logo_dark`).
     */
    public function deleteAppearanceLogo(string $variant): JsonResponse
    {
        $key = $this->appearanceLogoKey($variant);

        $this->deleteStoredFile($key);
        Setting::setValue($key, null);

        return to_api(status: 204);
    }

    /**
     * Upload the favicon.
     *
     * Accepts png/ico only (no svg, no jpeg/webp — favicons are small raster
     * or .ico). Stored under `appearance/`. `.ico` is not an `image` per the
     * validator's GD check, so the rule is mimes-only, not `image`.
     */
    public function uploadFavicon(UploadFaviconRequest $request): ApiResponse
    {
        $this->deleteStoredFile('appearance.favicon');

        $path = $request->file('favicon')->store('appearance', 'public');
        Setting::setValue('appearance.favicon', $path);

        return to_api(['favicon_url' => Storage::disk('public')->url($path)], __('sk-setting.flash.favicon_uploaded'));
    }

    /**
     * Delete the favicon.
     */
    public function deleteFavicon(): JsonResponse
    {
        $this->deleteStoredFile('appearance.favicon');
        Setting::setValue('appearance.favicon', null);

        return to_api(status: 204);
    }

    /**
     * Resolve the settings key for an appearance logo variant, rejecting any
     * value outside the known set so the variant cannot be used to write an
     * arbitrary settings key.
     */
    private function appearanceLogoKey(string $variant): string
    {
        return match ($variant) {
            'light' => 'appearance.logo_light',
            'dark' => 'appearance.logo_dark',
            default => abort(404),
        };
    }

    /**
     * Delete the file currently referenced by a settings key from the public
     * disk, if it exists. No-op when the key is empty.
     */
    private function deleteStoredFile(string $key): void
    {
        $path = Setting::getValue($key);
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Send a test email using current mail settings.
     */
    public function testMail(SendTestMailRequest $request, SendTestMailAction $action): RedirectResponse
    {
        try {
            $action->execute($request->input('test_email'));

            return back()->with('success', __('sk-setting.flash.test_mail_sent'));
        } catch (\Throwable $e) {
            // SMTP exceptions often include host/username/TLS details. Keep
            // that context in the server log but do not flash it back to
            // the admin — return a generic failure instead.
            Log::error('Test mail failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', __('sk-setting.flash.test_mail_failed'));
        }
    }
}
