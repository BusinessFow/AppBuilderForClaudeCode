<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemLog>
 */
class SystemLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $levels = ['debug', 'info', 'success', 'warning', 'error'];
        $channels = ['system', 'projects', 'claude', 'api', 'tasks', 'git'];
        
        return [
            'level' => $this->faker->randomElement($levels),
            'channel' => $this->faker->randomElement($channels),
            'message' => $this->faker->sentence(),
            'context' => $this->faker->optional()->passthrough([
                'details' => $this->faker->word(),
                'count' => $this->faker->numberBetween(1, 100),
            ]),
            'user_id' => $this->faker->optional()->randomElement(User::pluck('id')->toArray()),
            'project_id' => $this->faker->optional()->randomElement(Project::pluck('id')->toArray()),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'url' => $this->faker->url(),
        ];
    }

    public function info(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'info',
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'error',
        ]);
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'channel' => 'projects',
        ]);
    }
}