<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activity
        </x-slot>

        <div class="space-y-2">
            @forelse($this->getActivities()->take(10) as $activity)
                <div class="group relative flex items-start gap-3 p-3 rounded-lg border border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-all">
                    <!-- Icon -->
                    <div class="flex-shrink-0">
                        @php
                            $icon = match($activity->type) {
                                'project_created' => 'heroicon-o-folder-plus',
                                'project_updated' => 'heroicon-o-pencil',
                                'task_created' => 'heroicon-o-plus-circle',
                                'task_completed' => 'heroicon-o-check-circle',
                                'session_started' => 'heroicon-o-play',
                                'session_stopped' => 'heroicon-o-stop',
                                default => 'heroicon-o-information-circle'
                            };
                            $iconBg = match($activity->type) {
                                'project_created' => 'bg-success-100 dark:bg-success-900/20',
                                'project_updated' => 'bg-info-100 dark:bg-info-900/20',
                                'task_created' => 'bg-warning-100 dark:bg-warning-900/20',
                                'task_completed' => 'bg-success-100 dark:bg-success-900/20',
                                'session_started' => 'bg-primary-100 dark:bg-primary-900/20',
                                'session_stopped' => 'bg-danger-100 dark:bg-danger-900/20',
                                default => 'bg-gray-100 dark:bg-gray-900/20'
                            };
                            $iconColor = match($activity->type) {
                                'project_created' => 'text-success-600 dark:text-success-400',
                                'project_updated' => 'text-info-600 dark:text-info-400',
                                'task_created' => 'text-warning-600 dark:text-warning-400',
                                'task_completed' => 'text-success-600 dark:text-success-400',
                                'session_started' => 'text-primary-600 dark:text-primary-400',
                                'session_stopped' => 'text-danger-600 dark:text-danger-400',
                                default => 'text-gray-600 dark:text-gray-400'
                            };
                            $typeLabel = match($activity->type) {
                                'project_created' => 'New Project',
                                'project_updated' => 'Project Updated',
                                'task_created' => 'New Task',
                                'task_completed' => 'Task Completed',
                                'session_started' => 'Claude Started',
                                'session_stopped' => 'Claude Stopped',
                                default => 'Activity'
                            };
                        @endphp
                        <div class="p-2 rounded-lg {{ $iconBg }}">
                            <x-dynamic-component :component="$icon" style="width: 18px; height: 18px;" class="{{ $iconColor }}" />
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <!-- Header Row -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $activity->description }}
                                </h4>
                            </div>
                            <time class="text-xs text-gray-500 dark:text-gray-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}
                            </time>
                        </div>
                        
                        <!-- Metadata Row -->
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $iconBg }} {{ str_replace('dark:text-', 'dark:text-', str_replace('text-', 'text-', $iconColor)) }}">
                                {{ $typeLabel }}
                            </span>
                            @if($activity->project_name)
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    in
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ $activity->project_name }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12">
                    <x-heroicon-o-clock style="width: 48px; height: 48px;" class="text-gray-300 dark:text-gray-700 mx-auto mb-3" />
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">No recent activity</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Activities from the last 7 days will appear here</p>
                </div>
            @endforelse
        </div>

        @if($this->getActivities()->count() > 10)
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    Showing 10 of {{ $this->getActivities()->count() }} activities from the last 7 days
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>