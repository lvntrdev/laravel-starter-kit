<?php

namespace App\Models;

use App\Traits\HasMediaCollections;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Singleton container that owns globally-scoped media.
 *
 * Spatie MediaLibrary requires a concrete model for `media.model_type/model_id`.
 * This bucket exists so files uploaded via the "global" FileManager context
 * have a stable owner while the logical folder tree lives in `file_folders`.
 */
class GlobalFileBucket extends Model implements HasMedia
{
    use HasMediaCollections, HasUuids;

    protected $fillable = ['name'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }

    /**
     * Resolve (or lazily create) the singleton bucket instance.
     */
    public static function singleton(): self
    {
        return self::query()->firstOrCreate(['name' => 'default']);
    }
}
