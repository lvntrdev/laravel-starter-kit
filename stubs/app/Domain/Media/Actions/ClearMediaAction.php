<?php

namespace App\Domain\Media\Actions;

use App\Domain\Shared\Actions\BaseAction;
use Illuminate\Database\Eloquent\Model;

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
