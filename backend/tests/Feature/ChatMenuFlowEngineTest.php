<?php

use App\Models\ChatMenuFlow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatFlow\ChatMenuFlowEngine;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function makeChatMenuFlow(array $overrides = []): ChatMenuFlow
{
    return ChatMenuFlow::factory()->create(array_merge(['status' => 'active', 'trigger_keyword' => 'services'], $overrides));
}

it('starts a flow and sends its root node when the inbound text matches the trigger keyword', function () {
    $flow = makeChatMenuFlow();
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'company_id' => $flow->company_id]);
    $inbound = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound', 'text' => 'services']);

    $handled = app(ChatMenuFlowEngine::class)->handle($conversation, $inbound);

    expect($handled)->toBeTrue();

    $conversation->refresh();
    expect($conversation->current_chat_flow_id)->toBe($flow->id);
    expect($conversation->current_chat_flow_node_id)->toBe($flow->entry_node_id);

    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($reply->text)->toBe('What are you looking for?');
    expect($reply->buttons)->toHaveCount(2);
});

it('advances to the next node when a button is clicked via interactive_reply_id', function () {
    $flow = makeChatMenuFlow();
    $rootNode = collect($flow->nodes)->firstWhere('id', $flow->entry_node_id);
    $cateringButton = collect($rootNode['buttons'])->firstWhere('label', 'Catering');

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'channel' => 'whatsapp',
        'company_id' => $flow->company_id,
        'current_chat_flow_id' => $flow->id,
        'current_chat_flow_node_id' => $flow->entry_node_id,
    ]);
    $inbound = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'Catering',
        'interactive_reply_id' => $cateringButton['id'],
    ]);

    $handled = app(ChatMenuFlowEngine::class)->handle($conversation, $inbound);

    expect($handled)->toBeTrue();

    $conversation->refresh();
    // The catering node is a leaf (content, no buttons) so flow state clears.
    expect($conversation->current_chat_flow_id)->toBeNull();
    expect($conversation->current_chat_flow_node_id)->toBeNull();

    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($reply->text)->toContain('catering');
});

it('falls back to AI (returns false, keeps flow position) when free text is typed mid-flow', function () {
    $flow = makeChatMenuFlow();
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'channel' => 'whatsapp',
        'company_id' => $flow->company_id,
        'current_chat_flow_id' => $flow->id,
        'current_chat_flow_node_id' => $flow->entry_node_id,
    ]);
    $inbound = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'do you deliver on weekends?',
    ]);

    $handled = app(ChatMenuFlowEngine::class)->handle($conversation, $inbound);

    expect($handled)->toBeFalse();

    $conversation->refresh();
    expect($conversation->current_chat_flow_id)->toBe($flow->id);
    expect($conversation->current_chat_flow_node_id)->toBe($flow->entry_node_id);
});

it('does nothing when no flow is active and the inbound text does not match any trigger keyword', function () {
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $inbound = Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'inbound', 'text' => 'hello']);

    $handled = app(ChatMenuFlowEngine::class)->handle($conversation, $inbound);

    expect($handled)->toBeFalse();
});
