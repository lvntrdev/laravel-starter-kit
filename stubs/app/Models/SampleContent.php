<?php

namespace App\Models;

use Database\Factories\SampleContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class SampleContent extends Model
{
    /** @use HasFactory<SampleContentFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'description', 'content'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'content',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
