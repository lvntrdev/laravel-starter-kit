<?php

namespace Lvntr\StarterKit\Traits;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Enables Spatie activity logging on Eloquent models.
 *
 * Add this trait to any model that should be audited.
 * When `config('activitylog.enabled')` is false, Spatie skips recording.
 *
 * Automatically detects whether the model uses $fillable or $guarded
 * and chooses the appropriate logging strategy.
 *
 * Override `getActivitylogOptions()` in your model to customise behaviour.
 */
trait HasActivityLogging
{
    use LogsActivity;

    /**
     * Default activity-log options.
     *
     * Uses logFillable() for models with $fillable, logUnguarded() for
     * models using $guarded. Records only dirty values and skips empty logs.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();

        if (! empty($this->getFillable())) {
            return $options->logFillable();
        }

        return $options->logUnguarded();
    }
}
