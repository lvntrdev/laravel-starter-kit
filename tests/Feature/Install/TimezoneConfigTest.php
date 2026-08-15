<?php

use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\UpgradeCommand;

/**
 * Run UpgradeCommand's config/app.php timezone migration against an isolated file.
 *
 * @return array{changed: bool, changed_again: bool, content: string}
 */
function skRewriteTimezoneConfig(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'sk_timezone_');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary config file.');
    }

    file_put_contents($path, $content);

    try {
        $command = new UpgradeCommand;

        $files = new ReflectionProperty($command, 'files');
        $files->setValue($command, new Filesystem);

        $rewrite = new ReflectionMethod($command, 'rewriteDisplayTimezoneConfig');

        $changed = $rewrite->invoke($command, $path);
        $afterFirstRun = file_get_contents($path);
        $changedAgain = $rewrite->invoke($command, $path);

        return [
            'changed' => $changed,
            'changed_again' => $changedAgain,
            'content' => (string) $afterFirstRun,
        ];
    } finally {
        unlink($path);
    }
}

it('Timezone upgrade rewrites the legacy env key once and is idempotent', function (): void {
    $config = <<<'PHP'
<?php

return [
    'name' => env('APP_NAME', 'Laravel'),
    'display_timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
];
PHP;

    $result = skRewriteTimezoneConfig($config);

    expect($result['changed'])->toBeTrue()
        ->and($result['changed_again'])->toBeFalse()
        ->and($result['content'])->toContain("'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Europe/Istanbul')")
        ->and($result['content'])->not->toContain("env('APP_TIMEZONE'");
});

it('Timezone upgrade leaves an already-correct config untouched', function (): void {
    $config = <<<'PHP'
<?php

return [
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'UTC'),
];
PHP;

    $result = skRewriteTimezoneConfig($config);

    expect($result['changed'])->toBeFalse()
        ->and($result['changed_again'])->toBeFalse()
        ->and($result['content'])->toBe($config);
});
