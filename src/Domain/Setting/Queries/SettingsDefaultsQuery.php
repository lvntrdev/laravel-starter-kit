<?php

namespace Lvntr\StarterKit\Domain\Setting\Queries;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;
use Lvntr\StarterKit\Support\ThemeResolver;

/**
 * Query: Resolve settings with config fallbacks for each group.
 */
class SettingsDefaultsQuery
{
    use ResolvesMediaModel;

    /**
     * Get all settings groups with defaults.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'general' => $this->general(),
            'auth' => $this->auth(),
            'mail' => $this->mail(),
            'storage' => $this->storage(),
            'file_manager' => $this->fileManager(),
            'turnstile' => $this->turnstile(),
            'postman' => $this->postman(),
            'apidog' => $this->apidog(),
            'appearance' => $this->appearance(),
            'storage_usage' => $this->storageUsage(),
        ];
    }

    /**
     * Appearance defaults: the admin-controlled global theme/accent/dark-mode/
     * sidebar plus resolved logo + favicon URLs. `logo_light` falls back to the
     * legacy `general.logo` for apps that set a logo before this group existed.
     *
     * The `theme` value is the *active* theme resolved from the marker file /
     * VITE_SK_THEME / `main` (NOT the raw stored string) so the UI always shows
     * what the build will actually use. `available_themes` is the installed set
     * the Görünüm tab renders as selectable cards.
     *
     * @return array<string, mixed>
     */
    public function appearance(): array
    {
        $stored = Setting::getGroup('appearance');

        $logoLight = $stored['logo_light'] ?? null;
        // logo_light backward-compat: fall back to the legacy general.logo so
        // apps that already set a logo keep showing it under the new group.
        if (! $this->isFilled($logoLight)) {
            $logoLight = Setting::getValue('general.logo');
        }
        $logoDark = $stored['logo_dark'] ?? null;
        $favicon = $stored['favicon'] ?? null;

        return [
            'theme' => ThemeResolver::activeTheme(),
            'available_themes' => ThemeResolver::availableThemes(),
            'accent_color' => $stored['accent_color'] ?? 'default',
            'dark_mode_default' => ($stored['dark_mode_default'] ?? '0') === '1',
            'sidebar_style' => $stored['sidebar_style'] ?? 'colored',
            'logo_light_url' => $this->isFilled($logoLight) ? Storage::disk('public')->url($logoLight) : null,
            'logo_dark_url' => $this->isFilled($logoDark) ? Storage::disk('public')->url($logoDark) : null,
            'favicon_url' => $this->isFilled($favicon) ? Storage::disk('public')->url($favicon) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apidog(): array
    {
        $stored = Setting::getGroup('apidog');
        $accessToken = $stored['access_token'] ?? null;

        return [
            'project_id' => $stored['project_id'] ?? null,
            // Never expose the token; only tell the UI whether one exists.
            'access_token' => null,
            'access_token_is_set' => $this->isFilled($accessToken),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function postman(): array
    {
        $stored = Setting::getGroup('postman');
        $apiKey = $stored['api_key'] ?? null;

        return [
            'workspace_id' => $stored['workspace_id'] ?? null,
            'collection_id' => $stored['collection_id'] ?? null,
            // Never expose the key; only tell the UI whether one exists.
            'api_key' => null,
            'api_key_is_set' => $this->isFilled($apiKey),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function turnstile(): array
    {
        $stored = Setting::getGroup('turnstile');
        $secretKey = $stored['secret_key'] ?? config('services.turnstile.secret_key');

        return [
            'enabled' => ($stored['enabled'] ?? '0') === '1',
            'site_key' => $stored['site_key'] ?? config('services.turnstile.site_key'),
            // Never expose the secret value; only tell the UI whether one exists.
            'secret_key' => null,
            'secret_key_is_set' => $this->isFilled($secretKey),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fileManager(): array
    {
        $stored = Setting::getGroup('file_manager');

        $mimesRaw = $stored['accepted_mimes'] ?? null;
        if (is_string($mimesRaw)) {
            $decoded = json_decode($mimesRaw, true);
            $mimes = is_array($decoded) ? $decoded : [];
        } else {
            $mimes = is_array($mimesRaw) ? $mimesRaw : [];
        }

        // Strip BLOCKED_MIMES (SVG, HTML) from the stored list before
        // handing the payload to the admin UI — older installs may still
        // have them persisted from a previous seeder run, and the update
        // form now rejects them anyway.
        $blocked = ['image/svg+xml', 'image/svg', 'text/html', 'application/xhtml+xml'];
        $mimes = array_values(array_diff(array_map('strval', $mimes), $blocked));

        return [
            'max_size_mb' => (int) ($stored['max_size_mb'] ?? 10),
            'storage_quota_gb' => (int) ($stored['storage_quota_gb'] ?? 10),
            'accepted_mimes' => $mimes,
            'allow_video' => ($stored['allow_video'] ?? '0') === '1',
            'allow_audio' => ($stored['allow_audio'] ?? '0') === '1',
            'enable_trash' => ($stored['enable_trash'] ?? '1') === '1',
            'trash_retention_days' => (int) ($stored['trash_retention_days'] ?? 7),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function general(): array
    {
        $stored = Setting::getGroup('general');
        $defaultLanguages = implode(',', array_keys(config('app.languages', ['en' => 'English'])));

        $languages = explode(',', $stored['languages'] ?? $defaultLanguages);

        $logoPath = $stored['logo'] ?? null;

        return [
            'app_name' => $stored['app_name'] ?? config('app.name'),
            'timezone' => $stored['timezone'] ?? config('app.display_timezone'),
            'languages' => $languages,
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'welcome_message' => $stored['welcome_message'] ?? null,
            'tagline' => $stored['tagline'] ?? null,
            'admin_email' => $stored['admin_email'] ?? config('mail.from.address'),
            'support_email' => $stored['support_email'] ?? null,
            // The configured default must remain a member of the active set;
            // fall back to the first active language otherwise.
            'default_language' => in_array($stored['default_language'] ?? null, $languages, true)
                ? $stored['default_language']
                : ($languages[0] ?? config('app.fallback_locale', 'en')),
            'currency' => $stored['currency'] ?? 'TRY',
            'date_format' => $stored['date_format'] ?? 'd.m.Y',
        ];
    }

    /**
     * Fallbacks for the newer keys deliberately mirror the pre-feature
     * behavior: throttle already active, no expiry, and the kit's
     * hardened password baseline — the old stub AppServiceProvider raised
     * Password::defaults() to min 10 + mixed case + numbers + symbols, so
     * existing installs that never saved the new fields keep that exact
     * policy after sk:update (no silent weakening).
     *
     * @return array<string, bool|int>
     */
    public function auth(): array
    {
        $stored = Setting::getGroup('auth');

        return [
            'registration' => ($stored['registration'] ?? '1') === '1',
            'email_verification' => ($stored['email_verification'] ?? '1') === '1',
            'two_factor' => ($stored['two_factor'] ?? '1') === '1',
            'password_reset' => ($stored['password_reset'] ?? '1') === '1',
            'login_throttle' => ($stored['login_throttle'] ?? '1') === '1',
            'password_min_length' => (int) ($stored['password_min_length'] ?? 10),
            'password_expiry_days' => (int) ($stored['password_expiry_days'] ?? 0),
            'password_require_mixed_case' => ($stored['password_require_mixed_case'] ?? '1') === '1',
            'password_require_numbers' => ($stored['password_require_numbers'] ?? '1') === '1',
            'password_require_symbols' => ($stored['password_require_symbols'] ?? '1') === '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mail(): array
    {
        $stored = Setting::getGroup('mail');
        $password = $stored['password'] ?? config('mail.mailers.smtp.password');

        return [
            'mailer' => $stored['mailer'] ?? config('mail.default'),
            'host' => $stored['host'] ?? config('mail.mailers.smtp.host'),
            'port' => (int) ($stored['port'] ?? config('mail.mailers.smtp.port')),
            'username' => $stored['username'] ?? config('mail.mailers.smtp.username'),
            // Never expose the password; only tell the UI whether one exists.
            'password' => null,
            'password_is_set' => $this->isFilled($password),
            'encryption' => $stored['encryption'] ?? config('mail.mailers.smtp.encryption'),
            'from_address' => $stored['from_address'] ?? config('mail.from.address'),
            'from_name' => $stored['from_name'] ?? config('mail.from.name'),
            'reply_to' => $stored['reply_to'] ?? config('mail.reply_to.address'),
            // 0 = unlimited; the outgoing-mail throttle treats <= 0 as "no cap".
            'send_limit' => (int) ($stored['send_limit'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storage(): array
    {
        $stored = Setting::getGroup('storage');
        $spacesSecret = $stored['spaces_secret'] ?? config('filesystems.disks.do.secret');
        $awsSecret = $stored['aws_secret'] ?? config('filesystems.disks.s3.secret');

        return [
            'media_disk' => $stored['media_disk'] ?? config('media-library.disk_name'),
            'spaces_key' => $stored['spaces_key'] ?? config('filesystems.disks.do.key'),
            // Never expose S3/Spaces secrets; only tell the UI whether one exists.
            'spaces_secret' => null,
            'spaces_secret_is_set' => $this->isFilled($spacesSecret),
            'spaces_region' => $stored['spaces_region'] ?? config('filesystems.disks.do.region'),
            'spaces_bucket' => $stored['spaces_bucket'] ?? config('filesystems.disks.do.bucket'),
            'spaces_endpoint' => $stored['spaces_endpoint'] ?? config('filesystems.disks.do.endpoint'),
            'spaces_url' => $stored['spaces_url'] ?? config('filesystems.disks.do.url'),
            'aws_key' => $stored['aws_key'] ?? config('filesystems.disks.s3.key'),
            'aws_secret' => null,
            'aws_secret_is_set' => $this->isFilled($awsSecret),
            'aws_region' => $stored['aws_region'] ?? config('filesystems.disks.s3.region'),
            'aws_bucket' => $stored['aws_bucket'] ?? config('filesystems.disks.s3.bucket'),
            'aws_url' => $stored['aws_url'] ?? config('filesystems.disks.s3.url'),
            'aws_endpoint' => $stored['aws_endpoint'] ?? config('filesystems.disks.s3.endpoint'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function storageUsage(): array
    {
        return [
            'used_bytes' => $this->computeStorageUsed(),
            'quota_bytes' => $this->storageQuotaBytes(),
        ];
    }

    private function isFilled(mixed $value): bool
    {
        return is_string($value) ? $value !== '' : $value !== null;
    }
}
