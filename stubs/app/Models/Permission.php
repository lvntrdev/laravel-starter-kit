<?php

namespace App\Models;

use App\Traits\HasActivityLogging;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Custom Permission model extending Spatie's Permission with display_name JSON cast.
 *
 * @property array<string, string>|null $display_name
 */
class Permission extends SpatiePermission
{
    use HasActivityLogging;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_name' => 'array',
        ];
    }
}
