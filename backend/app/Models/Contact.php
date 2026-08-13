<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'avatar_url',
        'channel',
        'handle',
        'phone',
        'email',
        'location',
        'pipeline_stage_id',
        'deal_value',
        'last_interaction_at',
        'notes',
        'purchases',
    ];

    protected function casts(): array
    {
        return [
            'deal_value' => 'integer',
            'last_interaction_at' => 'datetime',
            'notes' => 'array',
            'purchases' => 'array',
        ];
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function phonebookFolders(): BelongsToMany
    {
        return $this->belongsToMany(PhonebookFolder::class, 'contact_phonebook_folder');
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
