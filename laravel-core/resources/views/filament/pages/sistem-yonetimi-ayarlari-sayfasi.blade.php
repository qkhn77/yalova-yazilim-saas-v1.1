<x-filament-panels::page>
    <div x-data x-on:sistem-logo-guncellendi.window="window.location.reload()">
    <form wire:submit="kaydet">
        {{ $this->form }}

        <div class="mt-6 flex justify-start">
            <x-filament::button type="submit">
                Sistem ayarlarını kaydet
            </x-filament::button>
        </div>
    </form>
    </div>
</x-filament-panels::page>
