<x-filament-panels::page>
    <form wire:submit="kaydet">
        {{ $this->form }}

        @if($this->ayarGuncelleYetkisiVarMi())
            <div class="mt-6 flex flex-wrap justify-start gap-3">
                <x-filament::button type="submit">
                    Kaydet
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="baglantiTesti">
                    Bağlantı testi yap
                </x-filament::button>

                <x-filament::button type="button" color="warning" wire:click="gelenBelgeleriCek">
                    Gelen e-belgeleri çek
                </x-filament::button>
            </div>
        @endif
    </form>

    <div class="mt-8">
        {!! $this->gelenBelgelerHtml() !!}
    </div>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
