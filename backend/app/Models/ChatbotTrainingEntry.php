<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTrainingEntry extends Model
{
    /** @use HasFactory<\Database\Factories\ChatbotTrainingEntryFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'chatbot_id',
        'question',
        'answer',
        'source',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
