<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'question' => fake()->sentence(6).'?',
            'answer' => fake()->paragraph(2),
            'position' => 1,
        ];
    }
}
