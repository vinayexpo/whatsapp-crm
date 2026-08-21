<?php

namespace App\Http\Controllers\Api\Widget;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PipelineStage;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\ChatFlow\ChatMenuFlowEngine;
use App\Services\Chatbot\ChatbotReplyServiceInterface;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class WidgetController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $chatbot = $this->resolveChatbot($request);

        $visitorId = $request->header('X-Visitor-Id');

        if (empty($visitorId)) {
            return response()->json(['message' => 'The X-Visitor-Id header is required.'], 422);
        }

        [, $conversation] = $this->resolveContactAndConversation($chatbot, $visitorId);

        $messages = $conversation->messages()->with('conversation')->orderBy('sent_at')->get();

        return response()->json([
            'data' => [
                'welcomeMessage' => $chatbot->welcome_message,
                'conversationId' => $conversation->uuid,
                'messages' => MessageResource::collection($messages),
            ],
        ]);
    }

    public function sendMessage(Request $request, ChatbotReplyServiceInterface $replyService, ChatMenuFlowEngine $chatMenuFlowEngine): JsonResponse
    {
        $chatbot = $this->resolveChatbot($request);

        $visitorId = $request->header('X-Visitor-Id');

        if (empty($visitorId)) {
            return response()->json(['message' => 'The X-Visitor-Id header is required.'], 422);
        }

        $data = $request->validate([
            'text' => ['required', 'string'],
            'buttonLabel' => ['sometimes', 'nullable', 'string'],
        ]);

        [$contact, $conversation] = $this->resolveContactAndConversation($chatbot, $visitorId);

        // A button click submits its id as `text` (so ChatMenuFlowEngine can match
        // it) alongside the human-readable `buttonLabel`. Store the label as the
        // visible message text and the id in interactive_reply_id, matching how
        // WhatsApp interactive replies are stored — otherwise the raw button
        // UUID ends up shown as the message text.
        $isButtonClick = ! empty($data['buttonLabel']);

        $inboundMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'text' => $isButtonClick ? $data['buttonLabel'] : $data['text'],
            'status' => 'delivered',
            'sent_at' => now(),
            'interactive_reply_id' => $isButtonClick ? $data['text'] : null,
        ]);

        $conversation->update([
            'last_message_at' => $inboundMessage->sent_at,
            'unread_count' => $conversation->unread_count + 1,
        ]);
        $contact->update(['last_interaction_at' => $inboundMessage->sent_at]);

        MessageReceived::dispatch($inboundMessage->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        $handledByChatFlow = $chatMenuFlowEngine->handle($conversation, $inboundMessage);

        if ($handledByChatFlow) {
            $outboundMessage = $conversation->messages()->where('direction', 'outbound')->latest('id')->first();

            return response()->json([
                'data' => [
                    'reply' => $outboundMessage?->text,
                    'message' => $outboundMessage ? new MessageResource($outboundMessage) : null,
                ],
            ], 201);
        }

        $replyText = $replyService->reply($chatbot, $conversation, $data['text']);

        $outboundMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $replyText,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $outboundMessage->sent_at]);

        MessageReceived::dispatch($outboundMessage->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        if ($replyService->isHandoff($replyText)) {
            $this->notifyHandoff($chatbot, $conversation);
        }

        return response()->json([
            'data' => [
                'reply' => $replyText,
                'message' => new MessageResource($outboundMessage),
            ],
        ], 201);
    }

    private function notifyHandoff(Chatbot $chatbot, Conversation $conversation): void
    {
        if (! Permission::where('name', 'chatbots.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $contactName = $conversation->contact?->name ?? 'a visitor';

        User::query()
            ->where('company_id', $chatbot->company_id)
            ->permission('chatbots.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'chatbot_handoff',
                'Chatbot needs human help',
                "\"{$chatbot->name}\" couldn't answer {$contactName} and may need a human follow-up.",
                ['conversationId' => $conversation->uuid],
            ));
    }

    public function messages(Request $request): JsonResponse
    {
        $chatbot = $this->resolveChatbot($request);

        $visitorId = $request->header('X-Visitor-Id');

        if (empty($visitorId)) {
            return response()->json(['message' => 'The X-Visitor-Id header is required.'], 422);
        }

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $chatbot->company_id)
            ->where('channel', 'website')
            ->where('handle', $visitorId)
            ->first();

        $conversation = $contact
            ? Conversation::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $chatbot->company_id)
                ->where('contact_id', $contact->id)
                ->where('chatbot_id', $chatbot->id)
                ->first()
            : null;

        if (! $conversation) {
            return response()->json(['message' => 'No conversation found for this visitor.'], 404);
        }

        $data = $request->validate([
            'since' => ['sometimes', 'nullable', 'string'],
        ]);

        $query = $conversation->messages()->with('conversation')->orderBy('sent_at');

        if (! empty($data['since'])) {
            $since = $data['since'];
            $sinceMessage = Message::query()->where('uuid', $since)->first();

            if ($sinceMessage) {
                $query->where('id', '>', $sinceMessage->id);
            } else {
                $query->where('sent_at', '>', $since);
            }
        }

        return response()->json([
            'data' => MessageResource::collection($query->get()),
        ]);
    }

    private function resolveChatbot(Request $request): Chatbot
    {
        return $request->attributes->get('chatbot');
    }

    /**
     * @return array{0: Contact, 1: Conversation}
     */
    private function resolveContactAndConversation(Chatbot $chatbot, string $visitorId): array
    {
        return DB::transaction(function () use ($chatbot, $visitorId) {
            $pipelineStageId = $this->resolvePipelineStageId($chatbot);

            // company_id is deliberately not mass-assignable on Contact/Conversation
            // (BelongsToCompany normally fills it from the authenticated user). The
            // widget flow has no authenticated user, so it must be set explicitly
            // here rather than relying on firstOrCreate()'s array-based mass
            // assignment, which would silently drop it.
            $contact = Contact::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $chatbot->company_id)
                ->where('channel', 'website')
                ->where('handle', $visitorId)
                ->first();

            if (! $contact) {
                $contact = new Contact([
                    'name' => 'Website Visitor',
                    'channel' => 'website',
                    'handle' => $visitorId,
                    'pipeline_stage_id' => $pipelineStageId,
                    'last_interaction_at' => now(),
                    'notes' => [],
                    'purchases' => [],
                ]);
                $contact->company_id = $chatbot->company_id;
                $contact->save();
            }

            $conversation = Conversation::withoutGlobalScope(CompanyScope::class)
                ->where('contact_id', $contact->id)
                ->where('chatbot_id', $chatbot->id)
                ->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'chatbot_id' => $chatbot->id,
                    'channel' => 'website',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $chatbot->company_id;
                $conversation->save();
            }

            return [$contact, $conversation];
        });
    }

    private function resolvePipelineStageId(Chatbot $chatbot): string
    {
        return PipelineStage::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $chatbot->company_id)
            ->orderByRaw("case when id = 'new-lead' then 0 else 1 end")
            ->orderBy('position')
            ->value('id')
            ?? 'new-lead';
    }
}
