<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only stub: consumer'ın App\Models\FileFavorite'ini simüle eder.
 * Package vendor migration'ları `file_favorites` tablosunu oluşturuyor.
 */
class TestFileFavorite extends Model
{
    use HasUuids;

    protected $table = 'file_favorites';

    public $timestamps = false;

    protected $fillable = ['owner_type', 'owner_id', 'favoritable_type', 'favoritable_id'];
}
