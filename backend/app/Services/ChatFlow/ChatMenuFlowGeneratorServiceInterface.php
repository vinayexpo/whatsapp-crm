<?php

namespace App\Services\ChatFlow;

use App\Models\Company;

interface ChatMenuFlowGeneratorServiceInterface
{
    /**
     * @return array{entryNodeId: string, nodes: array}|null
     */
    public function generate(Company $company, string $prompt): ?array;

    /**
     * Human-readable reason the most recent generate() call returned null, if any.
     */
    public function lastError(): ?string;
}
