<?php

namespace App\Http\BulkActions;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Http\Bulk\BulkDeleteAction as BaseBulkDeleteAction;

/**
 * Generic bulk delete action — consumer application override point.
 *
 * Extends the package base with App namespace so domain-specific actions
 * can use App imports (App\Models\User, App\Enums\RoleEnum, etc.).
 *
 * Domain actions should extend THIS class, not the package base directly.
 *
 * @extends BaseBulkDeleteAction<Model>
 */
class BulkDeleteAction extends BaseBulkDeleteAction
{
    //
}
