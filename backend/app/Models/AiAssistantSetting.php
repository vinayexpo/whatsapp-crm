<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AiAssistantSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'base_url', 'api_key', 'model',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'company_id' => Auth::check() ? Auth::user()->company_id : null,
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ]);
    }
}
