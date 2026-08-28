<?php

namespace Database\Factories;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deck>
 */
class DeckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * 系统卡组（无拥有者）。
     */
    public function system(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    /**
     * 某用户创建的用户卡组。
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
