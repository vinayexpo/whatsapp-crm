<?php

namespace App\Concerns;

use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if ($model->company_id === null && Auth::check() && Auth::user()->company_id !== null) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
