<?php

namespace App\Services\Calling;

use App\Models\AiAssistantSetting;
use App\Models\WhatsappCallFlow;
use App\Scopes\CompanyScope;
use App\Services\Ai\OpenAiCompletionClient;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CallFlowAiGenerator
{
    public function __construct(
        private OpenAiCompletionClient $client,
        private WhatsappCallFlowNodesValidator $nodesValidator,
    ) {
    }

    /**
     * Generate (or incrementally edit) a call flow's nodes from a plain-language
     * instruction. Returns a validated nodes array ready to save, or throws if
     * the assistant isn't configured, the model's response can't be parsed as
     * JSON, or the parsed nodes fail validation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generate(WhatsappCallFlow $flow, string $instruction, array $existingNodes = []): array
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $flow->company_id)
            ->first();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($instruction, $existingNodes)],
        ];

        $content = $this->client->complete(
            $settings,
            $messages,
            ['type' => 'json_object'],
            ['whatsapp_call_flow_id' => $flow->id, 'context' => 'call_flow_ai_generate'],
        );

        if ($content === null) {
            throw new RuntimeException('The AI assistant is not configured or did not respond. Check your AI assistant settings and try again.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! isset($decoded['nodes']) || ! is_array($decoded['nodes'])) {
            throw new RuntimeException("The AI assistant's response could not be understood. Try rephrasing your instruction.");
        }

        return $this->nodesValidator->validate($decoded['nodes']);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a call-flow designer for a WhatsApp Business voice calling system.
You must respond with ONLY a JSON object of the exact shape: {"nodes": [...]}.
Each item in "nodes" must be an object with:
- "id": a short unique string identifier (e.g. "greeting", "ask_budget").
- "type": one of "question", "menu", "transfer_human", "end_call".
- "prompt": the exact text the AI should speak for this step.
- "options" (only for type "menu"): an array of short option strings.
- "variable_key" (only for type "question"): a snake_case key the caller's spoken answer is stored under.
The flow must end with a node of type "end_call" or "transfer_human".
Do not include any explanation, markdown, or text outside the JSON object.
PROMPT;
    }

    private function userPrompt(string $instruction, array $existingNodes): string
    {
        $prompt = "Instruction: {$instruction}\n\n";

        if (! empty($existingNodes)) {
            $prompt .= "Current nodes (edit/extend these rather than starting over unless the instruction says otherwise):\n";
            $prompt .= json_encode($existingNodes, JSON_PRETTY_PRINT);
        } else {
            $prompt .= 'There are no existing nodes yet — design a complete flow from scratch.';
        }

        return $prompt;
    }
}
