<?php

namespace Lvntr\StarterKit\Domain\ApiRoute\Actions;

use Illuminate\Support\Facades\Artisan;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\StarterKitServiceProvider;

/**
 * Action: Regenerate the Scramble API documentation (OpenAPI export).
 */
class RegenerateApiDocsAction extends BaseAction
{
    public function execute(): string
    {
        // Runs inside a web request, where the provider's boot-time Scramble
        // context gate did not register the document wiring — apply it now so
        // the export carries the bearer scheme + ApiResponse envelope.
        StarterKitServiceProvider::applyScrambleDocumentWiring();

        Artisan::call('scramble:export');

        return Artisan::output();
    }
}
