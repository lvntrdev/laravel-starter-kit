<?php

namespace App\Domain\ApiRoute\Actions;

use App\Domain\Shared\Actions\BaseAction;
use Illuminate\Support\Facades\Artisan;

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
