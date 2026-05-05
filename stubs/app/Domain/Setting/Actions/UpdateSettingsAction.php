<?php

namespace App\Domain\Setting\Actions;

use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use App\Models\Setting;

/**
 * Action: Update a settings group.
 * Generic action that persists any settings DTO to the given group.
 */
class UpdateSettingsAction extends BaseAction
{
    /**
     * Execute the action.
     */
    public function execute(string $group, BaseDTO $dto): void
    {
        Setting::setGroup($group, $dto->toArray());
    }
}
