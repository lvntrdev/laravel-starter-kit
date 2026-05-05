<?php

use Lvntr\StarterKit\Tests\DatabaseTestCase;
use Lvntr\StarterKit\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest test bootstrap
|--------------------------------------------------------------------------
|
| Feature suite'e TestCase bağlanır. Unit testler (eklenirse) Pest default
| base class'ı kullanır — Laravel boot gerekmez.
|
| Feature/FileManager ve Feature/Settings testleri DatabaseTestCase ile
| çalışır: SQLite in-memory + migration + RefreshDatabase sağlar.
| Diğer Feature testleri (BackwardCompat) DB gerektirmediğinden
| hafif TestCase'i kullanmaya devam eder.
|
*/

// DB gerektiren test dizinleri
uses(DatabaseTestCase::class)->in('Feature/FileManager');
uses(DatabaseTestCase::class)->in('Feature/Settings');

// DB gerektirmeyen diğer Feature testleri
uses(TestCase::class)->in('Feature/BackwardCompat');
