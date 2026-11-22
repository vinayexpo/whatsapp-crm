<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CompanyDataUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(public int $companyId, public string $resource) {}

    public function broadcastOn(): array
    {
        $uuid = Company::query()->whereKey($this->companyId)->value('uuid');

        return $uuid ? [new PrivateChannel('company.'.$uuid.'.updates')] : [];
    }

    public function broadcastAs(): string
    {
        return 'company.data-updated';
    }

    public function broadcastWith(): array
    {
        // Send an invalidation only. Each API still enforces its own permissions.
        return ['resource' => $this->resource];
    }
}
