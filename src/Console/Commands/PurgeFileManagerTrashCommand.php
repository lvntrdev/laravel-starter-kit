<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PurgeFileManagerTrashCommand extends Command
{
    protected $signature = 'file-manager:purge-trash {--days= : Items older than this many days are permanently deleted; defaults to the configured trash retention period}';

    /**
     * Yalnızca FileManager'a ait dosyaları (collection_name = 'files') kalıcı olarak siler.
     * Avatar, logo, editor gibi diğer koleksiyonlar bu komut tarafından etkilenmez.
     */
    protected $description = 'Permanently delete file manager trash items (collection: files) older than the configured number of days.';

    public function handle(): int
    {
        // --days verilmezse admin ayarlarından (DB → config) gelen saklama
        // süresini kullan; o da yoksa 7 güne düş.
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('file-manager.settings.trash_retention_days', 7);
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        /** @var class-string<Model> $mediaModel */
        $mediaModel = config('media-library.media_model', 'Spatie\\MediaLibrary\\MediaCollections\\Models\\Media');

        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        // Yalnızca 'files' koleksiyonunu kapsar; avatar/logo/editor koleksiyonları dokunulmaz.
        $files = $mediaModel::onlyTrashed()
            ->where('collection_name', 'files')
            ->where('deleted_at', '<', $cutoff)
            ->get();

        foreach ($files as $media) {
            $media->forceDelete();
        }

        $folders = $folderModel::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get();

        foreach ($folders as $folder) {
            $folder->forceDelete();
        }

        // Legacy temizliği: trash yalnızca 'files' koleksiyonuna aittir.
        // Eski sürümlerde avatar/logo/form eki silmeleri de soft-delete'e
        // düşüyordu; bu satırlar trash UI'da görünmez, restore yolu yoktur
        // ve kotayı şişirir. Yaş şartı olmadan kalıcı süpürülür — model
        // forceDelete'i Spatie observer'ı tetikler, fiziksel dosya da gider.
        $orphans = $mediaModel::onlyTrashed()
            ->where('collection_name', '!=', 'files')
            ->get();

        foreach ($orphans as $media) {
            $media->forceDelete();
        }

        $this->info("Purged {$files->count()} file(s) and {$folders->count()} folder(s) from trash (older than {$days} days).");

        if ($orphans->count() > 0) {
            $this->info("Swept {$orphans->count()} orphaned non-FileManager media record(s) from trash (no age limit).");
        }

        return Command::SUCCESS;
    }
}
