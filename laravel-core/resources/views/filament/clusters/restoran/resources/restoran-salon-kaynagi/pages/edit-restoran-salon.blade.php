<x-filament-panels::page>
    @if (\App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi::detayModu())
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @else
        <form wire:submit="save" class="space-y-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input
                    type="checkbox"
                    wire:model="data.aktif_mi"
                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900"
                >
                Aktif
            </label>

            <button
                type="submit"
                class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                Kaydet
            </button>
        </form>
    @endif
</x-filament-panels::page>
