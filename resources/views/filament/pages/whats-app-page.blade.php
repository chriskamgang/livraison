<x-filament-panels::page>
    <div
        class="max-w-lg mx-auto"
        wire:poll.10s="loadStatus"
    >
        {{-- Statut --}}
        <div @class([
            'rounded-xl p-4 mb-6 text-center font-semibold text-lg',
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $connected,
            'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' => !$connected,
        ])>
            {{ $statusMessage ?: '⏳ Chargement...' }}
        </div>

        @if (!$connected)
            @if ($qrDataUrl)
                {{-- QR Code --}}
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 flex flex-col items-center gap-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                        Ouvrez WhatsApp → <strong>Appareils liés</strong> → <strong>Lier un appareil</strong> → scannez ce QR
                    </p>

                    <div class="border-4 border-gray-200 dark:border-gray-600 rounded-xl p-2">
                        <img src="{{ $qrDataUrl }}" alt="QR Code WhatsApp" class="w-64 h-64" />
                    </div>

                    <p class="text-xs text-gray-400 text-center">
                        Le QR se rafraîchit automatiquement toutes les 10 secondes
                    </p>

                    <x-filament::button wire:click="loadStatus" color="gray" size="sm" icon="heroicon-o-arrow-path">
                        Rafraîchir maintenant
                    </x-filament::button>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-6 flex flex-col items-center gap-4">
                    <x-filament::loading-indicator class="h-10 w-10 text-primary-500" />
                    <p class="text-gray-500 dark:text-gray-400">Génération du QR code...</p>
                    <x-filament::button wire:click="loadStatus" color="gray" size="sm" icon="heroicon-o-arrow-path">
                        Rafraîchir
                    </x-filament::button>
                </div>
            @endif
        @else
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-6 flex flex-col items-center gap-4">
                <div class="text-6xl">📱</div>
                <p class="text-gray-600 dark:text-gray-300 text-center">
                    WhatsApp est connecté et prêt à envoyer des notifications.
                </p>
                <x-filament::button wire:click="loadStatus" color="gray" size="sm" icon="heroicon-o-arrow-path">
                    Vérifier le statut
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
