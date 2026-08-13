<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Chatbot extends Model
{
    /** @use HasFactory<\Database\Factories\ChatbotFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'status',
        'widget_key',
        'allowed_origins',
        'welcome_message',
        'general_fallback_enabled',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'general_fallback_enabled' => 'boolean',
            'channels' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $chatbot) {
            if (empty($chatbot->widget_key)) {
                $chatbot->widget_key = Str::random(32);
            }

            if (empty($chatbot->channels)) {
                $chatbot->channels = ['website'];
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trainingEntries(): HasMany
    {
        return $this->hasMany(ChatbotTrainingEntry::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
