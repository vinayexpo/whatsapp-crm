<?php

namespace App\Services\ChatFlow;

use App\Models\Company;
use Illuminate\Support\Str;

class FakeChatMenuFlowGeneratorService implements ChatMenuFlowGeneratorServiceInterface
{
    public function lastError(): ?string
    {
        return null;
    }

    public function generate(Company $company, string $prompt): ?array
    {
        if (trim($prompt) === '') {
            return null;
        }

        $rootId = (string) Str::uuid();
        $leafId = (string) Str::uuid();

        return [
            'entryNodeId' => $rootId,
            'nodes' => [
                [
                    'id' => $rootId,
                    'type' => 'menu',
                    'message' => Str::limit("Fake reply for: {$prompt}", 1024, ''),
                    'mediaUrl' => null,
                    'mediaType' => null,
                    'renderAs' => 'button',
                    'buttons' => [
                        ['id' => (string) Str::uuid(), 'label' => 'Learn more', 'nextNodeId' => $leafId],
                    ],
                ],
                [
                    'id' => $leafId,
                    'type' => 'content',
                    'message' => 'Here is more information.',
                    'mediaUrl' => null,
                    'mediaType' => null,
                    'renderAs' => 'button',
                    'buttons' => [],
                ],
            ],
        ];
    }
}
