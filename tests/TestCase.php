<?php

namespace Lvntr\StarterKit\Tests;

use Illuminate\Foundation\Application;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base TestCase for the package backward-compatibility regression suite.
 *
 * Boots Orchestra Testbench with the package ServiceProvider so that
 * route, migration, config and middleware registration can be exercised
 * without depending on a consumer application skeleton.
 *
 * The skeleton-leaning regression tests under tests/Feature/BackwardCompat/
 * never hit the database — they assert package surface integrity (route
 * names, migration filenames, config keys, helper guards, ServiceProvider
 * source content) so the suite is fast and deterministic.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Register the package ServiceProvider for the testbench application.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            StarterKitServiceProvider::class,
        ];
    }
}
