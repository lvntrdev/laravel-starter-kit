<?php

namespace App\Domain\Media\Actions;

use App\Domain\Shared\Actions\BaseAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Action: Upload a file to a media collection, replacing any existing one.
 */
class UploadMediaAction extends BaseAction
{
    public function execute(Model $model, Request $request, string $collection, ?string $inputName = null): void
    {
        $inputName ??= $collection;

        $model->clearMediaCollection($collection);
        $model->addMediaFromRequest($inputName)->toMediaCollection($collection);
    }
}
