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
 *
 * Stub ile davranış senkronu: trash yalnızca FileManager 'files'
 * koleksiyonuna aittir — diğer koleksiyonlarda delete() kalıcı silmeye
 * (forceDelete) çevrilir. Stub'daki override'ın birebir kopyasıdır;
 * NonFileManagerMediaDeletionTest her ikisini de doğrular.
 */
class TestMedia extends Media
{
    use SoftDeletes;

    private const FILE_MANAGER_COLLECTION = 'files';

    public function delete(): ?bool
    {
        // forceDelete() bu metodu forceDeleting=true ile yeniden çağırır —
        // passthrough, aksi hâlde sonsuz özyineleme.
        if ($this->forceDeleting) {
            return parent::delete();
        }

        if (! $this->shouldMoveToTrash()) {
            return $this->forceDelete();
        }

        return parent::delete();
    }

    protected function shouldMoveToTrash(): bool
    {
        return $this->collection_name === self::FILE_MANAGER_COLLECTION
            && (bool) config('file-manager.settings.enable_trash', true);
    }
}
