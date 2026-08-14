<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCall extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappCallFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'whatsapp_call_flow_id',
        'contact_id',
        'conversation_id',
        'campaign_recipient_id',
        'direction',
        'status',
        'meta_call_id',
        'transcript',
        'collected_variables',
        'needs_human_followup',
        'human_followup_assigned_to',
        'human_followup_completed_at',
        'started_at',
        'ended_at',
        'local_sdp_offer',
        'remote_sdp_answer',
        'sdp_exchange_status',
        'remote_ice_candidates',
        'permission_request_message_id',
        'permission_request_status',
        'permission_request_failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'collected_variables' => 'array',
            'needs_human_followup' => 'boolean',
            'human_followup_completed_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'remote_ice_candidates' => 'array',
        ];
    }

    public function callFlow(): BelongsTo
    {
        return $this->belongsTo(WhatsappCallFlow::class, 'whatsapp_call_flow_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
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
