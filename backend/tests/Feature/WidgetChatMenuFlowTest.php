<?php

use App\Models\ChatMenuFlow;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\Message;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createWidgetChatbot(array $attributes = []): Chatbot
{
    return Chatbot::factory()->create(array_merge(
        ['company_id' => Company::factory()->create()->id],
        $attributes
    ));
}

it('starts a chat menu flow and returns its buttons when the widget message matches the trigger keyword', function () {
    $chatbot = createWidgetChatbot();
    ChatMenuFlow::factory()->create([
        'company_id' => $chatbot->company_id,
        'channel' => 'both',
        'status' => 'active',
        'trigger_keyword' => 'services',
    ]);

    $response = $this->postJson('/api/widget/messages', ['text' => 'services'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'widget-flow-visitor',
    ]);

    $response->assertCreated();
    expect($response->json('data.reply'))->toBe('What are you looking for?');
    expect($response->json('data.message.buttons'))->toHaveCount(2);
});

it('advances the flow when the widget submits a button id as plain text', function () {
    $chatbot = createWidgetChatbot();
    $flow = ChatMenuFlow::factory()->create([
        'company_id' => $chatbot->company_id,
        'channel' => 'both',
        'status' => 'active',
        'trigger_keyword' => 'services',
    ]);

    $this->postJson('/api/widget/messages', ['text' => 'services'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'widget-flow-visitor-2',
    ])->assertCreated();

    $rootNode = collect($flow->nodes)->firstWhere('id', $flow->entry_node_id);
    $cateringButton = collect($rootNode['buttons'])->firstWhere('label', 'Catering');

    $response = $this->postJson('/api/widget/messages', ['text' => $cateringButton['id']], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'widget-flow-visitor-2',
    ]);

    $response->assertCreated();
    expect($response->json('data.reply'))->toContain('catering');

    $inboundClick = Message::query()
        ->where('direction', 'inbound')
        ->where('text', $cateringButton['id'])
        ->first();
    expect($inboundClick)->not->toBeNull();
});
