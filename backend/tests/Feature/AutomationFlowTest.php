<?php

use App\Jobs\EvaluateAutomationFlows;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\AutomationFlow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsappFlow;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsAutomationRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated automation flow listing', function () {
    $this->getJson('/api/v1/automation-flows')->assertUnauthorized();
});

it('allows an admin to list automation flows', function () {
    AutomationFlow::factory()->count(2)->create();
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/automation-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('filters automation flows by status', function () {
    AutomationFlow::factory()->create(['status' => 'active']);
    AutomationFlow::factory()->create(['status' => 'draft']);
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/automation-flows?status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('active');
});

it('filters automation flows by trigger channel', function () {
    AutomationFlow::factory()->create(['trigger' => ['type' => 'new-message', 'channel' => 'whatsapp']]);
    AutomationFlow::factory()->create(['trigger' => ['type' => 'new-message', 'channel' => 'instagram']]);
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/automation-flows?channel=instagram');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('filters automation flows by search matching name', function () {
    AutomationFlow::factory()->create(['name' => 'Welcome New Leads']);
    AutomationFlow::factory()->create(['name' => 'Follow Up Reminder']);
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/automation-flows?search=Welcome');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Welcome New Leads');
});

it('forbids an agent from listing automation flows', function () {
    $user = actingAsAutomationRole('agent');

    $this->actingAs($user)->getJson('/api/v1/automation-flows')->assertForbidden();
});

it('allows a manager to create an automation flow', function () {
    $user = actingAsAutomationRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/automation-flows', [
        'name' => 'Welcome new leads',
        'description' => 'Greets new contacts automatically',
        'status' => 'draft',
        'trigger' => ['type' => 'new-contact', 'channel' => 'whatsapp'],
        'conditions' => [],
        'actions' => [
            ['id' => 'act-1', 'type' => 'send-message', 'value' => 'Welcome!'],
        ],
    ]);

    $response->assertCreated();
    expect(AutomationFlow::query()->count())->toBe(1);
    $flow = AutomationFlow::query()->first();
    expect($flow->name)->toBe('Welcome new leads');
    expect($flow->trigger)->toBe(['type' => 'new-contact', 'channel' => 'whatsapp']);
});

it('forbids an agent from creating an automation flow', function () {
    $user = actingAsAutomationRole('agent');

    $this->actingAs($user)->postJson('/api/v1/automation-flows', [
        'name' => 'Should fail',
        'trigger' => ['type' => 'new-message', 'channel' => 'both'],
    ])->assertForbidden();
});

it('validates required fields when creating an automation flow', function () {
    $user = actingAsAutomationRole('admin');

    $this->actingAs($user)->postJson('/api/v1/automation-flows', [])
        ->assertUnprocessable();
});

it('allows updating an automation flow', function () {
    $flow = AutomationFlow::factory()->create(['name' => 'Old name']);
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->patchJson("/api/v1/automation-flows/{$flow->uuid}", [
        'name' => 'New name',
    ]);

    $response->assertOk();
    expect($flow->fresh()->name)->toBe('New name');
});

it('allows updating an automation flow status', function () {
    $flow = AutomationFlow::factory()->create(['status' => 'draft']);
    $user = actingAsAutomationRole('manager');

    $response = $this->actingAs($user)->patchJson("/api/v1/automation-flows/{$flow->uuid}/status", [
        'status' => 'active',
    ]);

    $response->assertOk();
    expect($flow->fresh()->status)->toBe('active');
});

it('allows deleting an automation flow', function () {
    $flow = AutomationFlow::factory()->create();
    $user = actingAsAutomationRole('admin');

    $this->actingAs($user)->deleteJson("/api/v1/automation-flows/{$flow->uuid}")
        ->assertNoContent();

    expect(AutomationFlow::query()->count())->toBe(0);
});

it('dispatches automation evaluation when an inbound whatsapp message is processed', function () {
    Queue::fake();

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['wa_id' => '15550001111', 'profile' => ['name' => 'New Lead']]],
                        'messages' => [[
                            'from' => '15550001111',
                            'id' => 'wamid.AUTOTEST1',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'price please'],
                        ]],
                    ],
                ]],
            ]],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    Queue::assertPushed(EvaluateAutomationFlows::class);
});

it('sends an automated reply when a keyword trigger matches an inbound message', function () {
    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'keyword', 'channel' => 'whatsapp', 'keyword' => 'price'],
        'actions' => [
            ['id' => 'act-1', 'type' => 'send-message', 'value' => 'Here is our price list!'],
        ],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'What is the price for this?',
    ]);

    (new EvaluateAutomationFlows($conversation->id, $message->id, false))->handle();

    expect($flow->fresh()->triggered_count)->toBe(1);
    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->count())->toBe(1);
    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($reply->text)->toBe('Here is our price list!');
});

it('does not trigger a keyword flow when the keyword is absent', function () {
    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'keyword', 'channel' => 'whatsapp', 'keyword' => 'refund'],
        'actions' => [
            ['id' => 'act-1', 'type' => 'send-message', 'value' => 'Sorry to hear that!'],
        ],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'What is the price for this?',
    ]);

    (new EvaluateAutomationFlows($conversation->id, $message->id, false))->handle();

    expect($flow->fresh()->triggered_count)->toBe(0);
});

it('does not trigger a draft or paused flow', function () {
    $flow = AutomationFlow::factory()->create([
        'status' => 'paused',
        'trigger' => ['type' => 'new-message', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'send-message', 'value' => 'Hi!']],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound']);

    (new EvaluateAutomationFlows($conversation->id, $message->id, false))->handle();

    expect($flow->fresh()->triggered_count)->toBe(0);
});

it('triggers a new-contact flow only when the contact was newly created', function () {
    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'new-contact', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'add-tag', 'value' => 'new-lead']],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound']);

    (new EvaluateAutomationFlows($conversation->id, $message->id, true))->handle();

    expect($flow->fresh()->triggered_count)->toBe(1);
    expect($contact->tags()->where('name', 'new-lead')->exists())->toBeTrue();
});

it('moves a contact pipeline stage via automation action', function () {
    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'new-message', 'channel' => 'both'],
        'actions' => [['id' => 'act-1', 'type' => 'move-pipeline-stage', 'value' => 'qualified']],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp', 'pipeline_stage_id' => 'new-lead']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound']);

    (new EvaluateAutomationFlows($conversation->id, $message->id, false))->handle();

    expect($flow->fresh()->triggered_count)->toBe(1);
    expect($contact->fresh()->pipeline_stage_id)->toBe('qualified');
});

it('sends a whatsapp flow via automation action', function () {
    $whatsappFlow = WhatsappFlow::factory()->create(['name' => 'Book a demo', 'status' => 'published']);

    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'new-message', 'channel' => 'whatsapp'],
        'actions' => [['id' => 'act-1', 'type' => 'send-whatsapp-flow', 'value' => $whatsappFlow->uuid]],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound']);

    (new EvaluateAutomationFlows($conversation->id, $message->id, false))->handle();

    expect($flow->fresh()->triggered_count)->toBe(1);
    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($reply)->not->toBeNull();
    expect($reply->text)->toContain('Book a demo');
});

it('lists all synced whatsapp flows across connections', function () {
    WhatsappFlow::factory()->create(['status' => 'published']);
    WhatsappFlow::factory()->create(['status' => 'draft']);
    $user = actingAsAutomationRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/whatsapp-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
