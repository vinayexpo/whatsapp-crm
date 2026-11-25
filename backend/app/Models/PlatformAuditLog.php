<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    use HasUuid;

    protected $fillable = ['actor_id', 'company_id', 'actor_name', 'company_name', 'action', 'target_type', 'target_id', 'description'];
}
