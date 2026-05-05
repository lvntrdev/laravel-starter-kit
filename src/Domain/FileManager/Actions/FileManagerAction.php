<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Base class for all FileManager actions.
 *
 * Extends BaseAction and mixes in ResolvesMediaModel so every FileManager
 * action can call `$this->mediaModel()` to get the configured concrete
 * Media model class (defaults to Spatie's base but consumers may override
 * it via the `media-library.media_model` config key, e.g. to add SoftDeletes).
 */
abstract class FileManagerAction extends BaseAction
{
    use ResolvesMediaModel;
}
