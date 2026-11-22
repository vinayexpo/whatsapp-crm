<?php

use App\Events\CompanyDataUpdated;
use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsappTemplate;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

it('broadcasts messages and conversation updates to both private conversation and company channels', function () {
    $company = Company::factory()->create();
    $conversation = Conversation::factory()->create(['company_id' => $company->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id]);

    foreach ([new MessageReceived($message), new ConversationUpdated($conversation)] as $event) {
        expect(array_map(fn ($channel) => $channel->name, $event->broadcastOn()))->toBe([
            'private-conversation.'.$conversation->uuid,
            'private-company.'.$company->uuid.'.conversations',
        ]);
        expect($event->afterCommit)->toBeTrue();
    }
});

it('keeps the new conversation broadcast bounded to its most recent message', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->create(['conversation_id' => $conversation->id, 'text' => 'Older', 'sent_at' => now()->subMinute()]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'text' => 'Newest', 'sent_at' => now()]);

    expect((new ConversationCreated($conversation))->broadcastWith()['conversation']['lastMessagePreview'])->toBe('Newest');
});

it('emits company refresh events for campaign counters and recipient failures', function () {
    $company = Company::factory()->create();
    $campaign = Campaign::factory()->create(['company_id' => $company->id]);
    $recipient = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id]);
    Event::fake([CompanyDataUpdated::class]);

    $campaign->increment('read_count');
    $recipient->update(['status' => 'failed', 'failure_reason' => 'Rejected by Meta']);

    Event::assertDispatchedTimes(CompanyDataUpdated::class, 2);
    Event::assertDispatched(CompanyDataUpdated::class, fn ($event) => $event->companyId === $company->id && $event->resource === 'campaigns');
    $event = new CompanyDataUpdated($company->id, 'campaigns');
    expect($event->broadcastOn()[0]->name)->toBe('private-company.'.$company->uuid.'.updates');
    expect($event->broadcastWith())->toBe(['resource' => 'campaigns']);
    expect($event->afterCommit)->toBeTrue();
});

it('broadcasts read and status changes so other inboxes and dashboard counts refresh', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $conversation = Conversation::factory()->create(['unread_count' => 3, 'status' => 'open']);
    Event::fake([ConversationUpdated::class]);

    $this->actingAs($user)->postJson('/api/v1/conversations/'.$conversation->uuid.'/read')->assertOk();
    expect($conversation->fresh()->unread_count)->toBe(0);
    $this->patchJson('/api/v1/conversations/'.$conversation->uuid.'/status', ['status' => 'resolved'])->assertOk();
    Event::assertDispatchedTimes(ConversationUpdated::class, 2);
});

it('applies template approval and rejection webhooks only to the matching WABA', function (string $status, ?string $reason) {
    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp', 'waba_id' => 'waba-a']);
    $other = ApiConnection::factory()->create(['channel' => 'whatsapp', 'waba_id' => 'waba-b']);
    $template = WhatsappTemplate::factory()->create([
        'company_id' => $company->id, 'api_connection_id' => $connection->id, 'meta_template_id' => 'meta-123', 'status' => 'pending',
    ]);
    $foreign = WhatsappTemplate::factory()->create([
        'api_connection_id' => $other->id, 'meta_template_id' => 'meta-123', 'status' => 'pending',
    ]);
    Event::fake([CompanyDataUpdated::class]);
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => ['entry' => [[
            'id' => 'waba-a',
            'changes' => [[
                'field' => 'message_template_status_update',
                'value' => ['message_template_id' => 'meta-123', 'event' => strtoupper($status), 'reason' => $reason ?? 'NONE'],
            ]],
        ]]],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    expect($template->fresh()->status)->toBe($status);
    expect($template->fresh()->rejection_reason)->toBe($reason);
    expect($foreign->fresh()->status)->toBe('pending');
    Event::assertDispatched(CompanyDataUpdated::class, fn ($event) => $event->companyId === $company->id && $event->resource === 'templates');
})->with([['approved', null], ['rejected', 'INVALID_FORMAT']]);

it('authorizes company update subscriptions only for the signed-in users company', function () {
    config(['broadcasting.default' => 'reverb']);
    Broadcast::purge('reverb');
    require base_path('routes/channels.php');
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');

    $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-company.'.$other->uuid.'.updates',
    ])->assertForbidden();

    $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-company.'.$company->uuid.'.updates',
    ])->assertOk();
});
