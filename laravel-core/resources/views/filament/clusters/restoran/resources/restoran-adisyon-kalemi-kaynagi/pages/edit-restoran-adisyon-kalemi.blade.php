<x-filament-panels::page>
    @if (\App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi::detayModu())
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
            <label class="block max-w-sm">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Durum</span>
                <select
                    wire:model="data.durum"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white py-2 pl-3 pr-8 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                >
                    <option value="yeni">Yeni</option>
                    <option value="hazirlaniyor">Hazırlanıyor</option>
                    <option value="hazir">Hazır</option>
                    <option value="servis_edildi">Servis edildi</option>
                    <option value="iptal">İptal</option>
                </select>
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
