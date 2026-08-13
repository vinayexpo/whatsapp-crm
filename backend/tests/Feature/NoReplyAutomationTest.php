<?php

use App\Console\Commands\CheckNoReplyConversations;
use App\Jobs\EvaluateAutomationFlows;
use App\Models\AutomationFlow;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createStaleConversation(string $channel = 'whatsapp'): array
{
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => $channel]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => $channel,
        'status' => 'open',
        'last_message_at' => now()->subHours(30),
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(30),
    ]);

    return [$company, $contact, $conversation];
}

it('matches a no-reply flow for a conversation silent for more than 24 hours', function () {
    [$company, $contact, $conversation] = createStaleConversation();

    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'no-reply', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'send-message', 'value' => 'Still there?']],
    ]);

    (new EvaluateAutomationFlows($conversation->id, null, false, null, null, true))->handle();

    $message = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($message)->not->toBeNull();
    expect($message->text)->toBe('Still there?');
    expect($flow->fresh()->triggered_count)->toBe(1);
});

it('does not dispatch the no-reply check via the command when the last message was outbound', function () {
    [$company, $contact, $conversation] = createStaleConversation();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'sent_at' => now()->subHours(1),
    ]);

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'no-reply', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'send-message', 'value' => 'Still there?']],
    ]);

    $this->artisan(CheckNoReplyConversations::class)->assertSuccessful();

    expect($conversation->fresh()->no_reply_notified_at)->toBeNull();
    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->count())->toBe(1);
});

it('dispatches the no-reply automation for stale conversations via the scheduled command', function () {
    [$company, $contact, $conversation] = createStaleConversation();

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'no-reply', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'send-message', 'value' => 'Still there?']],
    ]);

    $this->artisan(CheckNoReplyConversations::class)->assertSuccessful();

    expect($conversation->fresh()->no_reply_notified_at)->not->toBeNull();
    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeTrue();
});

it('does not re-check a conversation that was already notified', function () {
    [$company, $contact, $conversation] = createStaleConversation();
    $conversation->update(['no_reply_notified_at' => now()]);

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'no-reply', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'send-message', 'value' => 'Still there?']],
    ]);

    $this->artisan(CheckNoReplyConversations::class)->assertSuccessful();

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeFalse();
});
