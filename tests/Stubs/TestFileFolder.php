<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test-only stub: consumer'ın App\Models\FileFolder'ını simüle eder.
 * Package vendor migration'ları `file_folders` tablosunu oluşturuyor.
 */
class TestFileFolder extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'file_folders';

    protected $fillable = ['id', 'parent_id', 'name', 'owner_type', 'owner_id'];
}
