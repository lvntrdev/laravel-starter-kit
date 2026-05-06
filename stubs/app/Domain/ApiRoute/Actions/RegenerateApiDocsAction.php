<?php

namespace App\Domain\ApiRoute\Actions;

use Illuminate\Support\Facades\Artisan;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Regenerate the Scramble API documentation (OpenAPI export).
 */
class RegenerateApiDocsAction extends BaseAction
{
    public function execute(): string
    {
        Artisan::call('scramble:export');

        return Artisan::output();
    }
}
