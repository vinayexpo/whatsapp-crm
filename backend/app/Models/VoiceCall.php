<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCall extends Model
{
    /** @use HasFactory<\Database\Factories\VoiceCallFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'voice_agent_id',
        'contact_id',
        'campaign_recipient_id',
        'conversation_id',
        'direction',
        'medium',
        'status',
        'provider_call_sid',
        'recording_url',
        'transcript',
        'qualification_fields',
        'qualification_outcome',
        'qualification_summary',
        'needs_human_followup',
        'human_followup_assigned_to',
        'human_followup_completed_at',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'qualification_fields' => 'array',
            'needs_human_followup' => 'boolean',
            'human_followup_completed_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function humanFollowupAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_followup_assigned_to');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
