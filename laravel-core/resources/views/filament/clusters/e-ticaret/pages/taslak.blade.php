<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-4">
        <x-filament::section>
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $this->getTitle() }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $aciklama }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Bu ekranın detay kuralları ve entegrasyonları Faz 3 ve sonrasında eklenecektir.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
