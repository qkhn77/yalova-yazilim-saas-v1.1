<x-filament-panels::page>
    <form wire:submit="kaydet">
        {{ $this->form }}

        @if($this->ayarGuncelleYetkisiVarMi())
            <div class="mt-6 flex flex-wrap justify-start gap-3">
                <x-filament::button type="submit">
                    Kaydet
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="testTelegramGonder">
                    Telegram test mesajı gönder
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
