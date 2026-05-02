<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FileFavorite extends Model
{
    use HasUuids;

    /** Only created_at; no updated_at column. */
    public $timestamps = false;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'favoritable_type',
        'favoritable_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];
}
