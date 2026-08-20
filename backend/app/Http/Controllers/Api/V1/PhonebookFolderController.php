<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Http\Resources\PhonebookFolderResource;
use App\Models\Contact;
use App\Models\PhonebookFolder;
use App\Services\Phonebook\ContactImportService;
use App\Services\Phonebook\ContactSpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhonebookFolderController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PhonebookFolder::class);

        return PhonebookFolderResource::collection(
            PhonebookFolder::query()
                ->withCount('contacts')
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function contacts(Request $request, PhonebookFolder $phonebookFolder): AnonymousResourceCollection
    {
        $this->authorize('view', $phonebookFolder);

        return ContactResource::collection(
            $phonebookFolder->contacts()->with('tags')->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PhonebookFolder::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folder = PhonebookFolder::query()->create(['name' => $data['name']]);

        return response()->json(['data' => new PhonebookFolderResource($folder->loadCount('contacts'))], 201);
    }

    public function show(PhonebookFolder $phonebookFolder): JsonResponse
    {
        $this->authorize('view', $phonebookFolder);

        return response()->json([
            'data' => new PhonebookFolderResource($phonebookFolder->loadCount('contacts')),
        ]);
    }

    public function update(Request $request, PhonebookFolder $phonebookFolder): JsonResponse
    {
        $this->authorize('update', $phonebookFolder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $phonebookFolder->update(['name' => $data['name']]);

        return response()->json(['data' => new PhonebookFolderResource($phonebookFolder->loadCount('contacts'))]);
    }

    public function destroy(PhonebookFolder $phonebookFolder): JsonResponse
    {
        $this->authorize('delete', $phonebookFolder);

        $phonebookFolder->delete();

        return response()->json(['message' => 'Phonebook folder deleted.']);
    }

    public function addContacts(Request $request, PhonebookFolder $phonebookFolder): JsonResponse
    {
        $this->authorize('manageContacts', $phonebookFolder);

        $data = $request->validate([
            'contactIds' => ['required', 'array', 'min:1'],
            'contactIds.*' => ['string'],
        ]);

        $contactIds = Contact::query()->whereIn('uuid', $data['contactIds'])->pluck('id');
        $phonebookFolder->contacts()->syncWithoutDetaching($contactIds);

        return response()->json([
            'data' => new PhonebookFolderResource($phonebookFolder->load('contacts.tags')->loadCount('contacts')),
        ]);
    }

    public function removeContact(PhonebookFolder $phonebookFolder, Contact $contact): JsonResponse
    {
        $this->authorize('manageContacts', $phonebookFolder);

        $phonebookFolder->contacts()->detach($contact->id);

        return response()->json([
            'data' => new PhonebookFolderResource($phonebookFolder->load('contacts.tags')->loadCount('contacts')),
        ]);
    }

    public function import(Request $request, ContactImportService $importer): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'folderId' => ['required_without:folderName', 'nullable', 'string'],
            'folderName' => ['required_without:folderId', 'nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['folderId']) && ! empty($data['folderName'])) {
            throw ValidationException::withMessages([
                'folderId' => 'Provide either folderId or folderName, not both.',
            ]);
        }

        if (! empty($data['folderId'])) {
            $folder = PhonebookFolder::query()->where('uuid', $data['folderId'])->first();

            if (! $folder) {
                throw ValidationException::withMessages([
                    'folderId' => 'The selected folder was not found.',
                ]);
            }

            $this->authorize('manageContacts', $folder);
        } else {
            $this->authorize('create', PhonebookFolder::class);
            $folder = PhonebookFolder::query()->firstOrCreate(['name' => $data['folderName']]);
        }

        $summary = $importer->import($request->file('file'), $folder);

        return response()->json([
            'data' => new PhonebookFolderResource($folder->loadCount('contacts')),
            'summary' => $summary,
        ]);
    }

    public function template(ContactSpreadsheetExporter $exporter): StreamedResponse
    {
        $this->authorize('viewAny', PhonebookFolder::class);

        return $exporter->template();
    }

    public function export(Request $request, PhonebookFolder $phonebookFolder, ContactSpreadsheetExporter $exporter): StreamedResponse
    {
        $this->authorize('view', $phonebookFolder);

        $data = $request->validate([
            'format' => ['sometimes', 'in:csv,xlsx'],
        ]);

        $contacts = $phonebookFolder->contacts()->get();
        $filename = 'phonebook-'.str($phonebookFolder->name)->slug();

        return $exporter->export($contacts, $data['format'] ?? 'xlsx', $filename);
    }
}
