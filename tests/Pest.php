<?php

use Lvntr\StarterKit\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest test bootstrap
|--------------------------------------------------------------------------
|
| Binds the package TestCase only to the Feature suite. Unit tests (added
| later) keep Pest's default base class, which is the cheaper option for
| pure-PHP assertions that do not need a Laravel application boot.
|
*/

uses(TestCase::class)->in('Feature');
