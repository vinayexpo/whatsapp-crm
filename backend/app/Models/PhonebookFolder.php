<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PhonebookFolder extends Model
{
    /** @use HasFactory<\Database\Factories\PhonebookFolderFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_phonebook_folder');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
