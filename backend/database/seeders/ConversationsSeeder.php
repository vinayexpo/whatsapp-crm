<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;

class ConversationsSeeder extends Seeder
{
    private const CONVERSATIONS = [
        [
            'handle' => '+1 415 555 0142',
            'channel' => 'whatsapp',
            'status' => 'open',
            'unread_count' => 2,
            'last_message_at' => '2026-08-01T14:32:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => "Hi! I'm interested in ordering in bulk for my store.", 'timestamp' => '2026-08-01T14:10:00Z', 'status' => 'read'],
                ['direction' => 'outbound', 'text' => "Hi Amara! That's great to hear. How many units are you looking to order?", 'timestamp' => '2026-08-01T14:15:00Z', 'status' => 'read'],
                ['direction' => 'inbound', 'text' => 'Around 200 units to start, possibly more each quarter.', 'timestamp' => '2026-08-01T14:20:00Z', 'status' => 'read'],
                ['direction' => 'inbound', 'text' => 'Can you send the bulk pricing sheet?', 'timestamp' => '2026-08-01T14:32:00Z', 'status' => 'delivered'],
            ],
        ],
        [
            'handle' => '@diego.codes',
            'channel' => 'instagram',
            'status' => 'pending',
            'unread_count' => 1,
            'last_message_at' => '2026-08-01T11:05:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => 'Hey! Saw your story, is this still available?', 'timestamp' => '2026-08-01T11:05:00Z', 'status' => 'delivered'],
            ],
        ],
        [
            'handle' => '+91 98765 43210',
            'channel' => 'whatsapp',
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => '2026-07-31T18:20:00Z',
            'messages' => [
                ['direction' => 'outbound', 'text' => "Hi Priya, here's the custom integration proposal we discussed.", 'timestamp' => '2026-07-31T17:50:00Z', 'status' => 'read'],
                ['direction' => 'inbound', 'text' => "Great, I'll review the proposal by Friday.", 'timestamp' => '2026-07-31T18:20:00Z', 'status' => 'read'],
            ],
        ],
        [
            'handle' => '@marcuschen.design',
            'channel' => 'instagram',
            'status' => 'resolved',
            'unread_count' => 0,
            'last_message_at' => '2026-07-30T09:15:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => 'Thanks again, loving the new dashboard!', 'timestamp' => '2026-07-30T09:15:00Z', 'status' => 'read'],
            ],
        ],
        [
            'handle' => '+39 320 555 1122',
            'channel' => 'whatsapp',
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => '2026-07-29T16:40:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => 'Avete un catalogo in italiano?', 'timestamp' => '2026-07-29T16:30:00Z', 'status' => 'read'],
                ['direction' => 'outbound', 'text' => 'Certo, vi mando il catalogo in italiano.', 'timestamp' => '2026-07-29T16:40:00Z', 'status' => 'sent'],
            ],
        ],
        [
            'handle' => '@tunde.b',
            'channel' => 'instagram',
            'status' => 'pending',
            'unread_count' => 3,
            'last_message_at' => '2026-08-01T08:50:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => 'How much for a custom order of 50 units?', 'timestamp' => '2026-08-01T08:50:00Z', 'status' => 'delivered'],
            ],
        ],
        [
            'handle' => '+82 10 5555 7890',
            'channel' => 'whatsapp',
            'status' => 'archived',
            'unread_count' => 0,
            'last_message_at' => '2026-07-20T10:00:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => "We've decided to go another direction, thanks.", 'timestamp' => '2026-07-20T10:00:00Z', 'status' => 'read'],
            ],
        ],
        [
            'handle' => '@liam.obrien',
            'channel' => 'instagram',
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => '2026-07-31T13:10:00Z',
            'messages' => [
                ['direction' => 'inbound', 'text' => 'Is there a loyalty discount for returning customers?', 'timestamp' => '2026-07-31T13:10:00Z', 'status' => 'read'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CONVERSATIONS as $data) {
            $contact = Contact::query()->where('handle', $data['handle'])->where('channel', $data['channel'])->first();
            if (! $contact) {
                continue;
            }

            $conversation = Conversation::query()->updateOrCreate(
                ['contact_id' => $contact->id, 'channel' => $data['channel']],
                [
                    'company_id' => $contact->company_id,
                    'status' => $data['status'],
                    'unread_count' => $data['unread_count'],
                    'last_message_at' => $data['last_message_at'],
                ],
            );

            foreach ($data['messages'] as $message) {
                Message::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sent_at' => $message['timestamp'],
                        'text' => $message['text'],
                    ],
                    [
                        'direction' => $message['direction'],
                        'status' => $message['status'],
                    ],
                );
            }
        }
    }
}
