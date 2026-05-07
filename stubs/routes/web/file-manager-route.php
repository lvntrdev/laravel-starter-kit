<?php

use Lvntr\StarterKit\Facades\FileManager;

/*
|--------------------------------------------------------------------------
| FileManager Routes (Vendor Mount)
|--------------------------------------------------------------------------
|
| Single line mount delegates to the vendor-defined route group at
| Lvntr\StarterKit\Facades\FileManager::routes(). Keep this stub one line
| so future package updates land via composer update — no route diff to
| review in your application repo.
|
| Need to customize routes? Replace this file with your own group that
| points to your custom controller. The package ServiceProvider detects
| whether this file exists and steps aside, so your version always wins.
|
| K1 (security): This file is intentionally mounted OUTSIDE the auth+verified
| middleware group by the orchestrator. The public share/show endpoint is
| protected only by `signed` + `throttle:60,1`. Auth-required routes
| declare their own middleware inside the route file.
|
*/

FileManager::routes();
