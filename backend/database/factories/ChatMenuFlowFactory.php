<?php

namespace Database\Factories;

use App\Models\ChatMenuFlow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMenuFlow>
 */
class ChatMenuFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rootId = (string) Str::uuid();
        $cateringId = (string) Str::uuid();
        $menuId = (string) Str::uuid();

        return [
            'name' => fake()->words(2, true).' Menu Flow',
            'channel' => 'both',
            'status' => 'paused',
            'trigger_keyword' => null,
            'entry_node_id' => $rootId,
            'nodes' => [
                [
                    'id' => $rootId,
                    'type' => 'menu',
                    'message' => 'What are you looking for?',
                    'mediaUrl' => null,
                    'mediaType' => null,
                    'renderAs' => 'button',
                    'buttons' => [
                        ['id' => (string) Str::uuid(), 'label' => 'Catering', 'nextNodeId' => $cateringId],
                        ['id' => (string) Str::uuid(), 'label' => 'Menu', 'nextNodeId' => $menuId],
                    ],
                ],
                [
                    'id' => $cateringId,
                    'type' => 'content',
                    'message' => 'We offer catering for weddings, birthdays, and corporate events. Call us at 630-966-6688.',
                    'mediaUrl' => null,
                    'mediaType' => null,
                    'renderAs' => 'button',
                    'buttons' => [],
                ],
                [
                    'id' => $menuId,
                    'type' => 'content',
                    'message' => 'You can view our full menu at eatersstop.in/menu.',
                    'mediaUrl' => null,
                    'mediaType' => null,
                    'renderAs' => 'button',
                    'buttons' => [],
                ],
            ],
        ];
    }
}
