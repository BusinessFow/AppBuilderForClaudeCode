<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            System Health
        </x-slot>
        
        <x-slot name="headerEnd">
            @php
                $status = $this->getOverallStatus();
            @endphp
            <span class="inline-flex items-center gap-1 text-xs font-medium">
                @if($status === 'operational')
                    <span class="inline-block w-2 h-2 bg-success-500 rounded-full animate-pulse"></span>
                    <span class="text-success-700 dark:text-success-300">All Systems Operational</span>
                @elseif($status === 'warning')
                    <span class="inline-block w-2 h-2 bg-warning-500 rounded-full animate-pulse"></span>
                    <span class="text-warning-700 dark:text-warning-300">Minor Issues</span>
                @else
                    <span class="inline-block w-2 h-2 bg-danger-500 rounded-full animate-pulse"></span>
                    <span class="text-danger-700 dark:text-danger-300">Issues Detected</span>
                @endif
            </span>
        </x-slot>

        <div class="space-y-3">
            @foreach($this->getHealthChecks() as $check)
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        @if($check['status'] === 'operational')
                            <x-heroicon-o-check-circle style="width: 20px; height: 20px;" class="text-success-600 dark:text-success-400" />
                        @elseif($check['status'] === 'warning')
                            <x-heroicon-o-exclamation-triangle style="width: 20px; height: 20px;" class="text-warning-600 dark:text-warning-400" />
                        @else
                            <x-heroicon-o-x-circle style="width: 20px; height: 20px;" class="text-danger-600 dark:text-danger-400" />
                        @endif
                        
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $check['name'] }}
                            </p>
                        </div>
                    </div>
                    
                    <span class="text-xs font-medium px-2 py-1 rounded-full
                        @if($check['status'] === 'operational')
                            bg-success-100 text-success-700 dark:bg-success-900/20 dark:text-success-300
                        @elseif($check['status'] === 'warning')
                            bg-warning-100 text-warning-700 dark:bg-warning-900/20 dark:text-warning-300
                        @else
                            bg-danger-100 text-danger-700 dark:bg-danger-900/20 dark:text-danger-300
                        @endif
                    ">
                        {{ $check['message'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>