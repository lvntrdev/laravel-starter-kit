<?php

use Illuminate\Support\Facades\Http;
use Lvntr\StarterKit\Support\KitDependencies;

/*
| The `-W` trap: Composer keeps the kit's locked dependencies and quietly
| installs an older kit. heldBackBy() spots it from Packagist; the
| `composer sk-update` script removes the need to remember `-W` at all.
*/

function fakePackagistReleases(array $versions): void
{
    Http::fake([
        'repo.packagist.org/*' => Http::response([
            'packages' => [
                'lvntr/laravel-starter-kit' => array_map(fn (string $v) => ['version' => $v], $versions),
            ],
        ]),
    ]);
}

test('heldBackBy names the newest stable release on the installed major line', function () {
    fakePackagistReleases(['v14.0.0', 'v13.9.0-beta1', 'v13.8.4', 'v13.8.2', 'v13.8.1']);

    expect(KitDependencies::heldBackBy('v13.8.1'))->toBe('v13.8.4')
        ->and(KitDependencies::heldBackBy('v13.8.4'))->toBeNull();
});

test('heldBackBy stays silent offline and for untagged installs', function () {
    Http::fake(['repo.packagist.org/*' => Http::response('', 503)]);
    expect(KitDependencies::heldBackBy('v13.8.1'))->toBeNull();

    Http::fake();
    expect(KitDependencies::heldBackBy('dev-main'))->toBeNull();
    Http::assertNothingSent();
});

test('ensureUpdateScript adds sk-update once, keeps empty sections and never overwrites a user script', function () {
    $path = tempnam(sys_get_temp_dir(), 'sk_root_composer_');
    file_put_contents($path, '{"require": {"php": "^8.4"}, "require-dev": {}, "scripts": {"test": "pest"}}');

    try {
        expect(KitDependencies::ensureUpdateScript($path))->toBeTrue()
            ->and(KitDependencies::ensureUpdateScript($path))->toBeFalse();

        $written = (string) file_get_contents($path);
        $decoded = json_decode($written, true);

        expect($written)->toContain('"require-dev": {}')
            ->and($decoded['scripts']['test'])->toBe('pest')
            ->and($decoded['scripts']['sk-update'])->toBe([
                'Composer\\Config::disableProcessTimeout',
                '@composer update lvntr/laravel-starter-kit -W',
                '@php artisan sk:update --ansi',
            ]);

        file_put_contents($path, '{"scripts": {"sk-update": "my-own"}}');
        expect(KitDependencies::ensureUpdateScript($path))->toBeFalse()
            ->and(json_decode((string) file_get_contents($path), true)['scripts']['sk-update'])->toBe('my-own');
    } finally {
        unlink($path);
    }
});
