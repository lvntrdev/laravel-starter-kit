<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Lvntr\StarterKit\Console\Commands\UpdateCommand;
use Lvntr\StarterKit\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// Feature/Update is not bound to a TestCase in Pest.php; bind it here.
uses(TestCase::class);

/*
| sk:update must offer `migrate` when a package migration (auto-loaded from
| vendor, never copied into the app) has not run yet — not only when a stub
| migration was copied in this run.
*/

function pendingPackageMigrationsCheck(): bool
{
    $command = new UpdateCommand;
    $command->setLaravel(app());

    return (new ReflectionMethod($command, 'hasNewMigrations'))->invoke($command);
}

/** @return list<string> */
function packageMigrationNames(): array
{
    return array_keys(app('migrator')->getMigrationFiles(dirname(__DIR__, 3).'/database/migrations'));
}

it('is false when the migrations table does not exist yet', function () {
    expect(pendingPackageMigrationsCheck())->toBeFalse();
});

it('is true when one package migration has not run', function () {
    $repository = app('migrator')->getRepository();
    $repository->createRepository();

    $names = packageMigrationNames();
    expect($names)->toContain('2026_10_04_100000_create_file_manager_share_links_table');

    foreach (array_diff($names, ['2026_10_04_100000_create_file_manager_share_links_table']) as $name) {
        $repository->log($name, 1);
    }

    expect(pendingPackageMigrationsCheck())->toBeTrue();
});

it('is false when every package migration has run', function () {
    $repository = app('migrator')->getRepository();
    $repository->createRepository();

    foreach (packageMigrationNames() as $name) {
        $repository->log($name, 1);
    }

    expect(pendingPackageMigrationsCheck())->toBeFalse();
});

it('is false when vendor migration loading is switched off', function () {
    config(['starter-kit.run_migrations' => false]);
    app('migrator')->getRepository()->createRepository();

    expect(pendingPackageMigrationsCheck())->toBeFalse();
});

it('is false and warns, instead of aborting the update, when the database is unreachable', function () {
    config([
        'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent/starter-kit/db.sqlite'],
        'database.default' => 'unreachable',
    ]);

    $command = new UpdateCommand;
    $command->setLaravel(app());
    $buffer = new BufferedOutput;
    $style = new OutputStyle(new ArrayInput([]), $buffer);
    $command->setOutput($style);
    (new ReflectionProperty($command, 'components'))->setValue($command, new ComponentsFactory($style));

    expect((new ReflectionMethod($command, 'hasNewMigrations'))->invoke($command))->toBeFalse()
        ->and($buffer->fetch())->toContain('Pending kit migrations could not be checked');
});
