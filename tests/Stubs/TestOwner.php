<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Test-only owner modeli.
 *
 * FileManagerContextDTO oluşturmak için gerekli minimal Model.
 * Gerçek tabloya ihtiyaç yoktur — sadece MediaLibrary'nin
 * model_type/model_id özelliğini kullanır.
 */
class TestOwner extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    protected $table = 'test_owners';

    protected $fillable = ['id', 'name'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }
}
