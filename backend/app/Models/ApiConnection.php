<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class ApiConnection extends Model
{
    /** @use HasFactory<\Database\Factories\ApiConnectionFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id', 'channel', 'label', 'account_name', 'identifier', 'status', 'access_token', 'verify_token', 'connected_at',
        'waba_id', 'phone_number_id', 'instagram_account_id', 'twilio_account_sid', 'twilio_phone_number',
        'calling_enabled', 'calling_status', 'calling_verified_at',
        'onboarding_type', 'smb_app_linked_at', 'wa_business_app_phone_number',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
            'calling_enabled' => 'boolean',
            'calling_verified_at' => 'datetime',
            'smb_app_linked_at' => 'datetime',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class);
    }

    public function flows(): HasMany
    {
        return $this->hasMany(WhatsappFlow::class);
    }

    public function callFlows(): HasMany
    {
        return $this->hasMany(WhatsappCallFlow::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Resolve the WhatsApp connection an inbound webhook payload belongs to.
     *
     * Prefers an exact match on the payload's phone_number_id. If none matches
     * and the company has exactly one WhatsApp connection, falls back to it
     * (preserves pre-coexistence behavior for single-connection companies).
     * Never guesses between multiple connections.
     */
    public static function findWhatsAppByPhoneNumberId(?string $phoneNumberId): ?self
    {
        if ($phoneNumberId) {
            $connection = static::query()
                ->where('channel', 'whatsapp')
                ->where('phone_number_id', $phoneNumberId)
                ->first();

            if ($connection) {
                return $connection;
            }
        }

        $whatsappConnections = static::query()->where('channel', 'whatsapp')->limit(2)->get();

        if ($whatsappConnections->count() === 1) {
            return $whatsappConnections->first();
        }

        Log::warning('Unmatched WhatsApp webhook phone_number_id', [
            'phone_number_id' => $phoneNumberId,
            'candidate_count' => $whatsappConnections->count(),
        ]);

        return null;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
