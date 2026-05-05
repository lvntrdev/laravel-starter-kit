<?php

namespace App\Domain\SampleContent\Actions;

use App\Domain\SampleContent\DTOs\SampleContentDTO;
use App\Domain\SampleContent\Events\SampleContentUpdated;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use App\Models\SampleContent;

/**
 * Action: Update an existing sample_content.
 */
class UpdateSampleContentAction extends BaseAction
{
    public function execute(SampleContent $model, SampleContentDTO $dto, int|string|null $performedBy = null): SampleContent
    {
        $data = $dto->toArray();
        $changedFields = array_keys(array_filter(
            $data,
            fn ($value, $key) => $model->getAttribute($key) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        $model->update($data);
        $model->refresh();

        if (! empty($changedFields)) {
            SampleContentUpdated::dispatch($model, $changedFields, $performedBy);
        }

        return $model;
    }
}
