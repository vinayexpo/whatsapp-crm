<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationFlowResource;
use App\Models\AutomationFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationFlowController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AutomationFlow::class);

        return AutomationFlowResource::collection(
            AutomationFlow::query()->latest('created_at')->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AutomationFlow::class);

        $data = $this->validated($request);

        $flow = AutomationFlow::query()->create($data)->refresh();

        return response()->json(['data' => new AutomationFlowResource($flow)], 201);
    }

    public function update(Request $request, AutomationFlow $automationFlow): JsonResponse
    {
        $this->authorize('update', $automationFlow);

        $data = $this->validated($request, partial: true);

        $automationFlow->update($data);

        return response()->json(['data' => new AutomationFlowResource($automationFlow)]);
    }

    public function updateStatus(Request $request, AutomationFlow $automationFlow): JsonResponse
    {
        $this->authorize('update', $automationFlow);

        $data = $request->validate([
            'status' => ['required', 'in:active,paused,draft'],
        ]);

        $automationFlow->update(['status' => $data['status']]);

        return response()->json(['data' => new AutomationFlowResource($automationFlow)]);
    }

    public function destroy(AutomationFlow $automationFlow): JsonResponse
    {
        $this->authorize('delete', $automationFlow);

        $automationFlow->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['in:active,paused,draft'],
            'trigger' => ['array'],
            'trigger.type' => ['in:keyword,new-message,new-contact,no-reply,story-mention,comment-received'],
            'trigger.channel' => ['in:whatsapp,instagram,both'],
            'trigger.keyword' => ['nullable', 'string'],
            'conditions' => ['array'],
            'actions' => ['array'],
        ];

        if (! $partial) {
            foreach (['name', 'trigger'] as $required) {
                $rules[$required][] = 'required';
            }
        }

        return $request->validate($rules);
    }
}
