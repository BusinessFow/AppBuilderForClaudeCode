<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach($this->getActions() as $action)
                <a 
                    href="{{ $action['url'] }}"
                    @if($action['disabled'])
                        class="relative block p-2 bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed"
                        onclick="return false;"
                    @else
                        class="relative block p-2 bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 hover:border-{{ $action['color'] }}-500 dark:hover:border-{{ $action['color'] }}-400 hover:shadow-sm transition-all duration-200 group"
                    @endif
                >
                    <div class="flex items-center space-x-2">
                        <div class="flex-shrink-0">
                            <x-dynamic-component 
                                :component="$action['icon']" 
                                style="width: 16px; height: 16px;" 
                                class="text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400" 
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-medium text-gray-900 dark:text-white group-hover:text-{{ $action['color'] }}-600 dark:group-hover:text-{{ $action['color'] }}-400 transition-colors truncate">
                                {{ $action['label'] }}
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $action['description'] }}
                            </p>
                        </div>
                        @if(!$action['disabled'])
                            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <x-heroicon-o-arrow-right style="width: 12px; height: 12px;" class="text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400" />
                            </div>
                        @endif
                    </div>
                    
                    @if($action['disabled'])
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-300 text-xs px-2 py-1 rounded-full">
                                API Key Required
                            </span>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>