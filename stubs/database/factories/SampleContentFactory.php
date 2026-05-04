<?php

namespace Database\Factories;

use App\Models\SampleContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleContent>
 */
class SampleContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => [
                'en' => fake()->sentence(3),
                'tr' => 'Başlık '.fake()->word(),
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'tr' => fake()->paragraph(),
            ],
            'content' => [
                'en' => '<p>'.fake()->text().'</p>',
                'tr' => '<p>'.fake()->text().'</p>',
            ],
            'user_id' => User::factory(),
        ];
    }
}
