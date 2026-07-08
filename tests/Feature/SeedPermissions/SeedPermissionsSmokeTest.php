<?php

/*
|--------------------------------------------------------------------------
| sk:seed-permissions — smoke test
|--------------------------------------------------------------------------
|
| The Testbench sandbox app never runs sk:install, so it has no published
| Database\Seeders\_01_RolePermissionSeeder. That is exactly the guard this
| test exercises: the command's class_exists() check must fail closed with a
| clear "run sk:install first" message and a non-zero exit code, rather than
| throwing a raw ClassNotFoundError or silently succeeding. This is the actual
| production guard (SeedPermissionsCommand::handle()), not a re-implementation.
|
*/

use Illuminate\Support\Facades\Artisan;
use Lvntr\StarterKit\Tests\TestCase;

// Feature/SeedPermissions is not bound to a TestCase in Pest.php; bind it at
// file scope so the Artisan container resolves.
uses(TestCase::class);

it('fails closed with an actionable message when the RolePermissionSeeder is not published', function (): void {
    expect(class_exists('Database\\Seeders\\_01_RolePermissionSeeder'))->toBeFalse();

    $exitCode = Artisan::call('sk:seed-permissions');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Seeder class not found')
        ->and($output)->toContain('sk:install');
});

it('is registered under its documented signature (including --fresh)', function (): void {
    $command = Artisan::all()['sk:seed-permissions'];

    expect($command->getDefinition()->hasOption('fresh'))->toBeTrue();
});
