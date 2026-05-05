<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Test-only Media modeli.
 *
 * Consumer'ın stubs/app/Models/Media.php'sini simüle eder:
 * Spatie base Media'ya SoftDeletes ekler. App namespace
 * gerektirmediği için testbench ortamında güvenle kullanılabilir.
 */
class TestMedia extends Media
{
    use SoftDeletes;
}
