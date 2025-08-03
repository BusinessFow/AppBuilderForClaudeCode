<x-filament-panels::page>
    <style>
        /* Custom styles for Claude Chat */
        .claude-chat-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 12rem);
            gap: 1rem;
        }
        
        @media (min-width: 1024px) {
            .claude-chat-container {
                flex-direction: row;
            }
        }
        
        .claude-todo-panel {
            flex: 0 0 100%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .claude-console-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        @media (min-width: 1024px) {
            .claude-todo-panel {
                flex: 0 0 33.333333%;
            }
        }
        
        .claude-section {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }
        
        .claude-section-header {
            flex-shrink: 0;
        }
        
        .claude-section-content {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        /* Scrollbar styles */
        .claude-section-content::-webkit-scrollbar { width: 4px; }
        .claude-section-content::-webkit-scrollbar-track { background: transparent; }
        .claude-section-content::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }
        .dark .claude-section-content::-webkit-scrollbar-thumb { background: #334155; }
        
        /* Compact Filament table in tasks panel */
        .claude-todo-panel .fi-ta-table {
            font-size: 0.875rem;
        }
        
        .claude-todo-panel .fi-ta-cell {
            padding: 0.5rem 0.75rem;
        }
        
        .claude-todo-panel .fi-ta-header-cell {
            padding: 0.5rem 0.75rem;
        }
        
        .claude-todo-panel .fi-ta-content {
            overflow-x: hidden;
        }
        
        #chat-messages::-webkit-scrollbar { width: 6px; }
        #chat-messages::-webkit-scrollbar-track { background: transparent; }
        #chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        #chat-messages::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .dark #chat-messages::-webkit-scrollbar-thumb { background: #475569; }
        .dark #chat-messages::-webkit-scrollbar-thumb:hover { background: #64748b; }
        
        /* Animations */
        .todo-item-hover { transition: all 0.15s ease; }
        .todo-item-hover:hover { transform: translateX(2px); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        
        .message-animate { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    
    <div class="claude-chat-container">
        {{-- Claude Tasks Section (Left - 1/3) --}}
        <div class="claude-todo-panel">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 claude-section">
                <div class="fi-section-header claude-section-header px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Claude Tasks
                    </h3>
                    <p class="fi-section-description mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Tasks for Claude to implement
                    </p>
                </div>
                
                {{-- Tasks Table --}}
                <div class="claude-section-content">
                    @livewire('claude-tasks-list', ['project' => $project, 'isRunning' => $isRunning])
                </div>
            </div>
        </div>
        
        {{-- Claude Console Section (Right - 2/3) --}}
        <div class="claude-console-panel">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 claude-section">
                <div class="fi-section-header claude-section-header flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Claude Console
                        </h3>
                        <p class="fi-section-description mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Status: 
                            <span class="font-medium {{ $isRunning ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $sessionStatus ?? 'Not started' }}
                            </span>
                            @if($isRunning)
                                <span class="ml-2 inline-flex">
                                    <span class="animate-pulse inline-flex h-2 w-2 rounded-full bg-green-400 status-pulse"></span>
                                </span>
                            @endif
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-x-2">
                        @if(!empty($messages))
                            <x-filament::button
                                wire:click="clearHistory"
                                icon="heroicon-o-trash"
                                size="sm"
                                color="gray"
                                outlined
                            >
                                Clear
                            </x-filament::button>
                        @endif
                    </div>
                </div>
                
                {{-- Messages Area --}}
                <div class="claude-section-content p-6" id="chat-messages">
                    <div class="space-y-4">
                        @forelse($messages as $message)
                            <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }} message-animate">
                                <div class="max-w-[85%]">
                                    <div class="flex items-center gap-x-2 mb-1">
                                        <span class="text-xs font-medium {{ $message['role'] === 'user' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $message['role'] === 'user' ? 'You' : 'Claude' }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ \Carbon\Carbon::parse($message['timestamp'])->format('H:i:s') }}
                                        </span>
                                    </div>
                                    <div class="{{ $message['role'] === 'user' ? 
                                        'bg-primary-100 dark:bg-primary-900/20 text-primary-900 dark:text-primary-100' : 
                                        'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' }} 
                                        rounded-2xl px-4 py-3 shadow-sm">
                                        <pre class="whitespace-pre-wrap font-sans text-sm">{{ $message['content'] }}</pre>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex items-center justify-center h-full min-h-[300px]">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No messages yet</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $isRunning ? 'Send a message to start the conversation!' : 'Start Claude to begin chatting' }}
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                
                {{-- Input Form --}}
                @if($isRunning)
                    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                        <form wire:submit="sendMessage" class="flex gap-2">
                            <textarea
                                wire:model="input"
                                class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 resize-none"
                                placeholder="Type your message to Claude..."
                                rows="3"
                                wire:keydown.ctrl.enter="sendMessage"
                            ></textarea>
                            
                            <x-filament::button 
                                type="submit" 
                                icon="heroicon-o-paper-airplane"
                                size="sm"
                            >
                                Send
                            </x-filament::button>
                        </form>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Press Ctrl+Enter to send
                        </p>
                    </div>
                @else
                    <div class="border-t border-gray-200 dark:border-gray-700 p-6">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Claude is not running. Click the Start button in the header to begin.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    {{-- Auto-refresh output --}}
    <script>
        @if($isRunning)
            setInterval(function() {
                Livewire.dispatch('refresh-output');
            }, 2000);
        @endif
        
        // Auto-scroll chat to bottom
        document.addEventListener('livewire:initialized', function() {
            function scrollToBottom() {
                const chatMessages = document.getElementById('chat-messages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }
            
            // Scroll on initial load
            scrollToBottom();
            
            // Scroll on updates
            Livewire.on('refreshChat', scrollToBottom);
            Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                succeed(({ snapshot, effect }) => {
                    scrollToBottom();
                });
            });
        });
    </script>
</x-filament-panels::page>