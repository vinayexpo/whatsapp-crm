<?php

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\PhonebookFolder;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsCampaignFolderRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('creates a campaign targeting a phonebook folder and resolves recipient count from its contacts', function () {
    $folder = PhonebookFolder::factory()->create(['name' => 'Launch List']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    Contact::factory()->create(['channel' => 'whatsapp']); // not in folder, should not count
    $folder->contacts()->attach($contact->id);

    $user = actingAsCampaignFolderRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Folder Campaign',
        'channel' => 'whatsapp',
        'message' => 'Hello!',
        'phonebookFolderId' => $folder->uuid,
    ]);

    $response->assertCreated();
    $campaign = Campaign::query()->first();
    expect($campaign->recipient_count)->toBe(1);
    expect($campaign->audience_tag)->toBeNull();
    expect($campaign->phonebook_folder_id)->toBe($folder->id);
    $response->assertJsonPath('data.phonebookFolderId', $folder->uuid);
    $response->assertJsonPath('data.audienceTag', null);
});

it('rejects campaign creation when both audienceTag and phonebookFolderId are provided', function () {
    $folder = PhonebookFolder::factory()->create();
    $user = actingAsCampaignFolderRole('manager');

    $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Bad Campaign',
        'channel' => 'whatsapp',
        'message' => 'Hello!',
        'audienceTag' => 'VIP',
        'phonebookFolderId' => $folder->uuid,
    ])->assertStatus(422);
});

it('rejects campaign creation when neither audienceTag nor phonebookFolderId are provided', function () {
    $user = actingAsCampaignFolderRole('manager');

    $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Bad Campaign',
        'channel' => 'whatsapp',
        'message' => 'Hello!',
    ])->assertStatus(422);
});

it('rejects an unknown phonebookFolderId', function () {
    $user = actingAsCampaignFolderRole('manager');

    $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Bad Campaign',
        'channel' => 'whatsapp',
        'message' => 'Hello!',
        'phonebookFolderId' => 'not-a-real-uuid',
    ])->assertStatus(422);
});

it('dispatches to the folder contacts fresh at dispatch time for folder-targeted campaigns', function () {
    Queue::fake();

    $folder = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $folder->contacts()->attach($contact->id);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'audience_tag' => null,
        'phonebook_folder_id' => $folder->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
    ]);

    // Add another contact to the folder after campaign creation, proving
    // recipient resolution happens fresh at dispatch time, not from a cached list.
    $secondContact = Contact::factory()->create(['channel' => 'whatsapp']);
    $folder->contacts()->attach($secondContact->id);

    Artisan::call('campaigns:dispatch');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(2);
    expect($campaign->fresh()->status)->toBe('completed');
    Queue::assertPushed(SendCampaignMessage::class, 2);
});

it('only sends to folder contacts matching the campaign channel', function () {
    Queue::fake();

    $folder = PhonebookFolder::factory()->create();
    $whatsappContact = Contact::factory()->create(['channel' => 'whatsapp']);
    $instagramContact = Contact::factory()->create(['channel' => 'instagram']);
    $folder->contacts()->attach([$whatsappContact->id, $instagramContact->id]);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'audience_tag' => null,
        'phonebook_folder_id' => $folder->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
    ]);

    Artisan::call('campaigns:dispatch');

    $recipientContactIds = CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('contact_id');
    expect($recipientContactIds)->toHaveCount(1);
    expect($recipientContactIds->first())->toBe($whatsappContact->id);
});
