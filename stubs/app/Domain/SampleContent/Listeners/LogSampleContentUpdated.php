<?php

namespace App\Domain\SampleContent\Listeners;

use App\Domain\SampleContent\Events\SampleContentUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSampleContentUpdated implements ShouldQueue
{
    public function handle(SampleContentUpdated $event): void
    {
        Log::channel('stack')->info('SampleContent Updated', [
            'sample_content_id' => $event->sample_content->id,
            'changed_fields' => $event->changedFields,
            'updated_by' => $event->performedBy,
        ]);
    }
}
