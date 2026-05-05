<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PurgeFileManagerTrashCommand extends Command
{
    protected $signature = 'file-manager:purge-trash {--days=7 : Items older than this many days are permanently deleted}';

    /**
     * Yalnızca FileManager'a ait dosyaları (collection_name = 'files') kalıcı olarak siler.
     * Avatar, logo, editor gibi diğer koleksiyonlar bu komut tarafından etkilenmez.
     */
    protected $description = 'Permanently delete file manager trash items (collection: files) older than the configured number of days.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
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

        $this->info("Purged {$files->count()} file(s) and {$folders->count()} folder(s) from trash (older than {$days} days).");

        return Command::SUCCESS;
    }
}
