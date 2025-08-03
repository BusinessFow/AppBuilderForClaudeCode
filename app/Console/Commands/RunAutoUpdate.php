<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunAutoUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-auto-update {--force : Force update even if auto-update is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run automatic application updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if auto-update is enabled
        if (!$this->option('force') && !Setting::get('auto_update_enabled', false)) {
            $this->info('Auto-update is disabled. Use --force to run anyway.');
            SystemLog::info('Auto-update skipped - feature disabled', [], 'updater');
            return Command::SUCCESS;
        }

        $this->info('Starting auto-update process...');
        SystemLog::info('Auto-update process started', [], 'updater');

        try {
            // Check for updates first
            $this->info('Checking for updates...');
            $checkResult = Artisan::call('updater:check');
            
            if ($checkResult !== 0) {
                $this->warn('No updates available.');
                SystemLog::info('No updates available', [], 'updater');
                return Command::SUCCESS;
            }

            // Run the update command
            $this->info('Updates found! Running update process...');
            SystemLog::warning('Updates found, starting update process', [], 'updater');
            
            // Put application in maintenance mode
            Artisan::call('down', [
                '--retry' => 60,
                '--refresh' => 15,
                '--secret' => config('app.maintenance_secret', 'secret'),
            ]);
            
            SystemLog::info('Application entered maintenance mode', [], 'updater');
            
            // Run the update
            $updateResult = Artisan::call('updater:update', [
                '--no-interaction' => true,
            ]);
            
            if ($updateResult === 0) {
                $this->info('Update completed successfully!');
                SystemLog::success('Application updated successfully', [
                    'updated_at' => now()->toDateTimeString(),
                ], 'updater');
                
                // Clear caches after update
                $this->clearCaches();
                
                // Run migrations if needed
                Artisan::call('migrate', ['--force' => true]);
                SystemLog::info('Database migrations completed', [], 'updater');
            } else {
                $this->error('Update failed!');
                SystemLog::error('Application update failed', [
                    'exit_code' => $updateResult,
                ], 'updater');
            }
            
        } catch (\Exception $e) {
            $this->error('Update process failed: ' . $e->getMessage());
            SystemLog::error('Update process crashed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'updater');
            
            Log::error('Auto-update failed', [
                'exception' => $e,
            ]);
            
            return Command::FAILURE;
            
        } finally {
            // Always bring application back online
            Artisan::call('up');
            SystemLog::info('Application exited maintenance mode', [], 'updater');
        }

        $this->info('Auto-update process completed.');
        SystemLog::info('Auto-update process completed', [], 'updater');
        
        return Command::SUCCESS;
    }
    
    /**
     * Clear various caches after update
     */
    protected function clearCaches(): void
    {
        $this->info('Clearing caches...');
        
        $commands = [
            'config:clear' => 'Configuration cache cleared',
            'route:clear' => 'Route cache cleared',
            'view:clear' => 'View cache cleared',
            'cache:clear' => 'Application cache cleared',
            'optimize:clear' => 'Optimizations cleared',
        ];
        
        foreach ($commands as $command => $message) {
            try {
                Artisan::call($command);
                $this->info($message);
                SystemLog::debug($message, [], 'updater');
            } catch (\Exception $e) {
                $this->warn("Failed to run {$command}: " . $e->getMessage());
                SystemLog::warning("Failed to clear cache: {$command}", [
                    'error' => $e->getMessage(),
                ], 'updater');
            }
        }
        
        // Re-optimize after clearing
        try {
            Artisan::call('optimize');
            $this->info('Application re-optimized');
            SystemLog::debug('Application re-optimized', [], 'updater');
        } catch (\Exception $e) {
            $this->warn('Failed to optimize: ' . $e->getMessage());
        }
    }
}
