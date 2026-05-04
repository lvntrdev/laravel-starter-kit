<?php

namespace App\Domain\SampleContent\Listeners;

use App\Domain\SampleContent\Events\SampleContentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSampleContentCreated implements ShouldQueue
{
    public function handle(SampleContentCreated $event): void
    {
        Log::channel('stack')->info('SampleContent Created', [
            'sample_content_id' => $event->sample_content->id,
            'created_by' => $event->performedBy,
        ]);
    }
}
