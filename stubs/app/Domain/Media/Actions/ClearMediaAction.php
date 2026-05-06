<?php

namespace App\Domain\Media\Actions;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Clear all files from a media collection.
 */
class ClearMediaAction extends BaseAction
{
    public function execute(Model $model, string $collection): void
    {
        $model->clearMediaCollection($collection);
    }
}
