<?php

namespace App\Services\Commerce;

use App\Models\ChatMenuFlow;
use App\Models\CommerceSetting;
use Illuminate\Validation\ValidationException;

/**
 * Commerce trigger keywords (commerce_settings.settings.trigger_keywords) and
 * ChatMenuFlow trigger keywords are two independent systems that both react
 * to a plain-text inbound WhatsApp message. Rather than silently resolving a
 * collision by precedence at runtime, a collision is rejected (422) at the
 * point either system's keyword is configured/saved.
 */
class TriggerKeywordUniquenessValidator
{
    /**
     * Assert that none of $keywords collide with any ChatMenuFlow trigger
     * keyword already configured for the company (case-insensitive),
     * excluding a specific flow (used when updating that flow's own keyword).
     *
     * @param  array<int, string>  $keywords
     */
    public function assertNoChatMenuFlowCollision(int $companyId, array $keywords, ?int $excludingFlowId = null): void
    {
        $normalized = $this->normalize($keywords);

        if (empty($normalized)) {
            return;
        }

        $flows = ChatMenuFlow::query()
            ->where('company_id', $companyId)
            ->whereNotNull('trigger_keyword')
            ->when($excludingFlowId, fn ($q) => $q->where('id', '!=', $excludingFlowId))
            ->get(['trigger_keyword']);

        foreach ($flows as $flow) {
            $flowKeyword = strtolower(trim($flow->trigger_keyword));

            if (isset($normalized[$flowKeyword])) {
                throw ValidationException::withMessages([
                    'triggerKeywords' => "The keyword \"{$normalized[$flowKeyword]}\" is already used as a chat menu trigger keyword. Choose a different keyword.",
                ]);
            }
        }
    }

    /**
     * Assert that a single ChatMenuFlow trigger keyword does not collide with
     * any commerce trigger keyword configured for the company.
     */
    public function assertNoCommerceCollision(?int $companyId, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '' || $companyId === null) {
            return;
        }

        $commerceKeywords = $this->normalize(
            CommerceSetting::query()->where('company_id', $companyId)->value('settings')['trigger_keywords'] ?? []
        );

        $normalizedKeyword = strtolower($keyword);

        if (isset($commerceKeywords[$normalizedKeyword])) {
            throw ValidationException::withMessages([
                'triggerKeyword' => "The keyword \"{$keyword}\" is already used as a commerce trigger keyword. Choose a different keyword.",
            ]);
        }
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<string, string> lowercased keyword => original keyword
     */
    private function normalize(array $keywords): array
    {
        $result = [];

        foreach ($keywords as $keyword) {
            $trimmed = trim((string) $keyword);

            if ($trimmed !== '') {
                $result[strtolower($trimmed)] = $trimmed;
            }
        }

        return $result;
    }
}
