<?php

namespace App\Services\Calling;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WhatsappCallFlowNodesValidator
{
    /**
     * Validate a nodes array against the same rules enforced by
     * WhatsappCallFlowController::store()/update(), so AI-generated nodes
     * can never save in a shape the manual builder couldn't also produce.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function validate(array $nodes): array
    {
        $validated = Validator::make(['nodes' => $nodes], [
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.id' => ['required', 'string'],
            'nodes.*.type' => ['required', 'in:question,menu,transfer_human,end_call'],
            'nodes.*.prompt' => ['required', 'string'],
            'nodes.*.options' => ['sometimes', 'nullable', 'array'],
            'nodes.*.variable_key' => ['sometimes', 'nullable', 'string'],
            'nodes.*.input_type' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        return $validated['nodes'];
    }
}
