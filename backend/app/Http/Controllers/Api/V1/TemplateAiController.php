<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use App\Services\Templates\AiTemplateDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TemplateAiController extends Controller
{
    public function __invoke(Request $request, ApiConnection $apiConnection, AiTemplateDraft $ai): JsonResponse
    {
        Gate::authorize('create', WhatsappTemplate::class);
        abort_unless($request->user()->company_id && $apiConnection->company_id === $request->user()->company_id, 404);
        abort_unless($apiConnection->channel === 'whatsapp', 422, 'Choose a WhatsApp connection to draft a template.');
        $input = $request->validate([
            'brief' => ['required', 'string', 'min:15', 'max:5000'],
            'language' => ['required', 'in:en_US,en_GB,es_ES,pt_BR,hi_IN'],
            'category' => ['required', 'in:utility,marketing'],
            'tone' => ['required', 'in:professional,friendly,concise'],
        ]);

        return response()->json(['data' => $ai->generate($request->user()->company_id, $input)]);
    }
}
