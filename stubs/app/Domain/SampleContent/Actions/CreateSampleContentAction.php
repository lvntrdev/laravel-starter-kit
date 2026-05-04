<?php

namespace App\Domain\SampleContent\Actions;

use App\Domain\SampleContent\DTOs\SampleContentDTO;
use App\Domain\SampleContent\Events\SampleContentCreated;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\SampleContent;

/**
 * Action: Create a new sample_content.
 */
class CreateSampleContentAction extends BaseAction
{
    public function execute(SampleContentDTO $dto): SampleContent
    {
        $model = SampleContent::create($dto->toArray());

        SampleContentCreated::dispatch($model, $dto->user_id);

        return $model;
    }
}
