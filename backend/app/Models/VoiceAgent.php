<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceAgent extends Model
{
    /** @use HasFactory<\Database\Factories\VoiceAgentFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'status',
        'voice_mode',
        'tts_voice_id',
        'greeting_audio_url',
        'qualification_criteria',
        'qualification_pass_criteria',
        'max_call_attempts',
        'system_prompt',
    ];

    protected function casts(): array
    {
        return [
            'qualification_criteria' => 'array',
            'max_call_attempts' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCall::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
