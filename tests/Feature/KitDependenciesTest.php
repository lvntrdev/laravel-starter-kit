<?php

use Lvntr\StarterKit\Support\KitDependencies;

test('returns an array', function () {
    expect(KitDependencies::missing())->toBeArray();
});

test('an installed kit dependency never appears as missing', function () {
    expect(KitDependencies::missing())->not->toContain('dedoc/scramble');
});

test('php and ext-* entries are never reported as missing packages', function () {
    $missing = KitDependencies::missing();

    expect($missing)->not->toContain('php');

    foreach ($missing as $name) {
        expect($name)->not->toStartWith('ext-');
    }
});

test('rootPinned reports kit-managed lvntr packages the root composer.json requires directly', function () {
    $path = tempnam(sys_get_temp_dir(), 'sk_root_composer_');
    file_put_contents($path, json_encode([
        'require' => ['laravel/framework' => '^13.0', 'lvntr/laravel-starter-kit' => '^13.8'],
        'require-dev' => ['lvntr/api-dock' => '^0.0.3'],
    ]));

    try {
        expect(KitDependencies::rootPinned($path))->toBe(['lvntr/api-dock']);
    } finally {
        unlink($path);
    }
});

test('rootPinned is empty when the root composer.json is missing or clean', function () {
    expect(KitDependencies::rootPinned('/nonexistent/composer.json'))->toBe([]);

    $path = tempnam(sys_get_temp_dir(), 'sk_root_composer_');
    file_put_contents($path, json_encode(['require' => ['lvntr/laravel-starter-kit' => '^13.8']]));

    try {
        expect(KitDependencies::rootPinned($path))->toBe([]);
    } finally {
        unlink($path);
    }
});
