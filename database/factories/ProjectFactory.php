<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frameworks = array_keys(Project::TECHNOLOGY_STACKS);
        $selectedFramework = $this->faker->randomElement($frameworks);
        $stack = Project::TECHNOLOGY_STACKS[$selectedFramework];
        
        return [
            'name' => $this->faker->company . ' ' . $this->faker->randomElement(['Website', 'Portal', 'App', 'Platform', 'API', 'Service']),
            'description' => $this->faker->paragraph,
            'project_path' => '/home/user/projects/' . $this->faker->slug,
            'project_type' => $this->faker->randomElement(['web', 'api', 'cli', 'library', 'mobile', 'desktop', 'fullstack']),
            'framework' => $stack['framework'],
            'language' => $stack['language'],
            'technologies' => $this->faker->randomElements([
                'PHP', 'JavaScript', 'TypeScript', 'Python', 'Ruby', 'Java', 'C#', 'Go',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Docker', 'Git'
            ], rand(3, 6)),
            'claude_settings' => [
                'model' => 'claude-3-opus',
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ],
            'claude_md' => null,
            'local_settings' => Project::DEFAULT_LOCAL_SETTINGS,
            'auto_commit' => $this->faker->boolean(30),
            'auto_test' => $this->faker->boolean(50),
            'tdd_mode' => $this->faker->boolean(20),
            'code_review' => $this->faker->boolean(40),
            'custom_rules' => $this->faker->optional()->randomElements([
                ['rule' => 'Always use TypeScript'],
                ['rule' => 'Follow PSR-12 coding standard'],
                ['rule' => 'Write tests for all new features'],
            ], rand(0, 2)),
            'dependencies' => $this->faker->optional()->randomElements([
                ['name' => 'axios', 'version' => '^1.0.0'],
                ['name' => 'lodash', 'version' => '^4.17.0'],
                ['name' => 'express', 'version' => '^4.18.0'],
            ], rand(0, 3)),
            'test_commands' => $stack['test_commands'],
            'build_commands' => $stack['build_commands'],
            'lint_commands' => $stack['lint_commands'],
            'git_branch' => $this->faker->randomElement(['main', 'develop', 'feature/new-feature', null]),
            'ignored_paths' => $this->faker->randomElements(['node_modules/', 'vendor/', 'dist/', '.git/'], rand(1, 3)),
            'focus_areas' => $this->faker->randomElements(['Performance', 'Security', 'Testing', 'Documentation', 'Refactoring'], rand(1, 3)),
            'status' => $this->faker->randomElement(['active', 'paused', 'inactive']),
            'started_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'completed_at' => null,
        ];
    }
    
    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
    
    /**
     * Indicate that the project has TDD mode enabled.
     */
    public function withTdd(): static
    {
        return $this->state(fn (array $attributes) => [
            'tdd_mode' => true,
            'auto_test' => true,
        ]);
    }
    
    /**
     * Indicate that the project has all automation enabled.
     */
    public function fullyAutomated(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_commit' => true,
            'auto_test' => true,
            'tdd_mode' => true,
            'code_review' => true,
        ]);
    }
}