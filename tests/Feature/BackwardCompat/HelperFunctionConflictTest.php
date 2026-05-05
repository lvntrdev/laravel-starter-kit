<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — Helper Function Conflict
|--------------------------------------------------------------------------
|
| Pins the contract that drove the Task 3-fix regression patch: when a
| consumer app already defines `to_api()` (or any other sk-helper) the
| vendor copy MUST NOT redeclare it. PHP would fatal-error on a second
| declaration, so every helper is wrapped in `function_exists` guards.
|
| Strategy:
|   - The TestCase already booted the ServiceProvider, which loaded the
|     vendor sk-helpers.php. So `function_exists('to_api')` is guaranteed
|     true here.
|   - We then re-include the vendor file and assert that no fatal error
|     is raised — proving every declaration is guarded. PHP would emit
|     `Cannot redeclare function ...` if any guard was missing, which
|     PHPUnit converts into a test failure.
|   - We also load the helper file *contents* and assert every public
|     helper is wrapped in a guard, catching regressions even before the
|     runtime would notice (e.g. someone adds a new helper without a
|     guard).
|
*/

use Lvntr\StarterKit\Http\Responses\ApiResponse;

it('declares every sk-helper inside a function_exists guard', function (): void {
    $helperPath = dirname(__DIR__, 3).'/src/sk-helpers.php';
    expect(is_file($helperPath))->toBeTrue("Vendor sk-helpers.php missing at {$helperPath}");

    $contents = file_get_contents($helperPath);
    expect($contents)->toBeString();

    // Every `function <name>(` declaration must be preceded (within the
    // same file) by a matching `if (! function_exists('<name>'))` guard.
    preg_match_all('/^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $contents, $matches);
    $declared = $matches[1] ?? [];

    expect($declared)->not->toBeEmpty('Expected at least one helper declaration in sk-helpers.php');

    foreach ($declared as $name) {
        $needle = "function_exists('{$name}')";
        expect(str_contains($contents, $needle))->toBeTrue(
            "Helper `{$name}` is declared without a `{$needle}` guard. Re-declaration would fatal on consumer apps that already define it."
        );
    }
});

it('does not fatal when sk-helpers.php is required twice', function (): void {
    // The ServiceProvider already loaded it during boot. A second
    // require_once is a no-op for `_once`; a plain require would fatal
    // on redeclaration if any guard was missing. Use require to make the
    // assertion meaningful — guards must catch the redeclaration.
    $helperPath = dirname(__DIR__, 3).'/src/sk-helpers.php';

    // Suppress "function already defined" notices and assert that
    // function_exists() is still true after the second load.
    $exitedCleanly = false;
    try {
        require $helperPath;
        $exitedCleanly = true;
    } catch (Throwable $e) {
        $exitedCleanly = false;
    }

    expect($exitedCleanly)->toBeTrue('Reloading sk-helpers.php must not throw — every declaration must be guarded.');
    expect(function_exists('to_api'))->toBeTrue();
    expect(function_exists('definition'))->toBeTrue();
    expect(function_exists('definitionLabel'))->toBeTrue();
    expect(function_exists('sk_locale_keys'))->toBeTrue();
    expect(function_exists('sk_default_locale'))->toBeTrue();
    expect(function_exists('format_date'))->toBeTrue();
});

it('routes to_api through the vendor ApiResponse class when no consumer override exists', function (): void {
    // Sanity check: when the consumer has not redeclared to_api, the
    // vendor implementation must hand off to Lvntr\StarterKit\Http\Responses\ApiResponse.
    $response = to_api(['ok' => true]);

    expect($response)->toBeInstanceOf(ApiResponse::class);
});
