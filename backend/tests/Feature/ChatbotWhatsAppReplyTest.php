<?php

use App\Jobs\GenerateChatbotWhatsAppReply;
use App\Models\ApiConnection;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chatbot\ChatbotReplyServiceInterface;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createWhatsAppConversation(): array
{
    $company = Company::factory()->create();
    $contact = Contact::factory()->create([
        'company_id' => $company->id,
        'handle' => '555444333',
        'channel' => 'whatsapp',
    ]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => 'whatsapp',
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'What are your hours?',
    ]);

    return [$company, $conversation, $message];
}

it('generates and sends an ai chatbot reply for an inbound whatsapp message', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wa_reply_1']]], 200),
    ]);

    [$company, $conversation, $message] = createWhatsAppConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['website', 'whatsapp'],
    ]);

    ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'phone_number_id' => 'wa-phone-1',
    ]);

    (new GenerateChatbotWhatsAppReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();

    expect($reply)->not->toBeNull();
    expect($reply->text)->toBe('Fake reply to: What are your hours?');
    expect($reply->external_message_id)->toBe('wa_reply_1');
    expect($conversation->fresh()->chatbot_id)->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && str_contains($request->url(), 'wa-phone-1')
            && $request['to'] === '555444333';
    });
});

it('notifies chatbot managers when the reply is a handoff to a human', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wa_reply_2']]], 200),
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);

    [$company, $conversation, $message] = createWhatsAppConversation();

    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['website', 'whatsapp'],
    ]);

    ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'phone_number_id' => 'wa-phone-2',
    ]);

    $handoffReply = new class implements ChatbotReplyServiceInterface
    {
        public function reply($chatbot, $conversation, string $visitorMessage): string
        {
            return "Sorry, I'm unable to answer right now.";
        }

        public function isHandoff(string $reply): bool
        {
            return true;
        }
    };

    (new GenerateChatbotWhatsAppReply($conversation->id, $message->id))->handle(
        $handoffReply,
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    $this->assertDatabaseHas('notifications', [
        'user_id' => $manager->id,
        'type' => 'chatbot_handoff',
    ]);
});

it('does not generate a reply when no whatsapp-enabled chatbot is configured', function () {
    [$company, $conversation, $message] = createWhatsAppConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['website'],
    ]);

    (new GenerateChatbotWhatsAppReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeFalse();
});

it('skips the whatsapp chatbot reply when an automation flow already replied', function () {
    [$company, $conversation, $message] = createWhatsAppConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['whatsapp'],
    ]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'text' => 'Automation already replied',
    ]);

    (new GenerateChatbotWhatsAppReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->count())->toBe(1);
});

it('does not reply for a non-whatsapp conversation', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create([
        'company_id' => $company->id,
        'handle' => '@ig_555444333',
        'channel' => 'instagram',
    ]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => 'instagram',
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'Hello',
    ]);

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['whatsapp', 'instagram'],
    ]);

    (new GenerateChatbotWhatsAppReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeFalse();
});
