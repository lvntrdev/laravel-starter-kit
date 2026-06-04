<?php

namespace App\Domain\Setting\Actions;

use App\Domain\Setting\DTOs\ThemeSettingsDTO;
use App\Models\Setting;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Update appearance (theme) settings.
 *
 * Persists the app-wide active theme preset name under `appearance.theme`.
 */
class UpdateThemeSettingsAction extends BaseAction
{
    public function execute(ThemeSettingsDTO $dto): void
    {
        Setting::setGroup('appearance', $dto->toArray());
    }
}
