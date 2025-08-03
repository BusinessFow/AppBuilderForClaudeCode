<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Jobs\ScrapeProjectJob;
use App\Services\WebScrapingService;
use Illuminate\Console\Command;

class ScrapeProjectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:project {id? : The project ID to scrape} {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape a web application project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->argument('id');
        
        if ($projectId) {
            $project = Project::find($projectId);
            if (!$project) {
                $this->error("Project with ID {$projectId} not found.");
                return 1;
            }
            
            $this->scrapeProject($project);
        } else {
            // Show list of projects to choose from
            $projects = Project::where('status', 'pending')->get();
            
            if ($projects->isEmpty()) {
                $this->info('No pending projects found.');
                return 0;
            }
            
            $this->table(
                ['ID', 'Name', 'URL', 'Status'],
                $projects->map(fn($p) => [$p->id, $p->name, $p->url, $p->status])
            );
            
            $selectedId = $this->ask('Enter the project ID to scrape');
            $project = $projects->find($selectedId);
            
            if (!$project) {
                $this->error('Invalid project ID.');
                return 1;
            }
            
            $this->scrapeProject($project);
        }
        
        return 0;
    }
    
    private function scrapeProject(Project $project): void
    {
        $this->info("Starting scraping for project: {$project->name}");
        
        if ($this->option('sync')) {
            // Run synchronously
            $scrapingService = new WebScrapingService($project);
            $success = $scrapingService->scrape();
            
            if ($success) {
                $this->info('Scraping completed successfully!');
            } else {
                $this->error('Scraping failed.');
            }
        } else {
            // Queue the job
            $project->update(['status' => 'running', 'started_at' => now()]);
            ScrapeProjectJob::dispatch($project);
            $this->info('Scraping job queued successfully!');
        }
    }
}
