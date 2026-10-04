<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\UpdateCommand;
use Lvntr\StarterKit\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

/*
| `sk:update --report=<path>`: the run summary as JSON, every preserved file
| paired with the stub it diverged from. Drives the real private writer.
*/

function bootUpdateReportCommand(array $state): UpdateCommand
{
    $command = new UpdateCommand;

    (new ReflectionProperty($command, 'files'))->setValue($command, new Filesystem);

    $style = new OutputStyle(new ArrayInput([], $command->getDefinition()), new BufferedOutput);
    $command->setInput(new ArrayInput([], $command->getDefinition()));
    $command->setOutput($style);
    (new ReflectionProperty($command, 'components'))->setValue($command, new ComponentsFactory($style));

    foreach ($state as $property => $value) {
        (new ReflectionProperty($command, $property))->setValue($command, $value);
    }

    return $command;
}

/** @return array<string, mixed> */
function writeUpdateReport(UpdateCommand $command, string $path, bool $dryRun, array $failedSteps = []): array
{
    (new ReflectionMethod($command, 'writeJsonReport'))->invoke($command, $path, $dryRun, $failedSteps);

    $target = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

    return json_decode((new Filesystem)->get($target), true, flags: JSON_THROW_ON_ERROR);
}

it('writes every bucket and pairs preserved files with their stub', function (): void {
    $skipped = 'app/Http/Controllers/Admin/DashboardController.php';
    $path = sys_get_temp_dir().'/sk_update_report_'.uniqid('', true).'.json';

    $report = writeUpdateReport(bootUpdateReportCommand([
        'updated' => ['resources/js/app.ts'],
        'skipped' => [$skipped],
        'safePathConflicts' => ['app/Enums/PermissionEnum.php'],
        'untracked' => ['resources/js/pages/Profile/Index.vue'],
    ]), $path, dryRun: true, failedSteps: ['Running new migrations']);

    expect($report)->toHaveKeys(['kit_version', 'added', 'removed', 'preserved_deprecated'])
        ->and($report['dry_run'])->toBeTrue()
        ->and($report['failed_steps'])->toBe(['Running new migrations'])
        ->and($report['updated'])->toBe(['resources/js/app.ts'])
        ->and($report['skipped'][0]['path'])->toBe($skipped)
        ->and($report['skipped'][0]['stub'])->toEndWith('stubs/'.$skipped)
        ->and(is_file($report['skipped'][0]['stub']) || is_file(base_path($report['skipped'][0]['stub'])))->toBeTrue()
        ->and($report['safe_path_conflicts'][0]['path'])->toBe('app/Enums/PermissionEnum.php')
        ->and($report['untracked'][0]['path'])->toBe('resources/js/pages/Profile/Index.vue');

    @unlink($path);
});

it('omits untracked outside a dry run, where they were already resolved', function (): void {
    $path = sys_get_temp_dir().'/sk_update_report_'.uniqid('', true).'.json';

    $report = writeUpdateReport(bootUpdateReportCommand([
        'untracked' => ['resources/js/pages/Profile/Index.vue'],
    ]), $path, dryRun: false);

    expect($report['untracked'])->toBe([])
        ->and($report['dry_run'])->toBeFalse();

    @unlink($path);
});

it('resolves a relative report path against the app root', function (): void {
    $relative = 'storage/sk-update-report-'.uniqid().'.json';

    $report = writeUpdateReport(bootUpdateReportCommand([]), $relative, dryRun: true);

    expect(is_file(base_path($relative)))->toBeTrue()
        ->and($report['skipped'])->toBe([]);

    @unlink(base_path($relative));
});
