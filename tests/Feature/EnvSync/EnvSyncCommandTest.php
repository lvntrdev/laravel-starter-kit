<?php

/*
|--------------------------------------------------------------------------
| EnvSyncCommand — idempotency regression (madde 36)
|--------------------------------------------------------------------------
|
| Running `env:sync` twice (in separate command invocations, i.e. two
| separate ".env has new keys" moments) must not stack a second
| "# Auto-added keys" header block on top of the first — the header is
| written once; subsequent runs append their missing keys without
| repeating it.
*/

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

function envSyncFixture(string $env, string $example): void
{
    $fs = new Filesystem;
    $fs->put(base_path('.env'), $env);
    $fs->put(base_path('.env.example'), $example);
}

afterEach(function (): void {
    $fs = new Filesystem;
    foreach (['.env', '.env.example'] as $relative) {
        $target = base_path($relative);
        if ($fs->exists($target)) {
            $fs->delete($target);
        }
    }
});

test('env:sync ilk çalıştırmada eksik key için tek "# Auto-added keys" başlığı ekler', function () {
    envSyncFixture(
        env: "APP_NAME=Test\nAPP_KEY=base64:abc\nFEATURE_FLAG_ENABLED=true\n",
        example: "APP_NAME=Test\nAPP_KEY=\n",
    );

    Artisan::call('env:sync');

    $content = file_get_contents(base_path('.env.example'));

    expect(substr_count($content, '# Auto-added keys'))->toBe(1)
        ->and($content)->toContain('FEATURE_FLAG_ENABLED=true');
});

test('env:sync iki kez ardışık koşulduğunda ikinci koşum yeni bir başlık bloğu biriktirmez', function () {
    envSyncFixture(
        env: "APP_NAME=Test\nAPP_KEY=base64:abc\nFIRST_FLAG=one\n",
        example: "APP_NAME=Test\nAPP_KEY=\n",
    );

    // 1) FIRST_FLAG eksik → başlık + key eklenir.
    Artisan::call('env:sync');

    // Aradaki .env yeni bir key kazanır (ikinci "gerçek koşum" senaryosu).
    file_put_contents(base_path('.env'), "APP_NAME=Test\nAPP_KEY=base64:abc\nFIRST_FLAG=one\nSECOND_FLAG=two\n");

    // 2) SECOND_FLAG eksik → mevcut blok başlığı TEKRARLANMAMALI.
    Artisan::call('env:sync');

    $content = file_get_contents(base_path('.env.example'));

    expect(substr_count($content, '# Auto-added keys'))->toBe(1)
        ->and($content)->toContain('FIRST_FLAG=one')
        ->and($content)->toContain('SECOND_FLAG=two');
});

test('env:sync eksik key yokken .env.example dosyasını değiştirmez', function () {
    envSyncFixture(
        env: "APP_NAME=Test\nAPP_KEY=base64:abc\n",
        example: "APP_NAME=Test\nAPP_KEY=\n",
    );

    $before = file_get_contents(base_path('.env.example'));

    Artisan::call('env:sync');

    $after = file_get_contents(base_path('.env.example'));

    expect($after)->toBe($before);
});
