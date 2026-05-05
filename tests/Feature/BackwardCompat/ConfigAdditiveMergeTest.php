<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — Config File Additive Merge
|--------------------------------------------------------------------------
|
| The package's two config files (config/starter-kit.php and
| config/file-manager.php) are loaded into the consumer app via
| mergeConfigFrom(). That helper performs a SHALLOW union — only top-level
| keys missing in the consumer's published copy get pulled in from the
| vendor copy. Renaming or removing a key on the vendor side therefore
| silently strips the value out of consumer apps that pinned the older
| key shape.
|
| Strategy:
|   - Assert the publicly-documented key paths still exist on disk.
|   - Assert the runtime config (after the ServiceProvider booted) carries
|     the same keys, so consumer overrides keep merging cleanly.
|   - Assert that key values stay backward-compatible (e.g. run_migrations
|     still defaults to true).
|
*/

it('ships every documented file-manager.php key', function (): void {
    $config = require dirname(__DIR__, 3).'/config/file-manager.php';

    expect($config)->toBeArray();

    // Top-level groups
    expect($config)->toHaveKey('models');
    expect($config)->toHaveKey('settings');

    // Model bindings introduced in Task 5 — frontend / FormRequest /
    // Authorizer all resolve through these keys.
    expect($config['models'])->toHaveKey('folder');
    expect($config['models'])->toHaveKey('favorite');
    expect($config['models'])->toHaveKey('global_bucket');

    // Upload settings — UploadFileRequest reads these.
    // Note: renamed max_size_kb → max_size_mb in v13.6.0, storage_quota_mb → storage_quota_gb in v13.5.2.
    expect($config['settings'])->toHaveKey('max_size_mb');
    expect($config['settings'])->toHaveKey('storage_quota_gb');
    expect($config['settings'])->toHaveKey('accepted_mimes');
    expect($config['settings'])->toHaveKey('allow_video');
    expect($config['settings'])->toHaveKey('allow_audio');

    // Default values — pin the contract for fresh installs.
    expect($config['settings']['max_size_mb'])->toBe(10);
    expect($config['settings']['storage_quota_gb'])->toBe(10);
    expect($config['settings']['accepted_mimes'])->toBeNull();
    expect($config['settings']['allow_video'])->toBeFalse();
    expect($config['settings']['allow_audio'])->toBeFalse();
});

it('ships every documented starter-kit.php key', function (): void {
    $config = require dirname(__DIR__, 3).'/config/starter-kit.php';

    expect($config)->toBeArray();

    // Migration loader switch (Task 8) — flipping the default to false
    // would silently stop loading vendor migrations on `composer update`.
    expect($config)->toHaveKey('run_migrations');
    expect($config['run_migrations'])->toBeTrue('starter-kit.run_migrations default must stay true.');

    // Stub manifest hash storage path (sk:update relies on this).
    expect($config)->toHaveKey('published_hashes');

    // Datatable defaults — DatatableQueryBuilder fall-back contract.
    expect($config)->toHaveKey('datatable');
    expect($config['datatable'])->toHaveKey('default_per_page');
    expect($config['datatable'])->toHaveKey('max_per_page');

    // Passport configuration (consumer apps rely on token TTL keys).
    expect($config)->toHaveKey('passport');
    expect($config['passport'])->toHaveKey('access_token_minutes');
    expect($config['passport'])->toHaveKey('refresh_token_days');
    expect($config['passport'])->toHaveKey('personal_token_days');

    // Legacy passport keys MUST keep existing — they exist for backward
    // compatibility and are documented as "fall back when set".
    expect($config['passport'])->toHaveKey('access_token_days');
    expect($config['passport'])->toHaveKey('personal_token_months');
});

it('merges file-manager config into the runtime container', function (): void {
    // After the ServiceProvider boots, `config('file-manager.*')` must
    // surface every key from the vendor file. mergeConfigFrom() is
    // shallow, so missing top-level keys here would mean the merge is
    // silently dropping the vendor defaults.
    expect(config('file-manager.models.folder'))->not->toBeNull();
    expect(config('file-manager.models.favorite'))->not->toBeNull();
    expect(config('file-manager.models.global_bucket'))->not->toBeNull();
    expect(config('file-manager.settings.max_size_mb'))->toBe(10);
    expect(config('file-manager.settings.storage_quota_gb'))->toBe(10);
});

it('merges starter-kit config into the runtime container', function (): void {
    expect(config('starter-kit.run_migrations'))->toBeTrue();
    expect(config('starter-kit.datatable.default_per_page'))->toBeInt();
    expect(config('starter-kit.datatable.max_per_page'))->toBeInt();
});

it('does not rename historically-stable starter-kit config keys', function (): void {
    // Capture the union of every shipped top-level key. Removing or
    // renaming any of these would break consumer apps whose published
    // config still references the old name.
    $config = require dirname(__DIR__, 3).'/config/starter-kit.php';

    $stableTopLevelKeys = [
        'run_migrations',
        'version',
        'published_hashes',
        'datatable',
        'app_namespace',
        'passport',
    ];

    foreach ($stableTopLevelKeys as $key) {
        expect(array_key_exists($key, $config))->toBeTrue(
            "Top-level config key `starter-kit.{$key}` was removed or renamed. "
            .'Consumer apps with a published config copy would lose this value.'
        );
    }
});
