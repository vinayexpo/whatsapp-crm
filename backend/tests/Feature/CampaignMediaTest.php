<?php

use App\Jobs\SendCampaignMessage;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsCampaignMediaRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('creates a campaign with an image attachment via URL', function () {
    $user = actingAsCampaignMediaRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Image Blast',
        'channel' => 'whatsapp',
        'message' => 'Check this out',
        'attachmentType' => 'image',
        'attachmentUrl' => 'https://example.com/promo.jpg',
        'audienceTag' => 'VIP',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.attachmentUrl', 'https://example.com/promo.jpg');
    $response->assertJsonPath('data.attachmentType', 'image');
});

it('creates a campaign with an uploaded video file and stores it on the public disk', function () {
    Storage::fake('public');

    $user = actingAsCampaignMediaRole('manager');

    $response = $this->actingAs($user)->post('/api/v1/campaigns', [
        'name' => 'Video Blast',
        'channel' => 'whatsapp',
        'message' => 'Watch this',
        'attachmentType' => 'video',
        'attachmentFile' => UploadedFile::fake()->create('promo.mp4', 500, 'video/mp4'),
        'audienceTag' => 'VIP',
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $campaign = Campaign::query()->first();
    expect($campaign->attachment_type)->toBe('video');
    expect($campaign->attachment_url)->not->toBeNull();

    $path = str_replace('/storage/', '', parse_url($campaign->attachment_url, PHP_URL_PATH));
    Storage::disk('public')->assertExists($path);
});

it('rejects a campaign with attachmentType but no url or file', function () {
    $user = actingAsCampaignMediaRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Broken',
        'channel' => 'whatsapp',
        'message' => 'Hi',
        'attachmentType' => 'image',
        'audienceTag' => 'VIP',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('attachmentUrl');
});

it('rejects a campaign with attachmentUrl but no attachmentType', function () {
    $user = actingAsCampaignMediaRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Broken',
        'channel' => 'whatsapp',
        'message' => 'Hi',
        'attachmentUrl' => 'https://example.com/promo.jpg',
        'audienceTag' => 'VIP',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('attachmentType');
});

it('carries the campaign attachment through to the outbound message', function () {
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(2),
    ]);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'message' => 'Check this out',
        'attachment_url' => 'https://example.com/promo.jpg',
        'attachment_type' => 'image',
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    $message = Message::query()->find($recipient->fresh()->message_id);
    expect($message->attachment_url)->toBe('https://example.com/promo.jpg');
    expect($message->attachment_type)->toBe('image');
});

it('sends a WhatsApp image message via the Graph API when the message has an attachment', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.media123']]], 200),
    ]);

    ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'phone_number_id' => 'phone-456',
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(2),
    ]);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'message' => 'Check this out',
        'attachment_url' => 'https://example.com/promo.jpg',
        'attachment_type' => 'image',
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && $request['type'] === 'image'
            && $request['image']['link'] === 'https://example.com/promo.jpg';
    });
});

it('includes a header component with the attachment when a template has a media header', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.template-media123']]], 200),
    ]);

    ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'phone_number_id' => 'phone-789',
    ]);

    $template = WhatsappTemplate::factory()->create([
        'name' => 'birthday',
        'status' => 'approved',
        'body' => 'Happy Birthday {{1}}!',
        'components' => [
            ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['https://scontent.whatsapp.net/example-header.jpg']]],
            ['type' => 'BODY', 'text' => 'Happy Birthday {{1}}!'],
        ],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'message' => 'fallback text',
        'whatsapp_template_id' => $template->id,
        'template_variables' => ['1' => 'Amara'],
        'attachment_url' => 'https://example.com/birthday-cake.jpg',
        'attachment_type' => 'image',
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    Http::assertSent(function ($request) {
        $components = collect($request['template']['components'] ?? []);
        $header = $components->firstWhere('type', 'header');

        return str_contains($request->url(), 'graph.facebook.com')
            && $request['type'] === 'template'
            && $header
            && $header['parameters'][0]['image']['link'] === 'https://example.com/birthday-cake.jpg';
    });

    expect($recipient->fresh()->status)->toBe('sent');
});

it('fails clearly when a media-header template is sent without a campaign attachment', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.template-media456']]], 200),
    ]);

    ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'phone_number_id' => 'phone-790',
    ]);

    // The template's own "example.header_handle" is a Meta-internal,
    // expiring scontent.whatsapp.net preview link -- Meta 403s when trying
    // to re-fetch it at send time, so it must never be used as a stand-in
    // for a real attachment. A campaign without its own media must fail
    // with a clear reason instead of silently sending a broken payload.
    $template = WhatsappTemplate::factory()->create([
        'name' => 'anniversary',
        'status' => 'approved',
        'body' => 'Congrats {{1}}!',
        'components' => [
            ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['https://scontent.whatsapp.net/example-header.jpg']]],
            ['type' => 'BODY', 'text' => 'Congrats {{1}}!'],
        ],
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'message' => 'fallback text',
        'whatsapp_template_id' => $template->id,
        'template_variables' => ['1' => 'Amara'],
        'attachment_url' => null,
        'attachment_type' => null,
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));

    $recipient->refresh();
    expect($recipient->status)->toBe('failed');
    expect($recipient->failure_reason)->toContain('requires an image/video header');
});
