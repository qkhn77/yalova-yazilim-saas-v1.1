<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen web-cork-screen web-cork-form-screen">
    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="false"
        />
    </x-filament-panels::form>
    </div>
</x-filament-panels::page>
