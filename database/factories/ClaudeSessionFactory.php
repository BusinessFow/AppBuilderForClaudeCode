<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaudeSession>
 */
class ClaudeSessionFactory extends Factory
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
            'process_id' => null,
            'status' => $this->faker->randomElement(['idle', 'running', 'stopped', 'error']),
            'last_input' => $this->faker->sentence(),
            'last_output' => $this->faker->paragraph(),
            'conversation_history' => [],
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'last_activity' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
