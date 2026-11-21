<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\ApiConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\MessagingDriverResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Conversation::class);

        $query = Conversation::query()
            ->with(['contact', 'assignedTo', 'messages' => fn ($q) => $q->latest('sent_at')->limit(1)])
            ->orderByDesc('last_message_at');

        if ($request->user()->hasRole('agent')) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->query('assignedTo') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->query('assignedTo') === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($request->filled('assignedTo')) {
            $query->whereHas('assignedTo', fn ($q) => $q->where('uuid', $request->query('assignedTo')));
        }

        if ($request->filled('contactId')) {
            $query->whereHas('contact', fn ($q) => $q->where('uuid', $request->query('contactId')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('handle', 'like', "%{$search}%"));
        }

        return ConversationResource::collection($query->paginate($this->perPageFrom($request)));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $limit = min((int) $request->query('limit', 30), 100);
        $limit = $limit < 1 ? 30 : $limit;

        $query = $conversation->messages()->with('conversation')->orderByDesc('sent_at');

        if ($request->filled('before')) {
            $cursor = $conversation->messages()->where('uuid', $request->query('before'))->first();

            if ($cursor) {
                $query->where('sent_at', '<', $cursor->sent_at);
            }
        }

        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        $page = $messages->take($limit)->reverse()->values();

        return response()->json([
            'data' => MessageResource::collection($page),
            'meta' => ['hasMore' => $hasMore],
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation, MessagingDriverResolver $resolver): JsonResponse
    {
        $this->authorize('reply', $conversation);

        $data = $request->validate([
            'text' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,pdf,doc,docx,xls,xlsx,ppt,pptx,mp3,ogg,amr,aac', 'max:51200'],
        ]);

        if (empty($data['text']) && ! $request->hasFile('attachmentFile')) {
            throw ValidationException::withMessages([
                'text' => 'Provide a message or an attachment.',
            ]);
        }

        $attachmentUrl = null;
        $attachmentType = null;
        $attachmentName = null;
        $attachmentFile = $request->hasFile('attachmentFile') ? $request->file('attachmentFile') : null;

        if ($attachmentFile) {
            $mimeType = $attachmentFile->getMimeType() ?? '';
            $attachmentType = match (true) {
                str_starts_with($mimeType, 'image/') => 'image',
                str_starts_with($mimeType, 'video/') => 'video',
                str_starts_with($mimeType, 'audio/') => 'audio',
                default => 'document',
            };
            // Kept only for display in the CRM's own UI (MessageResource) --
            // the actual send to Meta below uploads the file bytes directly
            // and never relies on this URL being publicly fetchable.
            $attachmentUrl = Storage::disk('public')->url($attachmentFile->store('conversation-attachments', 'public'));
            $attachmentName = $attachmentFile->getClientOriginalName();
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $data['text'] ?? '',
            'status' => 'sent',
            'attachment_url' => $attachmentUrl,
            'attachment_type' => $attachmentType,
            'attachment_name' => $attachmentName,
            'sent_at' => now(),
        ]);

        if ($conversation->channel === 'website') {
            // Website-channel conversations are widget visitors, not a
            // WhatsApp/Instagram API connection — there's nothing to push
            // to externally. The widget picks this reply up via its
            // GET /widget/messages polling endpoint instead.
        } else {
            $connection = $conversation->api_connection_id
                ? ApiConnection::find($conversation->api_connection_id)
                : ApiConnection::query()->where('channel', $conversation->channel)->first();
            $externalId = $resolver->forConnection($connection)->send($message, $connection ?? new ApiConnection, null, $attachmentFile);
            $message->update(['external_message_id' => $externalId]);
        }

        $conversation->update([
            'last_message_at' => $message->sent_at,
            'no_reply_notified_at' => null,
        ]);

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        return response()->json(['data' => new MessageResource($message->load('conversation'))], 201);
    }

    /**
     * Every image/video/audio/document exchanged in a conversation, so the
     * inbox can show a media gallery without paging through the whole thread.
     */
    public function media(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $limit = min((int) $request->query('limit', 200), 500);
        $limit = $limit < 1 ? 200 : $limit;

        $messages = $conversation->messages()
            ->with('conversation')
            ->whereNotNull('attachment_url')
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get();

        return response()->json(['data' => MessageResource::collection($messages)]);
    }

    /**
     * Stream a stored attachment back through the API so documents always
     * download with their original filename, even when the underlying file
     * lives on a remote disk (S3) and its own URL would open in-browser.
     */
    public function downloadAttachment(Message $message)
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);

        $this->authorize('view', $conversation);

        abort_if(empty($message->attachment_url), 404);

        $file = Http::get($message->attachment_url);

        abort_unless($file->successful(), 404);

        $name = $message->attachment_name ?: basename(parse_url($message->attachment_url, PHP_URL_PATH) ?? 'attachment');

        return response()->streamDownload(
            fn () => print $file->body(),
            $name,
            ['Content-Type' => $file->header('Content-Type') ?: 'application/octet-stream'],
        );
    }

    public function markRead(Conversation $conversation): JsonResponse
    {        $this->authorize('update', $conversation);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['data' => new ConversationResource($conversation->load(['contact', 'assignedTo']))]);
    }

    public function updateStatus(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $data = $request->validate([
            'status' => ['required', 'in:open,pending,resolved,archived'],
        ]);

        $conversation->update(['status' => $data['status']]);

        return response()->json(['data' => new ConversationResource($conversation->load(['contact', 'assignedTo']))]);
    }

    public function assign(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('assign', $conversation);

        $data = $request->validate([
            'userId' => ['nullable', 'string'],
        ]);

        if (empty($data['userId'])) {
            $conversation->update(['assigned_to' => null]);
        } else {
            $assignee = User::query()
                ->where('uuid', $data['userId'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();

            $conversation->update(['assigned_to' => $assignee->id]);
        }

        $conversation->refresh()->load(['contact', 'assignedTo']);

        ConversationUpdated::dispatch($conversation);

        return response()->json(['data' => new ConversationResource($conversation)]);
    }
}
