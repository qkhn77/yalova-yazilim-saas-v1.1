<x-filament-panels::page>
    <form wire:submit="kaydet">
        {{ $this->form }}

        <div class="mt-6 flex justify-start">
            <x-filament::button type="submit">
                Bakım modu ayarlarını kaydet
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
