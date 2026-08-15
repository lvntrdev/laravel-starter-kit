<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Commands\InstallCommand;
use Lvntr\StarterKit\Console\Commands\UpgradeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run a command's config/database.php timezone rewrite against an isolated file.
 *
 * @param  class-string<InstallCommand|UpgradeCommand>  $commandClass
 * @return array{changed_again: bool, config: array<string, mixed>, content: string, results: array<string, string>, results_again: array<string, string>}
 */
function skRewriteDatabaseTimezoneConfig(string $commandClass, string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'sk_database_timezone_');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary config file.');
    }

    file_put_contents($path, $content);

    try {
        $command = new $commandClass;

        $files = new ReflectionProperty($command, 'files');
        $files->setValue($command, new Filesystem);

        $rewrite = new ReflectionMethod($command, 'rewriteDatabaseTimezoneConfig');
        $results = $rewrite->invoke($command, $path);
        $afterFirstRun = (string) file_get_contents($path);
        $resultsAgain = $rewrite->invoke($command, $path);

        /** @var array<string, mixed> $evaluatedConfig */
        $evaluatedConfig = require $path;

        return [
            'changed_again' => $afterFirstRun !== (string) file_get_contents($path),
            'config' => $evaluatedConfig,
            'content' => $afterFirstRun,
            'results' => $results,
            'results_again' => $resultsAgain,
        ];
    } finally {
        unlink($path);
    }
}

dataset('database timezone commands', [
    'install' => [InstallCommand::class],
    'upgrade' => [UpgradeCommand::class],
]);

it('pins stock mysql and mariadb connections to UTC', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
        ],
        'mysql' => [
            'driver' => 'mysql',
        ],
        'mariadb' => [
            'driver' => 'mariadb',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
        ],
    ],
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['results'])->toBe(['mysql' => 'changed', 'mariadb' => 'changed'])
        ->and($result['config']['connections']['mysql']['timezone'])->toBe('+00:00')
        ->and($result['config']['connections']['mariadb']['timezone'])->toBe('+00:00')
        ->and($result['content'])->toContain("'timezone' => '+00:00'")
        ->and($result['content'])->not->toContain("env('DB_TIMEZONE'");
})->with('database timezone commands');

it('is idempotent on a second run', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'connections' => [
        'mysql' => ['driver' => 'mysql'],
        'mariadb' => ['driver' => 'mariadb'],
    ],
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['changed_again'])->toBeFalse()
        ->and($result['results_again'])->toBe(['mysql' => 'existing', 'mariadb' => 'existing']);
})->with('database timezone commands');

it('leaves a consumer timezone value untouched', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'timezone' => '+03:00',
        ],
        'mariadb' => ['driver' => 'mariadb'],
    ],
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['results'])->toBe(['mysql' => 'existing', 'mariadb' => 'changed'])
        ->and($result['config']['connections']['mysql']['timezone'])->toBe('+03:00')
        ->and($result['config']['connections']['mariadb']['timezone'])->toBe('+00:00');
})->with('database timezone commands');

it('does not touch sqlite or pgsql connections', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'connections' => [
        'sqlite' => ['driver' => 'sqlite'],
        'mysql' => ['driver' => 'mysql'],
        'mariadb' => ['driver' => 'mariadb'],
        'pgsql' => ['driver' => 'pgsql'],
    ],
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['config']['connections']['sqlite'])->not->toHaveKey('timezone')
        ->and($result['config']['connections']['pgsql'])->not->toHaveKey('timezone');
})->with('database timezone commands');

it('skips a missing mariadb connection', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'connections' => [
        'mysql' => ['driver' => 'mysql'],
    ],
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['results'])->toBe(['mysql' => 'changed', 'mariadb' => 'missing'])
        ->and($result['config']['connections']['mysql']['timezone'])->toBe('+00:00');
})->with('database timezone commands');

it('does not crash when connections are absent', function (string $commandClass): void {
    $config = <<<'PHP'
<?php

return [
    'default' => 'mysql',
];
PHP;

    $result = skRewriteDatabaseTimezoneConfig($commandClass, $config);

    expect($result['results'])->toBe(['mysql' => 'missing', 'mariadb' => 'missing'])
        ->and($result['content'])->toBe($config)
        ->and($result['changed_again'])->toBeFalse();
})->with('database timezone commands');

/*
|--------------------------------------------------------------------------
| sk:upgrade consent gate
|--------------------------------------------------------------------------
|
| The gate is the part of this change that can silently shift every rendered
| timestamp on an existing installation, so each skip path is pinned here.
|
*/

/**
 * Build an UpgradeCommand with just enough IO wiring to exercise the consent gate.
 *
 * @return array{command: UpgradeCommand, output: BufferedOutput}
 */
function skUpgradeCommandForGate(bool $interactive, array $parameters = []): array
{
    $command = new UpgradeCommand;
    $command->setLaravel(app());

    // The Artisan application, not the command, contributes the global options at runtime.
    $definition = $command->getDefinition();

    if (! $definition->hasOption('no-interaction')) {
        $definition->addOption(new InputOption('no-interaction', 'n', InputOption::VALUE_NONE));
    }

    $input = new ArrayInput($parameters, $definition);
    $input->setInteractive($interactive);

    $buffer = new BufferedOutput;
    $style = new OutputStyle($input, $buffer);

    foreach (['input' => $input, 'output' => $style, 'components' => new Factory($style)] as $property => $value) {
        (new ReflectionProperty($command, $property))->setValue($command, $value);
    }

    return ['command' => $command, 'output' => $buffer];
}

/**
 * Fake a connection whose session query and data probe answer as given.
 */
function skFakeTimezoneConnection(string $name, string $sessionTimezone, int $offsetSeconds, bool $hasData): void
{
    $schema = Mockery::mock(Builder::class);
    $schema->shouldReceive('hasTable')->with('users')->andReturn($hasData);

    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('exists')->andReturn($hasData);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('selectOne')->andReturn((object) [
        'time_zone' => $sessionTimezone,
        'offset_seconds' => $offsetSeconds,
    ]);
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
    $connection->shouldReceive('table')->with('users')->andReturn($query);

    DB::shouldReceive('connection')->with($name)->andReturn($connection);
}

it('skips the pin unattended when a data-holding session is not UTC', function (): void {
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', 'SYSTEM', 10800, true);

    ['command' => $command, 'output' => $output] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeFalse()
        ->and($output->fetch())->toContain('Non-interactive run detected');
});

it('still asks when a SYSTEM session currently resolves to a zero offset', function (): void {
    // A DST zone in its winter, or a host retimed after the rows were written: the offset reads
    // zero today while older rows were written at a different one.
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', 'SYSTEM', 0, true);

    ['command' => $command, 'output' => $output] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeFalse()
        ->and($output->fetch())->toContain('daylight saving');
});

it('inspects a non-default connection that the edit would change', function (): void {
    // The default is sqlite, but the mysql connection is the one being pinned — and the one
    // whose rows would shift.
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', 'Europe/Istanbul', 10800, true);

    ['command' => $command, 'output' => $output] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeFalse()
        ->and($output->fetch())->toContain('Europe/Istanbul');
});

it('applies without asking when the session is already UTC', function (): void {
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', '+00:00', 0, true);

    ['command' => $command] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeTrue();
});

it('applies without asking when the database holds no data', function (): void {
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', 'SYSTEM', 10800, false);

    ['command' => $command] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeTrue();
});

it('skips the pin when the session could not be inspected', function (): void {
    config()->set('database.connections.mysql.driver', 'mysql');
    DB::shouldReceive('connection')->with('mysql')->andThrow(new RuntimeException('connection refused'));

    ['command' => $command, 'output' => $output] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'changed', 'mariadb' => 'missing']))->toBeFalse()
        ->and($output->fetch())->toContain('Could not inspect');
});

it('does not probe a connection the edit leaves alone', function (): void {
    config()->set('database.connections.mysql.driver', 'mysql');
    DB::shouldReceive('connection')->never();

    ['command' => $command] = skUpgradeCommandForGate(interactive: false);

    $decide = new ReflectionMethod($command, 'shouldApplyDatabaseTimezoneConfig');

    expect($decide->invoke($command, ['mysql' => 'existing', 'mariadb' => 'missing']))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| sk:install re-run guard
|--------------------------------------------------------------------------
|
| sk:install is also the documented recovery path on an existing project, so a
| populated non-UTC database is left to sk:upgrade's consent-gated step.
|
*/

it('detects a populated non-UTC database on install', function (): void {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', 'SYSTEM', 10800, true);

    $holds = new ReflectionMethod(InstallCommand::class, 'databaseHoldsOffsetTimestamps');

    expect($holds->invoke(new InstallCommand))->toBeTrue();
});

it('treats a UTC session as nothing to protect on install', function (): void {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.driver', 'mysql');
    skFakeTimezoneConnection('mysql', '+00:00', 0, true);

    $holds = new ReflectionMethod(InstallCommand::class, 'databaseHoldsOffsetTimestamps');

    expect($holds->invoke(new InstallCommand))->toBeFalse();
});

it('treats an unreachable database as a fresh install', function (): void {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.driver', 'mysql');
    DB::shouldReceive('connection')->with('mysql')->andThrow(new RuntimeException('connection refused'));

    $holds = new ReflectionMethod(InstallCommand::class, 'databaseHoldsOffsetTimestamps');

    expect($holds->invoke(new InstallCommand))->toBeFalse();
});
