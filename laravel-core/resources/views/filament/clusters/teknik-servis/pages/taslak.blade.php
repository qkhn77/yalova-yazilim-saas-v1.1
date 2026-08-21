<x-filament-panels::page>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
            {{ $this->getHeading() ?? $this->getTitle() }}
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $aciklama ?? '' }}
        </p>
    </div>
</x-filament-panels::page>
