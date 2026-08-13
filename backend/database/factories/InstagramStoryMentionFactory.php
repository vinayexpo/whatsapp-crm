<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\InstagramStoryMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramStoryMention>
 */
class InstagramStoryMentionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mention_id' => 'ig_mention_'.fake()->unique()->numerify('#########'),
            'contact_id' => Contact::factory(),
            'media_id' => 'ig_media_'.fake()->numerify('#########'),
            'replied_at' => null,
        ];
    }
}
