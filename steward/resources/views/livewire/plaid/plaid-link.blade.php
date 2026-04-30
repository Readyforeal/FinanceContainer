<div>
    @if ($error)
        <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg mb-4">
            {{ $error }}
        </div>
    @endif

    @if (! $linkToken)
        <button
            wire:click="createLinkToken"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium px-4 py-2 rounded-lg transition-colors"
        >
            <x-lucide-plus class="w-4 h-4" />
            <span wire:loading.remove>Connect Bank Account</span>
            <span wire:loading>Connecting...</span>
        </button>
    @else
        <div class="text-sm text-gray-400">Launching Plaid...</div>
    @endif

    @if ($linkToken)
        <script>
            (function () {
                var script = document.createElement('script');
                script.src = 'https://cdn.plaid.com/link/v2/stable/link-initialize.js';
                script.onload = function () {
                    var handler = Plaid.create({
                        token: @json($linkToken),
                        onSuccess: function (publicToken, metadata) {
                            @this.call('onSuccess', publicToken, metadata);
                        },
                        onExit: function (err, metadata) {
                            if (err) {
                                console.error('Plaid exit error:', err);
                            }
                            @this.set('connecting', false);
                            @this.set('linkToken', null);
                        },
                        onLoad: function () {
                            handler.open();
                        },
                    });
                };
                document.head.appendChild(script);
            })();
        </script>
    @endif
</div>
