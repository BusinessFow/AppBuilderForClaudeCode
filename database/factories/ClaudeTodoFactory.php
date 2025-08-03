<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaudeTodo>
 */
class ClaudeTodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'command' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'priority' => $this->faker->numberBetween(1, 3),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'result' => null,
            'completed_by_claude' => false,
            'executed_at' => null,
            'completed_at' => null,
        ];
    }
}
