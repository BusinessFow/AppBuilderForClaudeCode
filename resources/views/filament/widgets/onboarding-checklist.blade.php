<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Getting Started with AppBuilder
        </x-slot>

        <x-slot name="description">
            Complete these steps to get the most out of your experience
        </x-slot>

        <div class="space-y-4">
            <!-- Progress Bar -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Progress: {{ $this->getCompletedCount() }} of {{ $this->getTotalCount() }} completed
                    </span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $this->getProgress() }}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                    <div 
                        class="bg-primary-600 h-2.5 rounded-full transition-all duration-300 ease-in-out" 
                        style="width: {{ $this->getProgress() }}%"
                    ></div>
                </div>
            </div>

            <!-- Checklist Items -->
            <div class="space-y-3">
                @foreach($this->getChecklistItems() as $item)
                    <div class="flex items-start space-x-4 p-4 rounded-lg border 
                        {{ $item['completed'] 
                            ? 'bg-success-50 border-success-200 dark:bg-success-900/10 dark:border-success-900/20' 
                            : 'bg-gray-50 border-gray-200 dark:bg-gray-900/10 dark:border-gray-700' 
                        }}">
                        
                        <!-- Icon -->
                        <div class="flex-shrink-0 mt-0.5">
                            @if($item['completed'])
                                <x-heroicon-o-check-circle style="width: 20px; height: 20px;" class="text-success-600 dark:text-success-400" />
                            @else
                                <x-dynamic-component 
                                    :component="$item['icon']" 
                                    style="width: 20px; height: 20px;"
                                    class="text-gray-400 dark:text-gray-500" 
                                />
                            @endif
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium {{ $item['completed'] ? 'text-success-700 dark:text-success-300' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $item['label'] }}
                            </h4>
                            <p class="text-sm {{ $item['completed'] ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400' }} mt-1">
                                {{ $item['description'] }}
                            </p>
                        </div>

                        <!-- Action Button -->
                        @if(!$item['completed'] && $item['action'])
                            <div class="flex-shrink-0">
                                <a 
                                    href="{{ $item['action'] }}"
                                    @if($item['disabled'] ?? false)
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md
                                            bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600
                                            cursor-not-allowed opacity-50"
                                        onclick="return false;"
                                    @else
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md
                                            text-white bg-primary-600 hover:bg-primary-700 
                                            dark:bg-primary-500 dark:hover:bg-primary-600
                                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500
                                            transition-colors duration-200"
                                    @endif
                                >
                                    {{ $item['action_label'] }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Success Message -->
            @if($this->getProgress() === 100)
                <div class="mt-6 p-4 bg-success-100 border border-success-300 rounded-lg dark:bg-success-900/20 dark:border-success-900/40">
                    <div class="flex">
                        <x-heroicon-o-sparkles style="width: 20px; height: 20px;" class="text-success-600 dark:text-success-400" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-success-800 dark:text-success-300">
                                Congratulations! You're all set up.
                            </h3>
                            <p class="mt-1 text-sm text-success-700 dark:text-success-400">
                                You've completed all the setup steps. Start building amazing projects with Claude!
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>