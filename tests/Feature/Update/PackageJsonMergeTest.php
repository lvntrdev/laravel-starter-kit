<?php

use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\UpdateCommand;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Lvntr\StarterKit\Tests\TestCase;

uses(TestCase::class);

/*
| sk:update merges package.json key by key: the consumer's own scripts survive
| next to the kit's, and a dry run writes nothing.
*/

function runPackageJsonMerge(bool $dryRun): UpdateCommand
{
    $command = new UpdateCommand;
    (new ReflectionProperty($command, 'files'))->setValue($command, new Filesystem);
    (new ReflectionMethod($command, 'mergePackageJson'))->invoke($command, $dryRun);

    return $command;
}

beforeEach(function (): void {
    $this->target = base_path('package.json');
    $this->backup = is_file($this->target) ? file_get_contents($this->target) : null;
    $this->stub = json_decode(file_get_contents(StarterKitServiceProvider::stubsPath('package.json')), true);

    file_put_contents($this->target, json_encode([
        'name' => 'consumer-app',
        'scripts' => ['push' => 'git push', 'dev' => 'old-dev', 'ci:local' => 'composer test'],
        'devDependencies' => ['my-own-tool' => '^1.0'],
    ], JSON_PRETTY_PRINT)."\n");
});

afterEach(function (): void {
    $this->backup === null ? @unlink($this->target) : file_put_contents($this->target, $this->backup);
});

it('keeps consumer scripts in their order and refreshes the kit scripts', function (): void {
    runPackageJsonMerge(false);

    $merged = json_decode(file_get_contents($this->target), true);

    expect(array_slice(array_keys($merged['scripts']), 0, 3))->toBe(['push', 'dev', 'ci:local'])
        ->and($merged['scripts']['dev'])->toBe($this->stub['scripts']['dev'])
        ->and($merged['scripts'])->toHaveKeys(array_keys($this->stub['scripts']))
        ->and($merged['devDependencies'])->toHaveKey('my-own-tool')
        ->and($merged['name'])->toBe('consumer-app');
});

it('reports the merge on a dry run without writing', function (): void {
    $before = file_get_contents($this->target);

    $command = runPackageJsonMerge(true);

    expect(file_get_contents($this->target))->toBe($before)
        ->and((new ReflectionProperty($command, 'updated'))->getValue($command))
        ->toContain('package.json (merged stub dependencies — run npm install)');
});

it('never treats package.json as a wholesale-copied modifiable file', function (): void {
    $method = new ReflectionMethod(new UpdateCommand, 'isNeverUpdate');

    expect($method->invoke(new UpdateCommand, 'package.json'))->toBeTrue();
});
