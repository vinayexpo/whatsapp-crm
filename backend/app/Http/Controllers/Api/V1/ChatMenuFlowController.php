<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMenuFlowResource;
use App\Models\ChatMenuFlow;
use App\Services\ChatFlow\ChatMenuFlowGeneratorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ChatMenuFlowController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChatMenuFlow::class);

        return ChatMenuFlowResource::collection(
            ChatMenuFlow::query()
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->query('channel')))
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ChatMenuFlow::class);

        $data = $this->validateFlow($request, isUpdate: false);

        $flow = ChatMenuFlow::query()->create([
            'name' => $data['name'],
            'channel' => $data['channel'] ?? 'both',
            'status' => $data['status'] ?? 'paused',
            'trigger_keyword' => $data['triggerKeyword'] ?? null,
            'entry_node_id' => $data['entryNodeId'],
            'nodes' => $data['nodes'],
        ]);

        return response()->json(['data' => new ChatMenuFlowResource($flow)], 201);
    }

    public function show(ChatMenuFlow $chatMenuFlow): JsonResponse
    {
        $this->authorize('view', $chatMenuFlow);

        return response()->json(['data' => new ChatMenuFlowResource($chatMenuFlow)]);
    }

    public function update(Request $request, ChatMenuFlow $chatMenuFlow): JsonResponse
    {
        $this->authorize('update', $chatMenuFlow);

        $data = $this->validateFlow($request, isUpdate: true);

        $update = [];
        $map = [
            'name' => 'name',
            'channel' => 'channel',
            'status' => 'status',
            'triggerKeyword' => 'trigger_keyword',
            'entryNodeId' => 'entry_node_id',
            'nodes' => 'nodes',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $chatMenuFlow->update($update);

        return response()->json(['data' => new ChatMenuFlowResource($chatMenuFlow)]);
    }

    public function destroy(ChatMenuFlow $chatMenuFlow): JsonResponse
    {
        $this->authorize('delete', $chatMenuFlow);

        $chatMenuFlow->delete();

        return response()->json(['message' => 'Chat menu flow deleted.']);
    }

    public function generate(Request $request, ChatMenuFlowGeneratorServiceInterface $generator): JsonResponse
    {
        $this->authorize('create', ChatMenuFlow::class);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $draft = $generator->generate($request->user()->company, $data['prompt']);

        if (! $draft) {
            throw ValidationException::withMessages([
                'prompt' => 'Could not generate a menu from that description. Make sure an AI assistant is configured, and try a more specific prompt.',
            ]);
        }

        return response()->json(['data' => $draft]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFlow(Request $request, bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'channel' => ['sometimes', 'in:whatsapp,web,both'],
            'status' => ['sometimes', 'in:active,paused'],
            'triggerKeyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entryNodeId' => [$required, 'string'],
            'nodes' => [$required, 'array', 'min:1'],
            'nodes.*.id' => ['required_with:nodes', 'string'],
            'nodes.*.type' => ['required_with:nodes', 'in:menu,content'],
            'nodes.*.message' => ['required_with:nodes', 'string'],
            'nodes.*.mediaUrl' => ['sometimes', 'nullable', 'string'],
            'nodes.*.mediaType' => ['sometimes', 'nullable', 'string'],
            'nodes.*.renderAs' => ['sometimes', 'in:button,list'],
            'nodes.*.buttons' => ['sometimes', 'array', 'max:10'],
            'nodes.*.buttons.*.id' => ['required_with:nodes.*.buttons', 'string'],
            'nodes.*.buttons.*.label' => ['required_with:nodes.*.buttons', 'string', 'max:60'],
            'nodes.*.buttons.*.nextNodeId' => ['required_with:nodes.*.buttons', 'string'],
        ]);
    }
}
