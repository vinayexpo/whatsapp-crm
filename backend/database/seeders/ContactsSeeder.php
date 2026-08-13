<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ContactsSeeder extends Seeder
{
    public function __construct(private readonly ?int $companyId = null) {}

    private const CONTACTS = [
        [
            'name' => 'Amara Okafor',
            'avatar_url' => 'https://i.pravatar.cc/150?img=47',
            'channel' => 'whatsapp',
            'handle' => '+1 415 555 0142',
            'phone' => '+1 415 555 0142',
            'email' => 'amara.okafor@brightleaf.co',
            'location' => 'San Francisco, US',
            'tags' => ['VIP', 'Retail'],
            'pipeline_stage_id' => 'negotiation',
            'deal_value' => 4200,
            'last_interaction_at' => '2026-08-01T14:32:00Z',
            'created_at' => '2026-06-02T09:00:00Z',
            'notes' => ['Interested in bulk order for Q3.', 'Prefers WhatsApp over email.'],
            'purchases' => [['id' => 'p1', 'item' => 'Starter Bundle', 'amount' => 350, 'date' => '2026-06-10']],
        ],
        [
            'name' => 'Diego Fernandez',
            'avatar_url' => 'https://i.pravatar.cc/150?img=12',
            'channel' => 'instagram',
            'handle' => '@diego.codes',
            'phone' => null,
            'email' => 'diego.f@mailbox.com',
            'location' => 'Madrid, ES',
            'tags' => ['New', 'Influencer'],
            'pipeline_stage_id' => 'new-lead',
            'deal_value' => 1200,
            'last_interaction_at' => '2026-08-01T11:05:00Z',
            'created_at' => '2026-07-28T10:00:00Z',
            'notes' => ['Reached out via IG story reply.'],
            'purchases' => [],
        ],
        [
            'name' => 'Priya Nair',
            'avatar_url' => 'https://i.pravatar.cc/150?img=32',
            'channel' => 'whatsapp',
            'handle' => '+91 98765 43210',
            'phone' => '+91 98765 43210',
            'email' => 'priya.nair@zenithtech.in',
            'location' => 'Bengaluru, IN',
            'tags' => ['Enterprise'],
            'pipeline_stage_id' => 'qualified',
            'deal_value' => 8600,
            'last_interaction_at' => '2026-07-31T18:20:00Z',
            'created_at' => '2026-05-14T09:00:00Z',
            'notes' => ['Needs custom integration proposal.'],
            'purchases' => [['id' => 'p2', 'item' => 'Pro Plan (Annual)', 'amount' => 4800, 'date' => '2026-05-20']],
        ],
        [
            'name' => 'Marcus Chen',
            'avatar_url' => 'https://i.pravatar.cc/150?img=51',
            'channel' => 'instagram',
            'handle' => '@marcuschen.design',
            'phone' => null,
            'email' => 'marcus@chendesign.studio',
            'location' => 'Toronto, CA',
            'tags' => ['Design', 'Repeat Customer'],
            'pipeline_stage_id' => 'won',
            'deal_value' => 2100,
            'last_interaction_at' => '2026-07-30T09:15:00Z',
            'created_at' => '2026-04-11T09:00:00Z',
            'notes' => ['Closed deal after demo call.'],
            'purchases' => [
                ['id' => 'p3', 'item' => 'Design Kit', 'amount' => 150, 'date' => '2026-04-20'],
                ['id' => 'p4', 'item' => 'Pro Plan (Monthly)', 'amount' => 89, 'date' => '2026-07-01'],
            ],
        ],
        [
            'name' => 'Sofia Rossi',
            'avatar_url' => 'https://i.pravatar.cc/150?img=24',
            'channel' => 'whatsapp',
            'handle' => '+39 320 555 1122',
            'phone' => '+39 320 555 1122',
            'email' => 'sofia.rossi@lumeitalia.it',
            'location' => 'Milan, IT',
            'tags' => ['Fashion', 'VIP'],
            'pipeline_stage_id' => 'contacted',
            'deal_value' => 3000,
            'last_interaction_at' => '2026-07-29T16:40:00Z',
            'created_at' => '2026-06-25T09:00:00Z',
            'notes' => ['Asked for catalog in Italian.'],
            'purchases' => [],
        ],
        [
            'name' => 'Tunde Bakare',
            'avatar_url' => 'https://i.pravatar.cc/150?img=33',
            'channel' => 'instagram',
            'handle' => '@tunde.b',
            'phone' => null,
            'email' => 'tunde.bakare@gmail.com',
            'location' => 'Lagos, NG',
            'tags' => ['New'],
            'pipeline_stage_id' => 'new-lead',
            'deal_value' => 600,
            'last_interaction_at' => '2026-08-01T08:50:00Z',
            'created_at' => '2026-07-30T09:00:00Z',
            'notes' => [],
            'purchases' => [],
        ],
        [
            'name' => 'Hannah Kim',
            'avatar_url' => 'https://i.pravatar.cc/150?img=44',
            'channel' => 'whatsapp',
            'handle' => '+82 10 5555 7890',
            'phone' => '+82 10 5555 7890',
            'email' => 'hannah.kim@seoulmarket.kr',
            'location' => 'Seoul, KR',
            'tags' => ['Wholesale'],
            'pipeline_stage_id' => 'lost',
            'deal_value' => 1500,
            'last_interaction_at' => '2026-07-20T10:00:00Z',
            'created_at' => '2026-05-02T09:00:00Z',
            'notes' => ['Went with a competitor.'],
            'purchases' => [],
        ],
        [
            'name' => "Liam O'Brien",
            'avatar_url' => 'https://i.pravatar.cc/150?img=15',
            'channel' => 'instagram',
            'handle' => '@liam.obrien',
            'phone' => null,
            'email' => 'liam.obrien@brightmail.com',
            'location' => 'Dublin, IE',
            'tags' => ['Repeat Customer', 'VIP'],
            'pipeline_stage_id' => 'qualified',
            'deal_value' => 5200,
            'last_interaction_at' => '2026-07-31T13:10:00Z',
            'created_at' => '2026-03-18T09:00:00Z',
            'notes' => ['Wants a loyalty discount.'],
            'purchases' => [['id' => 'p5', 'item' => 'Pro Plan (Annual)', 'amount' => 4800, 'date' => '2026-03-25']],
        ],
    ];

    public function run(): void
    {
        foreach (self::CONTACTS as $data) {
            $tagNames = $data['tags'];
            unset($data['tags']);

            $contact = Contact::query()->updateOrCreate(
                ['handle' => $data['handle'], 'channel' => $data['channel']],
                ['company_id' => $this->companyId, ...$data],
            );

            $tagIds = collect($tagNames)
                ->map(fn (string $name) => Tag::query()->firstOrCreate(
                    ['name' => $name, 'company_id' => $this->companyId],
                    ['company_id' => $this->companyId],
                )->id)
                ->all();

            $contact->tags()->sync($tagIds);
        }
    }
}
