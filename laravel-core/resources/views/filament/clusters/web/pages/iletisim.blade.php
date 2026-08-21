<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen web-cork-screen web-cork-form-screen">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($this->bolumler() as $bolumAnahtari => $bolumEtiketi)
            <x-filament::button
                type="button"
                size="sm"
                color="{{ $aktifBolum === $bolumAnahtari ? 'primary' : 'gray' }}"
                wire:click="bolumSec('{{ $bolumAnahtari }}')"
            >
                {{ $bolumEtiketi }}
            </x-filament::button>
        @endforeach
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="false"
        />
    </x-filament-panels::form>
    </div>
</x-filament-panels::page>
