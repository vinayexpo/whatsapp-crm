<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignRecipientFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'campaign_id', 'contact_id', 'message_id', 'idempotency_key',
        'status', 'failure_reason', 'sent_at', 'delivered_at', 'read_at', 'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Marks the contact's most recent campaign send as replied, if it hasn't
     * been already. Called when an inbound message arrives on that contact's
     * conversation, since a recipient has no direct link to a conversation --
     * only to the contact the campaign message was sent to.
     */
    public static function markMostRecentAsRepliedForContact(int $contactId): void
    {
        $recipient = static::query()
            ->where('contact_id', $contactId)
            ->whereNotNull('sent_at')
            ->whereNull('replied_at')
            ->whereNotIn('status', ['failed'])
            ->orderByDesc('sent_at')
            ->first();

        if (! $recipient) {
            return;
        }

        $recipient->update(['status' => 'replied', 'replied_at' => now()]);
        $recipient->campaign?->increment('replied_count');
    }
}
