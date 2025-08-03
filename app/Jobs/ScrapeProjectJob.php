<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\WebScrapingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeProjectJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting scraping job for project: {$this->project->name}");

        $scrapingService = new WebScrapingService($this->project);
        $success = $scrapingService->scrape();

        if ($success) {
            Log::info("Scraping completed successfully for project: {$this->project->name}");
        } else {
            Log::error("Scraping failed for project: {$this->project->name}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Scraping job failed for project {$this->project->name}: " . $exception->getMessage());
        
        $this->project->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}
