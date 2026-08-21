<x-filament-panels::page>
    <form wire:submit="kaydet">
        {{ $this->form }}

        <div class="mt-6 flex justify-start">
            <x-filament::button type="submit">
                Kaydet
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
