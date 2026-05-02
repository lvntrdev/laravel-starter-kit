<?php

namespace App\Console\Commands;

use App\Models\FileFolder;
use App\Models\Media;
use Illuminate\Console\Command;

class PurgeFileManagerTrash extends Command
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

        // Yalnızca 'files' koleksiyonunu kapsar; avatar/logo/editor koleksiyonları dokunulmaz.
        $files = Media::onlyTrashed()
            ->where('collection_name', 'files')
            ->where('deleted_at', '<', $cutoff)
            ->get();

        foreach ($files as $media) {
            $media->forceDelete();
        }

        $folders = FileFolder::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get();

        foreach ($folders as $folder) {
            $folder->forceDelete();
        }

        $this->info("Purged {$files->count()} file(s) and {$folders->count()} folder(s) from trash (older than {$days} days).");

        return Command::SUCCESS;
    }
}
