<?php

namespace App\Http\Actions;

use Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher as BaseBulkActionDispatcher;

/**
 * BulkActionDispatcher — consumer application alias.
 *
 * Thin subclass so host app controllers can use App namespace imports
 * while the implementation lives in the package.
 *
 * Usage in controllers:
 *   use App\Http\Actions\BulkActionDispatcher;
 *   $dispatcher = new BulkActionDispatcher;
 *   $dispatcher->register(new BulkDeleteUserAction);
 *   $result = $dispatcher->dispatch($user, 'delete', $items);
 */
class BulkActionDispatcher extends BaseBulkActionDispatcher
{
    //
}
