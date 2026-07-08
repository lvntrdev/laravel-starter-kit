<?php

/*
|--------------------------------------------------------------------------
| remove:sk-domain — smoke tests
|--------------------------------------------------------------------------
|
| RemoveDomainCommand is destructive (unconditional unlink()/rmdir() once past
| its guards), so these are deliberately narrow: the two hard-coded refusal
| guards (invalid name, the protected 'User' domain) and a --force run against
| a domain that was never scaffolded — every layer is reported skipped and
| nothing throws. base_path() resolves to the Testbench sandbox app (see
| tests/TestCase.php), never the package repo itself, so this is safe to run
| unconditionally.
|
*/

use Illuminate\Support\Facades\Artisan;
use Lvntr\StarterKit\Tests\TestCase;

// Feature/RemoveDomain is not bound to a TestCase in Pest.php; bind it at
// file scope so base_path()/config() resolve.
uses(TestCase::class);

it('refuses an invalid domain name without touching the filesystem', function (): void {
    $exitCode = Artisan::call('remove:sk-domain', ['name' => 'Bad Name', '--force' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Invalid domain name');
});

it('refuses to remove the User domain', function (): void {
    $exitCode = Artisan::call('remove:sk-domain', ['name' => 'User', '--force' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('User domain cannot be removed');
});

it('completes successfully (all layers skipped) for a domain that was never scaffolded', function (): void {
    $exitCode = Artisan::call('remove:sk-domain', ['name' => 'NeverScaffoldedWidget', '--force' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain("Domain 'NeverScaffoldedWidget' removed successfully")
        ->and($output)->toContain('Skipped (not found)');
});
