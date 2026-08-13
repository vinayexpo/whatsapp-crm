<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Services\Phonebook\ContactSpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $query = Contact::query()->with('tags')->orderByDesc('last_interaction_at');

        if ($request->user()->hasRole('agent')) {
            $query->whereHas('conversations', fn ($q) => $q->where('assigned_to', $request->user()->id));
        }

        return ContactResource::collection($query->get());
    }

    public function export(Request $request, ContactSpreadsheetExporter $exporter): StreamedResponse
    {
        $this->authorize('viewAny', Contact::class);

        $data = $request->validate([
            'format' => ['sometimes', 'in:csv,xlsx'],
        ]);

        $contactsQuery = Contact::query()->orderBy('name');

        if ($request->user()->hasRole('agent')) {
            $contactsQuery->whereHas('conversations', fn ($q) => $q->where('assigned_to', $request->user()->id));
        }

        return $exporter->export($contactsQuery->get(), $data['format'] ?? 'xlsx', 'contacts');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatarUrl' => ['nullable', 'string'],
            'channel' => ['required', 'in:whatsapp,instagram'],
            'handle' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'pipelineStage' => ['required', 'exists:pipeline_stages,id'],
            'dealValue' => ['nullable', 'integer', 'min:0'],
        ]);

        $contact = Contact::query()->create([
            'name' => $data['name'],
            'avatar_url' => $data['avatarUrl'] ?? null,
            'channel' => $data['channel'],
            'handle' => $data['handle'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'location' => $data['location'] ?? null,
            'pipeline_stage_id' => $data['pipelineStage'],
            'deal_value' => $data['dealValue'] ?? 0,
            'last_interaction_at' => now(),
            'notes' => [],
            'purchases' => [],
        ]);

        return response()->json(['data' => new ContactResource($contact->load('tags'))], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return response()->json(['data' => new ContactResource($contact->load('tags'))]);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatarUrl' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dealValue' => ['sometimes', 'integer', 'min:0'],
        ]);

        $map = [
            'name' => 'name',
            'avatarUrl' => 'avatar_url',
            'phone' => 'phone',
            'email' => 'email',
            'location' => 'location',
            'dealValue' => 'deal_value',
        ];

        $updates = [];
        foreach ($map as $inputKey => $column) {
            if (array_key_exists($inputKey, $data)) {
                $updates[$column] = $data[$inputKey];
            }
        }

        $contact->update($updates);

        return response()->json(['data' => new ContactResource($contact->load('tags'))]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }

    public function updatePipelineStage(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('movePipelineStage', $contact);

        $data = $request->validate([
            'pipelineStage' => ['required', 'exists:pipeline_stages,id'],
            'updatedAt' => ['required', 'date'],
        ]);

        if ($contact->updated_at->notEqualTo($data['updatedAt'])) {
            throw ValidationException::withMessages([
                'updatedAt' => 'This contact was modified by someone else. Please refresh and try again.',
            ]);
        }

        $contact->update(['pipeline_stage_id' => $data['pipelineStage']]);

        return response()->json(['data' => new ContactResource($contact->load('tags'))]);
    }

    public function addTag(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('manageTags', $contact);

        $data = $request->validate([
            'tag' => ['required', 'string', 'max:255'],
        ]);

        $tag = Tag::query()->firstOrCreate(['name' => $data['tag']]);
        $contact->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['data' => new ContactResource($contact->load('tags'))]);
    }
}
