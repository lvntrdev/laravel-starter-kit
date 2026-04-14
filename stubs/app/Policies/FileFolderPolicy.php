<?php

namespace App\Policies;

use App\Models\FileFolder;
use App\Models\User;

/**
 * Authorization rules for FileFolder records.
 *
 * Additive layer on top of the route-level `check.permission` middleware
 * and the FileManager `ContextRegistry` policy hooks. Use this when a
 * controller needs explicit row-level gating for folder operations
 * (`$this->authorize('update', $folder)`).
 *
 * ContextRegistry-driven flows continue to delegate to their context's
 * authorizer closure — this policy only fires when Laravel's Gate is
 * called directly on a FileFolder instance.
 */
class FileFolderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('files.read');
    }

    public function view(User $actor, FileFolder $folder): bool
    {
        return $actor->can('files.read');
    }

    public function create(User $actor): bool
    {
        return $actor->can('files.create');
    }

    public function update(User $actor, FileFolder $folder): bool
    {
        return $actor->can('files.update');
    }

    public function delete(User $actor, FileFolder $folder): bool
    {
        return $actor->can('files.delete');
    }
}
