<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\InstagramComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramComment>
 */
class InstagramCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => 'ig_comment_'.fake()->unique()->numerify('#########'),
            'contact_id' => Contact::factory(),
            'media_id' => 'ig_media_'.fake()->numerify('#########'),
            'text' => fake()->sentence(),
            'replied_at' => null,
        ];
    }
}
