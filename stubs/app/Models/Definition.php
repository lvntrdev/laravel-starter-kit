<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Definition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'value',
        'label',
        'explanation',
        'severity',
        'icon',
        'is_active',
        'order',
        'visibility',
        'lang',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'visibility' => 'boolean',
            'order' => 'integer',
        ];
    }
}
