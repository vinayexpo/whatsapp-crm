<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AiAssistantSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'base_url', 'api_key', 'model', 'stt_model', 'tts_model', 'tts_voice',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'company_id' => Auth::check() ? Auth::user()->company_id : null,
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'stt_model' => 'whisper-1',
            'tts_model' => 'tts-1',
            'tts_voice' => 'alloy',
        ]);
    }
}
