<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name', 'channel', 'status', 'message', 'attachment_url', 'attachment_type',
        'audience_tag', 'phonebook_folder_id',
        'recipient_count', 'delivered_count', 'read_count', 'replied_count',
        'scheduled_at', 'dispatched_at',
        'whatsapp_template_id', 'template_variables', 'voice_agent_id', 'whatsapp_call_flow_id',
    ];

    protected function casts(): array
    {
        return [
            'recipient_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'replied_count' => 'integer',
            'scheduled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'template_variables' => 'array',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    public function phonebookFolder(): BelongsTo
    {
        return $this->belongsTo(PhonebookFolder::class);
    }

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class);
    }

    public function whatsappCallFlow(): BelongsTo
    {
        return $this->belongsTo(WhatsappCallFlow::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
