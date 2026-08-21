<x-filament-panels::page>
    <div class="personel-cork-form-screen">
    <x-filament::section>
        <form wire:submit.prevent="kaydet" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-o-check-circle">
                Kaydet
            </x-filament::button>
        </form>
    </x-filament::section>
    </div>
</x-filament-panels::page>
