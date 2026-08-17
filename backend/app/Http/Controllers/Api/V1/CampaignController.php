<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignRecipientResource;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DailyMetric;
use App\Models\PhonebookFolder;
use App\Models\VoiceAgent;
use App\Models\WhatsappCallFlow;
use App\Models\WhatsappTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        return CampaignResource::collection(
            Campaign::query()
                ->with(['whatsappTemplate', 'phonebookFolder', 'voiceAgent', 'whatsappCallFlow'])
                ->withCount(['recipients as failed_recipients_count' => fn ($q) => $q->where('status', 'failed')])
                ->latest('created_at')
                ->get()
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'channel' => ['nullable', 'in:whatsapp,instagram,both'],
        ]);

        $campaigns = Campaign::query()
            ->when(! empty($data['channel']), fn ($q) => $q->where('channel', $data['channel']))
            ->when(! empty($data['from']), fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when(! empty($data['to']), fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
            ->get();

        $totals = [
            'campaignCount' => $campaigns->count(),
            'recipientCount' => (int) $campaigns->sum('recipient_count'),
            'deliveredCount' => (int) $campaigns->sum('delivered_count'),
            'readCount' => (int) $campaigns->sum('read_count'),
            'repliedCount' => (int) $campaigns->sum('replied_count'),
        ];
        $totals['deliveryRate'] = $totals['recipientCount'] > 0
            ? round(($totals['deliveredCount'] / $totals['recipientCount']) * 100, 1)
            : 0;
        $totals['readRate'] = $totals['deliveredCount'] > 0
            ? round(($totals['readCount'] / $totals['deliveredCount']) * 100, 1)
            : 0;
        $totals['replyRate'] = $totals['deliveredCount'] > 0
            ? round(($totals['repliedCount'] / $totals['deliveredCount']) * 100, 1)
            : 0;

        $ranked = $campaigns
            ->filter(fn (Campaign $c) => $c->recipient_count > 0)
            ->map(fn (Campaign $c) => [
                'id' => $c->uuid,
                'name' => $c->name,
                'channel' => $c->channel,
                'recipientCount' => $c->recipient_count,
                'deliveredCount' => $c->delivered_count,
                'readCount' => $c->read_count,
                'repliedCount' => $c->replied_count,
                'deliveryRate' => round(($c->delivered_count / $c->recipient_count) * 100, 1),
                'readRate' => $c->delivered_count > 0 ? round(($c->read_count / $c->delivered_count) * 100, 1) : 0,
                'replyRate' => $c->delivered_count > 0 ? round(($c->replied_count / $c->delivered_count) * 100, 1) : 0,
            ])
            ->sortByDesc('deliveryRate')
            ->values();

        $trend = DailyMetric::query()
            ->when(! empty($data['from']), fn ($q) => $q->whereDate('date', '>=', $data['from']))
            ->when(! empty($data['to']), fn ($q) => $q->whereDate('date', '<=', $data['to']))
            ->orderBy('date')
            ->get()
            ->map(fn (DailyMetric $m) => [
                'date' => $m->date->format('Y-m-d'),
                'sent' => $m->whatsapp_sent + $m->instagram_sent,
                'delivered' => $m->delivered,
                'read' => $m->read,
                'replied' => $m->replied,
            ])
            ->values();

        return response()->json([
            'data' => [
                'totals' => $totals,
                'trend' => $trend,
                'topPerformers' => $ranked->take(5)->values(),
                'bottomPerformers' => $ranked->reverse()->take(5)->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required_without_all:voiceAgentId,whatsappCallFlowId', 'nullable', 'in:whatsapp,instagram,both'],
            'message' => ['required_without_all:voiceAgentId,whatsappCallFlowId', 'nullable', 'string'],
            'attachmentType' => ['nullable', 'in:image,video'],
            'attachmentUrl' => ['nullable', 'url'],
            'attachmentFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm', 'max:51200'],
            'audienceTag' => ['required_without:phonebookFolderId', 'nullable', 'string'],
            'phonebookFolderId' => ['required_without:audienceTag', 'nullable', 'string'],
            'scheduledAt' => ['nullable', 'date'],
            'templateId' => ['nullable', 'string'],
            'templateVariables' => ['nullable', 'array'],
            'voiceAgentId' => ['nullable', 'string', 'prohibits:whatsappCallFlowId'],
            'whatsappCallFlowId' => ['nullable', 'string', 'prohibits:voiceAgentId'],
        ]);

        if (! empty($data['attachmentType']) && empty($data['attachmentUrl']) && ! $request->hasFile('attachmentFile')) {
            throw ValidationException::withMessages([
                'attachmentUrl' => 'Provide an attachmentUrl or attachmentFile when attachmentType is set.',
            ]);
        }

        if ((! empty($data['attachmentUrl']) || $request->hasFile('attachmentFile')) && empty($data['attachmentType'])) {
            throw ValidationException::withMessages([
                'attachmentType' => 'attachmentType is required when attaching media.',
            ]);
        }

        $attachmentUrl = $data['attachmentUrl'] ?? null;

        if ($request->hasFile('attachmentFile')) {
            $attachmentUrl = Storage::disk('public')->url(
                $request->file('attachmentFile')->store('campaign-attachments', 'public')
            );
        }

        if (! empty($data['audienceTag']) && ! empty($data['phonebookFolderId'])) {
            throw ValidationException::withMessages([
                'audienceTag' => 'Provide either audienceTag or phonebookFolderId, not both.',
            ]);
        }

        $template = null;

        if (! empty($data['templateId'])) {
            $template = WhatsappTemplate::query()->where('uuid', $data['templateId'])->first();

            if (! $template) {
                throw ValidationException::withMessages([
                    'templateId' => 'The selected template could not be found.',
                ]);
            }
        }

        $folder = null;

        if (! empty($data['phonebookFolderId'])) {
            $folder = PhonebookFolder::query()->where('uuid', $data['phonebookFolderId'])->first();

            if (! $folder) {
                throw ValidationException::withMessages([
                    'phonebookFolderId' => 'The selected phonebook folder could not be found.',
                ]);
            }
        }

        $isCallCampaign = ! empty($data['voiceAgentId']) || ! empty($data['whatsappCallFlowId']);

        $voiceAgent = null;

        if (! empty($data['voiceAgentId'])) {
            $voiceAgent = VoiceAgent::query()
                ->where('uuid', $data['voiceAgentId'])
                ->where('company_id', $request->user()->company_id)
                ->first();

            if (! $voiceAgent) {
                throw ValidationException::withMessages([
                    'voiceAgentId' => 'The selected voice agent could not be found.',
                ]);
            }
        }

        $whatsappCallFlow = null;

        if (! empty($data['whatsappCallFlowId'])) {
            $whatsappCallFlow = WhatsappCallFlow::query()
                ->where('uuid', $data['whatsappCallFlowId'])
                ->where('company_id', $request->user()->company_id)
                ->first();

            if (! $whatsappCallFlow) {
                throw ValidationException::withMessages([
                    'whatsappCallFlowId' => 'The selected WhatsApp call flow could not be found.',
                ]);
            }
        }

        $recipientCount = $folder
            ? $folder->contacts()
                ->when(! $isCallCampaign && $data['channel'] !== 'both', fn ($q) => $q->where('channel', $data['channel']))
                ->count()
            : Contact::query()
                ->whereHas('tags', fn ($q) => $q->where('name', $data['audienceTag']))
                ->when(! $isCallCampaign && $data['channel'] !== 'both', fn ($q) => $q->where('channel', $data['channel']))
                ->count();

        $isImmediate = empty($data['scheduledAt']);

        $campaign = Campaign::query()->create([
            'name' => $data['name'],
            'channel' => $data['channel'] ?? 'whatsapp',
            'status' => $isImmediate ? 'active' : 'scheduled',
            'message' => $data['message'] ?? '',
            'attachment_url' => $attachmentUrl,
            'attachment_type' => $attachmentUrl ? ($data['attachmentType'] ?? null) : null,
            'audience_tag' => $folder ? null : $data['audienceTag'],
            'phonebook_folder_id' => $folder?->id,
            'recipient_count' => $recipientCount,
            'delivered_count' => 0,
            'read_count' => 0,
            'replied_count' => 0,
            'scheduled_at' => $isImmediate ? now() : $data['scheduledAt'],
            'whatsapp_template_id' => $template?->id,
            'template_variables' => $template ? ($data['templateVariables'] ?? []) : null,
            'voice_agent_id' => $voiceAgent?->id,
            'whatsapp_call_flow_id' => $whatsappCallFlow?->id,
        ]);

        return response()->json(['data' => new CampaignResource($campaign)], 201);
    }

    public function recipients(Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('view', $campaign);

        return CampaignRecipientResource::collection(
            $campaign->recipients()
                ->with('contact')
                ->latest('id')
                ->get()
        );
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return response()->json(null, 204);
    }
}
