<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatbotTrainingEntryResource;
use App\Models\Chatbot;
use App\Models\ChatbotTrainingEntry;
use App\Services\Chatbot\TrainingEntryGeneratorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ChatbotTrainingEntryController extends Controller
{
    public function index(Chatbot $chatbot): AnonymousResourceCollection
    {
        $this->authorize('view', $chatbot);

        return ChatbotTrainingEntryResource::collection(
            $chatbot->trainingEntries()->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('update', $chatbot);

        $data = $request->validate([
            'question' => ['required', 'string'],
            'answer' => ['required', 'string'],
            'source' => ['sometimes', 'in:manual,ai'],
        ]);

        $entry = $chatbot->trainingEntries()->create([
            'question' => $data['question'],
            'answer' => $data['answer'],
            'source' => $data['source'] ?? 'manual',
        ]);

        return response()->json(['data' => new ChatbotTrainingEntryResource($entry)], 201);
    }

    public function update(Request $request, Chatbot $chatbot, ChatbotTrainingEntry $trainingEntry): JsonResponse
    {
        $this->authorize('update', $chatbot);
        $this->ensureBelongsToChatbot($chatbot, $trainingEntry);

        $data = $request->validate([
            'question' => ['sometimes', 'string'],
            'answer' => ['sometimes', 'string'],
        ]);

        $trainingEntry->update($data);

        return response()->json(['data' => new ChatbotTrainingEntryResource($trainingEntry)]);
    }

    public function destroy(Chatbot $chatbot, ChatbotTrainingEntry $trainingEntry): JsonResponse
    {
        $this->authorize('update', $chatbot);
        $this->ensureBelongsToChatbot($chatbot, $trainingEntry);

        $trainingEntry->delete();

        return response()->json(['message' => 'Training entry deleted.']);
    }

    public function generate(Request $request, Chatbot $chatbot, TrainingEntryGeneratorServiceInterface $generator): JsonResponse
    {
        $this->authorize('update', $chatbot);

        $data = $request->validate([
            'file' => ['required_without:text', 'nullable', 'file', 'mimes:txt,md', 'max:2048'],
            'text' => ['required_without:file', 'nullable', 'string', 'max:20000'],
        ]);

        if ($request->hasFile('file') && filled($data['text'] ?? null)) {
            throw ValidationException::withMessages([
                'file' => 'Provide either a file or pasted text, not both.',
            ]);
        }

        $sourceText = $request->hasFile('file')
            ? substr($request->file('file')->get(), 0, 20000)
            : $data['text'];

        $pairs = $generator->generate($chatbot, $sourceText);

        return response()->json(['data' => $pairs]);
    }

    private function ensureBelongsToChatbot(Chatbot $chatbot, ChatbotTrainingEntry $trainingEntry): void
    {
        abort_unless($trainingEntry->chatbot_id === $chatbot->id, 404);
    }
}
