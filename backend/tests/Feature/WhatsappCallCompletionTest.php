<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCall;
use App\Services\Calling\WhatsappCallFinalizer;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('creates a call-summary message in the linked conversation', function () {
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp_call', 'unread_count' => 0]);
    $whatsappCall = WhatsappCall::factory()->completed()->create([
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'status' => 'completed',
    ]);

    app(WhatsappCallFinalizer::class)->finalize($whatsappCall);

    $message = Message::query()->where('conversation_id', $conversation->id)->first();

    expect($message)->not->toBeNull();
    expect($message->direction)->toBe('inbound');
    expect($message->text)->toContain('Completed');
    expect($message->text)->toContain('budget');
    expect($conversation->fresh()->unread_count)->toBe(1);
});

it('creates a conversation for the call when none is linked yet', function () {
    $contact = Contact::factory()->create();
    $whatsappCall = WhatsappCall::factory()->create([
        'contact_id' => $contact->id,
        'conversation_id' => null,
        'status' => 'completed',
        'collected_variables' => [],
    ]);

    app(WhatsappCallFinalizer::class)->finalize($whatsappCall);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'whatsapp_call')->first();

    expect($conversation)->not->toBeNull();
    expect($whatsappCall->fresh()->conversation_id)->toBe($conversation->id);
});

it('marks needs_human_followup summary line differently when flagged', function () {
    $contact = Contact::factory()->create();
    $whatsappCall = WhatsappCall::factory()->create([
        'contact_id' => $contact->id,
        'status' => 'completed',
        'needs_human_followup' => true,
        'collected_variables' => [],
    ]);

    app(WhatsappCallFinalizer::class)->finalize($whatsappCall);

    $message = Message::query()->where('conversation_id', $whatsappCall->fresh()->conversation_id)->first();
    expect($message->text)->toContain('needs follow-up');
});

it('notifies users with whatsapp-calling.manage on a missed call', function () {
    app(\Database\Seeders\RolesAndPermissionsSeeder::class)->run();
    $company = \App\Models\Company::factory()->create();
    $manager = \App\Models\User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $whatsappCall = WhatsappCall::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'status' => 'missed',
        'needs_human_followup' => true,
        'collected_variables' => [],
    ]);

    app(WhatsappCallFinalizer::class)->finalize($whatsappCall);

    expect(\App\Models\Notification::query()->where('user_id', $manager->id)->where('type', 'whatsapp_call_missed')->exists())->toBeTrue();
});

it('does not notify for an in-progress call outcome', function () {
    app(\Database\Seeders\RolesAndPermissionsSeeder::class)->run();
    $company = \App\Models\Company::factory()->create();
    $manager = \App\Models\User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $whatsappCall = WhatsappCall::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'status' => 'in_progress',
        'collected_variables' => [],
    ]);

    app(WhatsappCallFinalizer::class)->finalize($whatsappCall);

    expect(\App\Models\Notification::query()->where('user_id', $manager->id)->exists())->toBeFalse();
});
