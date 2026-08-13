<?php

namespace App\Console\Commands;

use App\Models\DailyMetric;
use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('metrics:aggregate-daily {date? : The date to aggregate (Y-m-d), defaults to today}')]
#[Description('Aggregates message volume, delivery, read, reply, and response-time metrics for a single day.')]
class AggregateDailyMetrics extends Command
{
    public function handle(): void
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $outbound = Message::query()
            ->with('conversation')
            ->where('direction', 'outbound')
            ->whereBetween('sent_at', [$start, $end])
            ->get();

        $whatsappSent = $outbound->filter(fn (Message $m) => $m->conversation?->channel === 'whatsapp')->count();
        $instagramSent = $outbound->filter(fn (Message $m) => $m->conversation?->channel === 'instagram')->count();
        $delivered = $outbound->whereIn('status', ['delivered', 'read'])->count();
        $read = $outbound->where('status', 'read')->count();

        $inbound = Message::query()
            ->where('direction', 'inbound')
            ->whereBetween('sent_at', [$start, $end])
            ->get();
        $replied = $inbound->filter(function (Message $m) {
            return Message::query()
                ->where('conversation_id', $m->conversation_id)
                ->where('direction', 'outbound')
                ->where('sent_at', '>', $m->sent_at)
                ->exists();
        })->count();

        $responseMinutes = $inbound->map(function (Message $m) {
            $nextReply = Message::query()
                ->where('conversation_id', $m->conversation_id)
                ->where('direction', 'outbound')
                ->where('sent_at', '>', $m->sent_at)
                ->orderBy('sent_at')
                ->first();

            return $nextReply ? $m->sent_at->diffInMinutes($nextReply->sent_at, absolute: true) : null;
        })->filter(fn ($v) => $v !== null);

        $avgResponseMinutes = $responseMinutes->isNotEmpty() ? (int) round($responseMinutes->avg()) : 0;

        DailyMetric::query()->updateOrCreate(
            ['date' => $start->toDateString()],
            [
                'whatsapp_sent' => $whatsappSent,
                'instagram_sent' => $instagramSent,
                'delivered' => $delivered,
                'read' => $read,
                'replied' => $replied,
                'avg_response_minutes' => $avgResponseMinutes,
            ]
        );

        $this->info("Aggregated metrics for {$start->toDateString()}.");
    }
}
