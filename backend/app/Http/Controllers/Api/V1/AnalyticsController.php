<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function pipelineFunnel(): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $counts = Contact::query()
            ->whereNotNull('pipeline_stage_id')
            ->selectRaw('pipeline_stage_id as stage, count(*) as count')
            ->groupBy('pipeline_stage_id')
            ->pluck('count', 'stage');

        return response()->json([
            'data' => $counts->map(fn ($count, $stage) => [
                'stage' => $stage,
                'count' => $count,
            ])->values(),
        ]);
    }

    public function dashboardSummary(): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $totalContacts = Contact::query()->count();
        $activeLeads = Contact::query()->whereNotIn('pipeline_stage_id', ['won', 'lost'])->count();
        $wonValue = (int) Contact::query()->where('pipeline_stage_id', 'won')->sum('deal_value');

        $openChats = Conversation::query()->whereIn('status', ['open', 'pending'])->count();
        $unreadMessages = (int) Conversation::query()->sum('unread_count');

        $activeCampaigns = Campaign::query()->whereIn('status', ['active', 'scheduled'])->count();
        $totalCampaigns = Campaign::query()->count();
        $totalRecipients = (int) Campaign::query()->sum('recipient_count');
        $totalReplied = (int) Campaign::query()->sum('replied_count');
        $conversionRate = $totalRecipients > 0 ? (int) round(($totalReplied / $totalRecipients) * 100) : 0;

        return response()->json([
            'data' => [
                'totalContacts' => $totalContacts,
                'activeLeads' => $activeLeads,
                'wonValue' => $wonValue,
                'openChats' => $openChats,
                'unreadMessages' => $unreadMessages,
                'activeCampaigns' => $activeCampaigns,
                'totalCampaigns' => $totalCampaigns,
                'conversionRate' => $conversionRate,
            ],
        ]);
    }
}
