<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramStoryMention extends Model
{
    /** @use HasFactory<\Database\Factories\InstagramStoryMentionFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'mention_id',
        'contact_id',
        'media_id',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
