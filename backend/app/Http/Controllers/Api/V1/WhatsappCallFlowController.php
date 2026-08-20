<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsappCallFlowResource;
use App\Models\ApiConnection;
use App\Models\WhatsappCallFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WhatsappCallFlowController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsappCallFlow::class);

        return WhatsappCallFlowResource::collection(
            WhatsappCallFlow::query()->with('apiConnection')->orderBy('name')->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WhatsappCallFlow::class);

        $data = $request->validate([
            'apiConnectionId' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,paused'],
            'greetingMessage' => ['required', 'string'],
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.id' => ['required', 'string'],
            'nodes.*.type' => ['required', 'in:question,menu,transfer_human,end_call'],
            'nodes.*.prompt' => ['required', 'string'],
            'nodes.*.options' => ['sometimes', 'nullable', 'array'],
            'nodes.*.variable_key' => ['sometimes', 'nullable', 'string'],
            'nodes.*.input_type' => ['sometimes', 'nullable', 'string'],
            'fallbackMessage' => ['sometimes', 'nullable', 'string'],
            'maxRetries' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);

        $apiConnection = ApiConnection::query()->where('uuid', $data['apiConnectionId'])->where('channel', 'whatsapp')->firstOrFail();

        $callFlow = WhatsappCallFlow::query()->create([
            'api_connection_id' => $apiConnection->id,
            'name' => $data['name'],
            'status' => $data['status'] ?? 'paused',
            'greeting_message' => $data['greetingMessage'],
            'nodes' => $data['nodes'],
            'fallback_message' => $data['fallbackMessage'] ?? null,
            'max_retries' => $data['maxRetries'] ?? 2,
        ]);

        return response()->json(['data' => new WhatsappCallFlowResource($callFlow->load('apiConnection'))], 201);
    }

    public function show(WhatsappCallFlow $whatsappCallFlow): JsonResponse
    {
        $this->authorize('view', $whatsappCallFlow);

        return response()->json(['data' => new WhatsappCallFlowResource($whatsappCallFlow->load('apiConnection'))]);
    }

    public function update(Request $request, WhatsappCallFlow $whatsappCallFlow): JsonResponse
    {
        $this->authorize('update', $whatsappCallFlow);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,paused'],
            'greetingMessage' => ['sometimes', 'string'],
            'nodes' => ['sometimes', 'array', 'min:1'],
            'nodes.*.id' => ['required_with:nodes', 'string'],
            'nodes.*.type' => ['required_with:nodes', 'in:question,menu,transfer_human,end_call'],
            'nodes.*.prompt' => ['required_with:nodes', 'string'],
            'nodes.*.options' => ['sometimes', 'nullable', 'array'],
            'nodes.*.variable_key' => ['sometimes', 'nullable', 'string'],
            'nodes.*.input_type' => ['sometimes', 'nullable', 'string'],
            'fallbackMessage' => ['sometimes', 'nullable', 'string'],
            'maxRetries' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);

        $update = [];
        $map = [
            'name' => 'name',
            'status' => 'status',
            'greetingMessage' => 'greeting_message',
            'nodes' => 'nodes',
            'fallbackMessage' => 'fallback_message',
            'maxRetries' => 'max_retries',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $whatsappCallFlow->update($update);

        return response()->json(['data' => new WhatsappCallFlowResource($whatsappCallFlow->load('apiConnection'))]);
    }

    public function destroy(WhatsappCallFlow $whatsappCallFlow): JsonResponse
    {
        $this->authorize('delete', $whatsappCallFlow);

        $whatsappCallFlow->delete();

        return response()->json(['message' => 'Call flow deleted.']);
    }
}
