<?php

namespace App\Services\ChatFlow;

use App\Models\Company;

interface ChatMenuFlowGeneratorServiceInterface
{
    /**
     * @return array{entryNodeId: string, nodes: array}|null
     */
    public function generate(Company $company, string $prompt): ?array;
}
