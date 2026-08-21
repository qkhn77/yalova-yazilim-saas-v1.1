<x-filament-panels::page>
    <div class="max-w-4xl space-y-6">
        {{ $this->form }}
        @if (method_exists($this, 'formKaydetAction'))
            <div class="flex justify-end pt-1">
                <x-filament::actions :actions="[$this->formKaydetAction()]" />
            </div>
        @endif
    </div>
</x-filament-panels::page>
