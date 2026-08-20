<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsappTemplateResource;
use App\Models\ApiConnection;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Templates\TemplateDriverResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class WhatsappTemplateController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request, ApiConnection $apiConnection): AnonymousResourceCollection
    {
        if (! $request->user()->can('campaigns.view')) {
            throw new AuthorizationException;
        }

        return WhatsappTemplateResource::collection(
            $apiConnection->templates()->orderBy('name')->paginate($this->perPageFrom($request))
        );
    }

    public function sync(Request $request, ApiConnection $apiConnection, TemplateDriverResolver $resolver, NotificationDispatchService $dispatchService): JsonResponse
    {
        $this->authorizeCampaignsManage($request);

        if ($apiConnection->channel !== 'whatsapp') {
            throw ValidationException::withMessages([
                'apiConnection' => 'Template sync is only available for WhatsApp connections.',
            ]);
        }

        $templates = $resolver->forConnection($apiConnection)->fetchTemplates($apiConnection);
        $syncedAt = now();

        foreach ($templates as $template) {
            $existing = WhatsappTemplate::query()
                ->where('api_connection_id', $apiConnection->id)
                ->where('meta_template_id', $template['meta_template_id'])
                ->first();

            $previousStatus = $existing?->status;

            $record = WhatsappTemplate::query()->updateOrCreate(
                [
                    'api_connection_id' => $apiConnection->id,
                    'meta_template_id' => $template['meta_template_id'],
                ],
                [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'category' => $template['category'],
                    'status' => $template['status'],
                    'body' => $template['body'],
                    'variables' => $template['variables'],
                    'components' => $template['components'] ?? [],
                    'synced_at' => $syncedAt,
                ]
            );

            if ($previousStatus && $previousStatus !== $record->status) {
                $this->notifyStatusTransition($record, $dispatchService);
            }
        }

        return WhatsappTemplateResource::collection(
            $apiConnection->templates()->orderBy('name')->get()
        )->response();
    }

    public function store(Request $request, ApiConnection $apiConnection): JsonResponse
    {
        Gate::authorize('create', WhatsappTemplate::class);

        if ($apiConnection->channel !== 'whatsapp') {
            throw ValidationException::withMessages([
                'apiConnection' => 'Templates can only be created for WhatsApp connections.',
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:20',
            'category' => 'required|string|in:utility,marketing,authentication',
            'body' => 'required|string|max:1024',
            'variables' => 'array',
            'variables.*' => 'string',
            'components' => 'array',
        ]);

        $template = WhatsappTemplate::query()->create([
            'api_connection_id' => $apiConnection->id,
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'status' => 'draft',
            'body' => $data['body'],
            'variables' => $data['variables'] ?? [],
            'components' => $data['components'] ?? [],
            'created_by_user_id' => $request->user()->id,
        ]);

        return (new WhatsappTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(Request $request, WhatsappTemplate $whatsappTemplate): JsonResponse
    {
        Gate::authorize('update', $whatsappTemplate);

        if ($whatsappTemplate->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft templates can be edited.',
            ]);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'language' => 'sometimes|required|string|max:20',
            'category' => 'sometimes|required|string|in:utility,marketing,authentication',
            'body' => 'sometimes|required|string|max:1024',
            'variables' => 'array',
            'variables.*' => 'string',
            'components' => 'array',
        ]);

        $whatsappTemplate->update($data);

        return (new WhatsappTemplateResource($whatsappTemplate))->response();
    }

    public function submit(Request $request, WhatsappTemplate $whatsappTemplate, TemplateDriverResolver $resolver, NotificationDispatchService $dispatchService): JsonResponse
    {
        Gate::authorize('update', $whatsappTemplate);

        if ($whatsappTemplate->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft templates can be submitted.',
            ]);
        }

        $result = $resolver->forConnection($whatsappTemplate->apiConnection)->submitTemplate(
            $whatsappTemplate->apiConnection,
            $whatsappTemplate,
        );

        $whatsappTemplate->update([
            'meta_template_id' => $result['meta_template_id'],
            'status' => $result['status'],
            'submitted_at' => now(),
            'synced_at' => now(),
        ]);

        $this->notifyStatusTransition($whatsappTemplate, $dispatchService);

        return (new WhatsappTemplateResource($whatsappTemplate))->response();
    }

    public function destroy(WhatsappTemplate $whatsappTemplate): JsonResponse
    {
        Gate::authorize('delete', $whatsappTemplate);

        if ($whatsappTemplate->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft templates can be deleted.',
            ]);
        }

        $whatsappTemplate->delete();

        return response()->json(status: 204);
    }

    private function authorizeCampaignsManage(Request $request): void
    {
        if (! $request->user()->can('campaigns.manage')) {
            throw new AuthorizationException;
        }
    }

    private function notifyStatusTransition(WhatsappTemplate $template, NotificationDispatchService $dispatchService): void
    {
        if (! in_array($template->status, ['approved', 'rejected'], true)) {
            return;
        }

        if (! Permission::where('name', 'campaigns.manage')->exists()) {
            return;
        }

        $type = $template->status === 'approved' ? 'template_approved' : 'template_rejected';
        $title = $template->status === 'approved' ? 'Template approved' : 'Template rejected';
        $body = $template->status === 'approved'
            ? "\"{$template->name}\" has been approved by Meta."
            : "\"{$template->name}\" was rejected by Meta.";

        User::query()
            ->where('company_id', $template->company_id)
            ->permission('campaigns.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                $type,
                $title,
                $body,
                ['templateId' => $template->uuid],
            ));
    }
}
