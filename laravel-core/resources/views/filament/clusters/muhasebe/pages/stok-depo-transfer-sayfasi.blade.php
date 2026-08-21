<x-filament-panels::page>
    <form wire:submit="transferiKaydet" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Transferi kaydet</x-filament::button>
    </form>
</x-filament-panels::page>
